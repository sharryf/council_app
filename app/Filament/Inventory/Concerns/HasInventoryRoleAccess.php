<?php

namespace App\Filament\Inventory\Concerns;

use App\Enums\InventoryRole;
use App\Models\User;

/**
 * Shared by every Inventory Resource — hand-rolled against InventoryRole
 * rather than Filament's HasModuleAccess trait (that's the generic
 * admin-panel Viewer/Editor/Approver system this module deliberately
 * doesn't use, see the Phase 1 plan). Unlike Bureau's resources (which
 * each duplicate the same two checks inline), Inventory's reference-data
 * resources are numerous enough and identical enough in their access
 * rule that a shared trait is worth it here.
 */
trait HasInventoryRoleAccess
{
    /**
     * Any inventory role may view — spec 5.2's `user`-level capability
     * covers browsing the catalogue and reference data alike.
     */
    public static function userHasAnyInventoryRole(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && collect(InventoryRole::cases())->contains(fn (InventoryRole $role): bool => $user->hasInventoryRole($role));
    }

    /**
     * Only Stock Admin/Admin may create/edit/delete items and reference
     * data (spec 5.2).
     */
    public static function userIsStockAdminOrAbove(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && ($user->hasInventoryRole(InventoryRole::StockAdmin) || $user->hasInventoryRole(InventoryRole::Admin));
    }

    /**
     * Only Approver/Admin may approve/reject/return issue requests
     * (spec 5.2). hasInventoryRole() checks an exact role match (see
     * its own comment on why the system-wide spatie admin role — not
     * this module's own Admin case — is the one that implicitly holds
     * every InventoryRole), so Approver access needs this explicit OR
     * the same way StockAdmin access does above.
     */
    public static function userIsApproverOrAbove(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && ($user->hasInventoryRole(InventoryRole::Approver) || $user->hasInventoryRole(InventoryRole::Admin));
    }

    /**
     * Settings and role management (spec 10.9/10.10) are Admin-only —
     * no OR-with-a-lesser-role here, unlike the two helpers above.
     */
    public static function userIsAdmin(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && $user->hasInventoryRole(InventoryRole::Admin);
    }
}
