@php
    $cards = $this->getCards();
    $showLowStock = $this->showLowStock();
    $lowStockItems = $showLowStock ? $this->getLowStockItems() : [];
    $lowStockReportUrl = $showLowStock ? $this->getLowStockReportUrl() : null;
@endphp
<x-filament-widgets::widget>
    <div style="display: grid; grid-template-columns: {{ $showLowStock ? '1fr 1fr' : '1fr' }}; gap: 1rem; align-items: stretch;">
        <div style="display: {{ $showLowStock ? 'flex' : 'grid' }}; flex-direction: column; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            @foreach ($cards as $card)
                @if ($card['url'])
                    <a href="{{ $card['url'] }}" style="text-decoration: none; color: inherit;">
                @else
                    <div>
                @endif
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

                        @if ($card['links'])
                            <div style="display: flex; gap: 1rem; margin-top: 0.625rem; padding-top: 0.625rem; border-top: 1px solid var(--gray-100);">
                                @foreach ($card['links'] as $link)
                                    <a
                                        href="{{ $link['url'] }}"
                                        style="text-decoration: none; font-size: 0.8125rem; color: {{ $link['count'] > 0 ? 'var(--primary-600)' : 'var(--gray-500)' }}; font-weight: 500;"
                                    >
                                        {{ $link['label'] }} ({{ $link['count'] }})
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </x-filament::section>
                @if ($card['url'])
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>

        @if ($showLowStock)
            <x-filament::section style="height: 100%; display: flex; flex-direction: column;">
                <x-slot name="heading">
                    @if ($lowStockReportUrl)
                        <a href="{{ $lowStockReportUrl }}" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; color: inherit;">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 1.125rem; height: 1.125rem; color: var(--warning-500);" />
                            Low Stock
                        </a>
                    @else
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 1.125rem; height: 1.125rem; color: var(--warning-500);" />
                            Low Stock
                        </div>
                    @endif
                </x-slot>

                <div style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 0.25rem;">
                    @forelse ($lowStockItems as $item)
                        <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.875rem; padding: 0.375rem 0; border-bottom: 1px solid var(--gray-100);">
                            <span>{{ $item['name'] }}</span>
                            <span style="font-weight: 600; color: var(--warning-600); flex-shrink: 0;">{{ $item['qty'] }}</span>
                        </div>
                    @empty
                        <p style="color: var(--gray-500); font-size: 0.875rem; margin: 0;">No items below reorder level.</p>
                    @endforelse
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-widgets::widget>
