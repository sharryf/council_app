<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
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

                if (this.state) {
                    const image = new Image();
                    image.onload = () => ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
                    image.src = this.state;
                    this.isEmpty = false;
                }
            },
            clear() {
                const canvas = this.$refs.canvas;
                canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
                this.state = null;
                this.isEmpty = true;
            },
        }"
    >
        <canvas
            x-ref="canvas"
            width="600"
            height="200"
            style="touch-action: none; width: 100%; max-width: 28rem; height: 10rem; background: white; border: 1px dashed var(--gray-400); border-radius: 0.5rem; cursor: crosshair; display: block;"
        ></canvas>

        <div style="margin-top: 0.5rem;">
            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                x-on:click="clear()"
                x-bind:disabled="isEmpty"
            >
                Clear
            </x-filament::button>
        </div>
    </div>
</x-dynamic-component>
