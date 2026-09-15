@php($documents = $this->eligibleDocumentsPreview())
@php($totalCount = $this->totalEligibleCount())
@php($shown = $documents->count())
<div style="margin-top: 1.5rem;">
    <h3 style="font-size: 1rem; font-weight: 600; margin: 0 0 0.75rem;">
        {{ $totalCount }} {{ Str::plural('document', $totalCount) }} currently match
    </h3>

    @if ($totalCount === 0)
        <p style="font-size: 0.875rem; color: var(--gray-500); margin: 0;">
            Nothing matches this retention period right now.
        </p>
    @else
        <div style="border: 1px solid var(--gray-400); border-radius: 0.5rem; overflow: hidden;">
            @foreach ($documents as $document)
                <div
                    style="
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 1rem;
                        padding: 0.625rem 0.875rem;
                        font-size: 0.875rem;
                        {{ ! $loop->last ? 'border-bottom: 1px solid var(--gray-400);' : '' }}
                    "
                >
                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        {{ $document->displayTitle() }}
                    </span>
                    <span style="flex-shrink: 0; color: var(--gray-500); white-space: nowrap;">
                        {{ $document->status->getLabel() }} · {{ $document->created_at->format('M j, Y') }}
                    </span>
                </div>
            @endforeach
        </div>

        @if ($totalCount > $shown)
            <p style="font-size: 0.8125rem; color: var(--gray-500); margin: 0.5rem 0 0;">
                + {{ $totalCount - $shown }} more not shown.
            </p>
        @endif
    @endif
</div>
