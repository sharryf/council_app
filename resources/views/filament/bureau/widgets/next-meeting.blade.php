@php($meeting = $this->getNextMeeting())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('bureau.meeting.dashboard.next_meeting') }}
        </x-slot>

        @if ($meeting)
            <a href="{{ \App\Filament\Bureau\Resources\Meetings\MeetingResource::getUrl('view', ['record' => $meeting]) }}" style="text-decoration: none; color: inherit;">
                <div style="font-weight: 700; font-size: 1.0625rem;">{{ $meeting->name }}</div>
                <div style="color: var(--gray-500); font-size: 0.875rem; margin-top: 0.25rem;">
                    {{ $meeting->type_label }}
                    @if ($meeting->type)
                        —
                    @endif
                    {{ $meeting->scheduled_at->format('Y-m-d H:i') }}
                </div>
            </a>
        @else
            <p style="color: var(--gray-500); font-size: 0.875rem; margin: 0;">
                {{ __('bureau.meeting.dashboard.no_upcoming_meeting') }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
