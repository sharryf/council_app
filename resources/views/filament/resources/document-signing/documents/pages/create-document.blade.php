<x-filament-panels::page>
    @if ($step === 1)
        {{--
            wire:key matters here, not just as hygiene — without it,
            Livewire's DOM diffing has no way to tell this div and step
            2's (below) apart, since both are generic <div>s at the same
            tree position. It was trying to *morph* one into the other
            on the step transition instead of destroying/recreating,
            which left Alpine's already-bound x-data scope from THIS
            div (with its own `uploading` flag) wrapping leftover step-2
            content — including the page-nav arrows, which ended up
            nested inside a stale `x-show="!uploading"` that evaluated
            to hidden. Distinct keys force a clean replace.
        --}}
        <div
            wire:key="step-1-upload"
            x-data="{ isDragging: false, isHovering: false, uploading: false }"
            x-on:livewire-upload-start.window="uploading = true"
            x-on:livewire-upload-finish.window="uploading = false"
            x-on:livewire-upload-error.window="uploading = false"
            style="width: 100%;"
        >
            {{--
                The dashed border was invisible before not because of a
                bad color choice, but because of it being split across
                two attributes: a static `style="border: ...; padding: ...` plus a
                separate `x-bind:style` for the drag-active state. Alpine's
                x-bind:style, given a plain string (not an object),
                REPLACES the whole inline style rather than merging with
                it — so the moment Alpine ran (immediately, since
                isDragging starts false), it wiped the static border/
                padding/etc. down to whatever the bound string covered,
                leaving border-color set but border-style/width gone
                (defaulting to none — no border at all). Fixed by making
                x-bind:style the single reactive source for every
                property on this element, always returning the complete
                declaration.
            --}}
            <div
                x-on:dragover.prevent="isDragging = true"
                x-on:dragleave.prevent="isDragging = false"
                x-on:drop.prevent="
                    isDragging = false;
                    $refs.fileInput.files = $event.dataTransfer.files;
                    $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                "
                x-on:mouseenter="isHovering = true"
                x-on:mouseleave="isHovering = false"
                x-on:click="$refs.fileInput.click()"
                x-bind:style="`
                    border: 2px {{ $file ? 'solid' : 'dashed' }} ${isDragging ? '#0E7A82' : (isHovering ? '{{ $file ? '#22C55E' : 'rgba(14,122,130,0.75)' }}' : '{{ $file ? '#16A34A' : 'rgba(14,122,130,0.45)' }}')};
                    background: ${isDragging ? 'rgba(14,122,130,0.14)' : (isHovering ? '{{ $file ? 'rgba(22,163,74,0.1)' : 'rgba(14,122,130,0.08)' }}' : '{{ $file ? 'rgba(22,163,74,0.06)' : 'rgba(14,122,130,0.05)' }}')};
                    border-radius: 0.875rem;
                    padding: 3.5rem 1.5rem;
                    text-align: center;
                    cursor: pointer;
                    transform: ${isDragging ? 'scale(1.01)' : 'scale(1)'};
                    transition: border-color 0.15s ease, background-color 0.15s ease, transform 0.15s ease;
                `"
            >
                <input type="file" wire:model="file" x-ref="fileInput" accept="application/pdf" style="display: none;">

                {{--
                    x-show, not `<template x-if>`, on purpose: x-if clones
                    its template's content into the DOM once and doesn't
                    reliably pick up a later Livewire re-render of that
                    same server-rendered block (the clone and Livewire's
                    morph target can end up out of sync) — which is
                    exactly why the "Uploaded" state below (driven by
                    Blade's `$file`, not Alpine state) never appeared:
                    Alpine's clone was already in the DOM from before the
                    upload finished. x-show toggles visibility of one
                    persistent node instead, which Livewire morphs
                    directly like anything else.
                --}}
                <div x-show="!uploading">
                    <div>
                        @if ($file)
                            <div style="width: 2.5rem; height: 2.5rem; margin: 0 auto 1rem; border-radius: 9999px; background: rgba(22,163,74,0.15); display: flex; align-items: center; justify-content: center;">
                                <x-filament::icon
                                    icon="heroicon-o-check"
                                    style="width: 1.375rem; height: 1.375rem; color: #22C55E;"
                                />
                            </div>
                            <p style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; color: #22C55E; margin: 0 0 0.5rem; background: rgba(22,163,74,0.12); padding: 0.1875rem 0.625rem; border-radius: 9999px;">
                                Uploaded
                            </p>
                            <p style="font-weight: 700; font-size: 1.0625rem; margin: 0 0 0.375rem; color: #fff; word-break: break-word;">
                                {{ $file->getClientOriginalName() }}
                            </p>
                            <p style="color: rgba(255,255,255,0.55); font-size: 0.875rem; margin: 0;">
                                {{ number_format($file->getSize() / 1024, 0) }} KB — click or drop another file to replace it
                            </p>
                        @else
                            <x-filament::icon
                                icon="heroicon-o-arrow-up-tray"
                                style="width: 2.5rem; height: 2.5rem; color: #0E7A82; margin: 0 auto 1rem;"
                            />
                            <p style="font-weight: 700; font-size: 1.0625rem; margin: 0 0 0.375rem; color: #fff;">
                                Drag &amp; drop your PDF here
                            </p>
                            <p style="color: rgba(255,255,255,0.55); font-size: 0.875rem; margin: 0;">or click to browse files (PDF, up to 20MB)</p>
                        @endif
                    </div>
                </div>
                <p x-show="uploading" style="margin: 0; font-weight: 600; color: #fff;">Uploading…</p>
            </div>
            @error('file')
                <p style="color: var(--danger-500); font-size: 0.875rem; margin-top: 0.5rem;">{{ $message }}</p>
            @enderror

            <div style="margin-top: 1.75rem;">
                <label for="document-title-input" style="display: block; font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.5rem; color: #fff;">
                    Document title <span style="color: rgba(255,255,255,0.45); font-weight: 400;">(optional)</span>
                </label>
                {{-- Filament's own input components (not a raw <input>) — these ship in Filament's compiled CSS, unlike arbitrary utility classes referenced from our own blade files. --}}
                <x-filament::input.wrapper>
                    <x-filament::input
                        id="document-title-input"
                        type="text"
                        wire:model="documentTitle"
                        placeholder="Leave blank to use the file name"
                    />
                </x-filament::input.wrapper>
            </div>

            <div style="margin-top: 1.75rem; text-align: right;">
                <x-filament::button
                    wire:click="continueToPlacement"
                    wire:loading.attr="disabled"
                    wire:target="continueToPlacement,file"
                >
                    Continue
                </x-filament::button>
            </div>
        </div>
    @else
        <div
            wire:key="step-2-placement"
            x-data="documentPlacement({
                fileBase64: @js($fileBase64),
                pageCount: @js($pageCount),
                eligibleSignees: @js($this->getEligibleSignees()),
                organizationStamps: @js($this->getOrganizationStamps()),
            })"
            x-init="init()"
            style="display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; align-items: start;"
        >
            <div>
                <div
                    x-ref="pageContainer"
                    style="position: relative; background: var(--gray-100); border-radius: 0.5rem; overflow: hidden; line-height: 0;"
                >
                    <canvas x-ref="canvas" style="width: 100%; display: block;"></canvas>

                    <template x-for="box in visibleSignerBoxes()" :key="box._index">
                        <div
                            x-on:mousedown="startDrag($event, 'signer', box._index)"
                            x-on:touchstart="startDrag($event, 'signer', box._index)"
                            x-bind:style="`position:absolute; left:${box.x*100}%; top:${box.y*100}%; width:${box.w*100}%; height:${box.h*100}%; cursor:move; background:${box.color}22; border:2px dashed ${box.color}; border-radius:4px; user-select:none;`"
                        >
                            {{--
                                Full-width banner spanning the box, sitting
                                just above it (bottom:100%) rather than
                                tucked inside a corner — except when the box
                                sits within the top ~6% of the page, where
                                "above" would push the banner past y=0 and
                                the page container's own overflow:hidden
                                (further up the tree) would silently clip
                                it; that case flips to top:0 (overlapping
                                the box's own top edge) instead. Dark,
                                signer-color-agnostic background so the text
                                reads clearly regardless of which palette
                                color this signer landed on — the colored
                                bottom border keeps the "whose box is this"
                                cue instead. display:inline-block +
                                box-sizing:border-box so text-overflow:ellipsis
                                actually triggers instead of hard-clipping a
                                long name mid-character (a plain inline
                                <span> doesn't reliably respect
                                max-width/overflow for that).
                            --}}
                            <span
                                x-text="box.name"
                                x-bind:title="box.name"
                                x-bind:style="`position:absolute; left:0; ${box.y < 0.06 ? 'top:0; border-radius:2px 2px 0 0;' : 'bottom:100%; border-radius:5px 5px 0 0;'} width:100%; display:inline-block; box-sizing:border-box; font-size:12px; font-weight:700; letter-spacing:0.02em; color:#fff; background:rgba(15,23,32,0.92); border-bottom:3px solid ${box.color}; padding:5px 10px 4px; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; pointer-events:none; box-shadow:0 2px 6px rgba(0,0,0,0.35);`"
                            ></span>
                            <div
                                x-on:mousedown.stop="startResize($event, 'signer', box._index)"
                                x-on:touchstart.stop="startResize($event, 'signer', box._index)"
                                x-bind:style="`position:absolute; right:-6px; bottom:-6px; width:13px; height:13px; border-radius:50%; border:2px solid #fff; cursor:nwse-resize; background:${box.color};`"
                            ></div>
                        </div>
                    </template>

                    <template x-for="stamp in visibleStamps()" :key="stamp._index">
                        <div
                            x-on:mousedown="startDrag($event, 'stamp', stamp._index)"
                            x-on:touchstart="startDrag($event, 'stamp', stamp._index)"
                            x-bind:style="`position:absolute; left:${stamp.x*100}%; top:${stamp.y*100}%; width:${stamp.w*100}%; height:${stamp.h*100}%; cursor:move; background:rgba(184,147,74,0.15); border:2px dashed #B8934A; border-radius:4px; user-select:none;`"
                        >
                            <span
                                x-text="stampLabel(stamp.slot)"
                                x-bind:style="`position:absolute; left:0; ${stamp.y < 0.06 ? 'top:0; border-radius:2px 2px 0 0;' : 'bottom:100%; border-radius:5px 5px 0 0;'} width:100%; box-sizing:border-box; font-size:12px; font-weight:700; letter-spacing:0.02em; color:#fff; background:rgba(15,23,32,0.92); border-bottom:3px solid #B8934A; padding:5px 10px 4px; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; pointer-events:none; box-shadow:0 2px 6px rgba(0,0,0,0.35);`"
                            ></span>
                            <div
                                x-on:mousedown.stop="startResize($event, 'stamp', stamp._index)"
                                x-on:touchstart.stop="startResize($event, 'stamp', stamp._index)"
                                style="position:absolute; right:-6px; bottom:-6px; width:13px; height:13px; border-radius:50%; border:2px solid #fff; cursor:nwse-resize; background:#B8934A;"
                            ></div>
                        </div>
                    </template>
                </div>

                <div style="display:flex; flex-wrap:nowrap; align-items:center; justify-content:center; gap:0.375rem; margin:0.75rem auto 0; padding:0.25rem 0.375rem; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:9999px; width:fit-content;">
                    <x-filament::icon-button icon="heroicon-o-chevron-left" x-on:click="prevPage()" label="Previous page" size="sm" />
                    <span x-text="`Page ${currentPage} of ${pageCount}`" style="font-size: 0.8125rem; font-weight: 500; white-space: nowrap; min-width: 6rem; text-align: center; color: #fff;"></span>
                    <x-filament::icon-button icon="heroicon-o-chevron-right" x-on:click="nextPage()" label="Next page" size="sm" />
                </div>

                <p style="text-align:center; font-size: 0.75rem; color: var(--gray-500); margin-top: 0.375rem;">
                    Drag a box to move it, or drag its corner handle to resize.
                </p>

                <div style="margin-top: 1rem;">
                    <x-filament::button color="gray" wire:click="backToUpload">
                        Back
                    </x-filament::button>
                </div>
            </div>

            <div>
                <x-filament::section compact heading="Signing order">
                    <div x-data="{}">
                        <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.375rem; font-size: 0.875rem;">
                            <input type="radio" value="parallel" x-model="signingMode"> Parallel <span style="color: var(--gray-400);">— everyone can sign any time</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                            <input type="radio" value="sequential" x-model="signingMode"> Sequential <span style="color: var(--gray-400);">— in list order</span>
                        </label>
                    </div>
                </x-filament::section>

                <x-filament::section compact heading="Signees" style="margin-top: 1rem;">
                    <p style="font-size: 0.8125rem; color: var(--gray-500); margin: 0 0 0.75rem;">
                        Click a name to add them and drop a sign box on the document. Drag a box to move it, or its corner to resize. A signee can have more than one box — e.g. initials on every page plus a full signature on the last — flip to another page and click "Add a box here".
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 0.375rem;">
                        {{--
                            Explicit colors, not var(--gray-50)/var(--gray-200)
                            — those tokens are a fixed light gray/near-white
                            regardless of theme (Filament expects the CSS
                            author to pick a different token per theme, not
                            for the same token to flip), so the unselected
                            row rendered as a near-white card. The name span
                            had no explicit color of its own either, so it
                            just inherited the page's light text color —
                            invisible on that near-white background, which
                            is why a signee's name only showed up once
                            selected (a colored background from
                            signerColor() gave it contrast almost by
                            accident).
                        --}}
                        <template x-for="person in eligibleSignees" :key="person.id">
                            <div>
                                <div
                                    x-bind:style="signerColor(person.id)
                                        ? `display:flex; align-items:center; gap:0.5rem; padding:0.375rem 0.5rem; border-radius:0.5rem; border:1px solid ${signerColor(person.id)}; background:${signerColor(person.id)}1f;`
                                        : 'display:flex; align-items:center; gap:0.5rem; padding:0.375rem 0.5rem; border-radius:0.5rem; border:1px solid rgba(255,255,255,0.12); background:rgba(255,255,255,0.04);'"
                                >
                                    <button
                                        type="button"
                                        x-on:click="addSigner(person.id, person.name)"
                                        x-bind:disabled="signerColor(person.id) !== null"
                                        style="flex:1; display:flex; align-items:center; gap:0.5rem; min-width:0; text-align:left; background:none; border:none; padding:0; cursor:pointer;"
                                    >
                                        <span
                                            x-text="initials(person.name)"
                                            x-bind:style="`flex-shrink:0; width:1.75rem; height:1.75rem; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.6875rem; font-weight:700; color:#fff; background:${signerColor(person.id) || 'rgba(255,255,255,0.18)'};`"
                                        ></span>
                                        <span
                                            x-text="person.name"
                                            x-bind:style="`font-size:0.875rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#fff; font-weight:${signerColor(person.id) ? '600' : '500'};`"
                                        ></span>
                                    </button>
                                    <button
                                        type="button"
                                        x-show="signerColor(person.id) !== null"
                                        x-on:click="removeSigner(person.id)"
                                        style="font-size: 0.75rem; color: #F87171; flex-shrink: 0;"
                                    >
                                        Remove
                                    </button>
                                </div>
                                <div x-show="signerColor(person.id) !== null" style="padding: 0.375rem 0 0.25rem 2.25rem; display: flex; flex-direction: column; gap: 0.25rem;">
                                    <template x-for="box in signerBoxesFor(person.id)" :key="box._index">
                                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: var(--gray-500);">
                                            <span x-text="`Page ${box.page}`"></span>
                                            <button type="button" x-on:click="removeSignerBox(box._index)" style="color: var(--danger-600);">Remove</button>
                                        </div>
                                    </template>
                                    <button
                                        type="button"
                                        x-on:click="addSignerBox(person.id)"
                                        style="text-align: left; font-size: 0.75rem; color: var(--primary-500);"
                                    >
                                        <span x-text="`+ Add a box here (page ${currentPage})`"></span>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <p x-show="eligibleSignees.length === 0" style="font-size: 0.8125rem; color: var(--gray-500);">
                            No users have the Signee role yet — assign it from Settings → Roles.
                        </p>
                    </div>
                </x-filament::section>

                <x-filament::section compact heading="Organization Stamp" style="margin-top: 1rem;">
                    {{--
                        One button per stamp the organization has
                        configured (see Settings → Organization) — the
                        uploader picks which one to place rather than a
                        single implicit stamp. addStamp(slot) tags the
                        placed box with that slot so DocumentStampService
                        composites the right image at signing time. Only
                        one stamp is allowed per document, so the buttons
                        disappear once one's placed — Remove it below to
                        choose a different one instead of stacking both.
                    --}}
                    <template x-if="stamps.length === 0 && organizationStamps.length">
                        <div style="display: flex; flex-direction: column; gap: 0.375rem;">
                            <template x-for="orgStamp in organizationStamps" :key="orgStamp.slot">
                                <x-filament::button
                                    color="gray"
                                    x-on:click="addStamp(orgStamp.slot)"
                                    icon="heroicon-o-plus"
                                    style="width: 100%;"
                                >
                                    <span x-text="`Add ${orgStamp.label}`"></span>
                                </x-filament::button>
                            </template>
                        </div>
                    </template>
                    <p x-show="stamps.length === 0 && !organizationStamps.length" style="margin: 0; font-size: 0.8125rem; color: var(--gray-500);">
                        No organization stamps configured yet — set one up in Settings → Organization.
                    </p>
                    <template x-if="stamps.length">
                        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                            <template x-for="(stamp, index) in stamps" :key="index">
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                                    <span x-text="`${stampLabel(stamp.slot)} — page ${stamp.page}`"></span>
                                    <button type="button" x-on:click="removeStamp(index)" style="color: var(--danger-600);">Remove</button>
                                </div>
                            </template>
                        </div>
                    </template>
                </x-filament::section>

                <div style="margin-top: 1rem;">
                    <x-filament::button
                        x-on:click="send()"
                        x-bind:disabled="sending || signees.length === 0"
                        style="width: 100%;"
                    >
                        <span x-show="!sending">Send to Sign</span>
                        <span x-show="sending">Sending…</span>
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }

        document.addEventListener('alpine:init', () => {
            // Assigned to signers in the order they're added — also their
            // sequential signing order — and reused for that signer's
            // sign-box border/label so the signee list and the document
            // stay visually linked (see resources' Signees section).
            const PALETTE = ['#0E7A82', '#B8934A', '#6D4AB8', '#2E86DE', '#C0392B', '#27AE60', '#D35400', '#8E44AD'];

            Alpine.data('documentPlacement', (config) => {
                // pdf.js's document/page objects use native JS private
                // class fields (#foo) — Alpine's reactivity wraps every
                // property on the returned object in a Proxy, and a
                // Proxy around an object breaks its own private-field
                // access ("Cannot read private member from an object
                // whose class did not declare it"). Kept as plain
                // closure variables instead of `this.*` so pdf.js never
                // sees a proxied version of its own objects.
                let pdfDoc = null;
                let textCache = {};
                let loaded = false;
                let renderTask = null;

                return {
                fileBase64: config.fileBase64,
                pageCount: config.pageCount,
                eligibleSignees: config.eligibleSignees,
                organizationStamps: config.organizationStamps,
                currentPage: 1,
                // The current page's unscaled (scale: 1) size in PDF
                // points — needed to size a "square" stamp box, since a
                // page usually isn't itself square: a box that's square
                // in x/y *fraction* terms would look and print
                // rectangular unless corrected for the page's aspect
                // ratio (see addStamp()/onResize()).
                pageBaseWidth: null,
                pageBaseHeight: null,
                // Identity/order lives here, one entry per selected
                // signee. Their actual sign boxes live separately in
                // signerBoxes — a signee can have none, one, or several
                // (e.g. initials on every page plus a full signature on
                // the last), matched up by user_id, mirroring how
                // `stamps` already holds however many placements exist
                // rather than nesting them under an "owner".
                signees: [],
                signerBoxes: [],
                stamps: [],
                drag: null,
                resize: null,
                signingMode: 'parallel',
                sending: false,

                async init() {
                    // Livewire can re-render (and re-run x-init on) this
                    // block more than once in quick succession right
                    // after the upload settles — without this guard,
                    // pdf.js would load the document twice and race two
                    // renders onto the same canvas.
                    if (loaded) {
                        return;
                    }
                    loaded = true;

                    const raw = window.atob(this.fileBase64);
                    const bytes = new Uint8Array(raw.length);
                    for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
                    pdfDoc = await pdfjsLib.getDocument({ data: bytes }).promise;
                    await this.renderPage();
                },

                async renderPage() {
                    // Cancel an in-flight render before starting another
                    // — pdf.js throws if two render() calls target the
                    // same canvas concurrently (e.g. clicking the page
                    // nav arrows quickly).
                    if (renderTask) {
                        renderTask.cancel();
                    }

                    const page = await pdfDoc.getPage(this.currentPage);
                    const containerWidth = this.$refs.pageContainer.clientWidth || 800;
                    const baseViewport = page.getViewport({ scale: 1 });
                    this.pageBaseWidth = baseViewport.width;
                    this.pageBaseHeight = baseViewport.height;
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
                        if (e?.name !== 'RenderingCancelledException') {
                            throw e;
                        }
                    } finally {
                        renderTask = null;
                    }
                },

                async getPageText(pageNum) {
                    if (textCache[pageNum]) return textCache[pageNum];
                    const page = await pdfDoc.getPage(pageNum);
                    const viewport = page.getViewport({ scale: 1 });
                    const content = await page.getTextContent();
                    textCache[pageNum] = { items: content.items, viewport };
                    return textCache[pageNum];
                },

                async findNameOnPages(name) {
                    const needle = name.trim().toLowerCase();
                    if (!needle) return null;
                    for (let p = 1; p <= this.pageCount; p++) {
                        const { items, viewport } = await this.getPageText(p);
                        for (const item of items) {
                            if (item.str && item.str.toLowerCase().includes(needle)) {
                                const x = item.transform[4];
                                const yTop = viewport.height - item.transform[5];
                                const w = Math.max(item.width || 60, 70);
                                const h = Math.max((item.height || 12) * 3, 28);
                                return {
                                    page: p,
                                    x: Math.max(0, x / viewport.width),
                                    y: Math.max(0, (yTop - h) / viewport.height),
                                    w: Math.min(0.35, w / viewport.width),
                                    h: Math.min(0.15, h / viewport.height),
                                };
                            }
                        }
                    }
                    return null;
                },

                initials(name) {
                    const parts = name.trim().split(/\s+/).filter(Boolean);
                    return parts.map(p => p[0]).join('').slice(0, 2).toUpperCase();
                },

                signerColor(userId) {
                    return this.signees.find(s => s.user_id === userId)?.color ?? null;
                },

                signeeName(userId) {
                    return this.signees.find(s => s.user_id === userId)?.name ?? '';
                },

                async addSigner(id, name) {
                    if (this.signees.some(s => s.user_id === id)) return;
                    const color = PALETTE[this.signees.length % PALETTE.length];
                    this.signees.push({ user_id: id, name, color });

                    let placement = await this.findNameOnPages(name);
                    if (!placement) {
                        const stackIndex = this.signees.length - 1;
                        placement = {
                            page: this.currentPage,
                            x: 0.08,
                            y: Math.min(0.85, 0.6 + stackIndex * 0.1),
                            w: 0.2,
                            h: 0.08,
                        };
                    }
                    this.signerBoxes.push({ user_id: id, ...placement });
                    if (placement.page !== this.currentPage) {
                        this.currentPage = placement.page;
                        await this.renderPage();
                    }
                },

                removeSigner(id) {
                    this.signees = this.signees.filter(s => s.user_id !== id);
                    this.signerBoxes = this.signerBoxes.filter(b => b.user_id !== id);
                },

                // Adds another box for a signee who's already selected —
                // always on whatever page is currently being viewed, so
                // flipping to a later page and clicking this places it
                // there (unlike the first box, which tries to
                // auto-detect the signee's name in the text).
                addSignerBox(userId) {
                    const existingOnPage = this.signerBoxes.filter(b => b.user_id === userId && b.page === this.currentPage).length;
                    this.signerBoxes.push({
                        user_id: userId,
                        page: this.currentPage,
                        x: 0.08,
                        y: Math.min(0.85, 0.6 + existingOnPage * 0.1),
                        w: 0.2,
                        h: 0.08,
                    });
                },

                removeSignerBox(index) {
                    this.signerBoxes.splice(index, 1);
                },

                // Every box belonging to one signee, across all pages
                // (not just the one currently in view) — used by the
                // Signees sidebar list so a box on another page can
                // still be found and removed.
                signerBoxesFor(userId) {
                    return this.signerBoxes
                        .map((b, i) => ({ ...b, _index: i }))
                        .filter(b => b.user_id === userId);
                },

                addStamp(slot) {
                    // Only one stamp is allowed per document — the "Add"
                    // buttons already hide once one exists, this is just
                    // the same rule enforced at the source of truth.
                    if (this.stamps.length > 0) return;

                    const ratio = this.pageBaseWidth && this.pageBaseHeight ? this.pageBaseWidth / this.pageBaseHeight : 1;
                    const w = 0.14;
                    this.stamps.push({
                        slot,
                        page: this.currentPage,
                        x: 0.65,
                        y: 0.55,
                        w,
                        h: w * ratio,
                    });
                },

                removeStamp(index) {
                    this.stamps.splice(index, 1);
                },

                stampLabel(slot) {
                    const match = this.organizationStamps.find(s => s.slot === slot);
                    return match ? match.label : 'Stamp';
                },

                visibleSignerBoxes() {
                    return this.signerBoxes
                        .map((b, i) => ({ ...b, _index: i, color: this.signerColor(b.user_id), name: this.signeeName(b.user_id) }))
                        .filter(b => b.page === this.currentPage);
                },

                visibleStamps() {
                    return this.stamps
                        .map((s, i) => ({ ...s, _index: i }))
                        .filter(s => s.page === this.currentPage);
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

                startDrag(event, type, key) {
                    event.preventDefault();
                    const rect = this.$refs.pageContainer.getBoundingClientRect();
                    const point = event.touches ? event.touches[0] : event;
                    const item = type === 'signer' ? this.signerBoxes[key] : this.stamps[key];
                    if (!item) return;
                    const boxLeft = item.x * rect.width;
                    const boxTop = item.y * rect.height;
                    this.drag = {
                        type,
                        key,
                        offsetX: point.clientX - rect.left - boxLeft,
                        offsetY: point.clientY - rect.top - boxTop,
                    };
                    const move = (e) => this.onDrag(e, rect);
                    const up = () => {
                        window.removeEventListener('mousemove', move);
                        window.removeEventListener('mouseup', up);
                        window.removeEventListener('touchmove', move);
                        window.removeEventListener('touchend', up);
                        this.drag = null;
                    };
                    window.addEventListener('mousemove', move);
                    window.addEventListener('mouseup', up);
                    window.addEventListener('touchmove', move, { passive: false });
                    window.addEventListener('touchend', up);
                },

                onDrag(event, rect) {
                    if (!this.drag) return;
                    event.preventDefault();
                    const point = event.touches ? event.touches[0] : event;
                    const item = this.drag.type === 'signer' ? this.signerBoxes[this.drag.key] : this.stamps[this.drag.key];
                    if (!item) return;
                    let x = (point.clientX - rect.left - this.drag.offsetX) / rect.width;
                    let y = (point.clientY - rect.top - this.drag.offsetY) / rect.height;
                    x = Math.max(0, Math.min(1 - item.w, x));
                    y = Math.max(0, Math.min(1 - item.h, y));
                    item.x = x;
                    item.y = y;
                },

                // Resizing keeps the box's top-left corner fixed and
                // drags its bottom-right corner. A stamp is locked to a
                // physically square aspect (see pageBaseWidth/Height
                // above) — a signer's sign box resizes freely.
                startResize(event, type, key) {
                    event.preventDefault();
                    const rect = this.$refs.pageContainer.getBoundingClientRect();
                    const item = type === 'signer' ? this.signerBoxes[key] : this.stamps[key];
                    if (!item) return;
                    const ratio = type === 'stamp' && this.pageBaseWidth && this.pageBaseHeight
                        ? this.pageBaseWidth / this.pageBaseHeight
                        : null;
                    this.resize = { type, key, ratio };
                    const move = (e) => this.onResize(e, rect);
                    const up = () => {
                        window.removeEventListener('mousemove', move);
                        window.removeEventListener('mouseup', up);
                        window.removeEventListener('touchmove', move);
                        window.removeEventListener('touchend', up);
                        this.resize = null;
                    };
                    window.addEventListener('mousemove', move);
                    window.addEventListener('mouseup', up);
                    window.addEventListener('touchmove', move, { passive: false });
                    window.addEventListener('touchend', up);
                },

                onResize(event, rect) {
                    if (!this.resize) return;
                    event.preventDefault();
                    const point = event.touches ? event.touches[0] : event;
                    const item = this.resize.type === 'signer' ? this.signerBoxes[this.resize.key] : this.stamps[this.resize.key];
                    if (!item) return;

                    const minW = 0.04;
                    const minH = 0.03;
                    let w = Math.max(minW, Math.min(1 - item.x, (point.clientX - rect.left) / rect.width - item.x));
                    let h;

                    if (this.resize.ratio) {
                        h = w * this.resize.ratio;
                        const maxH = 1 - item.y;
                        if (h > maxH) {
                            h = maxH;
                            w = h / this.resize.ratio;
                        }
                    } else {
                        h = Math.max(minH, Math.min(1 - item.y, (point.clientY - rect.top) / rect.height - item.y));
                    }

                    item.w = w;
                    item.h = h;
                },

                send() {
                    if (this.signees.length === 0 || this.sending) return;
                    this.sending = true;
                    this.$wire.sendToSign(
                        this.signingMode,
                        this.signees.map(s => ({ user_id: s.user_id })),
                        this.signerBoxes.map(b => ({ user_id: b.user_id, page: b.page, x: b.x, y: b.y, w: b.w, h: b.h })),
                        this.stamps.map(s => ({ slot: s.slot, page: s.page, x: s.x, y: s.y, w: s.w, h: s.h })),
                    ).then(() => {
                        this.sending = false;
                    }).catch(() => {
                        this.sending = false;
                    });
                },
                };
            });
        });
    </script>
</x-filament-panels::page>
