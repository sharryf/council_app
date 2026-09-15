<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Filament\Resources\DocumentSigning\Documents\DocumentResource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers User::canAccessModule() / module_access — separate from
 * DocumentSigningRoleGatingTest, which covers this module's own roles
 * *within* a module a user can already reach. A user excluded from a
 * module here can't reach it at all, regardless of role.
 */
class ModuleAccessRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_null_module_access_can_reach_every_module(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['module_access' => null]);

        $this->assertTrue($user->canAccessModule('document-signing'));
    }

    public function test_a_user_restricted_to_other_modules_is_denied_the_create_page(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['module_access' => ['bureau']]);
        $user->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $this->assertFalse($user->canAccessModule('document-signing'));

        $this->actingAs($user)
            ->get(DocumentResource::getUrl('create'))
            ->assertForbidden();
    }

    public function test_admin_ignores_module_access_restrictions(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['module_access' => []]);
        $admin->assignRole('admin');

        $this->assertTrue($admin->canAccessModule('document-signing'));

        $this->actingAs($admin)
            ->get(DocumentResource::getUrl('create'))
            ->assertSuccessful();
    }
}
