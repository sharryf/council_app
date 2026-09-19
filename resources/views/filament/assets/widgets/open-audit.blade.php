@php
    $sessions = $this->getSessions();
@endphp

{{-- A Livewire component must always render exactly one root element
     — even when there's nothing to show, this <div> has to exist (just
     empty), or Livewire throws RootTagMissingFromViewException. --}}
<div style="display: flex; flex-direction: column; gap: 0.75rem;">
    @foreach ($sessions as $session)
        @php $progress = $this->getProgress($session); @endphp
        <x-filament::section>
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <p style="font-size: 0.8125rem; color: var(--gray-500);">Audit in progress</p>
                    <a href="{{ $this->getSessionUrl($session) }}" style="font-size: 0.9375rem; font-weight: 700; color: #0E7A82;">
                        {{ $session->name }}
                    </a>
                    <span style="font-size: 0.8125rem; color: var(--gray-500);">
                        — {{ $progress['verified'] }} of {{ $progress['total'] }} verified
                    </span>
                </div>
                <x-filament::button tag="a" :href="$this->getSessionUrl($session)" size="sm" color="gray">
                    Continue
                </x-filament::button>
            </div>
        </x-filament::section>
    @endforeach
</div>
