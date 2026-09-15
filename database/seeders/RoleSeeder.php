<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * `admin` is the only spatie/laravel-permission role the app uses —
     * it grants system administration (Users management) and Approver
     * level in every module (see App\Models\User::roleFor()).
     *
     * Per-module capability (Viewer/Editor/Approver) is handled by
     * App\Models\UserModuleLevel, not by a role per module — see the
     * "Module access levels" section of the README.
     */
    public function run(): void
    {
        Role::findOrCreate('admin', 'web');
    }
}
