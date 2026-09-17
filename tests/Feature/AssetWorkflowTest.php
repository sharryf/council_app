<?php

namespace Tests\Feature;

use App\Enums\AssetEditRequestStatus;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetRole;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Filament\Assets\Resources\Assets\Pages\CreateAsset;
use App\Filament\Assets\Resources\Assets\Pages\EditAsset;
use App\Filament\Assets\Resources\Assets\Pages\ViewAsset;
use App\Filament\Assets\Resources\EditRequests\AssetEditRequestResource;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetEditRequest;
use App\Models\AssetHistory;
use App\Models\AssetRoom;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AssetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('assets'));
    }

    public function test_a_newly_created_asset_is_a_draft_and_can_be_posted(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $this->actingAs($this->makeAdmin());

        Livewire::test(CreateAsset::class)
            ->fillForm($this->baseFormData($category, $room))
            ->call('create')
            ->assertHasNoFormErrors();

        $asset = Asset::where('name', 'Office Chair')->firstOrFail();
        $this->assertSame(AssetLifecycleStatus::Draft, $asset->lifecycle_status);

        $this->invoke(AssetResource::postAction(), $asset);

        $this->assertSame(AssetLifecycleStatus::Posted, $asset->fresh()->lifecycle_status);
        $this->assertTrue(AssetHistory::where('asset_id', $asset->id)->where('event_type', 'posted')->exists());
    }

    public function test_posting_is_blocked_without_a_photo(): void
    {
        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();

        $asset = Asset::create([
            'asset_tag' => 'AST-000001',
            'main_inventory_no' => 'AST-000001',
            'public_token' => str()->random(32),
            'name' => 'No Photo Asset',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'status' => 'in_use',
            'lifecycle_status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);
        $this->invoke(AssetResource::postAction(), $asset);

        $this->assertSame(AssetLifecycleStatus::Draft, $asset->fresh()->lifecycle_status);
    }

    public function test_editing_a_draft_asset_applies_immediately_with_no_approval(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $asset = $this->createDraftAsset($category, $room, $admin);

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'Renamed Chair'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Chair', $asset->fresh()->name);
        $this->assertSame(0, AssetEditRequest::count());
    }

    public function test_editing_a_posted_asset_requires_a_reason_and_creates_a_pending_request_without_changing_it(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $asset = $this->createPostedAsset($category, $room, $admin);

        // No reason supplied — rejected.
        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'Renamed Chair'])
            ->call('save')
            ->assertHasFormErrors(['edit_reason']);

        $this->assertSame('Office Chair', $asset->fresh()->name);

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'Renamed Chair', 'edit_reason' => 'Typo in the original name'])
            ->call('save')
            ->assertHasNoFormErrors();

        // Unchanged — the request is only proposed, not applied.
        $this->assertSame('Office Chair', $asset->fresh()->name);

        $request = AssetEditRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame(AssetEditRequestStatus::Pending, $request->status);
        $this->assertSame('Typo in the original name', $request->reason);
        $this->assertSame('Renamed Chair', $request->proposed_changes['name']);
        $this->assertTrue($asset->fresh()->hasPendingEditRequest());
        $this->assertTrue(AssetHistory::where('asset_id', $asset->id)->where('event_type', 'edit_requested')->exists());
    }

    public function test_replacing_a_posted_assets_photo_requires_approval_and_only_applies_once_approved(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->createPostedAsset($category, $room, $admin);

        $originalAttachmentId = $asset->fresh()->photo_attachment_id;

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm([
                'photo' => UploadedFile::fake()->image('new-photo.jpg'),
                'edit_reason' => 'Better lit photo',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Still the original photo — only proposed, not applied.
        $this->assertSame($originalAttachmentId, $asset->fresh()->photo_attachment_id);

        $request = AssetEditRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame(AssetEditRequestStatus::Pending, $request->status);
        $this->assertArrayHasKey('photo', $request->proposed_changes);
        $this->assertSame('Photo', $request->fieldsSummary());

        $manager = $this->makeManager();
        $this->actingAs($manager);
        $this->invoke(AssetEditRequestResource::approveAction(), $request);

        $asset->refresh();
        $this->assertNotSame($originalAttachmentId, $asset->photo_attachment_id);
        $this->assertTrue(AssetHistory::where('asset_id', $asset->id)->where('event_type', 'photo_replaced')->exists());
    }

    public function test_a_second_edit_cannot_be_submitted_while_one_is_pending(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $asset = $this->createPostedAsset($category, $room, $admin);

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'First Change', 'edit_reason' => 'First reason'])
            ->call('save');

        $this->assertSame(1, AssetEditRequest::count());

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'Second Change', 'edit_reason' => 'Second reason'])
            ->call('save');

        // Still only the first request — the second attempt was refused.
        $this->assertSame(1, AssetEditRequest::count());
    }

    public function test_manager_approving_an_edit_request_applies_the_change_and_logs_it(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->createPostedAsset($category, $room, $admin);

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'Renamed Chair', 'edit_reason' => 'Typo fix'])
            ->call('save');

        $request = AssetEditRequest::where('asset_id', $asset->id)->firstOrFail();

        $manager = $this->makeManager();
        $this->actingAs($manager);
        $this->invoke(AssetEditRequestResource::approveAction(), $request);

        $this->assertSame('Renamed Chair', $asset->fresh()->name);
        $this->assertSame(AssetEditRequestStatus::Approved, $request->fresh()->status);
        $this->assertSame($manager->id, $request->fresh()->reviewed_by);
        $this->assertTrue(
            AssetHistory::where('asset_id', $asset->id)
                ->where('event_type', 'field_changed')
                ->where('field_name', 'Name')
                ->where('old_value', 'Office Chair')
                ->where('new_value', 'Renamed Chair')
                ->exists(),
        );
    }

    public function test_manager_rejecting_an_edit_request_requires_a_note_and_leaves_the_asset_unchanged(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->createPostedAsset($category, $room, $admin);

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'Renamed Chair', 'edit_reason' => 'Typo fix'])
            ->call('save');

        $request = AssetEditRequest::where('asset_id', $asset->id)->firstOrFail();

        $manager = $this->makeManager();
        $this->actingAs($manager);
        $this->invoke(AssetEditRequestResource::rejectAction(), $request, ['review_note' => 'Not actually a typo']);

        $this->assertSame('Office Chair', $asset->fresh()->name);
        $this->assertSame(AssetEditRequestStatus::Rejected, $request->fresh()->status);
        $this->assertFalse($asset->fresh()->hasPendingEditRequest());
    }

    public function test_admin_cannot_approve_or_reject_edit_requests(): void
    {
        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->createPostedAsset($category, $room, $admin);

        $request = AssetEditRequest::create([
            'asset_id' => $asset->id,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'reason' => 'Because',
            'proposed_changes' => ['name' => 'New Name'],
            'status' => AssetEditRequestStatus::Pending,
        ]);

        $this->actingAs($admin);
        $this->assertFalse(AssetEditRequestResource::approveAction()->record($request)->isVisible());
        $this->assertFalse(AssetEditRequestResource::rejectAction()->record($request)->isVisible());
    }

    public function test_a_manager_who_also_holds_admin_cannot_approve_their_own_edit_request(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $both = $this->makeAdmin();
        $both->assetRoles()->create(['role' => AssetRole::Manager]);
        $this->actingAs($both);
        $asset = $this->createPostedAsset($category, $room, $both);

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'Renamed Chair', 'edit_reason' => 'Typo fix'])
            ->call('save');

        $request = AssetEditRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->assertFalse(AssetEditRequestResource::approveAction()->record($request)->isVisible());

        // Server-side check holds even if the button were reached
        // directly (e.g. a stale page from before the request was made).
        $this->invoke(AssetEditRequestResource::approveAction(), $request);

        $this->assertSame('Office Chair', $asset->fresh()->name);
        $this->assertSame(AssetEditRequestStatus::Pending, $request->fresh()->status);
    }

    public function test_a_different_manager_can_still_approve_an_edit_request_from_a_manager_requester(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $requester = $this->makeAdmin();
        $requester->assetRoles()->create(['role' => AssetRole::Manager]);
        $this->actingAs($requester);
        $asset = $this->createPostedAsset($category, $room, $requester);

        Livewire::test(EditAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['name' => 'Renamed Chair', 'edit_reason' => 'Typo fix'])
            ->call('save');

        $request = AssetEditRequest::where('asset_id', $asset->id)->firstOrFail();

        $otherManager = $this->makeManager();
        $this->actingAs($otherManager);

        $this->assertTrue(AssetEditRequestResource::approveAction()->record($request)->isVisible());

        $this->invoke(AssetEditRequestResource::approveAction(), $request);

        $this->assertSame('Renamed Chair', $asset->fresh()->name);
        $this->assertSame(AssetEditRequestStatus::Approved, $request->fresh()->status);
    }

    public function test_uploading_a_single_document_uses_the_typed_display_name(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->createPostedAsset($category, $room, $admin);

        Livewire::test(ViewAsset::class, ['record' => $asset->getKey()])
            ->mountAction('uploadDocument')
            ->setActionData([
                'document_name' => 'Warranty Card',
                'documents' => [UploadedFile::fake()->create('scan-4471.pdf', 10, 'application/pdf')],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $attachment = AssetAttachment::where('asset_id', $asset->id)->where('kind', 'document')->firstOrFail();
        $this->assertSame('Warranty Card', $attachment->document_name);
        $this->assertTrue(AssetHistory::where('asset_id', $asset->id)->where('event_type', 'attachment_added')->where('note', 'Warranty Card')->exists());
    }

    public function test_uploading_multiple_documents_at_once_ignores_the_typed_name(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $asset = $this->createPostedAsset($category, $room, $admin);

        Livewire::test(ViewAsset::class, ['record' => $asset->getKey()])
            ->mountAction('uploadDocument')
            ->setActionData([
                'document_name' => 'Warranty Card',
                'documents' => [
                    UploadedFile::fake()->create('scan-1.pdf', 10, 'application/pdf'),
                    UploadedFile::fake()->create('scan-2.pdf', 10, 'application/pdf'),
                ],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $attachments = AssetAttachment::where('asset_id', $asset->id)->where('kind', 'document')->get();
        $this->assertCount(2, $attachments);
        $this->assertTrue($attachments->every(fn (AssetAttachment $a): bool => $a->document_name === null));
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

    /**
     * @return array{0: AssetCategory, 1: AssetRoom}
     */
    private function makeCategoryAndRoom(): array
    {
        $top = AssetCategory::create(['name' => 'Furniture', 'gl_code' => '423001', 'is_active' => true]);
        $category = AssetCategory::create(['name' => 'Office Furniture', 'parent_id' => $top->id, 'asset_class_code' => 'Z652', 'is_active' => true]);
        $building = AssetBuilding::create(['name' => 'Main Office', 'is_active' => true]);
        $room = AssetRoom::create(['building_id' => $building->id, 'name' => 'Room 101', 'is_active' => true]);

        return [$category, $room];
    }

    private function baseFormData(AssetCategory $category, AssetRoom $room): array
    {
        return [
            'photo' => UploadedFile::fake()->image('chair.jpg'),
            'name' => 'Office Chair',
            'category_top_id' => $category->parent_id,
            'category_id' => $category->id,
            'building_top_id' => $room->building_id,
            'room_id' => $room->id,
            'status' => 'in_use',
            'asset_type' => 'donated',
            'donation_reference_no' => 'DON-2026-1',
        ];
    }

    private function createDraftAsset(AssetCategory $category, AssetRoom $room, User $admin): Asset
    {
        Livewire::test(CreateAsset::class)->fillForm($this->baseFormData($category, $room))->call('create');

        return Asset::where('name', 'Office Chair')->firstOrFail();
    }

    private function createPostedAsset(AssetCategory $category, AssetRoom $room, User $admin): Asset
    {
        $asset = $this->createDraftAsset($category, $room, $admin);
        $this->invoke(AssetResource::postAction(), $asset);

        return $asset->fresh();
    }
}
