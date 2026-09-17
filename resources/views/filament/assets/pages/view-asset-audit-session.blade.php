<x-filament-panels::page>
    @php
        $session = $this->getSession();
        $progress = $this->progress();
        $canAct = $session->isInProgress() && \App\Filament\Assets\Resources\Audits\AssetAuditSessionResource::userIsAssetAdminOrManager();
    @endphp

    <style>
        .aas-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            background: var(--gray-50);
        }
        html.dark .aas-row { background: rgba(255,255,255,0.04); }
        .aas-row .name { font-size: 0.875rem; font-weight: 600; }
        .aas-row .meta { font-size: 0.75rem; color: var(--gray-500); }
        .aas-progress-track {
            width: 100%;
            height: 0.5rem;
            border-radius: 999px;
            background: var(--gray-200);
            overflow: hidden;
        }
        html.dark .aas-progress-track { background: rgba(255,255,255,0.08); }
        .aas-progress-fill { height: 100%; background: #0E7A82; }
        .aas-tabs { display: flex; gap: 0.375rem; margin-bottom: 0.75rem; }
        /* A plain <select> renders its option list via the OS/browser's
           own native popup, which ignores every rule below — color-
           scheme wasn't reliable enough across browsers either, so this
           is a small hand-built Alpine dropdown instead (same idea as
           Filament's own Select field), fully themeable like the rest
           of this page. */
        .aas-filter { position: relative; }
        .aas-filter-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            width: 100%;
            background: none;
            border: none;
            padding: 0;
            font: inherit;
            color: inherit;
            cursor: pointer;
            text-align: left;
        }
        .aas-filter-chevron { width: 1rem; height: 1rem; color: var(--gray-400); flex-shrink: 0; }
        .aas-filter-panel {
            position: absolute;
            z-index: 20;
            top: calc(100% + 0.25rem);
            left: 0;
            right: 0;
            max-height: 16rem;
            overflow-y: auto;
            padding: 0.25rem;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        html.dark .aas-filter-panel { background: #26272b; border-color: rgba(255,255,255,0.1); }
        .aas-filter-option {
            display: block;
            width: 100%;
            text-align: left;
            padding: 0.375rem 0.625rem;
            border-radius: 0.375rem;
            background: none;
            border: none;
            font-size: 0.8125rem;
            cursor: pointer;
            color: inherit;
        }
        .aas-filter-option:hover { background: var(--gray-100); }
        html.dark .aas-filter-option:hover { background: rgba(255,255,255,0.08); }
    </style>

    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        {{-- Header --}}
        <x-filament::section>
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div>
                    <h2 style="font-size: 1.125rem; font-weight: 700;">{{ $session->name }}</h2>
                    <p class="meta">{{ $session->scopeDescription() }} · Started by {{ $session->startedBy?->name }} on {{ $session->started_at->format('M j, Y H:i') }}</p>
                </div>
                <x-filament::badge :color="$session->status->getColor()">{{ $session->status->getLabel() }}</x-filament::badge>
            </div>

            <div class="aas-progress-track">
                <div class="aas-progress-fill" style="width: {{ $progress['total'] > 0 ? round(($progress['verified'] / $progress['total']) * 100) : 0 }}%;"></div>
            </div>
            <p class="meta" style="margin-top: 0.375rem;">{{ $progress['verified'] }} of {{ $progress['total'] }} verified</p>

            @if ($session->status === \App\Enums\AssetAuditSessionStatus::Closed)
                <p class="meta" style="margin-top: 0.375rem;">Closed by {{ $session->closedBy?->name }} on {{ $session->closed_at?->format('M j, Y H:i') }}</p>
            @endif

            @if ($canAct)
                <div style="margin-top: 1rem;">
                    <x-filament::button
                        color="danger"
                        wire:click="closeSession"
                        wire:confirm="Close this session? Every item still unverified will be tagged 'Not Found in this Audit' — no further review or action is needed for those."
                    >
                        Close Session
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        {{-- Scan / verify --}}
        @if ($canAct)
            <x-filament::section heading="Scan or enter an asset">
                <div
                    x-data="{
                        supported: false,
                        scanning: false,
                        stream: null,
                        detector: null,
                        lastCode: null,
                        lastTime: 0,
                        init() {
                            this.supported = ('BarcodeDetector' in window);
                            // Filament navigates between pages via
                            // wire:navigate (no full reload), so leaving
                            // this page while the camera is running would
                            // otherwise leak the media stream and spin
                            // the requestAnimationFrame loop forever
                            // against a video element that no longer
                            // exists — that runaway loop is what made the
                            // module feel frozen until a hard refresh.
                            // stop() only touches JS/media state (no DOM
                            // lookups), so it's always safe to call here
                            // even after this element is gone.
                            document.addEventListener('livewire:navigating', () => this.stop());
                        },
                        async start() {
                            this.detector = new BarcodeDetector({ formats: ['qr_code'] });
                            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                            this.$refs.video.srcObject = this.stream;
                            this.scanning = true;
                            this.loop();
                        },
                        stop() {
                            this.scanning = false;
                            this.stream?.getTracks().forEach(t => t.stop());
                            this.stream = null;
                        },
                        async loop() {
                            if (! this.scanning || ! this.$refs.video) return;
                            try {
                                const codes = await this.detector.detect(this.$refs.video);
                                if (codes.length) {
                                    const value = codes[0].rawValue;
                                    const now = Date.now();
                                    if (value !== this.lastCode || (now - this.lastTime) > 2000) {
                                        this.lastCode = value;
                                        this.lastTime = now;
                                        $wire.call('verifyCode', value);
                                    }
                                }
                            } catch (e) {}
                            if (this.scanning) requestAnimationFrame(() => this.loop());
                        },
                    }"
                >
                    <div x-show="supported" style="margin-bottom: 1rem;">
                        <video x-ref="video" autoplay playsinline muted x-show="scanning" style="width: 100%; max-width: 320px; border-radius: 0.5rem; background: #000;"></video>
                        <div style="margin-top: 0.5rem;">
                            <x-filament::button type="button" x-show="! scanning" x-on:click="start" icon="heroicon-o-camera" color="gray">
                                Start Camera
                            </x-filament::button>
                            <x-filament::button type="button" x-show="scanning" x-cloak x-on:click="stop" icon="heroicon-o-stop-circle" color="danger">
                                Stop Camera
                            </x-filament::button>
                        </div>
                    </div>
                    <p x-show="! supported" class="meta" style="margin-bottom: 0.75rem;">
                        Live camera scanning isn't supported in this browser. Scan the printed label with your phone's own camera app, then paste or type what it reads below — or use the checklist underneath.
                    </p>
                </div>

                <form wire:submit="verifyCode" style="display: flex; gap: 0.5rem; max-width: 28rem;">
                    <x-filament::input.wrapper style="flex: 1;">
                        <input type="text" class="fi-input" wire:model="scanInput" placeholder="Asset tag, token, or scanned URL">
                    </x-filament::input.wrapper>
                    <x-filament::button type="submit">Verify</x-filament::button>
                </form>
            </x-filament::section>
        @endif

        {{-- Checklist --}}
        <x-filament::section heading="Checklist">
            <div class="aas-tabs">
                @foreach (['pending' => 'Pending', 'verified' => 'Verified'] as $key => $label)
                    <x-filament::button
                        size="sm"
                        :color="$tab === $key ? 'primary' : 'gray'"
                        wire:click="$set('tab', '{{ $key }}')"
                    >
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                <div style="flex: 1; min-width: 12rem; max-width: 20rem;">
                    <x-filament::input.wrapper>
                        <input type="text" class="fi-input" wire:model.live.debounce.400ms="search" placeholder="Search name or tag…">
                    </x-filament::input.wrapper>
                </div>

                {{-- A Room-scoped session is already one room — only
                     All/Building scope spans more than one location
                     worth narrowing further here. All scope gets both
                     (Room's own options narrow to whichever building is
                     picked); Building scope only needs Room, since its
                     building is already fixed. --}}
                @if ($session->scope_type === \App\Enums\AssetAuditScopeType::All)
                    <div class="aas-filter" x-data="{ open: false }" style="flex: 1; min-width: 10rem; max-width: 16rem;">
                        <x-filament::input.wrapper>
                            <button type="button" class="aas-filter-trigger" x-on:click="open = ! open">
                                <span>{{ $this->filterBuildingId ? ($this->filterBuildingOptions()[$this->filterBuildingId] ?? 'All buildings') : 'All buildings' }}</span>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="aas-filter-chevron" />
                            </button>
                        </x-filament::input.wrapper>
                        <div class="aas-filter-panel" x-show="open" x-cloak x-on:click.outside="open = false">
                            {{-- Changing the building drops any room
                                 filter that no longer belongs to it. --}}
                            <button type="button" class="aas-filter-option" wire:click="selectFilterBuilding(null)" x-on:click="open = false">All buildings</button>
                            @foreach ($this->filterBuildingOptions() as $id => $name)
                                <button type="button" class="aas-filter-option" wire:click="selectFilterBuilding({{ $id }})" x-on:click="open = false">{{ $name }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if (in_array($session->scope_type, [\App\Enums\AssetAuditScopeType::All, \App\Enums\AssetAuditScopeType::Building], true))
                    <div class="aas-filter" x-data="{ open: false }" style="flex: 1; min-width: 10rem; max-width: 16rem;">
                        <x-filament::input.wrapper>
                            <button type="button" class="aas-filter-trigger" x-on:click="open = ! open">
                                <span>{{ $this->filterRoomId ? ($this->filterRoomOptions()[$this->filterRoomId] ?? 'All rooms') : 'All rooms' }}</span>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="aas-filter-chevron" />
                            </button>
                        </x-filament::input.wrapper>
                        <div class="aas-filter-panel" x-show="open" x-cloak x-on:click.outside="open = false">
                            <button type="button" class="aas-filter-option" wire:click="$set('filterRoomId', null)" x-on:click="open = false">All rooms</button>
                            @foreach ($this->filterRoomOptions() as $id => $name)
                                <button type="button" class="aas-filter-option" wire:click="$set('filterRoomId', {{ $id }})" x-on:click="open = false">{{ $name }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                @forelse ($this->items() as $item)
                    <div class="aas-row" wire:key="item-{{ $item->id }}">
                        <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                            @if ($item->asset?->photo_attachment_id)
                                <img
                                    src="{{ route('assets.attachments.show', $item->asset->photo_attachment_id) }}"
                                    alt=""
                                    style="width: 2.75rem; height: 2.75rem; object-fit: cover; border-radius: 0.375rem; flex-shrink: 0;"
                                >
                            @else
                                <div style="width: 2.75rem; height: 2.75rem; border-radius: 0.375rem; background: var(--gray-200); flex-shrink: 0;"></div>
                            @endif
                            <div style="min-width: 0;">
                                <div class="name" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <x-filament::badge color="primary" size="sm">{{ $item->asset?->asset_tag ?? '—' }}</x-filament::badge>
                                    {{ $item->asset?->name ?? 'Deleted asset' }}
                                </div>
                                <div class="meta">
                                    Expected: {{ $item->expectedRoom->path() }}
                                    @if ($item->isVerified())
                                        · Verified {{ $item->verified_at->format('M j, H:i') }} ({{ $item->verify_method?->getLabel() }})
                                    @endif
                                    @if ($item->outcome)
                                        · <span style="color: var(--gray-600);">{{ $item->outcome->getLabel() }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            {{-- A tag can be lost/unreadable, and a room
                                 may hold several near-identical assets
                                 (e.g. many monitors) — this opens the
                                 asset's own page (photo, brand, model,
                                 serial number) in a new tab so it stays
                                 available without losing this checklist. --}}
                            @if ($item->asset)
                                <x-filament::icon-button
                                    icon="heroicon-o-eye"
                                    tag="a"
                                    href="{{ \App\Filament\Assets\Resources\Assets\AssetResource::getUrl('view', ['record' => $item->asset]) }}"
                                    target="_blank"
                                    label="Preview asset"
                                    tooltip="Preview asset"
                                    color="gray"
                                />
                            @endif
                            @if (! $item->asset)
                                <span class="meta">Asset was deleted — no action available.</span>
                            @elseif ($tab === 'pending' && $canAct)
                                <x-filament::button size="sm" wire:click="verifyManually({{ $item->id }})" icon="heroicon-o-check">
                                    Verify
                                </x-filament::button>

                                {{-- The asset IS present, just not where
                                     expected — this verifies it (same as
                                     a plain check) and raises an ordinary
                                     transfer request for a Manager to
                                     decide, rather than editing the
                                     asset's room directly. --}}
                                <div class="aas-filter" x-data="{ open: false }" style="position: relative;">
                                    <x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-map-pin" x-on:click="open = ! open">
                                        Found in Another Room
                                    </x-filament::button>
                                    <div class="aas-filter-panel" x-show="open" x-cloak x-on:click.outside="open = false" style="left: auto; right: 0; min-width: 16rem;">
                                        @forelse ($this->foundInRoomOptions($item) as $id => $name)
                                            <button type="button" class="aas-filter-option" wire:click="foundInAnotherRoom({{ $item->id }}, {{ $id }})" x-on:click="open = false">{{ $name }}</button>
                                        @empty
                                            <p class="meta" style="padding: 0.375rem 0.625rem;">No other rooms available.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="meta">Nothing here.</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
