<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AssetRole;
use App\Enums\BureauRole;
use App\Enums\DocumentSigningRole;
use App\Enums\InventoryRole;
use App\Enums\ModuleAccessLevel;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'name_dv', 'position', 'position_dv', 'email', 'password', 'is_active', 'signature_path', 'signature_path_2', 'default_signature_slot', 'module_access'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The `is_active` column defaults to true at the DB level (see its
     * migration), but MySQL doesn't hand column defaults back on
     * INSERT — only the auto-increment id is — so a freshly create()'d
     * model has no is_active in memory until it's refetched. Setting it
     * here too means canAccessPanel() never sees null on a brand-new
     * user (it did before this fix: TypeError, not just a wrong value).
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Any authenticated, active user may enter the panel — per-module
     * and per-action authorization happens inside it (see
     * App\Filament\Concerns\HasModuleAccess and roleFor() below), not
     * at this gate. Without the `true` default here, Filament's own
     * Authenticate middleware only allows access when APP_ENV=local, a
     * documented Filament security default (see
     * Filament\Models\Contracts\FilamentUser) — it would otherwise
     * lock everyone out entirely in staging/production.
     *
     * is_active is the one thing that does gate here: a deactivated
     * user (someone who has left) is never deleted — every
     * approval/request/signature is a permanent FK to their user row —
     * so this is what actually stops them signing in.
     *
     * Cast explicitly to bool rather than returning the attribute
     * directly: a row written before is_active existed, or any other
     * way a live DB row ends up with a genuine NULL in that column
     * (not just the fresh-model case the $attributes default above
     * covers), makes this throw a TypeError instead of just gating —
     * this treats that NULL as "not active" instead of a 500.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'module_access' => 'array',
        ];
    }

    public function moduleLevels(): HasMany
    {
        return $this->hasMany(UserModuleLevel::class);
    }

    /**
     * This user's capability level in a module (see config/modules.php
     * keys) — an explicit UserModuleLevel row, or Viewer if there isn't
     * one, so every user has at least Viewer in every module by
     * default. `admin` (see UserResource::canAccess()) is Users-page
     * administration only and grants nothing here — every module,
     * admin included, is assigned like anyone else (see
     * App\Filament\Resources\Users\Schemas\UserForm's "Module Roles"
     * section).
     */
    public function roleFor(string $module): ModuleAccessLevel
    {
        return $this->moduleLevels->firstWhere('module', $module)?->level
            ?? ModuleAccessLevel::Viewer;
    }

    public function hasModuleLevel(string $module, ModuleAccessLevel $minimum): bool
    {
        return $this->roleFor($module)->atLeast($minimum);
    }

    /**
     * Whether this user can see/use a module at all — separate from
     * roleFor(), which governs what they can *do* inside a module
     * they're allowed into. `module_access` is null by default, meaning
     * every module in config/modules.php; set it via UserResource to
     * restrict a user to a subset.
     */
    public function canAccessModule(string $module): bool
    {
        return $this->module_access === null || in_array($module, $this->module_access, true);
    }

    public function hasAnyModuleAccess(): bool
    {
        if ($this->module_access === null) {
            return true;
        }

        return collect($this->module_access)
            ->intersect(array_keys(config('modules')))
            ->isNotEmpty();
    }

    /**
     * Each user can save up to two signatures — the same two-slot
     * pattern used for organization stamps (see
     * App\Models\DocumentSigningOrganization).
     */
    public function hasSignatureInSlot(int $slot): bool
    {
        return filled($this->signaturePathForSlot($slot));
    }

    public function signaturePathForSlot(int $slot): ?string
    {
        return match ($slot) {
            1 => $this->signature_path,
            2 => $this->signature_path_2,
            default => null,
        };
    }

    public function hasSignature(): bool
    {
        return $this->hasSignatureInSlot(1) || $this->hasSignatureInSlot(2);
    }

    /**
     * The signature used wherever one is needed across the app (e.g.
     * Document Signing's sign action) — the user's chosen default slot,
     * falling back to whichever slot actually has one saved if the
     * default slot itself is empty (e.g. they removed it).
     */
    public function defaultSignaturePath(): ?string
    {
        // default_signature_slot has a DB-level default of 1, but a
        // freshly created-in-memory model (e.g. straight off
        // User::factory()->create()) won't have that default synced
        // back into its attributes without an explicit refresh().
        return $this->signaturePathForSlot($this->default_signature_slot ?? 1)
            ?? $this->signature_path
            ?? $this->signature_path_2;
    }

    /**
     * Document Signing's own role assignments — see
     * App\Enums\DocumentSigningRole for why this module doesn't use
     * roleFor()/ModuleAccessLevel like other modules do. A user can
     * hold any combination of the four roles (unlike moduleLevels(),
     * which is a single ranked value).
     */
    public function documentSigningRoles(): HasMany
    {
        return $this->hasMany(DocumentSigningUserRole::class);
    }

    public function hasDocumentSigningRole(DocumentSigningRole $role): bool
    {
        return $this->documentSigningRoles->contains(fn (DocumentSigningUserRole $row): bool => $row->role === $role);
    }

    /**
     * @return array<int, DocumentSigningRole>
     */
    public function documentSigningRoleList(): array
    {
        return $this->documentSigningRoles->pluck('role')->all();
    }

    /**
     * Bureau's own role assignments — same not-ranked, multi-role shape
     * as documentSigningRoles() above, see App\Enums\BureauRole.
     */
    public function bureauRoles(): HasMany
    {
        return $this->hasMany(BureauUserRole::class);
    }

    public function hasBureauRole(BureauRole $role): bool
    {
        return $this->bureauRoles->contains(fn (BureauUserRole $row): bool => $row->role === $role);
    }

    /**
     * @return array<int, BureauRole>
     */
    public function bureauRoleList(): array
    {
        return $this->bureauRoles->pluck('role')->all();
    }

    /**
     * "Simple majority of council membership" (see
     * App\Services\Bureau\DecisionVotingService) is measured against
     * every President/Councillor role holder system-wide — not just
     * meeting attendees — so a vote's threshold doesn't shrink just
     * because some council members are absent. Holding the system-wide
     * `admin` role doesn't itself count as council membership — only an
     * explicit President/Councillor BureauUserRole row does.
     */
    public static function bureauCouncilMembershipCount(): int
    {
        return static::query()
            ->whereHas('bureauRoles', fn ($query) => $query->whereIn('role', [BureauRole::President, BureauRole::Councillor]))
            ->distinct()
            ->count();
    }

    /**
     * Inventory's own role assignments — same not-ranked, multi-role
     * shape as bureauRoles()/documentSigningRoles() above, see
     * App\Enums\InventoryRole.
     */
    public function inventoryRoles(): HasMany
    {
        return $this->hasMany(InventoryUserRole::class);
    }

    public function hasInventoryRole(InventoryRole $role): bool
    {
        return $this->inventoryRoles->contains(fn (InventoryUserRole $row): bool => $row->role === $role);
    }

    /**
     * @return array<int, InventoryRole>
     */
    public function inventoryRoleList(): array
    {
        return $this->inventoryRoles->pluck('role')->all();
    }

    /**
     * Assets' own role assignments — same not-ranked, multi-role shape
     * as bureauRoles()/documentSigningRoles()/inventoryRoles() above,
     * see App\Enums\AssetRole.
     */
    public function assetRoles(): HasMany
    {
        return $this->hasMany(AssetUserRole::class);
    }

    public function hasAssetRole(AssetRole $role): bool
    {
        return $this->assetRoles->contains(fn (AssetUserRole $row): bool => $row->role === $role);
    }

    /**
     * @return array<int, AssetRole>
     */
    public function assetRoleList(): array
    {
        return $this->assetRoles->pluck('role')->all();
    }
}
