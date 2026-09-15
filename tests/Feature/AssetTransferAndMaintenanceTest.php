<?php

namespace Tests\Feature;

use App\Enums\AssetMaintenanceApprovalStatus;
use App\Enums\AssetRole;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Filament\Assets\Resources\Maintenance\AssetMaintenanceRecordResource;
use App\Filament\Assets\Resources\Transfers\AssetTransferRequestResource;
use App\Models\Asset;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetMaintenanceRecord;
use App\Models\AssetRoom;
use App\Models\AssetTransferRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTransferAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('assets'));
    }

    private function makeUser(?AssetRole $role = null): User
    {
        $user = User::factory()->create();

        if ($role) {
            $user->assetRoles()->create(['role' => $role]);
        }

        return $user;
    }

    private function makeAsset(User $admin, ?AssetRoom $room = null): Asset
    {
        $category = AssetCategory::create(['name' => 'Furniture', 'is_active' => true]);
        $room ??= $this->makeRoom('Room 101');

        return Asset::create([
            'asset_tag' => 'AST-'.random_int(100000, 999999),
            'public_token' => str()->random(32),
            'name' => 'Office Chair',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'status' => AssetStatus::InUse,
            'created_by' => $admin->id,
        ]);
    }

    private function makeRoom(string $name): AssetRoom
    {
        $building = AssetBuilding::firstOrCreate(['name' => 'Main Office'], ['is_active' => true]);

        return AssetRoom::create(['building_id' => $building->id, 'name' => $name, 'is_active' => true]);
    }

    /**
     * Invokes a Filament Action's own ->action() closure directly,
     * bypassing Livewire entirely — same reflection trick as
     * InventoryAdjustmentTest::invoke().
     */
    private function invoke(Action $action, Model $record, array $data = []): void
    {
        $callback = (fn () => $this->action)->call($action);
        $callback($record->fresh(), $data);
    }

    // ---- Transfers ----

    public function test_admin_can_request_a_transfer_and_it_creates_a_pending_request(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);
        $newRoom = $this->makeRoom('Room 202');

        $this->invoke(AssetResource::requestTransferAction(), $asset, ['to_room_id' => $newRoom->id, 'reason' => 'Reorganizing']);

        $this->assertTrue(AssetTransferRequest::where('asset_id', $asset->id)->where('status', AssetTransferStatus::Pending)->exists());
        $this->assertTrue($asset->fresh()->hasPendingTransfer());
    }

    public function test_cannot_request_a_second_transfer_while_one_is_pending(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);
        $newRoom = $this->makeRoom('Room 202');

        AssetTransferRequest::create([
            'asset_id' => $asset->id,
            'from_room_id' => $asset->room_id,
            'to_room_id' => $newRoom->id,
            'status' => AssetTransferStatus::Pending,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $this->assertFalse(
            AssetResource::requestTransferAction()->record($asset->fresh())->isVisible(),
        );
    }

    public function test_a_manager_can_approve_a_transfer_and_the_room_updates(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $originalRoom = $this->makeRoom('Room 101');
        $asset = $this->makeAsset($admin, $originalRoom);
        $newRoom = $this->makeRoom('Room 202');

        $request = AssetTransferRequest::create([
            'asset_id' => $asset->id,
            'from_room_id' => $originalRoom->id,
            'to_room_id' => $newRoom->id,
            'status' => AssetTransferStatus::Pending,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $manager = $this->makeUser(AssetRole::Manager);
        $this->actingAs($manager);

        $this->invoke(AssetTransferRequestResource::approveAction(), $request);

        $this->assertSame($newRoom->id, $asset->fresh()->room_id);
        $this->assertSame(AssetTransferStatus::Approved, $request->fresh()->status);
    }

    public function test_admin_cannot_approve_a_transfer_even_for_someone_elses_request(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);
        $newRoom = $this->makeRoom('Room 202');

        $request = AssetTransferRequest::create([
            'asset_id' => $asset->id,
            'from_room_id' => $asset->room_id,
            'to_room_id' => $newRoom->id,
            'status' => AssetTransferStatus::Pending,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $otherAdmin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($otherAdmin);

        $this->assertFalse(AssetTransferRequestResource::approveAction()->record($request)->isVisible());
    }

    public function test_a_manager_who_also_holds_admin_can_approve_their_own_request_and_it_is_flagged(): void
    {
        $both = $this->makeUser(AssetRole::Admin);
        $both->assetRoles()->create(['role' => AssetRole::Manager]);
        $this->actingAs($both);

        $asset = $this->makeAsset($both);
        $newRoom = $this->makeRoom('Room 202');

        $request = AssetTransferRequest::create([
            'asset_id' => $asset->id,
            'from_room_id' => $asset->room_id,
            'to_room_id' => $newRoom->id,
            'status' => AssetTransferStatus::Pending,
            'requested_by' => $both->id,
            'requested_at' => now(),
        ]);

        $this->assertTrue(AssetTransferRequestResource::approveAction()->record($request)->isVisible());

        $this->invoke(AssetTransferRequestResource::approveAction(), $request);

        $this->assertSame(AssetTransferStatus::Approved, $request->fresh()->status);
        $this->assertTrue($request->fresh()->isSelfApproved());
    }

    public function test_approval_is_blocked_if_the_asset_moved_since_the_request_was_made(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $originalRoom = $this->makeRoom('Room 101');
        $asset = $this->makeAsset($admin, $originalRoom);
        $newRoom = $this->makeRoom('Room 202');
        $elsewhereRoom = $this->makeRoom('Room 303');

        $request = AssetTransferRequest::create([
            'asset_id' => $asset->id,
            'from_room_id' => $originalRoom->id,
            'to_room_id' => $newRoom->id,
            'status' => AssetTransferStatus::Pending,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        // Asset moved by some other path.
        $asset->update(['room_id' => $elsewhereRoom->id]);

        $manager = $this->makeUser(AssetRole::Manager);
        $this->actingAs($manager);

        $this->invoke(AssetTransferRequestResource::approveAction(), $request);

        $this->assertSame(AssetTransferStatus::Pending, $request->fresh()->status);
        $this->assertSame($elsewhereRoom->id, $asset->fresh()->room_id);
    }

    public function test_the_requester_can_cancel_their_own_pending_transfer(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);
        $newRoom = $this->makeRoom('Room 202');

        $request = AssetTransferRequest::create([
            'asset_id' => $asset->id,
            'from_room_id' => $asset->room_id,
            'to_room_id' => $newRoom->id,
            'status' => AssetTransferStatus::Pending,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $this->assertTrue(AssetTransferRequestResource::cancelAction()->record($request)->isVisible());

        $this->invoke(AssetTransferRequestResource::cancelAction(), $request);

        $this->assertSame(AssetTransferStatus::Cancelled, $request->fresh()->status);
    }

    public function test_an_unrelated_viewer_cannot_cancel_someone_elses_transfer(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);
        $newRoom = $this->makeRoom('Room 202');

        $request = AssetTransferRequest::create([
            'asset_id' => $asset->id,
            'from_room_id' => $asset->room_id,
            'to_room_id' => $newRoom->id,
            'status' => AssetTransferStatus::Pending,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);

        $viewer = $this->makeUser(AssetRole::Viewer);
        $this->actingAs($viewer);

        $this->assertFalse(AssetTransferRequestResource::cancelAction()->record($request)->isVisible());
    }

    // ---- Maintenance ----

    public function test_logging_maintenance_flips_the_asset_to_under_repair(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);

        $this->invoke(AssetResource::logMaintenanceAction(), $asset, [
            'description' => 'Broken wheel',
            'maintenance_date' => now()->toDateString(),
        ]);

        $asset->refresh();
        $this->assertSame(AssetStatus::UnderRepair, $asset->status);

        $record = AssetMaintenanceRecord::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame(AssetStatus::InUse, $record->previous_status);
        $this->assertSame(AssetMaintenanceApprovalStatus::Pending, $record->approval_status);
    }

    public function test_only_one_open_maintenance_record_at_a_time(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);

        AssetMaintenanceRecord::create([
            'asset_id' => $asset->id,
            'description' => 'Existing issue',
            'maintenance_date' => now()->toDateString(),
            'previous_status' => AssetStatus::InUse,
            'approval_status' => AssetMaintenanceApprovalStatus::Pending,
            'recorded_by' => $admin->id,
        ]);

        $this->assertFalse(AssetResource::logMaintenanceAction()->record($asset->fresh())->isVisible());
    }

    public function test_rejecting_a_maintenance_record_does_not_restore_the_asset_status(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);
        $asset->update(['status' => AssetStatus::UnderRepair]);

        $record = AssetMaintenanceRecord::create([
            'asset_id' => $asset->id,
            'description' => 'Broken wheel',
            'maintenance_date' => now()->toDateString(),
            'previous_status' => AssetStatus::InUse,
            'approval_status' => AssetMaintenanceApprovalStatus::Pending,
            'recorded_by' => $admin->id,
        ]);

        $manager = $this->makeUser(AssetRole::Manager);
        $this->actingAs($manager);

        $this->invoke(AssetMaintenanceRecordResource::rejectAction(), $record, ['decision_note' => 'Not a valid repair']);

        $record->refresh();
        $this->assertSame(AssetMaintenanceApprovalStatus::Rejected, $record->approval_status);
        $this->assertSame(AssetStatus::UnderRepair, $asset->fresh()->status);
        $this->assertTrue($record->isOpen());
    }

    public function test_admin_closes_a_maintenance_record_and_the_asset_status_updates(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $asset = $this->makeAsset($admin);
        $asset->update(['status' => AssetStatus::UnderRepair]);

        $record = AssetMaintenanceRecord::create([
            'asset_id' => $asset->id,
            'description' => 'Broken wheel',
            'maintenance_date' => now()->toDateString(),
            'previous_status' => AssetStatus::InUse,
            'approval_status' => AssetMaintenanceApprovalStatus::Approved,
            'recorded_by' => $admin->id,
        ]);

        $this->invoke(AssetMaintenanceRecordResource::closeAction(), $record, ['closing_status' => AssetStatus::InUse->value]);

        $record->refresh();
        $this->assertFalse($record->isOpen());
        $this->assertSame(AssetStatus::InUse, $record->closing_status);
        $this->assertSame(AssetStatus::InUse, $asset->fresh()->status);
    }
}
