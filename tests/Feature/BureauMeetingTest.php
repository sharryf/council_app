<?php

namespace Tests\Feature;

use App\Enums\BureauAgendaItemKind;
use App\Enums\BureauAgendaStatus;
use App\Enums\BureauMeetingStatus;
use App\Enums\BureauRole;
use App\Filament\Bureau\Resources\Meetings\MeetingResource;
use App\Filament\Bureau\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\Bureau\Resources\Meetings\Pages\ListMeetings;
use App\Filament\Bureau\Widgets\NextMeetingWidget;
use App\Mail\Bureau\MeetingScheduledMail;
use App\Models\BureauAgendaItem;
use App\Models\BureauMeeting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Meeting — Bureau Admin creates it (approved, unclaimed agenda items
 * auto-attach, see CreateMeeting), sends it for the Council President's
 * approval, and once approved it's Scheduled — every attendee gets an
 * emailed agenda PDF (see MeetingResource::approveAction()).
 */
class BureauMeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('bureau'));
    }

    private function makeBureauAdmin(): User
    {
        $user = User::factory()->create();
        $user->bureauRoles()->create(['role' => BureauRole::BureauAdmin]);

        return $user;
    }

    private function makePresident(): User
    {
        $user = User::factory()->create();
        $user->bureauRoles()->create(['role' => BureauRole::President]);

        return $user;
    }

    private function makeApprovedAgendaItem(): BureauAgendaItem
    {
        $councillor = User::factory()->create();
        $councillor->bureauRoles()->create(['role' => BureauRole::Councillor]);

        return BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => BureauAgendaStatus::Approved,
            'created_by' => $councillor->id,
        ]);
    }

    public function test_a_bureau_admin_can_create_a_meeting_and_approved_items_auto_attach(): void
    {
        $bureauAdmin = $this->makeBureauAdmin();
        $attendee = User::factory()->create();
        $attendee->bureauRoles()->create(['role' => BureauRole::Participant]);

        $approvedItem1 = $this->makeApprovedAgendaItem();
        $approvedItem2 = $this->makeApprovedAgendaItem();
        $enteredItem = BureauAgendaItem::create([
            'details' => 'Still under review',
            'status' => BureauAgendaStatus::Entered,
            'created_by' => $bureauAdmin->id,
        ]);

        $this->actingAs($bureauAdmin);

        Livewire::test(CreateMeeting::class)
            ->fillForm([
                'type' => 'public',
                'scheduled_at' => now()->addWeek(),
                'attendees' => [$attendee->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $meeting = BureauMeeting::sole();

        $this->assertSame(BureauMeeting::buildName(1, 1, 'public'), $meeting->name);
        $this->assertSame(1, $meeting->term_number);
        $this->assertSame(1, $meeting->meeting_number);
        $this->assertSame($bureauAdmin->id, $meeting->created_by);
        $this->assertSame(BureauMeetingStatus::Draft, $meeting->status);
        $this->assertTrue($meeting->attendees->contains($attendee));

        $this->assertSame($meeting->id, $approvedItem1->fresh()->meeting_id);
        $this->assertSame(BureauAgendaStatus::AddedToMeeting, $approvedItem1->fresh()->status);
        $this->assertSame($meeting->id, $approvedItem2->fresh()->meeting_id);
        $this->assertSame(BureauAgendaStatus::AddedToMeeting, $approvedItem2->fresh()->status);
        $this->assertNull($enteredItem->fresh()->meeting_id);
        $this->assertSame(BureauAgendaStatus::Entered, $enteredItem->fresh()->status);
    }

    public function test_creating_a_meeting_auto_creates_agenda_passing_and_minutes_passing_items(): void
    {
        $bureauAdmin = $this->makeBureauAdmin();
        $attendee = User::factory()->create();
        $attendee->bureauRoles()->create(['role' => BureauRole::Participant]);

        $pastMeeting = BureauMeeting::create([
            'name' => 'Previous Council Session',
            'scheduled_at' => now()->subWeek(),
            'status' => BureauMeetingStatus::Scheduled,
            'created_by' => $bureauAdmin->id,
        ]);

        $this->actingAs($bureauAdmin);

        Livewire::test(CreateMeeting::class)
            ->assertFormSet(['minutes_passing_meeting_ids' => [$pastMeeting->id]])
            ->fillForm([
                'type' => 'public',
                'scheduled_at' => now()->addWeek(),
                'attendees' => [$attendee->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $meeting = BureauMeeting::where('id', '!=', $pastMeeting->id)->sole();

        $agendaPassing = $meeting->agendaItems()->where('kind', BureauAgendaItemKind::AgendaPassing)->first();
        $this->assertNotNull($agendaPassing);
        $this->assertSame(BureauAgendaStatus::AddedToMeeting, $agendaPassing->status);

        $minutesPassing = $meeting->agendaItems()->where('kind', BureauAgendaItemKind::MinutesPassing)->first();
        $this->assertNotNull($minutesPassing);
        $this->assertSame($pastMeeting->id, $minutesPassing->related_meeting_id);

        // Procedural items come first, ahead of any regular items.
        $orderedKinds = $meeting->agendaItems()->orderBy('id')->get()->pluck('kind')->take(2)->all();
        $this->assertSame([BureauAgendaItemKind::AgendaPassing, BureauAgendaItemKind::MinutesPassing], $orderedKinds);
    }

    public function test_the_agenda_passing_toggle_can_be_turned_off(): void
    {
        $bureauAdmin = $this->makeBureauAdmin();
        $attendee = User::factory()->create();
        $attendee->bureauRoles()->create(['role' => BureauRole::Participant]);

        $this->actingAs($bureauAdmin);

        Livewire::test(CreateMeeting::class)
            ->fillForm([
                'type' => 'public',
                'scheduled_at' => now()->addWeek(),
                'attendees' => [$attendee->id],
                'include_agenda_passing' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $meeting = BureauMeeting::sole();

        $this->assertSame(0, $meeting->agendaItems()->where('kind', BureauAgendaItemKind::AgendaPassing)->count());
    }

    public function test_minutes_passing_defaults_exclude_future_and_unscheduled_meetings(): void
    {
        $bureauAdmin = $this->makeBureauAdmin();

        $pastScheduled = BureauMeeting::create([
            'name' => 'Eligible past meeting',
            'scheduled_at' => now()->subWeek(),
            'status' => BureauMeetingStatus::Scheduled,
            'created_by' => $bureauAdmin->id,
        ]);

        BureauMeeting::create([
            'name' => 'Future meeting, not eligible',
            'scheduled_at' => now()->addWeek(),
            'status' => BureauMeetingStatus::Scheduled,
            'created_by' => $bureauAdmin->id,
        ]);

        BureauMeeting::create([
            'name' => 'Past draft, not eligible',
            'scheduled_at' => now()->subWeek(),
            'status' => BureauMeetingStatus::Draft,
            'created_by' => $bureauAdmin->id,
        ]);

        $this->actingAs($bureauAdmin);

        Livewire::test(CreateMeeting::class)
            ->assertFormSet(['minutes_passing_meeting_ids' => [$pastScheduled->id]]);
    }

    public function test_a_non_admin_cannot_create_a_meeting(): void
    {
        $councillor = User::factory()->create();
        $councillor->bureauRoles()->create(['role' => BureauRole::Councillor]);
        $this->actingAs($councillor);

        $this->assertFalse(MeetingResource::canCreate());
    }

    public function test_the_bureau_admin_can_send_a_draft_meeting_for_approval(): void
    {
        Storage::fake('local');

        $bureauAdmin = $this->makeBureauAdmin();

        $meeting = BureauMeeting::create([
            'name' => 'August Council Session',
            'place' => 'Council Hall',
            'scheduled_at' => now()->addWeek(),
            'status' => BureauMeetingStatus::Draft,
            'created_by' => $bureauAdmin->id,
        ]);

        $this->actingAs($bureauAdmin);

        Livewire::test(ListMeetings::class)
            ->callTableAction('sendForApproval', $meeting)
            ->assertHasNoTableActionErrors();

        $meeting->refresh();

        $this->assertSame(BureauMeetingStatus::PendingApproval, $meeting->status);
        $this->assertTrue($meeting->hasMeetingRequestPdf());
        Storage::disk('local')->assertExists($meeting->meeting_request_pdf_path);
    }

    public function test_the_president_can_approve_a_meeting_generating_a_pdf_and_emailing_attendees(): void
    {
        Storage::fake('local');
        Mail::fake();

        $bureauAdmin = $this->makeBureauAdmin();
        $president = $this->makePresident();
        $attendee1 = User::factory()->create();
        $attendee2 = User::factory()->create();
        $agendaItem = $this->makeApprovedAgendaItem();

        $meeting = BureauMeeting::create([
            'name' => 'August Council Session',
            'type' => 'Regular',
            'scheduled_at' => now()->addWeek(),
            'status' => BureauMeetingStatus::PendingApproval,
            'created_by' => $bureauAdmin->id,
        ]);
        $meeting->attendees()->attach([$attendee1->id, $attendee2->id]);
        $agendaItem->update(['meeting_id' => $meeting->id]);

        $this->actingAs($president);

        Livewire::test(ListMeetings::class)
            ->callTableAction('approve', $meeting)
            ->assertHasNoTableActionErrors();

        $meeting->refresh();

        $this->assertSame(BureauMeetingStatus::Scheduled, $meeting->status);
        $this->assertSame($president->id, $meeting->reviewed_by);
        $this->assertTrue($meeting->hasAgendaPdf());
        Storage::disk('local')->assertExists($meeting->agenda_pdf_path);

        Mail::assertSent(MeetingScheduledMail::class, 2);
        Mail::assertSent(MeetingScheduledMail::class, fn (MeetingScheduledMail $mail): bool => $mail->hasTo($attendee1->email));
        Mail::assertSent(MeetingScheduledMail::class, fn (MeetingScheduledMail $mail): bool => $mail->hasTo($attendee2->email));
    }

    public function test_the_president_can_reject_a_meeting_with_a_reason(): void
    {
        $bureauAdmin = $this->makeBureauAdmin();
        $president = $this->makePresident();

        $meeting = BureauMeeting::create([
            'name' => 'August Council Session',
            'scheduled_at' => now()->addWeek(),
            'status' => BureauMeetingStatus::PendingApproval,
            'created_by' => $bureauAdmin->id,
        ]);

        $this->actingAs($president);

        Livewire::test(ListMeetings::class)
            ->callTableAction('reject', $meeting, data: ['reason' => 'Wrong week, clashes with the budget review.'])
            ->assertHasNoTableActionErrors();

        $meeting->refresh();

        $this->assertSame(BureauMeetingStatus::Rejected, $meeting->status);
        $this->assertSame('Wrong week, clashes with the budget review.', $meeting->rejection_reason);
    }

    public function test_a_non_president_cannot_approve_a_meeting(): void
    {
        $bureauAdmin = $this->makeBureauAdmin();

        $meeting = BureauMeeting::create([
            'name' => 'August Council Session',
            'scheduled_at' => now()->addWeek(),
            'status' => BureauMeetingStatus::PendingApproval,
            'created_by' => $bureauAdmin->id,
        ]);

        $this->actingAs($bureauAdmin);

        Livewire::test(ListMeetings::class)
            ->assertTableActionHidden('approve', $meeting)
            ->assertTableActionHidden('reject', $meeting);
    }

    public function test_a_meeting_can_no_longer_be_edited_once_sent_for_approval(): void
    {
        $bureauAdmin = $this->makeBureauAdmin();

        $meeting = BureauMeeting::create([
            'name' => 'August Council Session',
            'scheduled_at' => now()->addWeek(),
            'status' => BureauMeetingStatus::PendingApproval,
            'created_by' => $bureauAdmin->id,
        ]);

        $this->actingAs($bureauAdmin);

        $this->assertFalse(MeetingResource::canEdit($meeting));
    }

    public function test_the_next_meeting_widget_shows_the_soonest_scheduled_meeting(): void
    {
        $this->seed(RoleSeeder::class);
        $bureauAdmin = $this->makeBureauAdmin();

        BureauMeeting::create([
            'name' => 'Draft meeting, not shown',
            'scheduled_at' => now()->addDay(),
            'status' => BureauMeetingStatus::Draft,
            'created_by' => $bureauAdmin->id,
        ]);

        $farMeeting = BureauMeeting::create([
            'name' => 'Far scheduled meeting',
            'scheduled_at' => now()->addMonth(),
            'status' => BureauMeetingStatus::Scheduled,
            'created_by' => $bureauAdmin->id,
        ]);

        $soonMeeting = BureauMeeting::create([
            'name' => 'Soon scheduled meeting',
            'scheduled_at' => now()->addDays(2),
            'status' => BureauMeetingStatus::Scheduled,
            'created_by' => $bureauAdmin->id,
        ]);

        $widget = new NextMeetingWidget();

        $this->assertSame($soonMeeting->id, $widget->getNextMeeting()->id);
        $this->assertNotSame($farMeeting->id, $widget->getNextMeeting()->id);
    }
}
