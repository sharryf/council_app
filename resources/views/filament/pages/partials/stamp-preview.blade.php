@if ($dataUri)
    <img
        src="{{ $dataUri }}"
        alt="Organization stamp preview"
        style="max-height: 120px; background: white; border: 1px dashed var(--gray-400); border-radius: 0.5rem; padding: 0.5rem;"
    >
@else
    <p class="fi-fo-field-wrp-helper-text text-sm text-gray-500 dark:text-gray-400">
        No stamp uploaded yet.
    </p>
@endif
