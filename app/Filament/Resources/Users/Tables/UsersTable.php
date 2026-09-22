<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
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
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('position')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                IconColumn::make('is_admin')
                    ->label('System Admin')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->hasRole('admin'))
                    ->tooltip('Can manage Users — separate from module access, see that column'),
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
                TernaryFilter::make('is_active')
                    ->label('Active'),
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
