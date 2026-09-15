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

        ul {
            margin: 0;
            padding-inline-start: 20px;
        }

        li {
            margin-bottom: 6px;
        }

        .agenda-group {
            margin-top: 20px;
        }

        .agenda-group-heading {
            font-family: 'Mv Galan Normal', 'Faruma', sans-serif;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .agenda-item {
            margin-bottom: 10px;
            font-size: 13px;
        }

        .agenda-item .number {
            font-weight: 700;
            margin-inline-end: 4px;
        }
    </style>
</head>
<body>
    <h1>{{ $meeting->name }}</h1>
    <p class="meta">
        {{ $meeting->type_label }}
        @if ($meeting->type)
            —
        @endif
        {{ $meeting->scheduled_at->format('Y-m-d H:i') }}
    </p>

    <h2>{{ __('bureau.meeting.pdf.attendees_heading') }}</h2>
    <ul>
        @foreach ($meeting->attendees as $attendee)
            <li>{{ $attendee->name }}</li>
        @endforeach
    </ul>

    <h2>{{ __('bureau.meeting.pdf.agenda_heading') }}</h2>
    @php $groups = $meeting->groupedAgendaItems(); @endphp
    @if (collect($groups)->every(fn ($group) => empty($group['items'])))
        <p>—</p>
    @else
        @foreach ($groups as $groupIndex => $group)
            @continue (empty($group['items']))
            <div class="agenda-group">
                <div class="agenda-group-heading">{{ $groupIndex + 1 }}. {{ $group['heading'] }}</div>
                @foreach ($group['items'] as $entry)
                    <div class="agenda-item">
                        <span class="number">{{ $entry['number'] }}</span>
                        <span>{{ $entry['item']->details }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</body>
</html>
