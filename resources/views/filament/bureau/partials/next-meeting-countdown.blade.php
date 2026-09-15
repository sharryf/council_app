{{--
    Rendered above the Meetings list page's own H1/breadcrumb via a
    PAGE_START render hook scoped to ListMeetings (see
    BureauPanelProvider) — Filament's normal getHeaderWidgets() renders
    inside .fi-page-content, which sits below the heading, not above it.
--}}
@php
    $meeting = \App\Models\BureauMeeting::nextUpcoming();
@endphp

<style>
    .bnc-card {
        display: block;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        text-decoration: none;
        color: inherit;
        background: linear-gradient(135deg, #0E7A82, #0a5b61);
        box-shadow: 0 8px 20px -8px rgba(14, 122, 130, 0.5);
    }
    .bnc-card:hover {
        background: linear-gradient(135deg, #0f868f, #0b6a71);
    }
    .bnc-empty {
        border-radius: 1rem;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        background: var(--gray-50);
        border: 1px dashed var(--gray-300);
        color: var(--gray-500);
        font-size: 0.875rem;
    }
    html.dark .bnc-empty {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.1);
    }
    .bnc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .bnc-label {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: rgba(255, 255, 255, 0.75);
        margin-bottom: 0.25rem;
    }
    .bnc-name {
        font-family: 'Mv Galan Normal', 'Faruma', sans-serif;
        font-weight: 700;
        font-size: 1.1875rem;
        color: #fff;
        line-height: 1.5;
    }
    .bnc-meta {
        font-size: 0.8125rem;
        color: rgba(255, 255, 255, 0.85);
        margin-top: 0.25rem;
    }
    .bnc-countdown {
        display: flex;
        gap: 0.625rem;
    }
    .bnc-unit {
        min-width: 3.5rem;
        text-align: center;
        background: rgba(255, 255, 255, 0.14);
        border-radius: 0.625rem;
        padding: 0.5rem 0.375rem;
    }
    .bnc-unit-value {
        font-size: 1.375rem;
        font-weight: 700;
        color: #fff;
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
    }
    .bnc-unit-label {
        font-size: 0.6875rem;
        color: rgba(255, 255, 255, 0.75);
        margin-top: 0.125rem;
    }
    .bnc-started {
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
        background: rgba(255, 255, 255, 0.14);
        border-radius: 0.625rem;
        padding: 0.5rem 1rem;
    }
</style>

@if ($meeting)
    <a
        href="{{ \App\Filament\Bureau\Resources\Meetings\MeetingResource::getUrl('view', ['record' => $meeting]) }}"
        class="bnc-card"
        x-data="{
            target: new Date('{{ $meeting->scheduled_at->toIso8601String() }}').getTime(),
            days: 0, hours: 0, minutes: 0, seconds: 0, started: false,
            tick() {
                const diff = this.target - Date.now();
                if (diff <= 0) {
                    this.started = true;
                    this.days = this.hours = this.minutes = this.seconds = 0;
                    return;
                }
                this.started = false;
                this.days = Math.floor(diff / 86400000);
                this.hours = Math.floor((diff % 86400000) / 3600000);
                this.minutes = Math.floor((diff % 3600000) / 60000);
                this.seconds = Math.floor((diff % 60000) / 1000);
            },
        }"
        x-init="tick(); setInterval(() => tick(), 1000)"
    >
        <div class="bnc-row">
            <div>
                <div class="bnc-label">{{ __('bureau.meeting.dashboard.next_meeting') }}</div>
                <div class="bnc-name">{{ $meeting->name }}</div>
                <div class="bnc-meta">
                    {{ $meeting->type_label }}
                    @if ($meeting->place)
                        — {{ $meeting->place }}
                    @endif
                    — {!! \App\Support\DhivehiDate::html($meeting->scheduled_at) !!}
                </div>
            </div>

            <div>
                <div class="bnc-countdown" x-show="! started" x-cloak>
                    <div class="bnc-unit">
                        <div class="bnc-unit-value" x-text="days"></div>
                        <div class="bnc-unit-label">{{ __('bureau.meeting.dashboard.countdown_days') }}</div>
                    </div>
                    <div class="bnc-unit">
                        <div class="bnc-unit-value" x-text="hours"></div>
                        <div class="bnc-unit-label">{{ __('bureau.meeting.dashboard.countdown_hours') }}</div>
                    </div>
                    <div class="bnc-unit">
                        <div class="bnc-unit-value" x-text="minutes"></div>
                        <div class="bnc-unit-label">{{ __('bureau.meeting.dashboard.countdown_minutes') }}</div>
                    </div>
                    <div class="bnc-unit">
                        <div class="bnc-unit-value" x-text="seconds"></div>
                        <div class="bnc-unit-label">{{ __('bureau.meeting.dashboard.countdown_seconds') }}</div>
                    </div>
                </div>

                <div class="bnc-started" x-show="started" x-cloak>
                    {{ __('bureau.meeting.dashboard.countdown_started') }}
                </div>
            </div>
        </div>
    </a>
@else
    <div class="bnc-empty">
        {{ __('bureau.meeting.dashboard.no_upcoming_meeting') }}
    </div>
@endif
