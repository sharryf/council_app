<?php

namespace Tests\Feature;

use App\Enums\BureauAgendaStatus;
use App\Enums\BureauRole;
use App\Filament\Bureau\Resources\AgendaItems\AgendaItemResource;
use App\Filament\Bureau\Resources\AgendaItems\Pages\CreateAgendaItem;
use App\Filament\Bureau\Resources\AgendaItems\Pages\EditAgendaItem;
use App\Filament\Bureau\Resources\AgendaItems\Pages\ListAgendaItems;
use App\Models\BureauAgendaItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Agenda — see the module's own workflow: President, Councillor, Bureau
 * Admin, or Participant may propose an item (with an optional attachment).
 * President items auto-approve on creation; others need President approval
 * via approveAction()/rejectAction() (see AgendaItemResource).
 *
 * Bureau isn't the default panel (admin is), and Livewire::test()
 * doesn't route through Filament's panel-detection middleware the way
 * a real HTTP request does — without setCurrentPanel() here, Filament
 * falls back to resolving routes/URLs against the default 'admin'
 * panel instead, which doesn't know about these Bureau pages at all.
 */
class BureauAgendaItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('bureau'));
    }

    private function makeCouncillor(): User
    {
        $user = User::factory()->create();
        $user->bureauRoles()->create(['role' => BureauRole::Councillor]);

        return $user;
    }

    private function makePresident(): User
    {
        $user = User::factory()->create();
        $user->bureauRoles()->create(['role' => BureauRole::President]);

        return $user;
    }

    public function test_a_councillor_can_propose_an_agenda_item_with_an_attachment(): void
    {
        Storage::fake('local');
        $councillor = $this->makeCouncillor();
        $this->actingAs($councillor);

        Livewire::test(CreateAgendaItem::class)
            ->fillForm([
                'details' => 'Repave the harbour road — long overdue after last monsoon.',
                'attachment' => UploadedFile::fake()->create('estimate.pdf', 100, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = BureauAgendaItem::sole();

        $this->assertSame('Repave the harbour road — long overdue after last monsoon.', $item->details);
        $this->assertSame($councillor->id, $item->created_by);
        $this->assertSame(BureauAgendaStatus::Entered, $item->status);
        $this->assertTrue($item->hasAttachment());
        $this->assertSame('estimate.pdf', $item->attachment_original_name);
        Storage::disk('local')->assertExists($item->attachment_path);
    }

    public function test_a_bureau_admin_can_create_an_agenda_item(): void
    {
        $bureauAdmin = User::factory()->create();
        $bureauAdmin->bureauRoles()->create(['role' => BureauRole::BureauAdmin]);
        $this->actingAs($bureauAdmin);

        $this->assertTrue(AgendaItemResource::canCreate());
    }

    public function test_a_participant_can_create_an_agenda_item(): void
    {
        $participant = User::factory()->create();
        $participant->bureauRoles()->create(['role' => BureauRole::Participant]);
        $this->actingAs($participant);

        $this->assertTrue(AgendaItemResource::canCreate());
    }

    public function test_a_staff_only_user_cannot_create_an_agenda_item(): void
    {
        $staff = User::factory()->create();
        $staff->bureauRoles()->create(['role' => BureauRole::Staff]);
        $this->actingAs($staff);

        $this->assertFalse(AgendaItemResource::canCreate());
    }

    /**
     * President can create agenda items, and they auto-approve upon
     * creation (no review needed from another President).
     */
    public function test_the_president_can_create_an_agenda_item_and_it_auto_approves(): void
    {
        Storage::fake('local');
        $president = $this->makePresident();
        $this->actingAs($president);

        Livewire::test(CreateAgendaItem::class)
            ->fillForm([
                'details' => 'Presidential directive on infrastructure',
                'attachment' => UploadedFile::fake()->create('directive.pdf', 100, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = BureauAgendaItem::sole();

        $this->assertSame('Presidential directive on infrastructure', $item->details);
        $this->assertSame($president->id, $item->created_by);
        $this->assertSame(BureauAgendaStatus::Approved, $item->status);
        $this->assertSame($president->id, $item->reviewed_by);
        $this->assertNotNull($item->reviewed_at);
        $this->assertTrue($item->hasAttachment());
    }

    public function test_the_president_can_approve_an_entered_item(): void
    {
        $councillor = $this->makeCouncillor();
        $president = $this->makePresident();

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::Entered,
            'created_by' => $councillor->id,
        ]);

        $this->actingAs($president);

        Livewire::test(ListAgendaItems::class)
            ->callTableAction('approve', $item)
            ->assertHasNoTableActionErrors();

        $item->refresh();

        $this->assertSame(BureauAgendaStatus::Approved, $item->status);
        $this->assertSame($president->id, $item->reviewed_by);
        $this->assertNotNull($item->reviewed_at);
    }

    public function test_the_president_can_reject_an_entered_item_with_a_reason(): void
    {
        $councillor = $this->makeCouncillor();
        $president = $this->makePresident();

        $item = BureauAgendaItem::create([
            'details' => 'Rename the ferry terminal',
            'status' => BureauAgendaStatus::Entered,
            'created_by' => $councillor->id,
        ]);

        $this->actingAs($president);

        Livewire::test(ListAgendaItems::class)
            ->callTableAction('reject', $item, data: ['reason' => 'Needs full council discussion first.'])
            ->assertHasNoTableActionErrors();

        $item->refresh();

        $this->assertSame(BureauAgendaStatus::Rejected, $item->status);
        $this->assertSame('Needs full council discussion first.', $item->rejection_reason);
        $this->assertSame($president->id, $item->reviewed_by);
    }

    public function test_a_non_president_cannot_approve_an_item(): void
    {
        $councillor = $this->makeCouncillor();

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::Entered,
            'created_by' => $councillor->id,
        ]);

        $this->actingAs($councillor);

        Livewire::test(ListAgendaItems::class)
            ->assertTableActionHidden('approve', $item)
            ->assertTableActionHidden('reject', $item);
    }

    public function test_only_the_creator_or_a_bureau_admin_can_edit_an_entered_item(): void
    {
        $councillor = $this->makeCouncillor();
        $otherCouncillor = $this->makeCouncillor();

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::Entered,
            'created_by' => $councillor->id,
        ]);

        $this->actingAs($councillor);
        $this->assertTrue(AgendaItemResource::canEdit($item->fresh()));

        $this->actingAs($otherCouncillor);
        $this->assertFalse(AgendaItemResource::canEdit($item->fresh()));
    }

    /**
     * Editing stays open through Approved — only AddedToMeeting closes
     * it off (see test_an_added_to_meeting_item_can_no_longer_be_edited()
     * below) — because an approved item can still need a wording fix
     * before it's pulled into a meeting.
     */
    public function test_an_approved_item_can_still_be_edited_by_its_creator(): void
    {
        $councillor = $this->makeCouncillor();

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::Approved,
            'created_by' => $councillor->id,
        ]);

        $this->actingAs($councillor);

        $this->assertTrue(AgendaItemResource::canEdit($item));
    }

    public function test_an_added_to_meeting_item_can_no_longer_be_edited(): void
    {
        $councillor = $this->makeCouncillor();

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::AddedToMeeting,
            'created_by' => $councillor->id,
        ]);

        $this->actingAs($councillor);

        $this->assertFalse(AgendaItemResource::canEdit($item));
    }

    /**
     * The core of the re-approval workflow: an edit to an Approved item
     * isn't silently kept approved — it drops back to Entered (and the
     * prior review is cleared) so the President reviews the edited
     * content, not the original.
     */
    public function test_editing_an_approved_item_sends_it_back_to_entered_for_re_approval(): void
    {
        $councillor = $this->makeCouncillor();
        $president = $this->makePresident();

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::Approved,
            'created_by' => $councillor->id,
            'reviewed_by' => $president->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($councillor);

        Livewire::test(EditAgendaItem::class, ['record' => $item->getKey()])
            ->fillForm(['details' => 'Adopt the revised budget'])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh();

        $this->assertSame('Adopt the revised budget', $item->details);
        $this->assertSame(BureauAgendaStatus::Entered, $item->status);
        $this->assertNull($item->reviewed_by);
        $this->assertNull($item->reviewed_at);
    }

    public function test_the_edit_button_is_hidden_once_an_item_is_added_to_a_meeting(): void
    {
        $councillor = $this->makeCouncillor();

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::AddedToMeeting,
            'created_by' => $councillor->id,
        ]);

        $this->actingAs($councillor);

        Livewire::test(ListAgendaItems::class)
            ->assertTableActionHidden('edit', $item);
    }

    public function test_the_system_admin_can_approve_without_an_explicit_bureau_role(): void
    {
        $this->seed(RoleSeeder::class);
        $councillor = $this->makeCouncillor();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::Entered,
            'created_by' => $councillor->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(ListAgendaItems::class)
            ->callTableAction('approve', $item)
            ->assertHasNoTableActionErrors();

        $this->assertSame(BureauAgendaStatus::Approved, $item->fresh()->status);
    }

    public function test_downloading_an_attachment_requires_a_bureau_role(): void
    {
        Storage::fake('local');
        $councillor = $this->makeCouncillor();

        $path = 'bureau/agenda/estimate.pdf';
        Storage::disk('local')->put($path, 'fake-pdf-bytes');

        $item = BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::Entered,
            'created_by' => $councillor->id,
            'attachment_path' => $path,
            'attachment_original_name' => 'estimate.pdf',
        ]);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('bureau.agenda-items.attachment', $item))
            ->assertForbidden();

        $this->actingAs($councillor)
            ->get(route('bureau.agenda-items.attachment', $item))
            ->assertSuccessful();
    }
}
