<?php

namespace App\Filament\Assets\Concerns;

use App\Enums\AssetRole;
use App\Models\User;

/**
 * Shared by every Resource/Page in the Assets panel — hand-rolled
 * against AssetRole, same shape as
 * App\Filament\Inventory\Concerns\HasInventoryRoleAccess. Assets has no
 * per-user ownership to scope visibility by (every asset is visible to
 * every role alike — spec section 8, "View asset list/detail": Admin/
 * Manager/Viewer all checked), so a user holding none of the three
 * AssetRoles must be denied at canAccess() entirely.
 */
trait HasAssetRoleAccess
{
    public static function userHasAnyAssetRole(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && collect(AssetRole::cases())->contains(fn (AssetRole $role): bool => $user->hasAssetRole($role));
    }

    /**
     * Full CRUD on assets, maintenance logging, transfer initiation,
     * category/location management, role management — Admin only, no
     * OR-with-another-role (spec section 4's permission matrix: Manager
     * is explicitly ❌ on every one of these).
     */
    public static function userIsAssetAdmin(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && $user->hasAssetRole(AssetRole::Admin);
    }

    /**
     * Approve/reject transfers and maintenance, start/close audits,
     * verify, review discrepancies — Manager only, no OR-with-Admin
     * (spec section 4: Admin is explicitly ❌ on approve/reject).
     */
    public static function userIsAssetManager(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && $user->hasAssetRole(AssetRole::Manager);
    }

    /**
     * A few actions both Admin and Manager may take — cancelling a
     * pending transfer, starting/closing/verifying an audit (spec
     * section 4 rows where both columns are checked).
     */
    public static function userIsAssetAdminOrManager(): bool
    {
        return self::userIsAssetAdmin() || self::userIsAssetManager();
    }
}
