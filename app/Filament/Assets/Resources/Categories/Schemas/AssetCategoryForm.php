<?php

namespace App\Filament\Assets\Resources\Categories\Schemas;

use App\Models\AssetCategory;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AssetCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Only top-level categories are offered as a parent —
                // caps depth at 2 by construction, matching the spec's
                // "max depth 2" rule without needing a runtime check.
                Select::make('parent_id')
                    ->label('Parent category')
                    ->helperText('Leave empty to create a top-level category.')
                    ->options(fn (?AssetCategory $record): array => AssetCategory::query()
                        ->whereNull('parent_id')
                        ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->live()
                    // Safety net behind the options() list above — max
                    // depth 2 is a hard rule (implementation plan
                    // section 3.1), not just a UI nicety.
                    ->rule(fn () => function (string $attribute, $value, Closure $fail): void {
                        if ($value && AssetCategory::find($value)?->parent_id !== null) {
                            $fail('Categories can only be nested two levels deep.');
                        }
                    }),
                TextInput::make('gl_code')
                    ->label('GL code')
                    ->helperText('The chart-of-accounts code this category posts under, e.g. "421001".')
                    ->maxLength(20)
                    ->visible(fn ($get): bool => blank($get('parent_id'))),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(120)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, $get) => $rule
                            ->where('parent_id', $get('parent_id'))
                            ->whereNull('deleted_at'),
                    ),
                TextInput::make('asset_class_code')
                    ->label('Asset class code')
                    ->helperText('The code printed on labels/registers for assets in this sub-category, e.g. "Z001".')
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->visible(fn ($get): bool => filled($get('parent_id'))),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText(fn (?AssetCategory $record): string => $record && $record->parent_id === null
                        ? 'Inactive categories stay on existing assets but drop out of new create/edit dropdowns. Deactivating a top-level category also deactivates its sub-categories.'
                        : 'Inactive categories stay on existing assets but drop out of new create/edit dropdowns.'),
            ]);
    }
}
