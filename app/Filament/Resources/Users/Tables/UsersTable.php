<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('position')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('access')
                    ->label('Module access')
                    ->getStateUsing(fn (User $record): string => static::describeAccess($record))
                    ->wrap(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function describeAccess(User $user): string
    {
        if ($user->hasRole('admin')) {
            return 'Admin — every module';
        }

        if ($user->module_access === null) {
            return 'All modules';
        }

        $modules = collect(config('modules'));

        $labels = collect($user->module_access)
            ->map(fn (string $key) => $modules[$key]['label'] ?? $key)
            ->join(', ');

        return filled($labels) ? $labels : 'No modules';
    }
}
