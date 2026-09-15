<x-filament-panels::page>
    @php($request = $this->getRequest())

    <x-filament::section>
        <x-slot name="heading">Request</x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-500);">Requester</div>
                <div>{{ $request->requester->name }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-500);">Location</div>
                <div>{{ $request->location->name }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-500);">Purpose</div>
                <div>{{ $request->purpose }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-500);">Required By</div>
                <div>{{ $request->required_by_date?->format('d/m/Y') ?? '—' }}</div>
            </div>
        </div>

        @if ($this->isSkippingApproval())
            <div style="margin-top: 1rem;">
                <x-filament::badge color="warning">Issuing directly — approval is not required by settings</x-filament::badge>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Items to Issue</x-slot>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid var(--gray-200);">
                        <th style="padding: 0.5rem;">Item</th>
                        <th style="padding: 0.5rem;">Approved</th>
                        <th style="padding: 0.5rem;">Already Issued</th>
                        <th style="padding: 0.5rem;">Issue Now</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->linesForIssue() as $line)
                        <tr style="border-bottom: 1px solid var(--gray-100);">
                            <td style="padding: 0.5rem;">
                                <div style="font-weight: 600;">{{ $line->item->code }}</div>
                                <div style="color: var(--gray-500);">{{ $line->item->name }}</div>
                            </td>
                            <td style="padding: 0.5rem;">{{ \Illuminate\Support\Number::format((float) ($this->isSkippingApproval() ? $line->requested_qty : $line->approved_qty)) }}</td>
                            <td style="padding: 0.5rem;">{{ \Illuminate\Support\Number::format((float) $line->issued_qty) }}</td>
                            <td style="padding: 0.5rem; width: 10rem;">
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="number"
                                        step="{{ $this->stepFor($line) }}"
                                        min="0"
                                        wire:model="issueQuantities.{{ $line->id }}"
                                    />
                                </x-filament::input.wrapper>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 1rem; text-align: center; color: var(--gray-500);">
                                Nothing left to issue on this request.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Receiver</x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    placeholder="Name of person receiving the items"
                    wire:model="receivedByName"
                />
            </x-filament::input.wrapper>

            @if ($this->signatureCaptureEnabled())
                <div
                    wire:ignore
                    x-data="{
                        state: $wire.$entangle('signatureDataUrl'),
                        drawing: false,
                        isEmpty: true,
                        init() {
                            const canvas = this.$refs.canvas;
                            const ctx = canvas.getContext('2d');
                            ctx.strokeStyle = '#1f2937';
                            ctx.lineWidth = 2;
                            ctx.lineJoin = 'round';
                            ctx.lineCap = 'round';

                            const posFromEvent = (event) => {
                                const rect = canvas.getBoundingClientRect();
                                const point = event.touches ? event.touches[0] : event;

                                return {
                                    x: (point.clientX - rect.left) * (canvas.width / rect.width),
                                    y: (point.clientY - rect.top) * (canvas.height / rect.height),
                                };
                            };

                            const start = (event) => {
                                event.preventDefault();
                                this.drawing = true;
                                this.isEmpty = false;
                                const { x, y } = posFromEvent(event);
                                ctx.beginPath();
                                ctx.moveTo(x, y);
                            };

                            const move = (event) => {
                                if (! this.drawing) return;
                                event.preventDefault();
                                const { x, y } = posFromEvent(event);
                                ctx.lineTo(x, y);
                                ctx.stroke();
                            };

                            const finish = () => {
                                if (! this.drawing) return;
                                this.drawing = false;
                                this.state = canvas.toDataURL('image/png');
                            };

                            canvas.addEventListener('mousedown', start);
                            canvas.addEventListener('mousemove', move);
                            window.addEventListener('mouseup', finish);
                            canvas.addEventListener('touchstart', start, { passive: false });
                            canvas.addEventListener('touchmove', move, { passive: false });
                            canvas.addEventListener('touchend', finish);
                        },
                        clear() {
                            const canvas = this.$refs.canvas;
                            canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
                            this.state = null;
                            this.isEmpty = true;
                        },
                    }"
                >
                    <div style="font-size: 0.75rem; color: var(--gray-500); margin-bottom: 0.375rem;">Receiver's signature <span style="color: #ef4444;">*</span></div>
                    <canvas
                        x-ref="canvas"
                        width="600"
                        height="160"
                        style="touch-action: none; width: 100%; max-width: 28rem; height: 8rem; background: white; border: 1px dashed var(--gray-400); border-radius: 0.5rem; cursor: crosshair; display: block;"
                    ></canvas>
                    <div style="margin-top: 0.5rem;">
                        <x-filament::button type="button" color="gray" size="sm" x-on:click="clear()" x-bind:disabled="isEmpty">
                            Clear
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>

    <div style="display: flex; justify-content: flex-end;">
        <x-filament::button wire:click="submitIssue" wire:loading.attr="disabled" wire:target="submitIssue" size="lg">
            Confirm Issue
        </x-filament::button>
    </div>
</x-filament-panels::page>
