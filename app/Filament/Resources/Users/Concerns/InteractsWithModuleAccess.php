<?php

namespace App\Filament\Resources\Users\Concerns;

use App\Enums\AssetRole;
use App\Enums\BureauRole;
use App\Enums\DocumentSigningRole;
use App\Enums\InventoryRole;
use App\Enums\ModuleAccessLevel;
use App\Models\AssetUserRole;
use App\Models\BureauUserRole;
use App\Models\DocumentSigningUserRole;
use App\Models\InventoryAuditLog;
use App\Models\InventoryUserRole;
use App\Models\User;
use Filament\Notifications\Notification;

/**
 * Shared by CreateUser and EditUser (different base classes, so a
 * trait rather than shared inheritance) to move every "virtual" form
 * field on UserForm — is_admin, module_access, and the whole "Module
 * Roles" section — between the form and its real storage.
 *
 * `admin` (see UserResource::canAccess()) is Users-page administration
 * only; it grants nothing in any module (see User::roleFor() and the
 * four hasXRole() methods). So an admin's own module capabilities are
 * assigned explicitly here exactly like anyone else's — this is the
 * one place a System Admin can see and set all of them for a user at
 * once, instead of visiting four separate per-module Roles pages (see
 * App\Filament\Bureau\Widgets\BureauRolesTable and its equivalents,
 * which every other user's roles are still managed from).
 *
 * `module_access` is a real User column (JSON array of module keys, or
 * null meaning "every module") so it doesn't need extraction like
 * is_admin does — but the form always submits an explicit array (every
 * box either checked or not), so "every module currently checked" is
 * normalized back to null here. That keeps a user who's never been
 * deliberately restricted seeing new modules automatically as they're
 * added to config/modules.php, rather than freezing them to today's
 * module list the first time an admin merely opens and saves their
 * form.
 */
trait InteractsWithModuleAccess
{
    protected bool $pendingIsAdmin = false;

    /** @var array<string, string> module key => ModuleAccessLevel value */
    protected array $pendingModuleLevels = [];

    /** @var array<int, string> */
    protected array $pendingBureauRoles = [];

    /** @var array<int, string> */
    protected array $pendingDocumentSigningRoles = [];

    /** @var array<int, string> */
    protected array $pendingInventoryRoles = [];

    /** @var array<int, string> */
    protected array $pendingAssetRoles = [];

    /**
     * Modules with no dedicated role enum of their own yet (see
     * config/modules.php) still use the generic Viewer/Editor/Approver
     * scale as their only capability level — document-signing uses it
     * as a base floor *alongside* its own DocumentSigningRole; Bureau,
     * Inventory, and Assets each have a dedicated role enum instead and
     * don't consult this scale at all (see each one's own Panel, which
     * never calls roleFor()), so showing a level selector for them
     * would set a value nothing ever reads.
     *
     * @var array<int, string>
     */
    private const GENERIC_LEVEL_MODULES = ['document-signing', 'registries', 'permits', 'hr-payroll', 'events'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractModuleLevelFields(array $data): array
    {
        $this->pendingIsAdmin = (bool) ($data['is_admin'] ?? false);
        unset($data['is_admin']);

        $allModules = array_keys(config('modules'));
        $selected = $data['module_access'] ?? [];

        $data['module_access'] = array_diff($allModules, $selected) === [] ? null : array_values($selected);

        foreach (self::GENERIC_LEVEL_MODULES as $moduleKey) {
            $field = "level_{$moduleKey}";
            $this->pendingModuleLevels[$moduleKey] = $data[$field] ?? ModuleAccessLevel::Viewer->value;
            unset($data[$field]);
        }

        $this->pendingBureauRoles = $data['bureau_roles'] ?? [];
        unset($data['bureau_roles']);

        $this->pendingDocumentSigningRoles = $data['document_signing_roles'] ?? [];
        unset($data['document_signing_roles']);

        $this->pendingInventoryRoles = $data['inventory_roles'] ?? [];
        unset($data['inventory_roles']);

        $this->pendingAssetRoles = $data['asset_roles'] ?? [];
        unset($data['asset_roles']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillModuleLevelFields(array $data, User $record): array
    {
        $data['is_admin'] = $record->hasRole('admin');
        $data['module_access'] = $record->module_access ?? array_keys(config('modules'));

        foreach (self::GENERIC_LEVEL_MODULES as $moduleKey) {
            $data["level_{$moduleKey}"] = $record->roleFor($moduleKey)->value;
        }

        $data['bureau_roles'] = $record->bureauRoles->pluck('role')->map(fn (BureauRole $role): string => $role->value)->all();
        $data['document_signing_roles'] = $record->documentSigningRoles->pluck('role')->map(fn (DocumentSigningRole $role): string => $role->value)->all();
        $data['inventory_roles'] = $record->inventoryRoles->pluck('role')->map(fn (InventoryRole $role): string => $role->value)->all();
        $data['asset_roles'] = $record->assetRoles->pluck('role')->map(fn (AssetRole $role): string => $role->value)->all();

        return $data;
    }

    protected function persistModuleLevels(User $user): void
    {
        $user->syncRoles($this->pendingIsAdmin ? ['admin'] : []);

        foreach ($this->pendingModuleLevels as $moduleKey => $level) {
            $user->moduleLevels()->updateOrCreate(['module' => $moduleKey], ['level' => $level]);
        }

        $user->bureauRoles()
            ->whereNotIn('role', $this->pendingBureauRoles)
            ->delete();
        foreach ($this->pendingBureauRoles as $role) {
            BureauUserRole::query()->firstOrCreate(['user_id' => $user->id, 'role' => $role]);
        }

        $user->documentSigningRoles()
            ->whereNotIn('role', $this->pendingDocumentSigningRoles)
            ->delete();
        foreach ($this->pendingDocumentSigningRoles as $role) {
            DocumentSigningUserRole::query()->firstOrCreate(['user_id' => $user->id, 'role' => $role]);
        }

        $user->assetRoles()
            ->whereNotIn('role', $this->pendingAssetRoles)
            ->delete();
        foreach ($this->pendingAssetRoles as $role) {
            AssetUserRole::query()->firstOrCreate(['user_id' => $user->id, 'role' => $role]);
        }

        $this->persistInventoryRoles($user);
    }

    /**
     * Inventory alone needs guarding and an audit trail here, matching
     * App\Filament\Inventory\Widgets\InventoryRolesTable::applyRoleChange
     * (the module's spec requires every role change to write to
     * inventory_audit_log, and the module can never be left with zero
     * Admins) — this is the second entry point into that same
     * invariant, so it re-checks it itself rather than trusting the
     * caller.
     */
    private function persistInventoryRoles(User $user): void
    {
        $current = $user->inventoryRoles->pluck('role');
        $selected = collect($this->pendingInventoryRoles)->map(fn (string $v): InventoryRole => InventoryRole::from($v))->unique()->values();

        $removingAdmin = $current->contains(InventoryRole::Admin) && ! $selected->contains(InventoryRole::Admin);

        if ($removingAdmin && $user->id === auth()->id()) {
            Notification::make()
                ->title("Kept your own Inventory Admin role")
                ->body("You can't remove your own Inventory Admin role — ask another Inventory Admin to do it.")
                ->warning()
                ->send();

            $selected->push(InventoryRole::Admin);
            $removingAdmin = false;
        }

        if ($removingAdmin) {
            $anotherAdminExists = InventoryUserRole::query()
                ->where('role', InventoryRole::Admin)
                ->where('user_id', '!=', $user->id)
                ->exists();

            if (! $anotherAdminExists) {
                Notification::make()
                    ->title("Kept {$user->name}'s Inventory Admin role")
                    ->body('At least one user must hold Inventory Admin — remove it here only after giving it to someone else.')
                    ->warning()
                    ->send();

                $selected->push(InventoryRole::Admin);
            }
        }

        $oldValues = $current->map(fn (InventoryRole $r): string => $r->value)->sort()->values()->all();
        $newValues = $selected->map(fn (InventoryRole $r): string => $r->value)->sort()->values()->all();

        $user->inventoryRoles()
            ->whereNotIn('role', $newValues)
            ->delete();
        foreach ($selected as $role) {
            InventoryUserRole::query()->firstOrCreate(['user_id' => $user->id, 'role' => $role]);
        }

        if ($oldValues !== $newValues) {
            InventoryAuditLog::write('USER_ROLE', $user->id, 'ROLE_ASSIGN', ['roles' => $oldValues], ['roles' => $newValues]);
        }

        $user->unsetRelation('inventoryRoles');
    }
}
