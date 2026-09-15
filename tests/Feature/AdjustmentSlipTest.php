<?php

namespace Tests\Feature;

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryAdjustmentType;
use App\Enums\InventoryRole;
use App\Models\InventoryLocation;
use App\Models\InventoryStockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentSlipTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdjustment(array $overrides = []): InventoryStockAdjustment
    {
        $location = InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);

        return InventoryStockAdjustment::create([
            'adjustment_no' => 'ADJ-2026-0001',
            'adjustment_date' => now(),
            'location_id' => $location->id,
            'adjustment_type' => InventoryAdjustmentType::StockTake,
            'reason' => 'Test adjustment',
            'status' => InventoryAdjustmentStatus::Draft,
            ...$overrides,
        ]);
    }

    public function test_the_slip_is_not_found_before_the_adjustment_is_posted(): void
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::StockAdmin]);

        $adjustment = $this->makeAdjustment();

        $this->actingAs($user)
            ->get(route('inventory.adjustments.slip', $adjustment))
            ->assertNotFound();
    }

    public function test_a_user_without_any_inventory_role_is_forbidden(): void
    {
        $user = User::factory()->create();

        $adjustment = $this->makeAdjustment([
            'status' => InventoryAdjustmentStatus::Posted,
            'posted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('inventory.adjustments.slip', $adjustment))
            ->assertForbidden();
    }
}
