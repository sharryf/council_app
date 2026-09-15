<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Filament\Resources\DocumentSigning\Documents\Pages\DocumentSigningRoles;
use App\Filament\Resources\DocumentSigning\Documents\Widgets\DocumentSigningRolesTable;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Settings > Roles — Admin-only (see App\Enums\DocumentSigningRole),
 * assigns this module's own roles. Unlike other modules' Viewer/Editor/
 * Approver level (a single value, editable via SelectColumn), a user
 * can hold any combination of these four, so each row is edited via a
 * modal form instead of an inline column.
 */
class DocumentSigningRolesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_reach_the_roles_page(): void
    {
        $this->seed(RoleSeeder::class);
        $editor = User::factory()->create();
        $editor->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $this->actingAs($editor)
            ->get(DocumentSigningRoles::getUrl())
            ->assertForbidden();
    }

    public function test_the_module_admin_role_can_reach_the_roles_page(): void
    {
        $this->seed(RoleSeeder::class);
        $moduleAdmin = User::factory()->create();
        $moduleAdmin->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        $this->actingAs($moduleAdmin)
            ->get(DocumentSigningRoles::getUrl())
            ->assertSuccessful();
    }

    public function test_a_user_excluded_from_the_module_cannot_reach_the_roles_page(): void
    {
        $this->seed(RoleSeeder::class);
        $moduleAdmin = User::factory()->create(['module_access' => ['bureau']]);
        $moduleAdmin->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        $this->actingAs($moduleAdmin)
            ->get(DocumentSigningRoles::getUrl())
            ->assertForbidden();
    }

    public function test_the_roles_table_excludes_admins_and_users_without_module_access(): void
    {
        $this->seed(RoleSeeder::class);
        $moduleAdmin = User::factory()->create();
        $moduleAdmin->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        $inScope = User::factory()->create(['name' => 'In Scope']);
        $excluded = User::factory()->create(['name' => 'Excluded', 'module_access' => ['bureau']]);
        $systemAdmin = User::factory()->create(['name' => 'System Admin User']);
        $systemAdmin->assignRole('admin');

        $this->actingAs($moduleAdmin);

        Livewire::test(DocumentSigningRolesTable::class)
            ->assertCanSeeTableRecords([$moduleAdmin, $inScope])
            ->assertCanNotSeeTableRecords([$excluded, $systemAdmin]);
    }

    public function test_editing_roles_replaces_the_full_set_for_that_user(): void
    {
        $this->seed(RoleSeeder::class);
        $moduleAdmin = User::factory()->create();
        $moduleAdmin->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        $target = User::factory()->create();
        $target->documentSigningRoles()->create(['role' => DocumentSigningRole::Viewer]);

        $this->actingAs($moduleAdmin);

        Livewire::test(DocumentSigningRolesTable::class)
            ->mountTableAction('editRoles', $target->getKey())
            ->assertTableActionDataSet(['roles' => ['viewer']])
            ->setTableActionData(['roles' => ['editor', 'signee']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $target->refresh();

        $this->assertFalse($target->hasDocumentSigningRole(DocumentSigningRole::Viewer));
        $this->assertTrue($target->hasDocumentSigningRole(DocumentSigningRole::Editor));
        $this->assertTrue($target->hasDocumentSigningRole(DocumentSigningRole::Signee));
    }
}
