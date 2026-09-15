<?php

namespace Tests\Feature;

use App\Enums\InventoryRole;
use App\Filament\Inventory\Pages\InventoryRoles;
use App\Filament\Inventory\Widgets\InventoryRolesTable;
use App\Models\InventoryAuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::Admin]);

        return $user;
    }

    public function test_a_non_admin_cannot_reach_the_roles_page(): void
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::StockAdmin]);

        $this->actingAs($user)->get(InventoryRoles::getUrl())->assertForbidden();
    }

    public function test_an_admin_can_reach_the_roles_page(): void
    {
        $this->actingAs($this->makeAdmin())->get(InventoryRoles::getUrl())->assertSuccessful();
    }

    public function test_editing_roles_replaces_the_full_set_and_writes_an_audit_row(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->create();
        $target->inventoryRoles()->create(['role' => InventoryRole::User]);

        $this->actingAs($admin);

        Livewire::test(InventoryRolesTable::class)
            ->mountTableAction('editRoles', $target->getKey())
            ->assertTableActionDataSet(['roles' => ['user']])
            ->setTableActionData(['roles' => ['approver', 'stock_admin']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $target->refresh();

        $this->assertFalse($target->hasInventoryRole(InventoryRole::User));
        $this->assertTrue($target->hasInventoryRole(InventoryRole::Approver));
        $this->assertTrue($target->hasInventoryRole(InventoryRole::StockAdmin));

        $log = InventoryAuditLog::where('entity_type', 'USER_ROLE')->where('entity_id', $target->id)->sole();
        $this->assertSame('ROLE_ASSIGN', $log->action);
        $this->assertSame(['user'], $log->old_values['roles']);
        $this->assertEqualsCanonicalizing(['approver', 'stock_admin'], $log->new_values['roles']);
        $this->assertSame($admin->id, $log->changed_by);
    }

    public function test_an_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->makeAdmin();
        // A second admin exists, so the last-admin guard alone
        // wouldn't be what blocks this — it must be the self-guard.
        $this->makeAdmin();

        $this->actingAs($admin);

        Livewire::test(InventoryRolesTable::class)
            ->mountTableAction('editRoles', $admin->getKey())
            ->setTableActionData(['roles' => []])
            ->callMountedTableAction();

        $this->assertTrue($admin->fresh()->hasInventoryRole(InventoryRole::Admin));
        $this->assertSame(0, InventoryAuditLog::where('entity_type', 'USER_ROLE')->count());
    }

    public function test_the_last_admin_cannot_be_demoted_by_someone_else(): void
    {
        $admin = $this->makeAdmin();
        $onlyAdmin = User::factory()->create();
        $onlyAdmin->inventoryRoles()->create(['role' => InventoryRole::Admin]);

        // Make $admin no longer an Admin so only $onlyAdmin remains —
        // but keep acting as $admin (who still passes canAccess() via
        // the spatie bypass isn't in play here; this test only needs
        // an authenticated actor to invoke the action, not necessarily
        // one who could reach the page through the UI's own gate).
        $admin->inventoryRoles()->where('role', InventoryRole::Admin)->delete();

        $this->actingAs($admin);

        Livewire::test(InventoryRolesTable::class)
            ->mountTableAction('editRoles', $onlyAdmin->getKey())
            ->setTableActionData(['roles' => ['user']])
            ->callMountedTableAction();

        $this->assertTrue($onlyAdmin->fresh()->hasInventoryRole(InventoryRole::Admin));
    }

    public function test_bulk_assigning_a_role_applies_to_every_selected_user_without_duplicating(): void
    {
        $admin = $this->makeAdmin();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userB->inventoryRoles()->create(['role' => InventoryRole::StockAdmin]);

        $this->actingAs($admin);

        Livewire::test(InventoryRolesTable::class)
            ->callTableBulkAction('assignRole', [$userA, $userB], data: ['role' => 'stock_admin']);

        $this->assertTrue($userA->fresh()->hasInventoryRole(InventoryRole::StockAdmin));
        $this->assertSame(1, $userB->inventoryRoles()->where('role', InventoryRole::StockAdmin)->count());

        // Only userA's role actually changed (userB already had it) —
        // exactly one audit row, not two.
        $this->assertSame(1, InventoryAuditLog::where('entity_type', 'USER_ROLE')->count());
    }
}
