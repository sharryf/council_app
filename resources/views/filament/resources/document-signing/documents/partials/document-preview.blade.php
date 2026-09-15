{{--
    Read-only PDF viewer: fetches a document over `fileUrl` and paginates
    it with pdf.js, same rendering approach as the upload wizard (see
    create-document.blade.php) but with no drag/drop — this is for
    looking, not placing. `highlight` optionally draws one or more
    labeled boxes, used to show a signee everywhere their own signature
    goes (a signer can have several placements — see
    DocumentResource::signerHighlight()). `stamps` optionally draws one
    or more labeled outline boxes for where the organization stamp will
    land — see `DocumentResource::stampBoxes()` for why these are only
    passed for a not-yet-Signed document.

    This markup is always delivered as part of a Filament action modal
    (signAction()'s schema, or previewAction()'s modalContent) — i.e.
    injected into the DOM via a Livewire AJAX response, never present in
    the page's initial HTML. Browsers never execute <script> tags that
    arrive that way, and a separately `Alpine.data()`-registered
    component would never get wired up in time either (its own
    registration script would have the same problem). So — unlike
    create-document.blade.php's `documentPlacement`, which lives on a
    real full-page load — everything here is a single self-contained
    IIFE inline in `x-data`, which Alpine evaluates directly as a JS
    expression regardless of how the element reached the DOM.
--}}
<div
    x-data="(function () {
        const config = @js(['fileUrl' => $fileUrl, 'highlight' => $highlight ?? [], 'stamps' => $stamps ?? []]);
        let pdfDoc = null;
        let loaded = false;
        let renderTask = null;

        function ensurePdfJs() {
            if (window.pdfjsLib) { return Promise.resolve(window.pdfjsLib); }
            if (!window.__pdfJsLoadPromise) {
                window.__pdfJsLoadPromise = new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                    script.onload = () => {
                        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                        resolve(window.pdfjsLib);
                    };
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }
            return window.__pdfJsLoadPromise;
        }

        return {
            fileUrl: config.fileUrl,
            highlight: config.highlight,
            stamps: config.stamps,
            currentPage: (config.highlight[0] && config.highlight[0].page) || 1,
            pageCount: 1,
            loading: true,

            async init() {
                if (loaded) { return; }
                loaded = true;

                await ensurePdfJs();
                const response = await fetch(this.fileUrl, { credentials: 'same-origin' });
                const bytes = new Uint8Array(await response.arrayBuffer());
                pdfDoc = await window.pdfjsLib.getDocument({ data: bytes }).promise;
                this.pageCount = pdfDoc.numPages;
                this.currentPage = Math.min(Math.max(this.currentPage, 1), this.pageCount);
                this.loading = false;
                await this.renderPage();
            },

            async renderPage() {
                if (renderTask) {
                    renderTask.cancel();
                }

                const page = await pdfDoc.getPage(this.currentPage);
                const containerWidth = this.$refs.pageContainer.clientWidth || 700;
                const baseViewport = page.getViewport({ scale: 1 });
                const scale = containerWidth / baseViewport.width;
                const viewport = page.getViewport({ scale });
                const canvas = this.$refs.canvas;
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                const ctx = canvas.getContext('2d');
                renderTask = page.render({ canvasContext: ctx, viewport });
                try {
                    await renderTask.promise;
                } catch (e) {
                    if (e && e.name !== 'RenderingCancelledException') {
                        throw e;
                    }
                } finally {
                    renderTask = null;
                }
            },

            async nextPage() {
                if (this.currentPage < this.pageCount) {
                    this.currentPage++;
                    await this.renderPage();
                }
            },

            async prevPage() {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    await this.renderPage();
                }
            },
        };
    })()"
    x-init="init()"
>
    <div
        x-ref="pageContainer"
        style="position: relative; background: var(--gray-100); border-radius: 0.5rem; overflow: hidden; line-height: 0; min-height: 240px;"
    >
        <canvas x-ref="canvas" style="width: 100%; display: block;"></canvas>

        {{--
            Full-width banner above the box (matches create-document.blade.php's
            placement labels) — except within the top ~6% of the page,
            where "above" would push the banner past y=0 and this
            container's own overflow:hidden would silently clip it; that
            case flips to overlapping the box's own top edge instead.
        --}}
        <template x-for="stamp in stamps.filter(s => s.page === currentPage)" :key="stamp.x + '-' + stamp.y">
            <div
                x-bind:style="`position:absolute; left:${stamp.x*100}%; top:${stamp.y*100}%; width:${stamp.w*100}%; height:${stamp.h*100}%; border:2px dashed ${stamp.color || '#B8934A'}; border-radius:4px; background:${stamp.color || '#B8934A'}22; pointer-events:none;`"
            >
                <span
                    x-text="stamp.label"
                    x-bind:style="`position:absolute; left:0; ${stamp.y < 0.06 ? 'top:0; border-radius:2px 2px 0 0;' : 'bottom:100%; border-radius:5px 5px 0 0;'} width:100%; box-sizing:border-box; background:rgba(15,23,32,0.92); border-bottom:3px solid ${stamp.color || '#B8934A'}; color:#fff; font-size:12px; font-weight:700; letter-spacing:0.02em; padding:5px 10px 4px; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; box-shadow:0 2px 6px rgba(0,0,0,0.35);`"
                ></span>
            </div>
        </template>

        <template x-for="box in highlight.filter(h => h.page === currentPage)" :key="box.x + '-' + box.y">
            <div
                x-bind:style="`position:absolute; left:${box.x*100}%; top:${box.y*100}%; width:${box.w*100}%; height:${box.h*100}%; border:2px dashed ${box.color || '#0E7A82'}; border-radius:4px; background:${box.color || '#0E7A82'}22; pointer-events:none;`"
            >
                <span
                    x-text="box.label"
                    x-bind:style="`position:absolute; left:0; ${box.y < 0.06 ? 'top:0; border-radius:2px 2px 0 0;' : 'bottom:100%; border-radius:5px 5px 0 0;'} width:100%; box-sizing:border-box; background:rgba(15,23,32,0.92); border-bottom:3px solid ${box.color || '#0E7A82'}; color:#fff; font-size:12px; font-weight:700; letter-spacing:0.02em; padding:5px 10px 4px; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; box-shadow:0 2px 6px rgba(0,0,0,0.35);`"
                ></span>
            </div>
        </template>

        <template x-if="loading">
            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:0.875rem; color:var(--gray-500);">
                Loading document…
            </div>
        </template>
    </div>

    {{--
        Filament's compiled CSS is pre-purged to only the utility classes
        Filament's own views use — arbitrary Tailwind classes referenced
        from our own blade files (like `flex`) aren't in that bundle, so
        this can't be a class. But an *inline* `display:flex` doesn't
        survive `x-show` either: Alpine's x-show reveals an element by
        removing only the inline `display` property (it doesn't restore a
        specific prior value), which drops back to the div's block
        default — stacking the arrows/label vertically. `x-if` sidesteps
        both problems: it adds/removes the whole element from the DOM
        rather than toggling a style property, so the inline style is
        never touched.
    --}}
    <template x-if="pageCount > 1">
        <div style="display:flex; flex-wrap:nowrap; align-items:center; justify-content:center; gap:0.375rem; margin:0.75rem auto 0; padding:0.25rem 0.375rem; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:9999px; width:fit-content;">
            <x-filament::icon-button icon="heroicon-o-chevron-left" x-on:click="prevPage()" label="Previous page" size="sm" />
            <span x-text="`Page ${currentPage} of ${pageCount}`" style="font-size: 0.8125rem; font-weight: 500; white-space: nowrap; min-width: 6rem; text-align: center; color: #fff;"></span>
            <x-filament::icon-button icon="heroicon-o-chevron-right" x-on:click="nextPage()" label="Next page" size="sm" />
        </div>
    </template>
</div>
