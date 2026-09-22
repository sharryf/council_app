<?php

namespace Tests\Feature;

use App\Enums\InventoryRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1's own "role checks return 403 correctly" check — see the
 * inventory module's Phase 1 plan. The panel itself admits any
 * authenticated user (User::canAccessPanel() is a single app-wide
 * gate); per-page authorization happens on
 * App\Filament\Inventory\Pages\Dashboard::canAccess() instead.
 */
class InventoryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));
    }

    public function test_a_user_with_no_inventory_role_is_denied(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/inventory')
            ->assertForbidden();
    }

    public function test_a_user_with_any_inventory_role_can_reach_the_dashboard(): void
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::User]);

        $this->actingAs($user)
            ->get('/inventory')
            ->assertOk();
    }

    /**
     * `admin` is Users-page administration only — it grants no
     * Inventory role by itself, so a bare admin is refused the panel
     * exactly like any other user with no inventory role.
     */
    public function test_the_system_admin_role_grants_no_inventory_access_by_itself(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/inventory')
            ->assertForbidden();
    }

    public function test_an_admin_with_an_explicit_inventory_role_can_reach_the_panel(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->inventoryRoles()->create(['role' => \App\Enums\InventoryRole::User]);

        $this->actingAs($user)
            ->get('/inventory')
            ->assertOk();
    }
}
