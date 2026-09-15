<?php

/**
 * Overrides just the `direction` key from Filament's own English default
 * (vendor/filament/filament/resources/lang/en/layout.php) — this is what
 * flips <html dir="..."> to RTL for the Bureau panel (see
 * base.blade.php: `dir="{{ __('filament-panels::layout.direction') }}"`).
 * Laravel merges this file over the package's English one
 * (array_replace_recursive), so every other framework chrome string
 * (Sign out, Notifications, sidebar labels, etc.) still falls back to
 * English until a full translation pass is done — deliberately left
 * that way for now rather than guessing at framework-wide strings.
 */
return [
    'direction' => 'rtl',
];
