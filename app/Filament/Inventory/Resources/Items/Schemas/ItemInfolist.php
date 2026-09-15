<?php

namespace App\Filament\Inventory\Resources\Items\Schemas;

use App\Filament\Inventory\Resources\Items\Tables\ItemsTable;
use App\Models\InventoryItem;
use App\Services\Inventory\ReorderCalculationService;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Overview tab only (spec 10.3) — Movements/Requests/Receipts/Settings
 * tabs need data that doesn't exist until Phases 3–5.
 */
class ItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // One full-width card: photo as a fixed left rail,
                // every other fact in a 2-column grid filling the rest
                // of the width beside it — not stacked on top, which
                // pushed the fields down and left the card unbalanced
                // against Stock below it.
                Section::make('Overview')
                    ->columns(3)
                    ->schema([
                        Group::make([
                            ImageEntry::make('image_path')
                                ->hiddenLabel()
                                ->imageHeight(320),
                        ])
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'pe-12'])
                            ->visible(fn (InventoryItem $record): bool => filled($record->image_path)),

                        Grid::make(2)
                            ->columnSpan(fn (InventoryItem $record): int => filled($record->image_path) ? 2 : 3)
                            ->schema([
                                TextEntry::make('code')->label('Code'),
                                TextEntry::make('name')->label('Name'),
                                TextEntry::make('category.name')->label('Category'),
                                TextEntry::make('uom.name')->label('Unit of measure'),
                                TextEntry::make('brand')->label('Brand')->placeholder('—'),
                                TextEntry::make('model_spec')->label('Model / Spec')->placeholder('—'),
                                TextEntry::make('defaultSupplier.name')->label('Default supplier')->placeholder('—'),
                                TextEntry::make('is_active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')->badge()->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                TextEntry::make('description')->label('Description')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Stock')
                    ->columnSpanFull()
                    ->columns(5)
                    ->schema([
                        TextEntry::make('on_hand')
                            ->label('On Hand')
                            ->state(fn (InventoryItem $record): string => (string) $record->stock->sum('on_hand'))
                            ->numeric(),
                        TextEntry::make('reserved')
                            ->label('Reserved')
                            ->state(fn (InventoryItem $record): string => (string) $record->stock->sum('reserved'))
                            ->numeric(),
                        TextEntry::make('available')
                            ->label('Available')
                            ->weight('bold')
                            ->state(fn (InventoryItem $record): string => (string) ($record->stock->sum('on_hand') - $record->stock->sum('reserved')))
                            ->numeric(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->state(fn (InventoryItem $record): string => ItemsTable::severityFor($record)['label'])
                            ->color(fn (InventoryItem $record): string => ItemsTable::severityFor($record)['color']),
                        TextEntry::make('days_of_cover')
                            ->label('Days of cover')
                            // Same ReorderCalculationService the Reorder
                            // Report already uses (spec 7.3) — null when
                            // there's no recent Issue history to average,
                            // shown as "—" rather than a fabricated 0.
                            ->state(function (InventoryItem $record): string {
                                $service = app(ReorderCalculationService::class);

                                return $service->daysOfCover($record, $service->availableFor($record)) ?? '—';
                            }),
                    ]),

                Section::make('Reorder settings')
                    ->columnSpanFull()
                    ->columns(5)
                    ->schema([
                        TextEntry::make('reorder_level')->label('Reorder level')->numeric(),
                        TextEntry::make('reorder_qty')->label('Reorder quantity')->numeric(),
                        TextEntry::make('max_level')->label('Max level')->numeric()->placeholder('—'),
                        TextEntry::make('lead_time_days')->label('Lead time (days)')->placeholder('—'),
                        TextEntry::make('is_stock_tracked')->label('Stock tracked')->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                    ]),
            ]);
    }
}
