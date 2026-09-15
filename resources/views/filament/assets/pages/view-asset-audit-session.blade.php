<x-filament-panels::page>
    @php
        $session = $this->getSession();
        $progress = $this->progress();
        $canAct = $session->isInProgress() && \App\Filament\Assets\Resources\Audits\AssetAuditSessionResource::userIsAssetAdminOrManager();
        $canReview = \App\Filament\Assets\Resources\Audits\AssetAuditSessionResource::userIsAssetAdminOrManager();
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
                        wire:confirm="Close this session? Every unverified item will be flagged Missing, and mismatches/missing items move to the review queue."
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
                        async start() {
                            this.detector = new BarcodeDetector({ formats: ['qr_code'] });
                            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                            this.$refs.video.srcObject = this.stream;
                            this.scanning = true;
                            this.loop();
                        },
                        stop() {
                            this.stream?.getTracks().forEach(t => t.stop());
                            this.scanning = false;
                        },
                        async loop() {
                            if (! this.scanning) return;
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
                    x-init="supported = ('BarcodeDetector' in window)"
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
                @foreach (['pending' => 'Pending', 'verified' => 'Verified', 'review' => 'Needs Review'] as $key => $label)
                    <x-filament::button
                        size="sm"
                        :color="$tab === $key ? 'primary' : 'gray'"
                        wire:click="$set('tab', '{{ $key }}')"
                    >
                        {{ $label }}
                        @if ($key === 'review' && $progress['review_remaining'] > 0)
                            ({{ $progress['review_remaining'] }})
                        @endif
                    </x-filament::button>
                @endforeach
            </div>

            <div style="margin-bottom: 0.75rem; max-width: 20rem;">
                <x-filament::input.wrapper>
                    <input type="text" class="fi-input" wire:model.live.debounce.400ms="search" placeholder="Search name or tag…">
                </x-filament::input.wrapper>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                @forelse ($this->items() as $item)
                    <div class="aas-row" wire:key="item-{{ $item->id }}">
                        <div>
                            <div class="name">{{ $item->asset->name }}</div>
                            <div class="meta">
                                {{ $item->asset->asset_tag }} · Expected: {{ $item->expectedRoom->path() }}
                                @if ($item->isVerified())
                                    · Verified {{ $item->verified_at->format('M j, H:i') }} ({{ $item->verify_method?->getLabel() }})
                                @endif
                                @if ($item->outcome)
                                    · <span style="color: var(--gray-600);">{{ $item->outcome->getLabel() }}</span>
                                @endif
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            @if ($tab === 'pending' && $canAct)
                                <x-filament::button size="sm" wire:click="verifyManually({{ $item->id }})" icon="heroicon-o-check">
                                    Verify
                                </x-filament::button>
                            @elseif ($tab === 'review' && $canReview)
                                <div x-data="{ note: '' }" style="display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap;">
                                    <input type="text" x-model="note" class="fi-input" placeholder="Note (optional)" style="width: 10rem; font-size: 0.8125rem;">
                                    <x-filament::button size="xs" color="danger" x-on:click="$wire.call('reviewItem', {{ $item->id }}, 'marked_lost', note)">
                                        Mark Lost
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="warning" x-on:click="$wire.call('reviewItem', {{ $item->id }}, 'location_corrected', note)">
                                        Correct Location
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="gray" x-on:click="$wire.call('reviewItem', {{ $item->id }}, 'kept_as_is', note)">
                                        Keep As Is
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="gray" x-on:click="$wire.call('reviewItem', {{ $item->id }}, 'dismissed', note)">
                                        Dismiss
                                    </x-filament::button>
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
