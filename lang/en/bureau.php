<?php

/**
 * English fallback for App\Enums\BureauRole::getLabel() — the rest of
 * the Bureau module's UI is Dhivehi-only by design (see lang/dv/bureau.php
 * and SetBureauLocale, which forces that panel's locale) and has no
 * English translation, but role labels are also shown outside that
 * forced-locale context: the Users page's "Module Roles" section (see
 * App\Filament\Resources\Users\Schemas\UserForm), reached from the
 * main, English-locale admin panel. Without this file, that section
 * would show raw keys like "bureau.roles.president" instead of a
 * label, since Laravel falls back to echoing the key when no
 * translation resolves in the active locale.
 */
return [
    'roles' => [
        'president' => 'President',
        'councillor' => 'Councillor',
        'participant' => 'Participant',
        'bureau_admin' => 'Bureau Admin',
        'staff' => 'Staff',
        'module_admin' => 'Module Admin',
    ],
];
