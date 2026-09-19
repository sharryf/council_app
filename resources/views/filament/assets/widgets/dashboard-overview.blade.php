@php
    $cards = $this->getCards();
    $statusRows = $this->getStatusRows();
@endphp
<x-filament-widgets::widget>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: stretch;">
        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem; align-content: start;">
            @foreach ($cards as $card)
                <a href="{{ $card['url'] }}" style="text-decoration: none; color: inherit;">
                    <x-filament::section compact>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div
                                style="
                                    flex-shrink: 0;
                                    width: 3rem;
                                    height: 3rem;
                                    border-radius: 9999px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    background: {{ $card['count'] > 0 ? 'var(--primary-500)' : 'var(--gray-200)' }};
                                "
                            >
                                <x-filament::icon
                                    :icon="$card['icon']"
                                    style="width: 1.5rem; height: 1.5rem; color: {{ $card['count'] > 0 ? '#fff' : 'var(--gray-500)' }};"
                                />
                            </div>
                            <div>
                                <div style="font-size: 0.8125rem; color: var(--gray-500); font-weight: 500;">
                                    {{ $card['label'] }}
                                </div>
                                <div style="font-size: 1.5rem; font-weight: 700; line-height: 1.2; color: {{ $card['count'] > 0 ? 'var(--primary-600)' : 'inherit' }};">
                                    {{ $card['count'] }}
                                </div>
                            </div>
                        </div>
                    </x-filament::section>
                </a>
            @endforeach
        </div>

        <x-filament::section style="height: 100%; display: flex; flex-direction: column;">
            <x-slot name="heading">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-archive-box" style="width: 1.125rem; height: 1.125rem; color: var(--gray-500);" />
                    Assets by Status
                </div>
            </x-slot>

            <div style="flex: 1; display: flex; flex-direction: column; gap: 0.25rem;">
                @foreach ($statusRows as $row)
                    <a
                        href="{{ $row['url'] }}"
                        style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; font-size: 0.875rem; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-100); text-decoration: none; color: inherit;"
                    >
                        <span style="{{ $row['label'] === 'Total Assets' ? 'font-weight: 600;' : '' }}">{{ $row['label'] }}</span>
                        <x-filament::badge :color="$row['color']">{{ $row['count'] }}</x-filament::badge>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
