{{--
    Approving an edit request that touches the photo means comparing two
    images, not two lines of text like every other field — this renders
    inside AssetEditRequestResource::approveAction()'s confirmation
    modal only when the request's proposed_changes include one.
--}}
<div style="display: flex; gap: 1rem;">
    <div style="flex: 1; min-width: 0;">
        <p style="font-size: 0.75rem; font-weight: 600; color: var(--gray-500); margin: 0 0 0.375rem;">Current</p>
        @if ($currentUrl)
            <img src="{{ $currentUrl }}" alt="Current photo" style="width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 0.5rem;">
        @else
            <p style="font-size: 0.8125rem; color: var(--gray-500);">No photo yet</p>
        @endif
    </div>
    <div style="flex: 1; min-width: 0;">
        <p style="font-size: 0.75rem; font-weight: 600; color: var(--gray-500); margin: 0 0 0.375rem;">Proposed</p>
        @if ($proposedDataUri)
            <img src="{{ $proposedDataUri }}" alt="Proposed photo" style="width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 0.5rem;">
        @else
            <p style="font-size: 0.8125rem; color: var(--gray-500);">Preview unavailable</p>
        @endif
    </div>
</div>
