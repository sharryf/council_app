<?php

namespace Tests\Feature;

use App\Enums\AssetAuditScopeType;
use App\Enums\AssetAuditSessionStatus;
use App\Enums\AssetDeleteRequestStatus;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetRole;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Filament\Assets\Resources\DeleteRequests\AssetDeleteRequestResource;
use App\Models\Asset;
use App\Models\AssetAuditItem;
use App\Models\AssetAuditSession;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetDeleteRequest;
use App\Models\AssetHistory;
use App\Models\AssetRoom;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDeleteRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('assets'));
    }

    public function test_admin_cannot_delete_a_posted_asset_directly(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makePostedAsset($admin);

        $this->assertFalse(AssetResource::canDelete($asset));
    }

    public function test_admin_can_still_delete_a_draft_asset_directly(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makeDraftAsset($admin);

        $this->assertTrue(AssetResource::canDelete($asset));
    }

    public function test_manager_can_request_deletion_of_a_posted_asset(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makePostedAsset($admin);

        $manager = $this->makeManager();
        $this->actingAs($manager);

        $this->invoke(AssetResource::requestDeleteAction(), $asset, ['reason' => 'Duplicate entry']);

        $request = AssetDeleteRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame(AssetDeleteRequestStatus::Pending, $request->status);
        $this->assertSame($manager->id, $request->requested_by);
        $this->assertSame('Duplicate entry', $request->reason);
        $this->assertNotNull($asset->fresh());

        $this->assertTrue(
            AssetHistory::where('asset_id', $asset->id)->where('event_type', 'delete_requested')->exists(),
        );
    }

    public function test_admin_cannot_request_deletion(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makePostedAsset($admin);

        $this->assertFalse(AssetResource::requestDeleteAction()->record($asset)->isVisible());
    }

    public function test_cannot_request_a_second_deletion_while_one_is_pending(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makePostedAsset($admin);

        AssetDeleteRequest::create([
            'asset_id' => $asset->id,
            'reason' => 'First request',
            'status' => AssetDeleteRequestStatus::Pending,
            'requested_by' => $this->makeManager()->id,
            'requested_at' => now(),
        ]);

        $manager = $this->makeManager();
        $this->actingAs($manager);

        $this->assertFalse(AssetResource::requestDeleteAction()->record($asset->fresh())->isVisible());
    }

    public function test_an_asset_with_an_active_audit_item_cannot_have_deletion_requested(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makePostedAsset($admin);

        $session = AssetAuditSession::create([
            'name' => 'Audit', 'scope_type' => AssetAuditScopeType::All, 'status' => AssetAuditSessionStatus::InProgress,
            'started_by' => $admin->id, 'started_at' => now(),
        ]);
        AssetAuditItem::create(['session_id' => $session->id, 'asset_id' => $asset->id, 'expected_room_id' => $asset->room_id]);

        $manager = $this->makeManager();
        $this->actingAs($manager);

        $this->assertFalse(AssetResource::requestDeleteAction()->record($asset->fresh())->isVisible());
    }

    public function test_a_different_manager_can_approve_a_delete_request_and_it_deletes_the_asset(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makePostedAsset($admin);

        $requester = $this->makeManager();
        $this->actingAs($requester);
        $this->invoke(AssetResource::requestDeleteAction(), $asset, ['reason' => 'Duplicate entry']);

        $request = AssetDeleteRequest::where('asset_id', $asset->id)->firstOrFail();

        $approver = $this->makeManager();
        $this->actingAs($approver);

        $this->assertTrue(AssetDeleteRequestResource::approveAction()->record($request)->isVisible());

        $this->invoke(AssetDeleteRequestResource::approveAction(), $request);

        $this->assertSame(AssetDeleteRequestStatus::Approved, $request->fresh()->status);
        $this->assertSame($approver->id, $request->fresh()->reviewed_by);
        $this->assertTrue($asset->fresh()->trashed());
        $this->assertTrue(
            AssetHistory::where('asset_id', $asset->id)->where('event_type', 'delete_approved')->exists(),
        );
    }

    public function test_a_manager_cannot_approve_their_own_delete_request(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makePostedAsset($admin);

        $manager = $this->makeManager();
        $this->actingAs($manager);
        $this->invoke(AssetResource::requestDeleteAction(), $asset, ['reason' => 'Duplicate entry']);

        $request = AssetDeleteRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->assertFalse(AssetDeleteRequestResource::approveAction()->record($request)->isVisible());

        // Server-side check holds even if the button were reached
        // directly (e.g. a stale page from before the request was made).
        $this->invoke(AssetDeleteRequestResource::approveAction(), $request);

        $this->assertSame(AssetDeleteRequestStatus::Pending, $request->fresh()->status);
        $this->assertFalse($asset->fresh()->trashed());
    }

    public function test_manager_rejecting_a_delete_request_requires_a_note_and_leaves_the_asset_intact(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->makePostedAsset($admin);

        $requester = $this->makeManager();
        $this->actingAs($requester);
        $this->invoke(AssetResource::requestDeleteAction(), $asset, ['reason' => 'Duplicate entry']);

        $request = AssetDeleteRequest::where('asset_id', $asset->id)->firstOrFail();

        $approver = $this->makeManager();
        $this->actingAs($approver);
        $this->invoke(AssetDeleteRequestResource::rejectAction(), $request, ['review_note' => 'Not a duplicate']);

        $this->assertSame(AssetDeleteRequestStatus::Rejected, $request->fresh()->status);
        $this->assertFalse($asset->fresh()->trashed());
        $this->assertFalse($asset->fresh()->hasPendingDeleteRequest());
    }

    private function invoke(Action $action, Model $record, array $data = []): void
    {
        $callback = (fn () => $this->action)->call($action);
        $callback($record->fresh(), $data);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Admin]);

        return $user;
    }

    private function makeManager(): User
    {
        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Manager]);

        return $user;
    }

    private function makeDraftAsset(User $admin): Asset
    {
        $top = AssetCategory::create(['name' => 'Furniture', 'gl_code' => '423001', 'is_active' => true]);
        $category = AssetCategory::create(['name' => 'Office Furniture', 'parent_id' => $top->id, 'asset_class_code' => 'Z652', 'is_active' => true]);
        $building = AssetBuilding::create(['name' => 'Main Office', 'is_active' => true]);
        $room = AssetRoom::create(['building_id' => $building->id, 'name' => 'Room 101', 'is_active' => true]);

        return Asset::create([
            'asset_tag' => 'AST-'.random_int(100000, 999999),
            'public_token' => str()->random(32),
            'name' => 'Office Chair',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'status' => 'in_use',
            'lifecycle_status' => AssetLifecycleStatus::Draft,
            'created_by' => $admin->id,
        ]);
    }

    private function makePostedAsset(User $admin): Asset
    {
        $asset = $this->makeDraftAsset($admin);
        $asset->update(['lifecycle_status' => AssetLifecycleStatus::Posted]);

        return $asset->fresh();
    }
}
