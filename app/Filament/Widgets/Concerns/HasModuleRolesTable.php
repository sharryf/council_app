<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\ModuleAccessLevel;
use App\Models\User;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * An inline-editable Viewer/Editor/Approver table for one module, for
 * use on a Filament\Widgets\TableWidget. Add `use HasModuleRolesTable;`
 * and declare:
 *
 *     protected static ?string $moduleKey = 'inventory';
 *
 * Only lists users who currently have access to the module (see
 * User::canAccessModule(), managed from UserResource) — a user with no
 * access to a module has nothing to set a level for. `admin` users are
 * excluded too: they're Approver everywhere unconditionally (see
 * User::roleFor()), so a level row for them would be editable but
 * meaningless.
 */
trait HasModuleRolesTable
{
    // $moduleKey is declared by the consuming class, not here — PHP
    // requires a trait's static property and the using class's
    // override to share the exact same default value (see
    // App\Filament\Concerns\HasModuleAccess for the same rule).

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(
                User::query()
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'))
                    ->where(fn ($query) => $query
                        ->whereNull('module_access')
                        ->orWhereJsonContains('module_access', static::$moduleKey))
                    ->orderBy('name'),
            )
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('position')->placeholder('—'),
                TextColumn::make('email')->label('Email address'),
                SelectColumn::make('level')
                    ->label('Level')
                    ->options(collect(ModuleAccessLevel::cases())
                        ->mapWithKeys(fn (ModuleAccessLevel $case): array => [$case->value => $case->getLabel()])
                        ->all())
                    ->selectablePlaceholder(false)
                    ->getStateUsing(fn (User $record): string => $record->roleFor(static::$moduleKey)->value)
                    ->updateStateUsing(function (User $record, string $state): void {
                        if ($state === ModuleAccessLevel::Viewer->value) {
                            $record->moduleLevels()->where('module', static::$moduleKey)->delete();

                            return;
                        }

                        $record->moduleLevels()->updateOrCreate(
                            ['module' => static::$moduleKey],
                            ['level' => $state],
                        );
                    }),
            ]);
    }
}
