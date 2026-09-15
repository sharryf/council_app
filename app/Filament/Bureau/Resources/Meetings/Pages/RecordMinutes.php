<?php

namespace App\Filament\Bureau\Resources\Meetings\Pages;

use App\Enums\BureauAttendanceStatus;
use App\Enums\BureauDecisionStatus;
use App\Enums\BureauMinutesAttendanceGroup;
use App\Enums\BureauMinutesStatus;
use App\Enums\BureauRole;
use App\Filament\Bureau\Resources\Meetings\MeetingResource;
use App\Models\BureauDecisionRequest;
use App\Models\BureauMeeting;
use App\Models\BureauMeetingMinutes;
use App\Models\BureauMinutesComment;
use App\Models\User;
use App\Services\Bureau\DecisionVotingService;
use App\Services\Bureau\MinutesDraftingService;
use App\Services\Bureau\MinutesHandoffService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The live-meeting minutes workstation — see the module's own workflow
 * doc: start minutes (+ audio, see the Blade view's MediaRecorder
 * script), roll call (council/secretariat/listeners), write the
 * introduction, then per agenda item: record comments (bullet points ->
 * optionally AI-drafted prose), raise decision requests, and vote them
 * in order (see DecisionVotingService). Ends with chair/times/closing
 * notes saved together, which moves the minutes into Review — where
 * any meeting attendee may edit any comment — before the President
 * approves and it's hand off to Document Signing (see
 * MinutesHandoffService).
 *
 * The Bureau Admin is the sole live-meeting operator (per the module's
 * own spec: "Bureau admin enter every thing") — every action here
 * writes straight to the database rather than batching local Livewire
 * state, so nothing is lost if the browser is refreshed mid-meeting.
 * The meeting-info fields (chair/times) are the one exception — they're
 * held as bound properties and only persisted together when the
 * Bureau Admin hits Save (endMinutes()), same as closing notes always
 * has been.
 */
class RecordMinutes extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MeetingResource::class;

    protected string $view = 'filament.bureau.pages.record-minutes';

    /** @var array<int, array{speaker_id: ?int, bullet_points: string, decision_text: ?string}> */
    public array $commentForms = [];

    /**
     * Scratch bullet points typed purely to feed generateAiDraft() —
     * never persisted (there's no column for it); the AI's expansion of
     * these gets appended into commentForms[$id]['bullet_points'], the
     * one field that actually gets saved by addComment(). Cleared after
     * each successful generation.
     *
     * @var array<int, string>
     */
    public array $aiDraftForms = [];

    /**
     * The editable content for every already-saved comment, keyed by
     * comment id — seeded in mount()/addComment() from
     * drafted_text ?: bullet_points, so it always shows (and saves) the
     * comment's current content rather than starting blank the way a
     * separate Alpine-only edit box used to. Kept editable for as long
     * as canEditComments() allows (Draft-stage recording, then any
     * attendee during Review) — it stops being editable the moment
     * minutes moves past Review (approved/signed), matching "we only
     * change it once minutes is completed".
     *
     * @var array<int, string>
     */
    public array $commentEditForms = [];

    /**
     * Scratch bullet points for generateAiDraftForComment() — the
     * already-saved-comment counterpart to aiDraftForms, same
     * never-persisted/append-then-clear behavior.
     *
     * @var array<int, string>
     */
    public array $commentAiForms = [];

    /**
     * A decision-request text field per already-saved comment, keyed by
     * comment id — replaces the old single generic per-agenda-item
     * decisionForms field. Proposing a decision this way records
     * proposed_by as that comment's own speaker_id (see
     * addDecisionForComment()), rather than leaving it anonymous.
     *
     * @var array<int, string>
     */
    public array $commentDecisionForms = [];

    /** @var array<int, array<int, string>> */
    public array $voteForms = [];

    /**
     * Which decision request's vote table is currently open for
     * editing (via editVotes()) — separate from activeDecisionFor(),
     * which finds the naturally-Pending one for an item. A decision
     * that's already Passed/Failed only shows its editable table while
     * its id matches this.
     */
    public ?int $editingDecisionId = null;

    public string $introduction = '';

    public string $closingNotes = '';

    public ?int $chairId = null;

    /**
     * Day/month/year kept as three separate bound fields (month as a
     * Dhivehi-labelled Select) instead of a single native
     * <input type="date"> — same reasoning as MeetingForm's own
     * scheduled_day/scheduled_month/scheduled_year fields: the native
     * date picker's calendar UI can only ever render in English (no
     * Dhivehi locale in the browser/dayjs), so this is the only way the
     * date reads in Dhivehi. See combinedStartDate() for where these
     * three recombine into a single value.
     */
    public ?int $startDay = null;

    public ?int $startMonth = null;

    public ?int $startYear = null;

    public ?string $startTime = null;

    public ?string $breakStartTime = null;

    public ?string $breakEndTime = null;

    public ?string $endTime = null;

    /** @var array{name: string, address: string} */
    public array $listenerForm = ['name' => '', 'address' => ''];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $meeting = $this->getMeeting();
        $minutes = $meeting->minutes;

        if ($minutes) {
            $this->introduction = $minutes->introduction ?: BureauMeetingMinutes::defaultIntroduction($meeting);
            $this->closingNotes = $minutes->closing_notes ?: BureauMeetingMinutes::defaultClosingNotes($meeting);
            $this->chairId = $minutes->chaired_by;
            $start = $minutes->started_at ?? $meeting->scheduled_at;
            $this->startDay = $start->day;
            $this->startMonth = $start->month;
            $this->startYear = $start->year;
            $this->startTime = $start->format('H:i');
            $this->breakStartTime = $minutes->break_started_at?->format('H:i');
            $this->breakEndTime = $minutes->break_ended_at?->format('H:i');
            $this->endTime = $minutes->ended_at?->format('H:i');

            foreach ($minutes->comments as $comment) {
                $this->commentEditForms[$comment->id] = $comment->drafted_text ?: $comment->bullet_points;
            }
        } else {
            $this->startDay = $meeting->scheduled_at->day;
            $this->startMonth = $meeting->scheduled_at->month;
            $this->startYear = $meeting->scheduled_at->year;
            $this->startTime = $meeting->scheduled_at->format('H:i');
        }
    }

    /**
     * Recombines startDay/startMonth/startYear into a single "Y-m-d"
     * value for Carbon::parse() — see the three properties' own comment
     * for why they're separate bound fields in the first place.
     */
    private function combinedStartDate(): ?string
    {
        if (! $this->startDay || ! $this->startMonth || ! $this->startYear) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $this->startYear, $this->startMonth, $this->startDay);
    }

    public function getTitle(): string
    {
        return __('bureau.minutes.label').' — '.$this->getMeeting()->name;
    }

    public function getMeeting(): BureauMeeting
    {
        /** @var BureauMeeting $meeting */
        $meeting = $this->getRecord();

        return $meeting;
    }

    public function getMinutes(): ?BureauMeetingMinutes
    {
        return $this->getMeeting()->minutes()->with([
            'chair',
            'councilAttendance',
            'secretariatAttendance',
            'listeners',
            'comments.speaker',
            'comments.agendaItem',
            'decisionRequests.votes',
            'decisionRequests.agendaItem',
        ])->first();
    }

    private function isBureauAdmin(): bool
    {
        return (bool) auth()->user()?->hasBureauRole(BureauRole::BureauAdmin);
    }

    private function isPresident(): bool
    {
        return (bool) auth()->user()?->hasBureauRole(BureauRole::President);
    }

    public function isRecordingStage(): bool
    {
        return $this->getMinutes()?->status === BureauMinutesStatus::Draft;
    }

    /**
     * Whether to show the "Start minutes" button — before any minutes
     * row exists, isRecordingStage() is false, so canRecord() (which
     * gates every action that needs an existing Draft minutes row)
     * can't be used here.
     */
    public function canStart(): bool
    {
        return $this->isBureauAdmin() && ! $this->getMeeting()->minutes;
    }

    /**
     * Draft-stage recording is Bureau-Admin-only (the sole operator);
     * Review-stage comment edits are open to any invited attendee.
     */
    public function canRecord(): bool
    {
        return $this->isBureauAdmin() && $this->isRecordingStage();
    }

    public function canEditComments(): bool
    {
        $minutes = $this->getMinutes();

        if (! $minutes) {
            return false;
        }

        if ($this->canRecord()) {
            return true;
        }

        $user = auth()->user();

        if (! $user || $minutes->status !== BureauMinutesStatus::Review) {
            return false;
        }

        // Any invited attendee of this specific meeting, or any Bureau
        // Admin regardless of whether they were invited — Bureau Admins
        // keep edit access through Review the same way they had it
        // during Draft, not just whoever happened to be on the
        // attendee list.
        return $this->getMeeting()->attendees->contains('id', $user->id)
            || $user->hasBureauRole(BureauRole::BureauAdmin);
    }

    public function canApprove(): bool
    {
        return $this->isPresident() && $this->getMinutes()?->status === BureauMinutesStatus::Review;
    }

    /**
     * Voters shown in the decision-voting table — council members
     * actually marked Present for this meeting (not just invited), now
     * that real attendance is tracked. The pass/fail threshold itself
     * is unaffected (DecisionVotingService still weighs against total
     * system-wide council membership, present or not).
     *
     * @return array<int, User>
     */
    public function votingMembers(): array
    {
        return $this->presentUsers($this->getMinutes()?->councilAttendance ?? collect())->all();
    }

    /**
     * Attendees selectable as a comment's speaker — President,
     * Councillor, and Participant are the three roles the permission
     * table grants "can speak in the meetings" to (Bureau Admin, Staff,
     * and Module Admin are not eligible, even though Bureau Admin
     * shares the Secretariat roll-call bucket with Participant) —
     * further scoped to whoever is actually marked Present, now that
     * real attendance is tracked.
     *
     * @return array<int, User>
     */
    public function eligibleSpeakers(): array
    {
        $minutes = $this->getMinutes();

        if (! $minutes) {
            return [];
        }

        $presentParticipants = $this->presentUsers($minutes->secretariatAttendance)
            ->filter(fn (User $user): bool => $user->hasBureauRole(BureauRole::Participant));

        return $this->presentUsers($minutes->councilAttendance)
            ->merge($presentParticipants)
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $attendance
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function presentUsers($attendance): \Illuminate\Support\Collection
    {
        return collect($attendance)
            ->filter(fn (User $user): bool => $user->pivot->status === BureauAttendanceStatus::Present->value)
            ->values();
    }

    public function activeDecisionFor(int $agendaItemId): ?BureauDecisionRequest
    {
        return $this->getMinutes()?->decisionRequests
            ->where('agenda_item_id', $agendaItemId)
            ->firstWhere('status', BureauDecisionStatus::Pending);
    }

    public function startMinutes(): void
    {
        abort_unless($this->isBureauAdmin(), 403);

        $meeting = $this->getMeeting();

        if ($meeting->minutes) {
            return;
        }

        $minutes = $meeting->minutes()->create([
            'status' => BureauMinutesStatus::Draft,
            'started_at' => $meeting->scheduled_at,
            'started_by' => auth()->id(),
        ]);

        // mount() only ever runs once, before this minutes row existed
        // (the "Start minutes" screen is shown instead of these bound
        // fields) — so the introduction/closing templates need setting
        // here too, otherwise the two textareas render blank the moment
        // recording actually starts.
        $this->introduction = BureauMeetingMinutes::defaultIntroduction($meeting);
        $this->closingNotes = BureauMeetingMinutes::defaultClosingNotes($meeting);

        $councilIds = User::query()
            ->whereHas('bureauRoles', fn ($query) => $query->whereIn('role', [BureauRole::President, BureauRole::Councillor]))
            ->pluck('id');

        foreach ($councilIds as $userId) {
            $minutes->councilAttendance()->attach($userId, [
                'role_group' => BureauMinutesAttendanceGroup::Council->value,
                'status' => BureauAttendanceStatus::Absent->value,
            ]);
        }

        // A user holding both a council and a secretariat role only
        // ever gets one attendance row per meeting (the unique
        // constraint is on minutes_id+user_id) — council takes
        // priority, since that's the more authoritative category for
        // roll-call purposes.
        $secretariatIds = User::query()
            ->whereHas('bureauRoles', fn ($query) => $query->whereIn('role', [BureauRole::Participant, BureauRole::BureauAdmin]))
            ->whereNotIn('id', $councilIds)
            ->pluck('id');

        foreach ($secretariatIds as $userId) {
            $minutes->secretariatAttendance()->attach($userId, [
                'role_group' => BureauMinutesAttendanceGroup::Secretariat->value,
                'status' => BureauAttendanceStatus::Absent->value,
            ]);
        }

        Notification::make()->title(__('bureau.minutes.actions.start'))->success()->send();
    }

    public function updateCouncilAttendanceStatus(int $userId, string $status): void
    {
        abort_unless($this->canEditComments(), 403);

        $this->getMinutes()?->councilAttendance()->updateExistingPivot($userId, ['status' => $status]);
    }

    public function updateCouncilAttendanceTime(int $userId, ?string $time): void
    {
        abort_unless($this->canEditComments(), 403);

        $startDate = $this->combinedStartDate();

        $this->getMinutes()?->councilAttendance()->updateExistingPivot($userId, [
            'attended_at' => (filled($time) && $startDate) ? "{$startDate} {$time}" : null,
        ]);
    }

    public function updateSecretariatAttendance(int $userId, string $status): void
    {
        abort_unless($this->canEditComments(), 403);

        $this->getMinutes()?->secretariatAttendance()->updateExistingPivot($userId, ['status' => $status]);
    }

    public function addListener(): void
    {
        abort_unless($this->canEditComments(), 403);

        $name = trim($this->listenerForm['name'] ?? '');

        if ($name === '') {
            return;
        }

        $this->getMinutes()?->listeners()->create([
            'name' => $name,
            'address' => trim($this->listenerForm['address'] ?? '') ?: null,
        ]);

        $this->listenerForm = ['name' => '', 'address' => ''];
    }

    public function removeListener(int $listenerId): void
    {
        abort_unless($this->canEditComments(), 403);

        $this->getMinutes()?->listeners()->whereKey($listenerId)->delete();
    }

    /**
     * Card 1's Review-stage save action — endMinutes() also persists
     * chair/times, but only while canRecord() (it transitions Draft to
     * Review, which only happens once). This is the counterpart for
     * editing chair/times afterwards, through Review, without
     * re-triggering that transition — same relationship saveClosingNotes()
     * has to endMinutes() for closing_notes.
     */
    public function saveMeetingInfo(): void
    {
        abort_unless($this->canEditComments(), 403);

        $startDate = $this->combinedStartDate();

        $this->getMinutes()?->update([
            'chaired_by' => $this->chairId,
            'started_at' => ($startDate && filled($this->startTime))
                ? Carbon::parse("{$startDate} {$this->startTime}")
                : null,
            'break_started_at' => ($startDate && filled($this->breakStartTime)) ? Carbon::parse("{$startDate} {$this->breakStartTime}") : null,
            'break_ended_at' => ($startDate && filled($this->breakEndTime)) ? Carbon::parse("{$startDate} {$this->breakEndTime}") : null,
            'ended_at' => ($startDate && filled($this->endTime)) ? Carbon::parse("{$startDate} {$this->endTime}") : null,
        ]);

        Notification::make()->title(__('bureau.minutes.actions.save_meeting_info'))->success()->send();
    }

    public function saveIntroduction(): void
    {
        abort_unless($this->canEditComments(), 403);

        $this->getMinutes()?->update(['introduction' => $this->introduction]);

        Notification::make()->title(__('bureau.minutes.actions.save_introduction'))->success()->send();
    }

    /**
     * Card 6's Review-stage save action — endMinutes() also persists
     * closing_notes, but only while canRecord() (it transitions Draft
     * to Review, which only happens once). This is the counterpart for
     * editing closing_notes afterwards, through Review, without
     * re-triggering that transition.
     */
    public function saveClosingNotes(): void
    {
        abort_unless($this->canEditComments(), 403);

        $this->getMinutes()?->update(['closing_notes' => $this->closingNotes]);

        Notification::make()->title(__('bureau.minutes.actions.save_closing_notes'))->success()->send();
    }

    public function addComment(int $agendaItemId): void
    {
        abort_unless($this->canEditComments(), 403);

        $form = $this->commentForms[$agendaItemId] ?? [];
        $bulletPoints = trim((string) ($form['bullet_points'] ?? ''));

        if ($bulletPoints === '') {
            Notification::make()->title(__('bureau.minutes.field.comment_text'))->danger()->send();

            return;
        }

        $minutes = $this->getMinutes();
        $nextOrder = $minutes->comments()->where('agenda_item_id', $agendaItemId)->count();

        $comment = $minutes->comments()->create([
            'agenda_item_id' => $agendaItemId,
            'speaker_id' => $form['speaker_id'] ?? null,
            'bullet_points' => $bulletPoints,
            'sort_order' => $nextOrder,
            'created_by' => auth()->id(),
        ]);

        // The new comment gets its own edit field seeded with what was
        // just typed — see commentEditForms' own comment — so its Edit
        // button opens straight to that content rather than blank.
        $this->commentEditForms[$comment->id] = $bulletPoints;

        unset($this->commentForms[$agendaItemId]);

        // Tells the Blade view's Alpine composer (scoped to this one
        // agenda item) to collapse back to the "add a comment" trigger
        // button, ready for the next speaker — see the composer's own
        // x-on:minutes-comment-added.window listener.
        $this->dispatch('minutes-comment-added', agendaItemId: $agendaItemId);
    }

    /**
     * The in-progress-comment counterpart to generateAiDraftForComment()
     * — expands the scratch aiDraftForms bullet points and appends the
     * result into commentForms[$id]['bullet_points'] (adding to
     * whatever's already typed there, never overwriting), rather than
     * operating on an already-saved BureauMinutesComment. The scratch
     * field is cleared afterwards since it's never itself persisted.
     */
    public function generateAiDraft(int $agendaItemId): void
    {
        abort_unless($this->canEditComments(), 403);

        $bulletPoints = trim((string) ($this->aiDraftForms[$agendaItemId] ?? ''));

        if ($bulletPoints === '') {
            return;
        }

        $agendaItem = $this->getMeeting()->agendaItems()->findOrFail($agendaItemId);
        $speakerId = $this->commentForms[$agendaItemId]['speaker_id'] ?? null;
        $speaker = $speakerId ? User::find($speakerId) : null;

        try {
            $drafted = app(MinutesDraftingService::class)->draft(
                $agendaItem->details,
                $speaker?->name,
                $bulletPoints,
            );

            $existing = trim((string) ($this->commentForms[$agendaItemId]['bullet_points'] ?? ''));
            $this->commentForms[$agendaItemId]['bullet_points'] = $existing !== '' ? "{$existing}\n\n{$drafted}" : $drafted;
            $this->aiDraftForms[$agendaItemId] = '';

            Notification::make()->title(__('bureau.minutes.actions.generate_ai_text'))->success()->send();
        } catch (RuntimeException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    /**
     * The already-saved-comment counterpart to generateAiDraft() — same
     * append-then-clear behavior, but expands commentAiForms into
     * commentEditForms (the field saveComment() persists), rather than
     * an in-progress commentForms entry that doesn't exist yet.
     */
    public function generateAiDraftForComment(int $commentId): void
    {
        abort_unless($this->canEditComments(), 403);

        $bulletPoints = trim((string) ($this->commentAiForms[$commentId] ?? ''));

        if ($bulletPoints === '') {
            return;
        }

        /** @var BureauMinutesComment $comment */
        $comment = BureauMinutesComment::query()->with(['agendaItem', 'speaker'])->findOrFail($commentId);

        try {
            $drafted = app(MinutesDraftingService::class)->draft(
                $comment->agendaItem->details,
                $comment->speaker?->name,
                $bulletPoints,
            );

            $existing = trim((string) ($this->commentEditForms[$commentId] ?? ''));
            $this->commentEditForms[$commentId] = $existing !== '' ? "{$existing}\n\n{$drafted}" : $drafted;
            $this->commentAiForms[$commentId] = '';

            Notification::make()->title(__('bureau.minutes.actions.generate_ai_text'))->success()->send();
        } catch (RuntimeException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    /**
     * Persists commentEditForms[$commentId] — the one field a saved
     * comment's content lives in. Shown read-only by default, behind an
     * Edit toggle, while canEditComments() allows it (Draft-stage
     * recording, then any attendee through Review; locked once minutes
     * moves past Review, i.e. approved and sent for signing).
     */
    public function saveComment(int $commentId): void
    {
        abort_unless($this->canEditComments(), 403);

        $text = trim((string) ($this->commentEditForms[$commentId] ?? ''));

        BureauMinutesComment::query()->whereKey($commentId)->update([
            'drafted_text' => $text,
            'edited_by' => auth()->id(),
            'edited_at' => now(),
        ]);

        Notification::make()->title(__('bureau.minutes.actions.save_comment'))->success()->send();

        // Tells the Blade view's Alpine edit toggle (scoped to this one
        // comment) to collapse back to the read-only display — see that
        // block's own x-on:minutes-comment-saved.window listener.
        $this->dispatch('minutes-comment-saved', commentId: $commentId);
    }

    /**
     * Shared by addDecisionForComment()/addDecisionForComposer() — a
     * decision request is now always raised in the context of a
     * specific speaker (proposed_by), from either an already-saved
     * comment's own field or the in-progress composer's field, rather
     * than one generic per-agenda-item field.
     */
    private function createDecisionRequest(int $agendaItemId, ?int $proposedBy, string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $minutes = $this->getMinutes();

        // Once one decision request on this item has passed, the
        // matter is settled — adding another would just sit there
        // moot, so it's blocked outright rather than only skipping the
        // vote once cast (see DecisionVotingService).
        $alreadyDecided = $minutes->decisionRequests()
            ->where('agenda_item_id', $agendaItemId)
            ->where('status', BureauDecisionStatus::Passed)
            ->exists();

        if ($alreadyDecided) {
            Notification::make()->title(__('bureau.minutes.decision_locked'))->warning()->send();

            return;
        }

        // One speaker may not raise a second decision request on the
        // same agenda item — regardless of status (pending, failed,
        // skipped), a second proposal from the same person is blocked
        // outright rather than silently accepted.
        $alreadyProposedBySpeaker = $proposedBy && $minutes->decisionRequests()
            ->where('agenda_item_id', $agendaItemId)
            ->where('proposed_by', $proposedBy)
            ->exists();

        if ($alreadyProposedBySpeaker) {
            Notification::make()->title(__('bureau.minutes.decision_already_proposed'))->warning()->send();

            return;
        }

        $nextOrder = $minutes->decisionRequests()->where('agenda_item_id', $agendaItemId)->count();

        $minutes->decisionRequests()->create([
            'agenda_item_id' => $agendaItemId,
            'proposed_by' => $proposedBy,
            'text' => $text,
            'sort_order' => $nextOrder,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Raises a decision request from an already-saved comment's own
     * field — proposed_by is that comment's speaker, not the anonymous
     * Bureau Admin typing it in.
     *
     * Widened to canEditComments() (not Draft-only) — a Bureau Admin who
     * ended the meeting early on paper notes needs to add decision
     * requests they didn't get to record live, while finishing the
     * minutes during Review.
     */
    public function addDecisionForComment(int $commentId): void
    {
        abort_unless($this->canEditComments(), 403);

        /** @var BureauMinutesComment $comment */
        $comment = BureauMinutesComment::findOrFail($commentId);

        $this->createDecisionRequest(
            $comment->agenda_item_id,
            $comment->speaker_id,
            (string) ($this->commentDecisionForms[$commentId] ?? ''),
        );

        $this->commentDecisionForms[$commentId] = '';
    }

    /**
     * Raises a decision request straight from the in-progress
     * new-comment composer — proposed_by is whichever speaker is
     * currently selected there, letting a decision be raised without
     * first having to save a comment.
     *
     * Widened to canEditComments() — see addDecisionForComment()'s own
     * comment.
     */
    public function addDecisionForComposer(int $agendaItemId): void
    {
        abort_unless($this->canEditComments(), 403);

        $form = $this->commentForms[$agendaItemId] ?? [];

        $this->createDecisionRequest(
            $agendaItemId,
            $form['speaker_id'] ?? null,
            (string) ($form['decision_text'] ?? ''),
        );

        if (isset($this->commentForms[$agendaItemId])) {
            $this->commentForms[$agendaItemId]['decision_text'] = '';
        }
    }

    /**
     * Opens a decision's already-recorded votes for editing — pre-fills
     * voteForms from the votes that exist so the table starts showing
     * the current picks, not blank. Gated the same as comment editing
     * (canEditComments()): Bureau Admin during recording, then any
     * attendee through Review — locked once minutes is approved.
     */
    public function editVotes(int $decisionRequestId): void
    {
        abort_unless($this->canEditComments(), 403);

        /** @var BureauDecisionRequest $decision */
        $decision = BureauDecisionRequest::query()->with('votes')->findOrFail($decisionRequestId);

        $this->voteForms[$decisionRequestId] = $decision->votes
            ->mapWithKeys(fn ($vote) => [$vote->user_id => $vote->vote->value])
            ->all();

        $this->editingDecisionId = $decisionRequestId;
    }

    public function recordVotes(int $decisionRequestId): void
    {
        abort_unless($this->canEditComments(), 403);

        $votes = $this->voteForms[$decisionRequestId] ?? [];

        try {
            app(DecisionVotingService::class)->recordVotes(
                BureauDecisionRequest::findOrFail($decisionRequestId),
                $votes,
            );

            unset($this->voteForms[$decisionRequestId]);

            if ($this->editingDecisionId === $decisionRequestId) {
                $this->editingDecisionId = null;
            }

            Notification::make()->title(__('bureau.minutes.actions.record_votes'))->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title(collect($exception->errors())->flatten()->implode(' '))->danger()->send();
        }
    }

    public function endMinutes(): void
    {
        abort_unless($this->canRecord(), 403);

        $startDate = $this->combinedStartDate();

        $this->getMinutes()?->update([
            'status' => BureauMinutesStatus::Review,
            'chaired_by' => $this->chairId,
            'started_at' => ($startDate && filled($this->startTime))
                ? Carbon::parse("{$startDate} {$this->startTime}")
                : null,
            'break_started_at' => ($startDate && filled($this->breakStartTime)) ? Carbon::parse("{$startDate} {$this->breakStartTime}") : null,
            'break_ended_at' => ($startDate && filled($this->breakEndTime)) ? Carbon::parse("{$startDate} {$this->breakEndTime}") : null,
            'ended_at' => ($startDate && filled($this->endTime)) ? Carbon::parse("{$startDate} {$this->endTime}") : null,
            'closing_notes' => $this->closingNotes,
        ]);

        Notification::make()->title(__('bureau.minutes.actions.end'))->success()->send();
    }

    public function approveMinutes(): void
    {
        abort_unless($this->canApprove(), 403);

        app(MinutesHandoffService::class)->approveAndSendForSigning($this->getMinutes(), auth()->user());

        Notification::make()->title(__('bureau.minutes.actions.approve'))->success()->send();
    }
}
