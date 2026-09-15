<?php

/**
 * Module registry.
 *
 * Each entry is one future application module. `resource` is the
 * Filament Resource class for that module, or null if it hasn't been
 * built yet — the dashboard's module grid (see
 * App\Filament\Widgets\ModuleCardsWidget) uses this to decide between a
 * clickable card and a "Coming soon" one.
 *
 * Every authenticated user has access to every module here at at least
 * Viewer level — there's no per-module on/off switch. A Filament
 * Resource joins a module by using App\Filament\Concerns\HasModuleAccess
 * and declaring:
 *
 *     protected static ?string $moduleKey = 'inventory';
 *
 * That trait gates canAccess() / shouldRegisterNavigation() (module key
 * must resolve to an entry here) and the default Create/Edit/Delete
 * authorization (Editor+, per App\Models\User::roleFor()). The `admin`
 * role is Approver everywhere — see "Module access levels" in the
 * README for the full Viewer/Editor/Approver design.
 *
 * To add module #N later:
 *   1. Add an entry here with a unique key, a label, an icon, and a
 *      one-line description.
 *   2. Build Resources under app/Filament/Resources/<ModuleName>/, each
 *      using HasModuleAccess with $moduleKey set to the key you added,
 *      then set this entry's `resource` to that Resource's class name.
 * No other file needs to change.
 *
 * A module can instead be its own separate Filament panel (`panel` key
 * instead of `resource`) rather than a Resource inside the main admin
 * panel — see `bureau` below, which needs a fully RTL/Dhivehi layout
 * Filament's shared per-panel chrome can't mix mid-panel with the rest
 * of the (English/LTR) app. ModuleCardsWidget resolves the dashboard
 * card's URL from whichever key is present.
 */

return [

    'document-signing' => [
        'label' => 'Document Signing',
        'description' => 'Route documents for signature and track approval status.',
        'icon' => 'heroicon-o-pencil-square',
        'resource' => \App\Filament\Resources\DocumentSigning\Documents\DocumentResource::class,
    ],

    'bureau' => [
        'label' => 'Bureau',
        'description' => 'Council meeting minutes, agendas, and records.',
        'icon' => 'heroicon-o-building-library',
        'panel' => 'bureau',
    ],

    'inventory' => [
        'label' => 'Inventory',
        'description' => 'Track office stock and supplies.',
        'icon' => 'heroicon-o-archive-box',
        'panel' => 'inventory',
    ],

    'assets' => [
        'label' => 'Assets',
        'description' => 'Manage and track office equipment and assets.',
        'icon' => 'heroicon-o-computer-desktop',
        'panel' => 'assets',
    ],

    'registries' => [
        'label' => 'Registries',
        'description' => 'Maintain official registries and records.',
        'icon' => 'heroicon-o-clipboard-document-list',
        'resource' => null,
    ],

    'permits' => [
        'label' => 'Permits',
        'description' => 'Issue and track permits and licenses.',
        'icon' => 'heroicon-o-document-check',
        'resource' => null,
    ],

    'hr-payroll' => [
        'label' => 'HR & Payroll',
        'description' => 'Staff records, attendance, and payroll.',
        'icon' => 'heroicon-o-banknotes',
        'resource' => null,
    ],

    'events' => [
        'label' => 'Events & Activities',
        'description' => 'Plan and track council events and activities.',
        'icon' => 'heroicon-o-calendar-days',
        'resource' => null,
    ],

];
