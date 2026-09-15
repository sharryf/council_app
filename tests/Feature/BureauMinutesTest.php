<?php

namespace Tests\Feature;

use App\Enums\BureauAttendanceStatus;
use App\Enums\BureauDecisionStatus;
use App\Enums\BureauMeetingStatus;
use App\Enums\BureauMinutesAttendanceGroup;
use App\Enums\BureauMinutesStatus;
use App\Enums\BureauRole;
use App\Enums\DocumentStatus;
use App\Filament\Bureau\Resources\Meetings\Pages\RecordMinutes;
use App\Models\BureauMeeting;
use App\Models\BureauMeetingMinutes;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Minutes — the live-meeting recording workstation (see RecordMinutes):
 * start (auto-seeds the full council/secretariat roll call) ->
 * attendance/chair/times -> introduction -> per agenda item comments
 * (+ optional AI drafting) and decision requests voted in order (see
 * DecisionVotingService) -> chair/times/closing notes saved together
 * moves it to Review, where any attendee may edit a comment ->
 * President approval hands it to Document Signing, signed only by
 * council members marked Present (see MinutesHandoffService).
 */
class BureauMinutesTest extends TestCase
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

    private function makeCouncilMember(BureauRole $role): User
    {
        $user = User::factory()->create();
        $user->bureauRoles()->create(['role' => $role]);

        return $user;
    }

    private function makeScheduledMeeting(): BureauMeeting
    {
        $bureauAdmin = $this->makeBureauAdmin();

        $meeting = BureauMeeting::create([
            'name' => 'August Council Session',
            'type' => 'Regular',
            'scheduled_at' => now()->addWeek(),
            'status' => BureauMeetingStatus::Scheduled,
            'created_by' => $bureauAdmin->id,
        ]);

        \App\Models\BureauAgendaItem::create([
            'details' => 'Adopt the new budget',
            'status' => \App\Enums\BureauAgendaStatus::AddedToMeeting,
            'created_by' => $bureauAdmin->id,
            'meeting_id' => $meeting->id,
        ]);

        return $meeting;
    }

    public function test_the_bureau_admin_can_start_minutes(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes');

        $minutes = BureauMeetingMinutes::sole();
        $this->assertSame($meeting->id, $minutes->meeting_id);
        $this->assertSame(BureauMinutesStatus::Draft, $minutes->status);
        $this->assertSame($bureauAdmin->id, $minutes->started_by);
    }

    public function test_a_non_admin_cannot_start_minutes(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $councillor = $this->makeCouncilMember(BureauRole::Councillor);

        $this->withoutExceptionHandling();
        $this->expectException(HttpException::class);

        Livewire::actingAs($councillor)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes');
    }

    public function test_starting_minutes_seeds_the_full_council_and_secretariat_roster(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $president = $this->makeCouncilMember(BureauRole::President);
        $councillor = $this->makeCouncilMember(BureauRole::Councillor);
        $participant = $this->makeCouncilMember(BureauRole::Participant);

        // Not attendees of this meeting — full-roster seeding shouldn't
        // depend on the meeting's own invited-attendees list.
        $this->assertFalse($meeting->attendees->contains('id', $president->id));

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes');

        $minutes = BureauMeetingMinutes::sole();

        $this->assertTrue($minutes->councilAttendance->contains('id', $president->id));
        $this->assertTrue($minutes->councilAttendance->contains('id', $councillor->id));
        $this->assertTrue($minutes->secretariatAttendance->contains('id', $participant->id));
        $this->assertTrue($minutes->secretariatAttendance->contains('id', $bureauAdmin->id));

        // Defaults to Absent until actively marked otherwise.
        $row = $minutes->councilAttendance->firstWhere('id', $president->id);
        $this->assertSame(BureauAttendanceStatus::Absent->value, $row->pivot->status);
        $this->assertSame(BureauMinutesAttendanceGroup::Council->value, $row->pivot->role_group);
    }

    public function test_the_admin_can_record_council_attendance_status_and_time(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $president = $this->makeCouncilMember(BureauRole::President);

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('updateCouncilAttendanceStatus', $president->id, BureauAttendanceStatus::Present->value)
            ->call('updateCouncilAttendanceTime', $president->id, '10:20');

        $minutes = BureauMeetingMinutes::sole();
        $row = $minutes->councilAttendance->firstWhere('id', $president->id);

        $this->assertSame(BureauAttendanceStatus::Present->value, $row->pivot->status);
        $this->assertSame('10:20:00', \Illuminate\Support\Carbon::parse($row->pivot->attended_at)->format('H:i:s'));
    }

    public function test_the_admin_can_mark_secretariat_present(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('updateSecretariatAttendance', $bureauAdmin->id, BureauAttendanceStatus::Present->value);

        $minutes = BureauMeetingMinutes::sole();
        $row = $minutes->secretariatAttendance->firstWhere('id', $bureauAdmin->id);

        $this->assertSame(BureauAttendanceStatus::Present->value, $row->pivot->status);
    }

    public function test_the_admin_can_add_and_remove_a_listener(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();

        $component = Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set('listenerForm.name', 'Ahmed Zahir')
            ->set('listenerForm.address', 'H. Sunny Side')
            ->call('addListener');

        $minutes = BureauMeetingMinutes::sole();
        $listener = $minutes->listeners()->sole();
        $this->assertSame('Ahmed Zahir', $listener->name);
        $this->assertSame('H. Sunny Side', $listener->address);

        $component->call('removeListener', $listener->id);
        $this->assertSame(0, $minutes->listeners()->count());
    }

    public function test_introduction_and_closing_notes_are_pre_filled_with_the_council_template(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->assertSet('introduction', fn (string $value): bool => str_contains($value, (string) $meeting->meeting_number))
            ->assertSet('closingNotes', fn (string $value): bool => str_contains($value, (string) $meeting->meeting_number));
    }

    public function test_the_admin_can_add_a_comment_and_draft_it_with_ai(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'ބަޖެޓާ ބެހޭގޮތުން މަޝްވަރާ ކުރެވުނު.'],
                    ]]],
                ],
            ]),
        ]);

        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set("commentForms.{$item->id}.bullet_points", 'budget up 5%, roads')
            ->call('addComment', $item->id);

        $comment = BureauMeetingMinutes::sole()->comments()->sole();
        $this->assertSame('budget up 5%, roads', $comment->bullet_points);

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->assertSet("commentEditForms.{$comment->id}", 'budget up 5%, roads')
            ->set("commentAiForms.{$comment->id}", 'expand on this')
            ->call('generateAiDraftForComment', $comment->id)
            ->assertSet("commentEditForms.{$comment->id}", "budget up 5%, roads\n\nބަޖެޓާ ބެހޭގޮތުން މަޝްވަރާ ކުރެވުނު.")
            ->assertSet("commentAiForms.{$comment->id}", '')
            ->call('saveComment', $comment->id);

        $this->assertSame("budget up 5%, roads\n\nބަޖެޓާ ބެހޭގޮތުން މަޝްވަރާ ކުރެވުނު.", $comment->fresh()->drafted_text);

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent');
    }

    public function test_ai_drafting_fails_gracefully_without_a_configured_key(): void
    {
        config(['services.gemini.key' => null]);

        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set("commentForms.{$item->id}.bullet_points", 'budget up 5%')
            ->call('addComment', $item->id);

        $comment = BureauMeetingMinutes::sole()->comments()->sole();

        // Should not throw — the page catches the RuntimeException and
        // shows a notification instead of blocking the recording flow.
        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->set("commentAiForms.{$comment->id}", 'budget up 5%')
            ->call('generateAiDraftForComment', $comment->id)
            ->assertSet("commentEditForms.{$comment->id}", $comment->bullet_points);

        $this->assertNull($comment->fresh()->drafted_text);
    }

    public function test_decision_requests_vote_in_order_and_skip_once_one_passes(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();

        $president = $this->makeCouncilMember(BureauRole::President);
        $councillor1 = $this->makeCouncilMember(BureauRole::Councillor);
        $councillor2 = $this->makeCouncilMember(BureauRole::Councillor);
        // Total council membership is 3 (president + 2 councillors) -> majority threshold is 2.

        $component = Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('updateCouncilAttendanceStatus', $president->id, BureauAttendanceStatus::Present->value)
            ->call('updateCouncilAttendanceStatus', $councillor1->id, BureauAttendanceStatus::Present->value)
            ->call('updateCouncilAttendanceStatus', $councillor2->id, BureauAttendanceStatus::Present->value)
            ->set("commentForms.{$item->id}.decision_text", 'Approve the budget as drafted')
            ->call('addDecisionForComposer', $item->id)
            ->set("commentForms.{$item->id}.decision_text", 'Approve the budget with amendments')
            ->call('addDecisionForComposer', $item->id);

        $minutes = BureauMeetingMinutes::sole();
        [$first, $second] = $minutes->decisionRequests()->orderBy('sort_order')->get()->all();

        $component->set("voteForms.{$first->id}.{$president->id}", 'yes')
            ->set("voteForms.{$first->id}.{$councillor1->id}", 'yes')
            ->set("voteForms.{$first->id}.{$councillor2->id}", 'no')
            ->call('recordVotes', $first->id);

        $this->assertSame(BureauDecisionStatus::Passed, $first->fresh()->status);
        $this->assertSame(BureauDecisionStatus::Skipped, $second->fresh()->status);
        $this->assertCount(3, $first->fresh()->votes);

        // Once a decision on this item has passed, adding another is blocked outright.
        $component->set("commentForms.{$item->id}.decision_text", 'A third, moot proposal')
            ->call('addDecisionForComposer', $item->id);

        $this->assertSame(2, $minutes->decisionRequests()->where('agenda_item_id', $item->id)->count());
    }

    public function test_votes_can_be_edited_before_minutes_is_approved(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();

        $president = $this->makeCouncilMember(BureauRole::President);
        $councillor1 = $this->makeCouncilMember(BureauRole::Councillor);
        $councillor2 = $this->makeCouncilMember(BureauRole::Councillor);

        $component = Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('updateCouncilAttendanceStatus', $president->id, BureauAttendanceStatus::Present->value)
            ->call('updateCouncilAttendanceStatus', $councillor1->id, BureauAttendanceStatus::Present->value)
            ->call('updateCouncilAttendanceStatus', $councillor2->id, BureauAttendanceStatus::Present->value)
            ->set("commentForms.{$item->id}.decision_text", 'Approve the budget as drafted')
            ->call('addDecisionForComposer', $item->id);

        $decision = BureauMeetingMinutes::sole()->decisionRequests()->sole();

        // First vote fails (only 1 of 3 in favor).
        $component->set("voteForms.{$decision->id}.{$president->id}", 'yes')
            ->set("voteForms.{$decision->id}.{$councillor1->id}", 'no')
            ->set("voteForms.{$decision->id}.{$councillor2->id}", 'no')
            ->call('recordVotes', $decision->id);

        $this->assertSame(BureauDecisionStatus::Failed, $decision->fresh()->status);

        // editVotes() pre-fills voteForms from what was actually recorded.
        $component->call('editVotes', $decision->id)
            ->assertSet("voteForms.{$decision->id}.{$president->id}", 'yes')
            ->assertSet("voteForms.{$decision->id}.{$councillor1->id}", 'no')
            ->assertSet("voteForms.{$decision->id}.{$councillor2->id}", 'no')
            ->assertSet('editingDecisionId', $decision->id);

        // Editing flips the outcome — now 2 of 3 in favor.
        $component->set("voteForms.{$decision->id}.{$councillor1->id}", 'yes')
            ->call('recordVotes', $decision->id)
            ->assertSet('editingDecisionId', null);

        $this->assertSame(BureauDecisionStatus::Passed, $decision->fresh()->status);
    }

    public function test_votes_cannot_be_edited_once_minutes_is_approved(): void
    {
        Storage::fake('local');

        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();
        $president = $this->makeCouncilMember(BureauRole::President);

        $component = Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('updateCouncilAttendanceStatus', $president->id, BureauAttendanceStatus::Present->value)
            ->set("commentForms.{$item->id}.decision_text", 'Approve the budget as drafted')
            ->call('addDecisionForComposer', $item->id);

        $decision = BureauMeetingMinutes::sole()->decisionRequests()->sole();

        $component->set("voteForms.{$decision->id}.{$president->id}", 'yes')
            ->call('recordVotes', $decision->id)
            ->set('closingNotes', 'Meeting closed.')
            ->call('endMinutes');

        Livewire::actingAs($president)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('approveMinutes');

        $this->assertSame(BureauMinutesStatus::Approved, BureauMeetingMinutes::sole()->status);

        $this->withoutExceptionHandling();
        $this->expectException(HttpException::class);

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('editVotes', $decision->id);
    }

    public function test_a_skipped_decision_becomes_votable_again_once_the_passed_sibling_is_edited_to_fail(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();

        $president = $this->makeCouncilMember(BureauRole::President);
        $councillor1 = $this->makeCouncilMember(BureauRole::Councillor);
        $councillor2 = $this->makeCouncilMember(BureauRole::Councillor);
        // Total council membership is 3 -> majority threshold is 2.

        $component = Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('updateCouncilAttendanceStatus', $president->id, BureauAttendanceStatus::Present->value)
            ->call('updateCouncilAttendanceStatus', $councillor1->id, BureauAttendanceStatus::Present->value)
            ->call('updateCouncilAttendanceStatus', $councillor2->id, BureauAttendanceStatus::Present->value)
            ->set("commentForms.{$item->id}.decision_text", 'First proposal')
            ->call('addDecisionForComposer', $item->id)
            ->set("commentForms.{$item->id}.decision_text", 'Second proposal')
            ->call('addDecisionForComposer', $item->id)
            ->set("commentForms.{$item->id}.decision_text", 'Third proposal')
            ->call('addDecisionForComposer', $item->id);

        [$first, $second, $third] = BureauMeetingMinutes::sole()->decisionRequests()->orderBy('sort_order')->get()->all();

        // 1st fails.
        $component->set("voteForms.{$first->id}.{$president->id}", 'no')
            ->set("voteForms.{$first->id}.{$councillor1->id}", 'no')
            ->set("voteForms.{$first->id}.{$councillor2->id}", 'yes')
            ->call('recordVotes', $first->id);

        // 2nd passes — 3rd is auto-skipped, never voted on.
        $component->set("voteForms.{$second->id}.{$president->id}", 'yes')
            ->set("voteForms.{$second->id}.{$councillor1->id}", 'yes')
            ->set("voteForms.{$second->id}.{$councillor2->id}", 'no')
            ->call('recordVotes', $second->id);

        $this->assertSame(BureauDecisionStatus::Failed, $first->fresh()->status);
        $this->assertSame(BureauDecisionStatus::Passed, $second->fresh()->status);
        $this->assertSame(BureauDecisionStatus::Skipped, $third->fresh()->status);
        $this->assertCount(0, $third->fresh()->votes);

        // Editing the 2nd back to a fail un-settles the item.
        $component->call('editVotes', $second->id)
            ->set("voteForms.{$second->id}.{$councillor1->id}", 'no')
            ->call('recordVotes', $second->id);

        $this->assertSame(BureauDecisionStatus::Failed, $second->fresh()->status);

        // The 3rd — still Skipped, never voted — can now be reopened and voted to pass.
        $component->call('editVotes', $third->id)
            ->set("voteForms.{$third->id}.{$president->id}", 'yes')
            ->set("voteForms.{$third->id}.{$councillor1->id}", 'yes')
            ->set("voteForms.{$third->id}.{$councillor2->id}", 'no')
            ->call('recordVotes', $third->id);

        $this->assertSame(BureauDecisionStatus::Passed, $third->fresh()->status);
    }

    public function test_a_failed_decision_request_moves_to_the_next_one(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();

        $president = $this->makeCouncilMember(BureauRole::President);
        $councillor1 = $this->makeCouncilMember(BureauRole::Councillor);
        $councillor2 = $this->makeCouncilMember(BureauRole::Councillor);

        $component = Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set("commentForms.{$item->id}.decision_text", 'Approve the budget as drafted')
            ->call('addDecisionForComposer', $item->id)
            ->set("commentForms.{$item->id}.decision_text", 'Approve the budget with amendments')
            ->call('addDecisionForComposer', $item->id);

        $minutes = BureauMeetingMinutes::sole();
        [$first, $second] = $minutes->decisionRequests()->orderBy('sort_order')->get()->all();

        $component->set("voteForms.{$first->id}.{$president->id}", 'no')
            ->set("voteForms.{$first->id}.{$councillor1->id}", 'no')
            ->set("voteForms.{$first->id}.{$councillor2->id}", 'yes')
            ->call('recordVotes', $first->id);

        $this->assertSame(BureauDecisionStatus::Failed, $first->fresh()->status);
        $this->assertSame(BureauDecisionStatus::Pending, $second->fresh()->status);

        $component->set("voteForms.{$second->id}.{$president->id}", 'yes')
            ->set("voteForms.{$second->id}.{$councillor1->id}", 'yes')
            ->set("voteForms.{$second->id}.{$councillor2->id}", 'yes')
            ->call('recordVotes', $second->id);

        $this->assertSame(BureauDecisionStatus::Passed, $second->fresh()->status);
    }

    public function test_decision_requests_record_who_proposed_them(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();
        $councillor = $this->makeCouncilMember(BureauRole::Councillor);
        $president = $this->makeCouncilMember(BureauRole::President);

        $component = Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set("commentForms.{$item->id}.speaker_id", $councillor->id)
            ->set("commentForms.{$item->id}.bullet_points", 'raised the budget concern')
            ->call('addComment', $item->id)
            ->set("commentForms.{$item->id}.speaker_id", $president->id)
            ->set("commentForms.{$item->id}.bullet_points", 'seconded the concern')
            ->call('addComment', $item->id);

        [$councillorComment, $presidentComment] = BureauMeetingMinutes::sole()->comments()->orderBy('sort_order')->get()->all();
        $this->assertSame($councillor->id, $councillorComment->speaker_id);
        $this->assertSame($president->id, $presidentComment->speaker_id);

        // Proposed straight from the composer (before any further comment is saved).
        $component->set("commentForms.{$item->id}.speaker_id", $councillor->id)
            ->set("commentForms.{$item->id}.decision_text", 'Proposed from the composer')
            ->call('addDecisionForComposer', $item->id);

        // Proposed from a different speaker's already-saved comment.
        $component->set("commentDecisionForms.{$presidentComment->id}", 'Proposed from the saved comment')
            ->call('addDecisionForComment', $presidentComment->id);

        $decisions = BureauMeetingMinutes::sole()->decisionRequests()->orderBy('sort_order')->get();

        $this->assertCount(2, $decisions);
        $this->assertSame($councillor->id, $decisions[0]->proposed_by);
        $this->assertSame('Proposed from the composer', $decisions[0]->text);
        $this->assertSame($president->id, $decisions[1]->proposed_by);
        $this->assertSame('Proposed from the saved comment', $decisions[1]->text);
    }

    public function test_a_speaker_cannot_propose_a_second_decision_request_on_the_same_agenda_item(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();
        $councillor = $this->makeCouncilMember(BureauRole::Councillor);

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set("commentForms.{$item->id}.speaker_id", $councillor->id)
            ->set("commentForms.{$item->id}.decision_text", 'First proposal')
            ->call('addDecisionForComposer', $item->id)
            ->set("commentForms.{$item->id}.decision_text", 'Second proposal, same speaker')
            ->call('addDecisionForComposer', $item->id);

        $decisions = BureauMeetingMinutes::sole()->decisionRequests()->where('agenda_item_id', $item->id)->get();

        $this->assertCount(1, $decisions);
        $this->assertSame('First proposal', $decisions->first()->text);
    }

    public function test_ending_minutes_saves_chair_and_times_and_moves_to_review(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();
        $president = $this->makeCouncilMember(BureauRole::President);
        $attendee = $this->makeCouncilMember(BureauRole::Councillor);
        $meeting->attendees()->attach($attendee->id);

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set('chairId', $president->id)
            ->set('endTime', '11:45')
            ->set("commentForms.{$item->id}.bullet_points", 'raised concerns about timeline')
            ->call('addComment', $item->id)
            ->set('closingNotes', 'Meeting closed in good order.')
            ->call('endMinutes');

        $minutes = BureauMeetingMinutes::sole();
        $this->assertSame(BureauMinutesStatus::Review, $minutes->status);
        $this->assertSame($president->id, $minutes->chaired_by);
        $this->assertNotNull($minutes->started_at);
        $this->assertNotNull($minutes->ended_at);
        $this->assertSame('11:45', $minutes->ended_at->format('H:i'));

        $comment = $minutes->comments()->sole();

        $this->withoutExceptionHandling();

        Livewire::actingAs($attendee)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->set("commentEditForms.{$comment->id}", 'Edited by the attendee for accuracy.')
            ->call('saveComment', $comment->id);

        $this->assertSame('Edited by the attendee for accuracy.', $comment->fresh()->drafted_text);
        $this->assertSame($attendee->id, $comment->fresh()->edited_by);
    }

    public function test_a_non_attendee_cannot_edit_comments_during_review(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();
        $outsider = User::factory()->create();

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set("commentForms.{$item->id}.bullet_points", 'a point raised')
            ->call('addComment', $item->id)
            ->call('endMinutes');

        $comment = BureauMeetingMinutes::sole()->comments()->sole();

        $this->withoutExceptionHandling();
        $this->expectException(HttpException::class);

        Livewire::actingAs($outsider)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->set("commentEditForms.{$comment->id}", 'Should not be allowed.')
            ->call('saveComment', $comment->id);
    }

    public function test_any_bureau_admin_can_edit_comments_during_review_even_if_not_an_attendee(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $recordingAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();

        // A second Bureau Admin who was never invited as an attendee
        // of this specific meeting.
        $otherAdmin = $this->makeBureauAdmin();
        $this->assertFalse($meeting->attendees->contains('id', $otherAdmin->id));

        Livewire::actingAs($recordingAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->set("commentForms.{$item->id}.bullet_points", 'a point raised')
            ->call('addComment', $item->id)
            ->call('endMinutes');

        $comment = BureauMeetingMinutes::sole()->comments()->sole();

        $this->withoutExceptionHandling();

        Livewire::actingAs($otherAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->set("commentEditForms.{$comment->id}", 'Edited by a bureau admin not on the attendee list.')
            ->call('saveComment', $comment->id);

        $this->assertSame('Edited by a bureau admin not on the attendee list.', $comment->fresh()->drafted_text);
        $this->assertSame($otherAdmin->id, $comment->fresh()->edited_by);
    }

    public function test_introduction_and_closing_notes_can_be_saved_separately_during_review(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('endMinutes');

        $this->assertSame(BureauMinutesStatus::Review, BureauMeetingMinutes::sole()->status);

        $this->withoutExceptionHandling();

        // A second Bureau Admin, not the one who ran the recording —
        // still allowed to edit through Review (see canEditComments()).
        $reviewer = $this->makeBureauAdmin();

        Livewire::actingAs($reviewer)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->set('introduction', 'Corrected introduction during review.')
            ->call('saveIntroduction')
            ->set('closingNotes', 'Corrected closing notes during review.')
            ->call('saveClosingNotes');

        $minutes = BureauMeetingMinutes::sole();
        $this->assertSame('Corrected introduction during review.', $minutes->introduction);
        $this->assertSame('Corrected closing notes during review.', $minutes->closing_notes);
        // saveClosingNotes() must not re-trigger the Draft->Review transition.
        $this->assertSame(BureauMinutesStatus::Review, $minutes->status);
    }

    public function test_a_comment_can_be_added_during_review_for_an_agenda_item_left_unattended_while_recording(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $item = $meeting->agendaItems->first();

        // Nothing said about the only agenda item while recording —
        // ended without ever opening its composer.
        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('endMinutes');

        $this->assertSame(BureauMinutesStatus::Review, BureauMeetingMinutes::sole()->status);
        $this->assertCount(0, BureauMeetingMinutes::sole()->comments);

        $this->withoutExceptionHandling();

        $reviewer = $this->makeBureauAdmin();

        Livewire::actingAs($reviewer)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->set("commentForms.{$item->id}.speaker_id", $bureauAdmin->id)
            ->set("commentForms.{$item->id}.bullet_points", 'Added during review — nothing was said live.')
            ->call('addComment', $item->id);

        $comment = BureauMeetingMinutes::sole()->comments()->sole();
        $this->assertSame('Added during review — nothing was said live.', $comment->bullet_points);
        $this->assertSame($item->id, $comment->agenda_item_id);
    }

    public function test_the_president_can_approve_minutes_and_only_present_council_members_sign(): void
    {
        Storage::fake('local');

        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();
        $president = $this->makeCouncilMember(BureauRole::President);
        $absentCouncillor = $this->makeCouncilMember(BureauRole::Councillor);

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            // Only the president is marked Present — the absent
            // councillor stays Absent (the seeded default) and must not
            // become a signer.
            ->call('updateCouncilAttendanceStatus', $president->id, BureauAttendanceStatus::Present->value)
            ->set('closingNotes', 'Meeting closed.')
            ->call('endMinutes');

        Livewire::actingAs($president)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('approveMinutes');

        $minutes = BureauMeetingMinutes::sole();
        $this->assertSame(BureauMinutesStatus::Approved, $minutes->status);
        $this->assertSame($president->id, $minutes->reviewed_by);
        $this->assertNotNull($minutes->document_id);

        $document = $minutes->document;
        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertCount(1, $document->signers);
        $this->assertSame($president->id, $document->signers->first()->user_id);
        Storage::disk('local')->assertExists($minutes->minutes_pdf_path);
    }

    public function test_a_non_president_cannot_approve_minutes(): void
    {
        $meeting = $this->makeScheduledMeeting();
        $bureauAdmin = $this->makeBureauAdmin();

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('startMinutes')
            ->call('endMinutes');

        $this->withoutExceptionHandling();
        $this->expectException(HttpException::class);

        Livewire::actingAs($bureauAdmin)
            ->test(RecordMinutes::class, ['record' => $meeting->id])
            ->call('approveMinutes');
    }
}
