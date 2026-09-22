<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The `admin` role used to be a full bypass — every module-role
     * check (User::roleFor(), hasBureauRole(), hasDocumentSigningRole(),
     * hasInventoryRole(), hasAssetRole()) short-circuited to "yes" for
     * anyone holding it, so an admin never needed real role rows. That
     * bypass is gone: `admin` now means Users-page administration only
     * (see UserResource::canAccess()), and every module capability is
     * assigned explicitly like anyone else's.
     *
     * Without this, every existing admin (on this database and on
     * every already-deployed client install) would lose all module
     * access the moment this ships. This gives each of them, once,
     * exactly what they already had: every role in every module. From
     * here on it's a normal, editable starting point — see the Users
     * page's "Module Roles" section — not a re-applied bypass.
     */
    public function up(): void
    {
        $adminIds = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', \App\Models\User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->pluck('users.id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($adminIds as $userId) {
            foreach (['document-signing', 'registries', 'permits', 'hr-payroll', 'events'] as $module) {
                DB::table('user_module_levels')->updateOrInsert(
                    ['user_id' => $userId, 'module' => $module],
                    ['level' => 'approver', 'updated_at' => $now, 'created_at' => $now],
                );
            }

            foreach (['president', 'councillor', 'participant', 'bureau_admin', 'staff', 'module_admin'] as $role) {
                DB::table('bureau_user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role' => $role],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }

            foreach (['admin', 'editor', 'signee', 'viewer'] as $role) {
                DB::table('document_signing_user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role' => $role],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }

            foreach (['user', 'approver', 'stock_admin', 'admin'] as $role) {
                DB::table('inventory_user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role' => $role],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }

            foreach (['admin', 'manager', 'viewer'] as $role) {
                DB::table('asset_user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role' => $role],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }

    /**
     * Not reversible in the way that matters — the whole point is that
     * admins keep the access they had. Rolling back this migration
     * doesn't restore the old bypass logic (that's a code change, not a
     * data one) and removing these rows now would just as surely lock
     * every admin out, so there is nothing safe to do here.
     */
    public function down(): void {}
};
