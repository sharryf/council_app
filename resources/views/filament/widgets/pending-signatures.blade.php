{{--
    Blade's component-tag compiler parses a <x-... ...> tag's whole
    attribute list before @if/@endif directives are compiled, so a
    directive can't conditionally include/exclude attributes inside the
    tag itself (it breaks the parser). Every attribute below is always
    present; only its VALUE varies with $count, and filterToPending()
    itself no-ops when there's nothing to filter to.
--}}
@php($count = $this->getPendingCount())
<x-filament-widgets::widget>
    <x-filament::section
        compact
        wire:click="filterToPending"
        role="button"
        tabindex="0"
        x-on:keydown.enter="$wire.filterToPending()"
        style="{{ $count > 0 ? 'cursor: pointer;' : '' }}"
        data-clickable="{{ $count > 0 ? '1' : '' }}"
        x-on:mouseenter="if ($el.dataset.clickable) $el.style.background = 'rgba(255,255,255,0.04)'"
        x-on:mouseleave="$el.style.background = ''"
    >
        <div style="display: flex; align-items: center; gap: 1.25rem;">
            <div
                style="
                    flex-shrink: 0;
                    width: 4rem;
                    height: 4rem;
                    border-radius: 9999px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: {{ $count > 0 ? 'var(--primary-500)' : 'var(--gray-200)' }};
                    box-shadow: {{ $count > 0 ? '0 0 0 6px var(--primary-50)' : 'none' }};
                "
            >
                <span style="font-size: 1.75rem; font-weight: 800; line-height: 1; color: {{ $count > 0 ? '#fff' : 'var(--gray-500)' }};">
                    {{ $count }}
                </span>
            </div>

            <div>
                @if ($count > 0)
                    <p style="margin: 0; font-size: 1.0625rem; font-weight: 600;">
                        {{ $count }} {{ Str::plural('document', $count) }} waiting for your signature
                    </p>
                    <p style="margin: 0.25rem 0 0; font-size: 0.9375rem; color: var(--gray-500);">
                        Click to filter the list to {{ $count === 1 ? 'it' : 'them' }}.
                    </p>
                @else
                    <p style="margin: 0; font-size: 1.0625rem; font-weight: 600;">
                        All caught up
                    </p>
                    <p style="margin: 0.25rem 0 0; font-size: 0.9375rem; color: var(--gray-500);">
                        Nothing is waiting for your signature right now.
                    </p>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
