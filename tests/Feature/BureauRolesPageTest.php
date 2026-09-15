<?php

namespace Tests\Feature;

use App\Enums\BureauRole;
use App\Filament\Bureau\Pages\BureauRoles;
use App\Filament\Bureau\Widgets\BureauRolesTable;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Settings > Roles — Module-Admin-only (see App\Enums\BureauRole),
 * assigns this module's six roles. Same shape as
 * DocumentSigningRolesPageTest — a user can hold any combination of
 * them, so each row is edited via a modal form instead of an inline
 * column.
 */
class BureauRolesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('bureau'));
    }

    public function test_a_non_module_admin_cannot_reach_the_roles_page(): void
    {
        $this->seed(RoleSeeder::class);
        $councillor = User::factory()->create();
        $councillor->bureauRoles()->create(['role' => BureauRole::Councillor]);

        $this->actingAs($councillor)
            ->get(BureauRoles::getUrl())
            ->assertForbidden();
    }

    public function test_the_module_admin_role_can_reach_the_roles_page(): void
    {
        $this->seed(RoleSeeder::class);
        $moduleAdmin = User::factory()->create();
        $moduleAdmin->bureauRoles()->create(['role' => BureauRole::ModuleAdmin]);

        $this->actingAs($moduleAdmin)
            ->get(BureauRoles::getUrl())
            ->assertSuccessful();
    }

    public function test_a_user_excluded_from_the_module_cannot_reach_the_roles_page(): void
    {
        $this->seed(RoleSeeder::class);
        $moduleAdmin = User::factory()->create(['module_access' => ['document-signing']]);
        $moduleAdmin->bureauRoles()->create(['role' => BureauRole::ModuleAdmin]);

        $this->actingAs($moduleAdmin)
            ->get(BureauRoles::getUrl())
            ->assertForbidden();
    }

    public function test_the_roles_table_excludes_admins_and_users_without_module_access(): void
    {
        $this->seed(RoleSeeder::class);
        $moduleAdmin = User::factory()->create();
        $moduleAdmin->bureauRoles()->create(['role' => BureauRole::ModuleAdmin]);

        $inScope = User::factory()->create(['name' => 'In Scope']);
        $excluded = User::factory()->create(['name' => 'Excluded', 'module_access' => ['document-signing']]);
        $systemAdmin = User::factory()->create(['name' => 'System Admin User']);
        $systemAdmin->assignRole('admin');

        $this->actingAs($moduleAdmin);

        Livewire::test(BureauRolesTable::class)
            ->assertCanSeeTableRecords([$moduleAdmin, $inScope])
            ->assertCanNotSeeTableRecords([$excluded, $systemAdmin]);
    }

    public function test_editing_roles_replaces_the_full_set_for_that_user(): void
    {
        $this->seed(RoleSeeder::class);
        $moduleAdmin = User::factory()->create();
        $moduleAdmin->bureauRoles()->create(['role' => BureauRole::ModuleAdmin]);

        $target = User::factory()->create();
        $target->bureauRoles()->create(['role' => BureauRole::Staff]);

        $this->actingAs($moduleAdmin);

        Livewire::test(BureauRolesTable::class)
            ->mountTableAction('editRoles', $target->getKey())
            ->assertTableActionDataSet(['roles' => ['staff']])
            ->setTableActionData(['roles' => ['councillor', 'president']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $target->refresh();

        $this->assertFalse($target->hasBureauRole(BureauRole::Staff));
        $this->assertTrue($target->hasBureauRole(BureauRole::Councillor));
        $this->assertTrue($target->hasBureauRole(BureauRole::President));
    }
}
