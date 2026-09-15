<!doctype html>
<html dir="rtl" lang="dv">
<head>
    <meta charset="utf-8">
    @include('bureau.pdf.partials.fonts')
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Faruma', sans-serif;
            margin: 0;
            padding: 40px;
            color: #1a1a1a;
        }

        h1 {
            font-family: 'Mv Galan Normal', 'Faruma', sans-serif;
            font-size: 24px;
            margin: 0 0 4px;
        }

        .meta {
            color: #555;
            font-size: 14px;
            margin-bottom: 24px;
        }

        h2 {
            font-family: 'Mv Galan Normal', 'Faruma', sans-serif;
            font-size: 16px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 6px;
            margin-top: 28px;
        }

        h3 {
            font-family: 'Mv Galan Normal', 'Faruma', sans-serif;
            font-size: 14px;
            margin: 18px 0 6px;
        }

        ul {
            margin: 0;
            padding-inline-start: 20px;
        }

        li {
            margin-bottom: 6px;
        }

        .comment {
            margin-bottom: 10px;
        }

        .comment .speaker {
            font-weight: 700;
            font-size: 13px;
        }

        .comment .text {
            font-size: 13px;
            margin-top: 2px;
        }

        .decision {
            font-size: 13px;
            margin-bottom: 8px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .decision .status {
            font-weight: 700;
        }

        .decision .votes {
            color: #555;
            font-size: 12px;
            margin-top: 4px;
        }

        .decision .locked {
            color: #a15c00;
            font-size: 12px;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <h1>{{ __('bureau.minutes.pdf.title') }}</h1>
    <p class="meta">
        {{ $meeting->name }}
        @if ($meeting->type)
            — {{ $meeting->type_label }}
        @endif
        — {{ $meeting->scheduled_at->format('Y-m-d H:i') }}
    </p>
    <p class="meta">
        {{ __('bureau.minutes.field.chaired_by') }}: {{ $minutes->chair?->name_dv ?? $minutes->chair?->name ?? '—' }}
        — {{ __('bureau.minutes.field.started_at') }}: {{ $minutes->started_at?->format('Y-m-d H:i') }}
        @if ($minutes->break_started_at)
            — {{ __('bureau.minutes.field.break_started_at') }}: {{ $minutes->break_started_at->format('H:i') }}
            — {{ __('bureau.minutes.field.break_ended_at') }}: {{ $minutes->break_ended_at?->format('H:i') }}
        @endif
        — {{ __('bureau.minutes.field.ended_at') }}: {{ $minutes->ended_at?->format('H:i') }}
    </p>

    <h2>{{ __('bureau.minutes.attendance_group.council') }}</h2>
    <ul>
        @foreach ($minutes->councilAttendance as $attendee)
            <li>{{ $attendee->name_dv ?? $attendee->name }} — {{ __('bureau.minutes.attendance_status.'.$attendee->pivot->status) }}</li>
        @endforeach
    </ul>

    <h2>{{ __('bureau.minutes.attendance_group.secretariat') }}</h2>
    <ul>
        @foreach ($minutes->secretariatAttendance as $attendee)
            <li>{{ $attendee->name_dv ?? $attendee->name }} — {{ __('bureau.minutes.attendance_status.'.$attendee->pivot->status) }}</li>
        @endforeach
    </ul>

    @if ($minutes->listeners->isNotEmpty())
        <h2>{{ __('bureau.minutes.field.listeners') }}</h2>
        <ul>
            @foreach ($minutes->listeners as $listener)
                <li>{{ $listener->name }}@if ($listener->address) — {{ $listener->address }} @endif</li>
            @endforeach
        </ul>
    @endif

    @if ($minutes->introduction)
        <h2>{{ __('bureau.minutes.pdf.introduction_heading') }}</h2>
        <p>{{ $minutes->introduction }}</p>
    @endif

    @foreach ($meeting->agendaItems as $index => $item)
        <h2>{{ $index + 1 }}. {{ $item->shortLabel() }}</h2>

        @php
            $itemComments = $minutes->comments->where('agenda_item_id', $item->id);
            $itemDecisions = $minutes->decisionRequests->where('agenda_item_id', $item->id);
        @endphp

        @foreach ($itemComments as $comment)
            <div class="comment">
                <div class="speaker">{{ $comment->speaker?->name ?? '—' }}</div>
                <div class="text">{{ $comment->drafted_text ?: $comment->bullet_points }}</div>
            </div>
        @endforeach

        @foreach ($itemDecisions as $decision)
            <div class="decision">
                <div>{{ $decision->text }}</div>
                <div class="status">{{ $decision->status->getLabel() }}</div>
                @if ($decision->status === \App\Enums\BureauDecisionStatus::Skipped)
                    <div class="locked">{{ __('bureau.minutes.decision_locked') }}</div>
                @endif
                <div class="votes">
                    @foreach ($decision->votes as $vote)
                        {{ $vote->voter?->name }} ({{ $vote->vote->getLabel() }}){{ ! $loop->last ? '، ' : '' }}
                    @endforeach
                </div>
            </div>
        @endforeach
    @endforeach

    @if ($minutes->closing_notes)
        <h2>{{ __('bureau.minutes.pdf.closing_heading') }}</h2>
        <p>{{ $minutes->closing_notes }}</p>
        <p class="meta">{{ $minutes->ended_at?->format('Y-m-d H:i') }}</p>
    @endif
</body>
</html>
