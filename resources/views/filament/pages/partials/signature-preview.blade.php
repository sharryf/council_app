@if ($dataUri)
    <img
        src="{{ $dataUri }}"
        alt="Your saved signature"
        style="max-height: 60px; background: white; border: 1px dashed var(--gray-400); border-radius: 0.5rem; padding: 0.5rem;"
    >
@else
    <p class="fi-fo-field-wrp-helper-text text-sm text-gray-500 dark:text-gray-400">
        No signature saved yet.
    </p>
@endif
