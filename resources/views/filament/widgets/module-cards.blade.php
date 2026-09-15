<x-filament-widgets::widget>
    <h2 class="fi-section-header-heading text-base font-semibold council-module-grid-heading">
        Modules
    </h2>

    <div class="council-module-grid">
        @foreach ($this->getCards() as $card)
            @if ($card['isBuilt'])
                <a href="{{ $card['url'] }}" class="council-module-card-link">
            @else
                <div class="council-module-card-link council-module-card-disabled">
            @endif

                <x-filament::section
                    heading-tag="h3"
                    :icon="$card['icon']"
                    :icon-color="$card['isBuilt'] ? 'primary' : 'gray'"
                    :heading="$card['label']"
                    :description="$card['description']"
                >
                    <x-slot name="afterHeader">
                        <x-filament::badge :color="$card['level']->getColor()" size="sm">
                            {{ $card['level']->getLabel() }}
                        </x-filament::badge>
                        @unless ($card['isBuilt'])
                            <x-filament::badge color="muted" size="sm">
                                Coming soon
                            </x-filament::badge>
                        @endunless
                    </x-slot>
                </x-filament::section>

            @if ($card['isBuilt'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    <style>
        .council-module-grid-heading {
            margin-bottom: 0.75rem;
        }

        .council-module-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.25rem;
        }

        @media (max-width: 1024px) {
            .council-module-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .council-module-grid {
                grid-template-columns: 1fr;
            }
        }

        .council-module-card-link .fi-section {
            height: 100%;
        }

        .council-module-card-link .fi-section-header {
            padding: 1.5rem;
            min-height: 9rem;
            align-items: flex-start;
        }

        .council-module-card-link .fi-section-header-heading {
            font-size: 1.1rem;
        }

        .council-module-card-link .fi-section-header-description {
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .council-module-card-link {
            display: block;
            height: 100%;
            color: inherit;
            text-decoration: none;
            border-radius: 0.75rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .council-module-card-link:not(.council-module-card-disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgb(0 0 0 / 0.18);
        }

        .council-module-card-disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }
    </style>
</x-filament-widgets::widget>
