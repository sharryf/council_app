<x-filament-panels::page>
    <style>
        .rmc-section > .fi-section-header {
            background: #f9fafb;
        }
        html.dark .rmc-section > .fi-section-header {
            background: rgba(255, 255, 255, 0.03);
        }
        .rmc-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        @media (max-width: 480px) {
            .rmc-grid { grid-template-columns: 1fr; }
        }
        .rmc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            background: #f9fafb;
            border-radius: 0.625rem;
            padding: 0.5rem 0.75rem;
        }
        html.dark .rmc-row {
            background: rgba(255, 255, 255, 0.04);
        }
        .rmc-row .name {
            font-size: 0.875rem;
            font-weight: 600;
        }
        .rmc-field-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-500);
            margin-bottom: 0.375rem;
        }
        html.dark .rmc-field-label {
            color: var(--gray-400);
        }
        /* Filament's .fi-input CSS only stretches <input> elements —
           <select> (see the chair field) and <textarea> (introduction,
           discussion notes, closing statement) are left at their native
           browser default size (a few characters wide) unless forced
           full-width here. */
        .rmc-section textarea.fi-input {
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5rem;
            box-sizing: border-box;
            resize: vertical;
        }
        .rmc-vote-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }
        .rmc-vote-table th {
            text-align: center;
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--gray-500);
            padding: 0.25rem 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .rmc-vote-table th:first-child {
            text-align: start;
        }
        .rmc-vote-table td {
            padding: 0.25rem 0.5rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
            line-height: 1.3;
        }
        .rmc-vote-table .rmc-vote-position {
            color: var(--gray-500);
            font-size: 0.6875rem;
        }
        .rmc-vote-table tfoot td {
            border-bottom: none;
            border-top: 1px solid var(--gray-200);
            font-weight: 700;
            background: var(--gray-50);
        }
        .rmc-vote-cell {
            width: 3.5rem;
            text-align: center;
            cursor: pointer;
            font-size: 0.9375rem;
            user-select: none;
        }
        .rmc-vote-cell:hover {
            background: var(--gray-50);
        }
        .rmc-vote-cell-yes.rmc-vote-selected {
            background: rgba(14, 122, 130, 0.12);
            color: #0E7A82;
            font-weight: 700;
        }
        .rmc-vote-cell-no.rmc-vote-selected {
            background: rgba(220, 38, 38, 0.1);
            color: #DC2626;
            font-weight: 700;
        }
        .rmc-ai-btn {
            background: linear-gradient(135deg, rgba(184, 147, 74, 0.16), rgba(14, 122, 130, 0.12)) !important;
            color: #8a6a2f !important;
            border: 1px solid rgba(184, 147, 74, 0.4) !important;
            box-shadow: none !important;
        }
        .rmc-ai-btn:hover {
            background: linear-gradient(135deg, rgba(184, 147, 74, 0.24), rgba(14, 122, 130, 0.18)) !important;
        }
        html.dark .rmc-ai-btn {
            background: linear-gradient(135deg, rgba(184, 147, 74, 0.28), rgba(14, 122, 130, 0.2)) !important;
            color: #e8cf9a !important;
            border-color: rgba(184, 147, 74, 0.5) !important;
        }
        html.dark .rmc-ai-btn:hover {
            background: linear-gradient(135deg, rgba(184, 147, 74, 0.36), rgba(14, 122, 130, 0.26)) !important;
        }
    </style>

    @php
        $meeting = $this->getMeeting();
        $minutes = $this->getMinutes();
        $canRecord = $this->canRecord();
        $canEditComments = $this->canEditComments();
        $canApprove = $this->canApprove();
        $votingMembers = $this->votingMembers();
    @endphp

    @if (! $minutes)
        <x-filament::section class="rmc-section">
            @if ($this->canStart())
                <p style="margin-bottom: 1rem;">{{ __('bureau.minutes.actions.start') }}?</p>
                <x-filament::button wire:click="startMinutes">
                    {{ __('bureau.minutes.actions.start') }}
                </x-filament::button>
            @else
                <p>—</p>
            @endif
        </x-filament::section>
    @else
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            {{-- Status / audio header --}}
            <x-filament::section class="rmc-section">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <x-filament::badge :color="$minutes->status->getColor()">
                            {{ $minutes->status->getLabel() }}
                        </x-filament::badge>
                    </div>

                    @if ($canRecord)
                        <div
                            x-data="{
                                recording: false,
                                mediaRecorder: null,
                                chunks: [],
                                minutesId: {{ $minutes->id }},
                                async start() {
                                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                                    this.chunks = [];
                                    this.mediaRecorder = new MediaRecorder(stream);
                                    this.mediaRecorder.ondataavailable = (e) => this.chunks.push(e.data);
                                    this.mediaRecorder.onstop = () => this.upload();
                                    this.mediaRecorder.start();
                                    this.recording = true;
                                },
                                stop() {
                                    this.mediaRecorder?.stop();
                                    this.recording = false;
                                },
                                async upload() {
                                    const blob = new Blob(this.chunks, { type: 'audio/webm' });
                                    const form = new FormData();
                                    form.append('audio', blob, 'recording.webm');
                                    await fetch(`/bureau/minutes/${this.minutesId}/audio`, {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
                                        body: form,
                                    });
                                },
                            }"
                        >
                            <x-filament::button x-show="! recording" x-on:click="start" color="gray" icon="heroicon-o-microphone">
                                {{ __('bureau.minutes.actions.start_recording') }}
                            </x-filament::button>
                            <x-filament::button x-show="recording" x-cloak x-on:click="stop" color="danger" icon="heroicon-o-stop-circle">
                                {{ __('bureau.minutes.actions.stop_recording') }}
                            </x-filament::button>
                        </div>
                    @elseif ($minutes->hasAudio())
                        <span style="color: var(--gray-500); font-size: 0.875rem;">🎙 {{ __('bureau.minutes.field.audio') }}</span>
                    @endif
                </div>
            </x-filament::section>

            {{-- Card 1: Meeting info --}}
            <x-filament::section class="rmc-section" :heading="__('bureau.minutes.field.meeting_info_heading')">
                <div class="rmc-grid">
                    <div>
                        <label class="rmc-field-label">{{ __('bureau.minutes.field.chaired_by') }}</label>
                        <x-filament::input.wrapper>
                            <select class="fi-input" style="width: 100%; height: 2.25rem; padding: 0.375rem 0.75rem; font-size: 0.875rem; line-height: 1.5rem;" wire:model="chairId" @disabled(! $canEditComments)>
                                <option value="">—</option>
                                @foreach ($minutes->councilAttendance as $member)
                                    <option value="{{ $member->id }}">{{ $member->name_dv ?? $member->name }}</option>
                                @endforeach
                            </select>
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="rmc-field-label">{{ __('bureau.minutes.field.start_date') }}</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <div style="flex: 0 0 4.5rem;">
                                <x-filament::input.wrapper>
                                    <input
                                        type="number" min="1" max="31"
                                        class="fi-input"
                                        style="width: 100%; text-align: center;"
                                        wire:model="startDay"
                                        @disabled(! $canEditComments)
                                    />
                                </x-filament::input.wrapper>
                            </div>
                            <div style="flex: 1;">
                                <x-filament::input.wrapper>
                                    <select
                                        class="fi-input"
                                        style="width: 100%; height: 2.25rem; padding: 0.375rem 0.75rem; font-size: 0.875rem; line-height: 1.5rem;"
                                        wire:model="startMonth"
                                        @disabled(! $canEditComments)
                                    >
                                        @foreach (\App\Support\DhivehiDate::MONTHS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </x-filament::input.wrapper>
                            </div>
                            <div style="flex: 0 0 5.5rem;">
                                <x-filament::input.wrapper>
                                    <input
                                        type="number"
                                        class="fi-input"
                                        style="width: 100%; text-align: center;"
                                        wire:model="startYear"
                                        @disabled(! $canEditComments)
                                    />
                                </x-filament::input.wrapper>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="rmc-field-label">{{ __('bureau.minutes.field.start_time') }}</label>
                        <x-filament::input.wrapper>
                            <input type="time" class="fi-input" wire:model="startTime" @disabled(! $canEditComments) />
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="rmc-field-label">{{ __('bureau.minutes.field.break_started_at') }}</label>
                        <x-filament::input.wrapper>
                            <input type="time" class="fi-input" wire:model="breakStartTime" @disabled(! $canEditComments) />
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="rmc-field-label">{{ __('bureau.minutes.field.break_ended_at') }}</label>
                        <x-filament::input.wrapper>
                            <input type="time" class="fi-input" wire:model="breakEndTime" @disabled(! $canEditComments) />
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="rmc-field-label">{{ __('bureau.minutes.field.ended_at') }}</label>
                        <x-filament::input.wrapper>
                            <input type="time" class="fi-input" wire:model="endTime" @disabled(! $canEditComments) />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                @if ($canEditComments && ! $canRecord)
                    <div style="margin-top: 0.75rem;">
                        <x-filament::button size="sm" wire:click="saveMeetingInfo">
                            {{ __('bureau.minutes.actions.save_meeting_info') }}
                        </x-filament::button>
                    </div>
                @endif
            </x-filament::section>

            {{-- Card 2: Attendance --}}
            <x-filament::section class="rmc-section" :heading="__('bureau.minutes.field.attendance_card_heading')">
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">

                    {{-- Council --}}
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 700; margin-bottom: 0.5rem;">
                            {{ __('bureau.minutes.attendance_group.council') }}
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            @foreach ($minutes->councilAttendance as $member)
                                @php
                                    $time = $member->pivot->attended_at
                                        ? \Illuminate\Support\Carbon::parse($member->pivot->attended_at)->format('H:i')
                                        : $meeting->scheduled_at->format('H:i');
                                @endphp
                                <div class="rmc-row">
                                    <span class="name">{{ $member->name_dv ?? $member->name }}</span>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <select
                                            class="fi-input"
                                            wire:change="updateCouncilAttendanceStatus({{ $member->id }}, $event.target.value)"
                                            @disabled(! $canEditComments)
                                        >
                                            @foreach (\App\Enums\BureauAttendanceStatus::cases() as $status)
                                                <option value="{{ $status->value }}" @selected($member->pivot->status === $status->value)>
                                                    {{ $status->getLabel() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input
                                            type="time"
                                            class="fi-input"
                                            value="{{ $time }}"
                                            wire:change="updateCouncilAttendanceTime({{ $member->id }}, $event.target.value)"
                                            @disabled(! $canEditComments)
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Secretariat --}}
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 700; margin-bottom: 0.5rem;">
                            {{ __('bureau.minutes.attendance_group.secretariat') }}
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            @foreach ($minutes->secretariatAttendance as $member)
                                <div class="rmc-row">
                                    <span class="name">{{ $member->name_dv ?? $member->name }}</span>
                                    <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem;">
                                        <input
                                            type="checkbox"
                                            @checked($member->pivot->status === \App\Enums\BureauAttendanceStatus::Present->value)
                                            wire:change="updateSecretariatAttendance({{ $member->id }}, $event.target.checked ? '{{ \App\Enums\BureauAttendanceStatus::Present->value }}' : '{{ \App\Enums\BureauAttendanceStatus::Absent->value }}')"
                                            @disabled(! $canEditComments)
                                        />
                                        {{ __('bureau.minutes.attendance_status.present') }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Listeners --}}
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 700; margin-bottom: 0.5rem;">
                            {{ __('bureau.minutes.field.listeners') }}
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            @foreach ($minutes->listeners as $listener)
                                <div class="rmc-row">
                                    <span class="name">{{ $listener->name }}@if ($listener->address) — {{ $listener->address }} @endif</span>
                                    @if ($canEditComments)
                                        <x-filament::icon-button
                                            icon="heroicon-o-x-mark"
                                            size="sm"
                                            color="danger"
                                            wire:click="removeListener({{ $listener->id }})"
                                        />
                                    @endif
                                </div>
                            @endforeach

                            @if ($canEditComments)
                                <div style="display: flex; gap: 0.5rem; border: 1px dashed var(--gray-300); border-radius: 0.5rem; padding: 0.75rem;">
                                    <x-filament::input.wrapper>
                                        <input type="text" class="fi-input" placeholder="{{ __('bureau.minutes.field.listener_name') }}" wire:model="listenerForm.name" />
                                    </x-filament::input.wrapper>
                                    <x-filament::input.wrapper>
                                        <input type="text" class="fi-input" placeholder="{{ __('bureau.minutes.field.listener_address') }}" wire:model="listenerForm.address" />
                                    </x-filament::input.wrapper>
                                    <x-filament::button size="sm" wire:click="addListener">
                                        {{ __('bureau.agenda.actions.add') }}
                                    </x-filament::button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </x-filament::section>

            {{-- Card 3: Introduction --}}
            <x-filament::section class="rmc-section" :heading="__('bureau.minutes.field.introduction')">
                @if ($canEditComments)
                    <x-filament::input.wrapper>
                        <textarea class="fi-input" rows="4" wire:model="introduction"></textarea>
                    </x-filament::input.wrapper>
                    <div style="margin-top: 0.5rem;">
                        <x-filament::button size="sm" wire:click="saveIntroduction">
                            {{ __('bureau.minutes.actions.save_introduction') }}
                        </x-filament::button>
                    </div>
                @else
                    <p style="white-space: pre-line;">{{ $minutes->introduction ?: \App\Models\BureauMeetingMinutes::defaultIntroduction($meeting) }}</p>
                @endif
            </x-filament::section>

            {{-- Card 4: Agenda list --}}
            <x-filament::section class="rmc-section" :heading="__('bureau.meeting.field.agenda_items')">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    @foreach ($meeting->groupedAgendaItems() as $groupIndex => $group)
                        @continue (empty($group['items']))
                        <div>
                            <h3 style="font-size: 0.8125rem; font-weight: 700; margin-bottom: 0.25rem;">
                                {{ $groupIndex + 1 }}. {{ $group['heading'] }}
                            </h3>
                            @foreach ($group['items'] as $entry)
                                <div style="font-size: 0.8125rem; padding: 0.25rem 0;">
                                    {{ $entry['number'] }} — {{ $entry['item']->details }}
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <h2 style="font-family: 'Mv Galan Normal', 'Faruma', sans-serif; font-weight: 700; font-size: 1.125rem; text-align: center;">
                {{ __('bureau.minutes.field.discussions_heading') }}
            </h2>

            {{-- Card 5: one per agenda item — existing discussion/decision/vote mechanics, restyled.
                 Iterates the same grouped/numbered order as Card 4 (groupedAgendaItems():
                 Agenda Passing, then Minutes Passing, then President-, Councillor-, and
                 Bureau-Admin-created items) rather than raw creation order, so the cards
                 below always appear in that fixed sequence. --}}
            @foreach ($meeting->groupedAgendaItems() as $group)
                @foreach ($group['items'] as $entry)
                @php
                    $item = $entry['item'];
                    $itemComments = $minutes->comments->where('agenda_item_id', $item->id);
                    $itemDecisions = $minutes->decisionRequests->where('agenda_item_id', $item->id);
                    $activeDecision = $this->activeDecisionFor($item->id);
                    // Re-derived live rather than trusting a Skipped
                    // decision's own stale status — editing a passed
                    // sibling back to Failed (see editVotes()) un-settles
                    // the item, and a Skipped decision with no votes yet
                    // should become votable again once that happens.
                    $itemHasPassedDecision = $itemDecisions->contains('status', \App\Enums\BureauDecisionStatus::Passed);
                @endphp

                <x-filament::section class="rmc-section" :heading="$entry['number'].'. '.$item->details">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">

                        {{-- Discussion --}}
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            @foreach ($itemComments as $comment)
                                <div
                                    wire:key="comment-{{ $comment->id }}"
                                    x-data="{ editOpen: false }"
                                    x-on:minutes-comment-saved.window="if ($event.detail.commentId === {{ $comment->id }}) editOpen = false"
                                    style="border: 1px solid var(--gray-200); border-radius: 0.5rem; padding: 0.75rem;"
                                >
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; gap: 0.5rem;">
                                        <strong style="font-size: 0.875rem;">{{ $comment->speaker?->name_dv ?? $comment->speaker?->name ?? '—' }}</strong>

                                        @if ($canEditComments)
                                            <x-filament::button size="xs" color="gray" type="button" x-show="! editOpen" x-on:click="editOpen = true">
                                                {{ __('bureau.minutes.actions.edit_comment') }}
                                            </x-filament::button>
                                        @endif
                                    </div>

                                    @if ($canEditComments)
                                        <div x-show="! editOpen">
                                            <p style="font-size: 0.875rem;">{{ $comment->drafted_text ?: $comment->bullet_points }}</p>
                                        </div>

                                        <div x-show="editOpen" x-cloak>
                                            <div style="margin-bottom: 0.75rem;">
                                                <label class="rmc-field-label">{{ __('bureau.minutes.field.comment_text') }}</label>
                                                <x-filament::input.wrapper>
                                                    <textarea class="fi-input" rows="4" wire:model="commentEditForms.{{ $comment->id }}"></textarea>
                                                </x-filament::input.wrapper>
                                            </div>

                                            <div x-data="{ aiOpen: false }" style="margin-bottom: 0.75rem;">
                                                <div x-show="! aiOpen">
                                                    <x-filament::button size="sm" class="rmc-ai-btn" icon="heroicon-o-sparkles" type="button" x-on:click="aiOpen = true">
                                                        {{ __('bureau.minutes.actions.generate_ai_text_prompt') }}
                                                    </x-filament::button>
                                                </div>
                                                <div x-show="aiOpen" x-cloak>
                                                    <label class="rmc-field-label">{{ __('bureau.minutes.field.ai_points') }}</label>
                                                    <x-filament::input.wrapper>
                                                        <textarea class="fi-input" rows="2" wire:model="commentAiForms.{{ $comment->id }}"></textarea>
                                                    </x-filament::input.wrapper>
                                                    <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                                                        <x-filament::button size="sm" class="rmc-ai-btn" icon="heroicon-o-sparkles" wire:click="generateAiDraftForComment({{ $comment->id }})">
                                                            {{ __('bureau.minutes.actions.generate_ai_text') }}
                                                        </x-filament::button>
                                                        <x-filament::button size="sm" color="danger" type="button" x-on:click="aiOpen = false">
                                                            {{ __('bureau.minutes.actions.cancel_ai_text') }}
                                                        </x-filament::button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                <x-filament::button size="sm" wire:click="saveComment({{ $comment->id }})">
                                                    {{ __('bureau.minutes.actions.save_comment') }}
                                                </x-filament::button>
                                                <x-filament::button size="sm" color="danger" type="button" x-on:click="editOpen = false">
                                                    {{ __('bureau.minutes.actions.cancel_edit_comment') }}
                                                </x-filament::button>
                                            </div>
                                        </div>

                                        {{-- Every speaker gets their own decision-request field, right
                                             where their remarks are — not one shared field at the bottom.
                                             Open through Review too — a Bureau Admin who ended the
                                             meeting early on paper notes still needs to add decisions
                                             they didn't get to record live (see addDecisionForComment()).
                                             Independent of editOpen — adding a decision doesn't require
                                             opening the text editor. --}}
                                        <div style="margin-top: 0.75rem; border-top: 1px dashed var(--gray-200); padding-top: 0.75rem;">
                                            <label class="rmc-field-label">{{ __('bureau.minutes.field.decision_text') }}</label>
                                            <x-filament::input.wrapper>
                                                <textarea class="fi-input" rows="2" wire:model="commentDecisionForms.{{ $comment->id }}"></textarea>
                                            </x-filament::input.wrapper>
                                            <div style="margin-top: 0.5rem;">
                                                <x-filament::button size="sm" color="warning" wire:click="addDecisionForComment({{ $comment->id }})">
                                                    {{ __('bureau.minutes.actions.add_decision') }}
                                                </x-filament::button>
                                            </div>
                                        </div>
                                    @else
                                        <p style="font-size: 0.875rem;">{{ $comment->drafted_text ?: $comment->bullet_points }}</p>
                                    @endif
                                </div>
                            @endforeach

                            @if ($canEditComments)
                                <div
                                    x-data="{ addOpen: false }"
                                    x-on:minutes-comment-added.window="if ($event.detail.agendaItemId === {{ $item->id }}) addOpen = false"
                                >
                                    <div x-show="! addOpen">
                                        <x-filament::button size="sm" type="button" x-on:click="addOpen = true">
                                            {{ __('bureau.minutes.actions.add_comment') }}
                                        </x-filament::button>
                                    </div>

                                    <div x-show="addOpen" x-cloak style="border: 1px dashed var(--gray-300); border-radius: 0.5rem; padding: 0.75rem; margin-top: 0.5rem;">
                                        <div style="margin-bottom: {{ filled($commentForms[$item->id]['speaker_id'] ?? null) ? '0.75rem' : '0' }};">
                                            <label class="rmc-field-label">{{ __('bureau.minutes.field.speaker') }}</label>
                                            <x-filament::input.wrapper>
                                                <select
                                                    class="fi-input"
                                                    style="width: 100%; height: 2.25rem; padding: 0.375rem 0.75rem; font-size: 0.875rem; line-height: 1.5rem;"
                                                    wire:model.live="commentForms.{{ $item->id }}.speaker_id"
                                                >
                                                    <option value="">—</option>
                                                    @foreach ($this->eligibleSpeakers() as $attendee)
                                                        <option value="{{ $attendee->id }}">{{ $attendee->name_dv ?? $attendee->name }}</option>
                                                    @endforeach
                                                </select>
                                            </x-filament::input.wrapper>
                                        </div>

                                        @if (filled($commentForms[$item->id]['speaker_id'] ?? null))
                                            <div style="margin-bottom: 0.75rem;">
                                                <label class="rmc-field-label">{{ __('bureau.minutes.field.comment_text') }}</label>
                                                <x-filament::input.wrapper>
                                                    <textarea class="fi-input" rows="5" wire:model="commentForms.{{ $item->id }}.bullet_points"></textarea>
                                                </x-filament::input.wrapper>
                                            </div>

                                            <div x-data="{ aiOpen: false }" style="margin-bottom: 0.75rem;">
                                                <div x-show="! aiOpen">
                                                    <x-filament::button size="sm" class="rmc-ai-btn" icon="heroicon-o-sparkles" type="button" x-on:click="aiOpen = true">
                                                        {{ __('bureau.minutes.actions.generate_ai_text_prompt') }}
                                                    </x-filament::button>
                                                </div>
                                                <div x-show="aiOpen" x-cloak>
                                                    <label class="rmc-field-label">{{ __('bureau.minutes.field.ai_points') }}</label>
                                                    <x-filament::input.wrapper>
                                                        <textarea class="fi-input" rows="2" wire:model="aiDraftForms.{{ $item->id }}"></textarea>
                                                    </x-filament::input.wrapper>
                                                    <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                                                        <x-filament::button size="sm" class="rmc-ai-btn" icon="heroicon-o-sparkles" wire:click="generateAiDraft({{ $item->id }})">
                                                            {{ __('bureau.minutes.actions.generate_ai_text') }}
                                                        </x-filament::button>
                                                        <x-filament::button size="sm" color="danger" type="button" x-on:click="aiOpen = false">
                                                            {{ __('bureau.minutes.actions.cancel_ai_text') }}
                                                        </x-filament::button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                                                <x-filament::button size="sm" wire:click="addComment({{ $item->id }})">
                                                    {{ __('bureau.minutes.actions.save_comment') }}
                                                </x-filament::button>
                                                <x-filament::button size="sm" color="danger" type="button" x-on:click="addOpen = false">
                                                    {{ __('bureau.minutes.actions.cancel_add_comment') }}
                                                </x-filament::button>
                                            </div>

                                            {{-- Raises a decision request straight from the currently
                                                 selected speaker, without needing to save a comment first.
                                                 Open through Review too — see addDecisionForComposer()'s
                                                 own comment. --}}
                                            <div style="border-top: 1px dashed var(--gray-200); padding-top: 0.75rem;">
                                                <label class="rmc-field-label">{{ __('bureau.minutes.field.decision_text') }}</label>
                                                <x-filament::input.wrapper>
                                                    <textarea class="fi-input" rows="2" wire:model="commentForms.{{ $item->id }}.decision_text"></textarea>
                                                </x-filament::input.wrapper>
                                                <div style="margin-top: 0.5rem;">
                                                    <x-filament::button size="sm" color="warning" wire:click="addDecisionForComposer({{ $item->id }})">
                                                        {{ __('bureau.minutes.actions.add_decision') }}
                                                    </x-filament::button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Decision requests — saved below all comments for this item,
                             numbered from 1st raised regardless of which speaker
                             proposed each one. --}}
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <h4 style="font-size: 0.8125rem; font-weight: 700;">{{ __('bureau.minutes.field.decision_requests_heading') }}</h4>

                            @forelse ($itemDecisions as $decisionIndex => $decision)
                                <div style="border: 1px solid var(--gray-200); border-radius: 0.5rem; padding: 0.75rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-size: 0.875rem;">{{ $decisionIndex + 1 }}. {{ $decision->text }}</span>
                                        <x-filament::badge :color="$decision->status->getColor()">
                                            {{ $decision->status->getLabel() }}
                                        </x-filament::badge>
                                    </div>

                                    @if ($decision->proposer)
                                        <p style="color: var(--gray-500); font-size: 0.75rem; margin-top: 0.25rem;">
                                            {{ __('bureau.minutes.field.proposed_by') }}: {{ $decision->proposer->name_dv ?? $decision->proposer->name }}
                                        </p>
                                    @endif

                                    @if ($decision->status === \App\Enums\BureauDecisionStatus::Skipped && $itemHasPassedDecision)
                                        <p style="color: #a15c00; font-size: 0.8125rem; margin-top: 0.375rem;">
                                            {{ __('bureau.minutes.decision_locked') }}
                                        </p>
                                    @endif

                                    @php
                                        $isEditingThisDecision = $editingDecisionId === $decision->id;
                                        $isActivePending = $activeDecision && $activeDecision->id === $decision->id;
                                        // A Skipped decision that was never actually voted on (it was
                                        // auto-skipped when a sibling passed) — if that sibling no
                                        // longer shows as Passed (see $itemHasPassedDecision above,
                                        // recomputed live), this one can be reopened for voting too.
                                        $isReopenable = $decision->status === \App\Enums\BureauDecisionStatus::Skipped
                                            && $decision->votes->isEmpty()
                                            && ! $itemHasPassedDecision;
                                    @endphp

                                    @if ($decision->votes->isNotEmpty() && ! $isEditingThisDecision)
                                        @php
                                            $yesTotal = $decision->votes->where('vote', \App\Enums\BureauVoteChoice::Yes)->count();
                                            $noTotal = $decision->votes->where('vote', \App\Enums\BureauVoteChoice::No)->count();
                                        @endphp
                                        <div style="margin-top: 0.5rem; overflow-x: auto;">
                                            <table class="rmc-vote-table">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('bureau.meeting.field.participant_name') }}</th>
                                                        <th>{{ __('bureau.minutes.vote.yes') }}</th>
                                                        <th>{{ __('bureau.minutes.vote.no') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($decision->votes as $vote)
                                                        @php $position = $vote->voter?->position_dv ?: $vote->voter?->position; @endphp
                                                        <tr>
                                                            <td>
                                                                {{ $vote->voter?->name_dv ?? $vote->voter?->name }}
                                                                @if ($position)
                                                                    <span class="rmc-vote-position">— {{ $position }}</span>
                                                                @endif
                                                            </td>
                                                            <td class="rmc-vote-cell rmc-vote-cell-yes @if ($vote->vote === \App\Enums\BureauVoteChoice::Yes) rmc-vote-selected @endif">
                                                                @if ($vote->vote === \App\Enums\BureauVoteChoice::Yes) ✓ @endif
                                                            </td>
                                                            <td class="rmc-vote-cell rmc-vote-cell-no @if ($vote->vote === \App\Enums\BureauVoteChoice::No) rmc-vote-selected @endif">
                                                                @if ($vote->vote === \App\Enums\BureauVoteChoice::No) ✗ @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td>{{ __('bureau.minutes.field.vote_total') }}</td>
                                                        <td class="rmc-vote-cell">{{ $yesTotal }}</td>
                                                        <td class="rmc-vote-cell">{{ $noTotal }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        @if ($canEditComments)
                                            <div style="margin-top: 0.5rem;">
                                                <x-filament::button size="xs" color="gray" wire:click="editVotes({{ $decision->id }})">
                                                    {{ __('bureau.minutes.actions.edit_votes') }}
                                                </x-filament::button>
                                            </div>
                                        @endif
                                    @endif

                                    @if ($isReopenable && $canEditComments && ! $isEditingThisDecision)
                                        <div style="margin-top: 0.5rem;">
                                            <x-filament::button size="xs" color="warning" wire:click="editVotes({{ $decision->id }})">
                                                {{ __('bureau.minutes.actions.edit_votes') }}
                                            </x-filament::button>
                                        </div>
                                    @endif

                                    @if ($canEditComments && ($isActivePending || $isEditingThisDecision))
                                        <div style="margin-top: 0.75rem; overflow-x: auto;">
                                            <table class="rmc-vote-table">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('bureau.meeting.field.participant_name') }}</th>
                                                        <th>{{ __('bureau.minutes.vote.yes') }}</th>
                                                        <th>{{ __('bureau.minutes.vote.no') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($votingMembers as $member)
                                                        @php
                                                            $currentVote = $voteForms[$decision->id][$member->id] ?? null;
                                                            $position = $member->position_dv ?: $member->position;
                                                        @endphp
                                                        <tr>
                                                            <td>
                                                                {{ $member->name_dv ?? $member->name }}
                                                                @if ($position)
                                                                    <span class="rmc-vote-position">— {{ $position }}</span>
                                                                @endif
                                                            </td>
                                                            <td
                                                                class="rmc-vote-cell rmc-vote-cell-yes @if ($currentVote === 'yes') rmc-vote-selected @endif"
                                                                wire:click="$set('voteForms.{{ $decision->id }}.{{ $member->id }}', 'yes')"
                                                            >
                                                                @if ($currentVote === 'yes') ✓ @endif
                                                            </td>
                                                            <td
                                                                class="rmc-vote-cell rmc-vote-cell-no @if ($currentVote === 'no') rmc-vote-selected @endif"
                                                                wire:click="$set('voteForms.{{ $decision->id }}.{{ $member->id }}', 'no')"
                                                            >
                                                                @if ($currentVote === 'no') ✗ @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div style="margin-top: 0.75rem;">
                                            <x-filament::button size="sm" wire:click="recordVotes({{ $decision->id }})">
                                                {{ __('bureau.minutes.actions.record_votes') }}
                                            </x-filament::button>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p style="color: var(--gray-500); font-size: 0.8125rem;">—</p>
                            @endforelse
                        </div>
                    </div>
                </x-filament::section>
                @endforeach
            @endforeach

            {{-- Card 6: Closing notes --}}
            <x-filament::section class="rmc-section" :heading="__('bureau.minutes.field.closing_card_heading')">
                @if ($canEditComments)
                    <x-filament::input.wrapper>
                        <textarea class="fi-input" rows="4" wire:model="closingNotes"></textarea>
                    </x-filament::input.wrapper>
                    <div style="margin-top: 0.75rem;">
                        @if ($canRecord)
                            <x-filament::button wire:click="endMinutes">
                                {{ __('bureau.minutes.actions.end') }}
                            </x-filament::button>
                        @else
                            <x-filament::button size="sm" wire:click="saveClosingNotes">
                                {{ __('bureau.minutes.actions.save_closing_notes') }}
                            </x-filament::button>
                        @endif
                    </div>
                @else
                    <p style="white-space: pre-line;">{{ $minutes->closing_notes ?: \App\Models\BureauMeetingMinutes::defaultClosingNotes($meeting) }}</p>
                @endif
            </x-filament::section>

            @if ($canApprove)
                <x-filament::section class="rmc-section">
                    <x-filament::button wire:click="approveMinutes" color="primary">
                        {{ __('bureau.minutes.actions.approve') }}
                    </x-filament::button>
                </x-filament::section>
            @endif

            @if ($minutes->document_id)
                <x-filament::section class="rmc-section">
                    <a href="{{ \App\Filament\Resources\DocumentSigning\Documents\DocumentResource::getUrl('view', ['record' => $minutes->document_id], panel: 'admin') }}">
                        {{ __('bureau.minutes.status.'.$minutes->status->value) }} →
                    </a>
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
