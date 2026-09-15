<?php

namespace Tests\Feature;

use App\Enums\AssetAuditOutcome;
use App\Enums\AssetAuditReviewAction;
use App\Enums\AssetAuditScopeType;
use App\Enums\AssetAuditSessionStatus;
use App\Enums\AssetRole;
use App\Enums\AssetStatus;
use App\Filament\Assets\Resources\Audits\Pages\CreateAssetAuditSession;
use App\Filament\Assets\Resources\Audits\Pages\ViewAssetAuditSession;
use App\Models\Asset;
use App\Models\AssetAuditItem;
use App\Models\AssetAuditSession;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetRoom;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetAuditTest extends TestCase
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

    private function makeRoom(string $buildingName, string $roomName): AssetRoom
    {
        $building = AssetBuilding::firstOrCreate(['name' => $buildingName], ['is_active' => true]);

        return AssetRoom::create(['building_id' => $building->id, 'name' => $roomName, 'is_active' => true]);
    }

    private function makeAsset(User $creator, AssetRoom $room, AssetStatus $status = AssetStatus::InUse, string $name = 'Office Chair'): Asset
    {
        $category = AssetCategory::firstOrCreate(['name' => 'Furniture'], ['is_active' => true]);

        return Asset::create([
            'asset_tag' => 'AST-'.random_int(100000, 999999),
            'public_token' => str()->random(32),
            'name' => $name,
            'category_id' => $category->id,
            'room_id' => $room->id,
            'status' => $status,
            'created_by' => $creator->id,
        ]);
    }

    public function test_starting_a_session_snapshots_in_scope_assets_excluding_disposed_including_lost(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);

        $roomA = $this->makeRoom('Main Office', 'Room 101');
        $inUse = $this->makeAsset($admin, $roomA, AssetStatus::InUse, 'Chair A');
        $lost = $this->makeAsset($admin, $roomA, AssetStatus::Lost, 'Chair B');
        $disposed = $this->makeAsset($admin, $roomA, AssetStatus::Disposed, 'Chair C');

        Livewire::test(CreateAssetAuditSession::class)
            ->fillForm(['name' => 'Q3 Audit', 'scope_type' => AssetAuditScopeType::All->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $session = AssetAuditSession::firstOrFail();
        $assetIds = AssetAuditItem::where('session_id', $session->id)->pluck('asset_id');

        $this->assertTrue($assetIds->contains($inUse->id));
        $this->assertTrue($assetIds->contains($lost->id));
        $this->assertFalse($assetIds->contains($disposed->id));
    }

    public function test_a_room_scoped_session_only_snapshots_assets_in_that_room(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);

        $roomA = $this->makeRoom('Main Office', 'Room 101');
        $roomB = $this->makeRoom('Main Office', 'Room 202');
        $inRoomA = $this->makeAsset($admin, $roomA);
        $inRoomB = $this->makeAsset($admin, $roomB);

        Livewire::test(CreateAssetAuditSession::class)
            ->fillForm(['name' => 'Room Audit', 'scope_type' => AssetAuditScopeType::Room->value, 'scope_room_id' => $roomA->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $session = AssetAuditSession::firstOrFail();
        $assetIds = AssetAuditItem::where('session_id', $session->id)->pluck('asset_id');

        $this->assertTrue($assetIds->contains($inRoomA->id));
        $this->assertFalse($assetIds->contains($inRoomB->id));
    }

    public function test_only_one_in_progress_session_is_allowed(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $room = $this->makeRoom('Main Office', 'Room 101');
        $this->makeAsset($admin, $room);

        AssetAuditSession::create([
            'name' => 'Existing', 'scope_type' => AssetAuditScopeType::All, 'status' => AssetAuditSessionStatus::InProgress,
            'started_by' => $admin->id, 'started_at' => now(),
        ]);

        Livewire::test(CreateAssetAuditSession::class)
            ->fillForm(['name' => 'Second Audit', 'scope_type' => AssetAuditScopeType::All->value])
            ->call('create');

        $this->assertSame(1, AssetAuditSession::count());
    }

    public function test_verifying_by_code_is_idempotent_and_rejects_out_of_scope_assets(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $room = $this->makeRoom('Main Office', 'Room 101');
        $inScope = $this->makeAsset($admin, $room, name: 'In Scope Chair');
        $outOfScope = $this->makeAsset($admin, $room, name: 'Out Of Scope Chair');

        $session = AssetAuditSession::create([
            'name' => 'Audit', 'scope_type' => AssetAuditScopeType::All, 'status' => AssetAuditSessionStatus::InProgress,
            'started_by' => $admin->id, 'started_at' => now(),
        ]);
        AssetAuditItem::create(['session_id' => $session->id, 'asset_id' => $inScope->id, 'expected_room_id' => $room->id]);
        // outOfScope deliberately has no AssetAuditItem row.

        $component = Livewire::test(ViewAssetAuditSession::class, ['record' => $session->getKey()]);

        $component->call('verifyCode', $inScope->asset_tag);
        $item = AssetAuditItem::where('session_id', $session->id)->where('asset_id', $inScope->id)->first();
        $this->assertNotNull($item->verified_at);

        // Re-verifying is idempotent — no exception, stays verified once.
        $component->call('verifyCode', $inScope->asset_tag);
        $this->assertSame(1, AssetAuditItem::where('asset_id', $inScope->id)->whereNotNull('verified_at')->count());

        // Out-of-scope asset: no item ever created for it.
        $component->call('verifyCode', $outOfScope->asset_tag);
        $this->assertFalse(AssetAuditItem::where('asset_id', $outOfScope->id)->exists());
    }

    /**
     * Regression test for a bug caught only via live browser
     * verification: getSession() used ->loadMissing() for the
     * closedBy relation, which had already been cached as null on an
     * earlier render (before the session was closed) — closeSession()
     * setting closed_by afterwards never invalidated that cache, so
     * the same page load kept showing "Closed by" with no name until
     * a full page reload. Fixed by using ->load() (always fresh)
     * instead.
     */
    public function test_the_closed_by_name_is_available_immediately_after_closing_in_the_same_request(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $room = $this->makeRoom('Main Office', 'Room 101');
        $this->makeAsset($admin, $room);

        $session = AssetAuditSession::create([
            'name' => 'Audit', 'scope_type' => AssetAuditScopeType::All, 'status' => AssetAuditSessionStatus::InProgress,
            'started_by' => $admin->id, 'started_at' => now(),
        ]);

        $component = Livewire::test(ViewAssetAuditSession::class, ['record' => $session->getKey()]);
        // Renders once with closedBy still null, caching the relation
        // as empty before it's ever set — this is the exact sequence
        // that reproduced the bug.
        $component->assertSee($session->name);

        $component->call('closeSession');

        $this->assertSame($admin->name, $component->instance()->getSession()->closedBy?->name);
    }

    public function test_a_viewer_cannot_verify_or_close_a_session(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $room = $this->makeRoom('Main Office', 'Room 101');
        $asset = $this->makeAsset($admin, $room);

        $session = AssetAuditSession::create([
            'name' => 'Audit', 'scope_type' => AssetAuditScopeType::All, 'status' => AssetAuditSessionStatus::InProgress,
            'started_by' => $admin->id, 'started_at' => now(),
        ]);
        $item = AssetAuditItem::create(['session_id' => $session->id, 'asset_id' => $asset->id, 'expected_room_id' => $room->id]);

        $viewer = $this->makeUser(AssetRole::Viewer);
        $this->actingAs($viewer);

        Livewire::test(ViewAssetAuditSession::class, ['record' => $session->getKey()])
            ->call('verifyManually', $item->id)
            ->call('closeSession');

        $this->assertNull($item->fresh()->verified_at);
        $this->assertSame(AssetAuditSessionStatus::InProgress, $session->fresh()->status);
    }

    public function test_closing_a_session_computes_outcomes_including_location_mismatch(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);

        $expectedRoom = $this->makeRoom('Main Office', 'Room 101');
        $auditRoom = $this->makeRoom('Main Office', 'Room 202');

        $verifiedInPlace = $this->makeAsset($admin, $expectedRoom, name: 'Stays Put');
        $foundElsewhere = $this->makeAsset($admin, $expectedRoom, name: 'Wandered Off');
        $neverVerified = $this->makeAsset($admin, $expectedRoom, name: 'Missing Chair');

        // A room-scoped session on $auditRoom: verifying "Wandered Off"
        // there (its expected room is $expectedRoom) should compute as
        // a location mismatch once closed.
        $session = AssetAuditSession::create([
            'name' => 'Room Audit', 'scope_type' => AssetAuditScopeType::Room, 'scope_room_id' => $auditRoom->id,
            'status' => AssetAuditSessionStatus::InProgress, 'started_by' => $admin->id, 'started_at' => now(),
        ]);
        $itemInPlace = AssetAuditItem::create(['session_id' => $session->id, 'asset_id' => $verifiedInPlace->id, 'expected_room_id' => $auditRoom->id]);
        $itemElsewhere = AssetAuditItem::create(['session_id' => $session->id, 'asset_id' => $foundElsewhere->id, 'expected_room_id' => $expectedRoom->id]);
        $itemMissing = AssetAuditItem::create(['session_id' => $session->id, 'asset_id' => $neverVerified->id, 'expected_room_id' => $expectedRoom->id]);

        $component = Livewire::test(ViewAssetAuditSession::class, ['record' => $session->getKey()]);
        $component->call('verifyManually', $itemInPlace->id);
        $component->call('verifyManually', $itemElsewhere->id);
        $component->call('closeSession');

        $this->assertSame(AssetAuditOutcome::Verified, $itemInPlace->fresh()->outcome);
        $this->assertSame(AssetAuditOutcome::LocationMismatch, $itemElsewhere->fresh()->outcome);
        $this->assertSame(AssetAuditOutcome::Missing, $itemMissing->fresh()->outcome);
        $this->assertSame(AssetAuditSessionStatus::Closed, $session->fresh()->status);
    }

    public function test_reviewing_marked_lost_updates_the_asset_status_and_history(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $room = $this->makeRoom('Main Office', 'Room 101');
        $asset = $this->makeAsset($admin, $room);

        $session = AssetAuditSession::create([
            'name' => 'Audit', 'scope_type' => AssetAuditScopeType::All, 'status' => AssetAuditSessionStatus::Closed,
            'started_by' => $admin->id, 'started_at' => now(), 'closed_by' => $admin->id, 'closed_at' => now(),
        ]);
        $item = AssetAuditItem::create([
            'session_id' => $session->id, 'asset_id' => $asset->id, 'expected_room_id' => $room->id,
            'outcome' => AssetAuditOutcome::Missing,
        ]);

        Livewire::test(ViewAssetAuditSession::class, ['record' => $session->getKey()])
            ->call('reviewItem', $item->id, AssetAuditReviewAction::MarkedLost->value, 'Confirmed missing after search');

        $this->assertSame(AssetStatus::Lost, $asset->fresh()->status);
        $freshItem = $item->fresh();
        $this->assertSame(AssetAuditReviewAction::MarkedLost, $freshItem->review_action);
        $this->assertNotNull($freshItem->reviewed_at);
    }

    public function test_reviewing_kept_as_is_does_not_change_the_asset(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $room = $this->makeRoom('Main Office', 'Room 101');
        $asset = $this->makeAsset($admin, $room);

        $session = AssetAuditSession::create([
            'name' => 'Audit', 'scope_type' => AssetAuditScopeType::All, 'status' => AssetAuditSessionStatus::Closed,
            'started_by' => $admin->id, 'started_at' => now(), 'closed_by' => $admin->id, 'closed_at' => now(),
        ]);
        $item = AssetAuditItem::create([
            'session_id' => $session->id, 'asset_id' => $asset->id, 'expected_room_id' => $room->id,
            'outcome' => AssetAuditOutcome::Missing,
        ]);

        Livewire::test(ViewAssetAuditSession::class, ['record' => $session->getKey()])
            ->call('reviewItem', $item->id, AssetAuditReviewAction::KeptAsIs->value);

        $this->assertSame(AssetStatus::InUse, $asset->fresh()->status);
        $this->assertSame(AssetAuditReviewAction::KeptAsIs, $item->fresh()->review_action);
    }
}
