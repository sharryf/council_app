{{--
    Filament's own logo component shows either the brand image OR the
    brand name text, never both (vendor/filament/filament/resources/
    views/components/logo.blade.php) — passing this view as the panel's
    brandLogo (it implements Htmlable, which that component treats as
    arbitrary content rather than an <img> src) is how both appear
    together. It renders inside that component's own `.fi-logo` div, so
    the "Oceancy" text inherits that class's font styling for free —
    only the image size and the gap between them are set here.

    $suffix: the module name shown after "Oceancy" (e.g. "Inventory"),
    or null on the main panel, which shows the bare brand name.
--}}
@php
    $suffix ??= null;
@endphp

<span style="display: inline-flex; align-items: center; gap: 0.5rem;">
    <img
        src="{{ asset('images/oceancy-logo-brand.png') }}"
        alt="Oceancy logo"
        style="height: 1.75rem; width: auto; display: block;"
    />
    <span>Oceancy{{ $suffix ? " {$suffix}" : '' }}</span>
</span>
