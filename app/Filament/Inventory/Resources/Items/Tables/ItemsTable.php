<?php

namespace App\Filament\Inventory\Resources\Items\Tables;

use App\Filament\Inventory\Resources\Items\ItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventorySupplier;
use App\Services\Inventory\ReorderCalculationService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItemsTable
{
    /**
     * Spec 7.2's reorder severity buckets — same shape everywhere an
     * item's stock health is shown (this list, the view page, the
     * dashboard reorder widget). Delegates to
     * ReorderCalculationService::severityForAvailable() (Phase 6) so
     * the thresholds live in exactly one place.
     *
     * @return array{label: string, color: string}
     */
    public static function severityFor(InventoryItem $item): array
    {
        if (! $item->is_stock_tracked) {
            return ['label' => 'Not tracked', 'color' => 'gray'];
        }

        $service = app(ReorderCalculationService::class);

        return $service->severityForAvailable($service->availableFor($item), (string) $item->reorder_level);
    }

    /**
     * Keyed off the category relationship rather than parsing the item
     * code's own prefix — codes aren't a consistent length (STAT-0001
     * vs. ST-0020 for the same category), so this stays correct
     * regardless of how a code happens to be formatted. Unmapped/future
     * categories fall back to grey rather than erroring.
     */
    private static function categoryColor(InventoryItem $item): string
    {
        return match ($item->category?->code) {
            'ST' => 'info',
            'EL' => 'warning',
            'CL' => 'success',
            'IT' => 'primary',
            'OT' => 'gray',
            default => 'gray',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['category', 'uom', 'stock'])->orderBy('name'))
            ->recordUrl(fn (InventoryItem $record): string => ItemResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn (InventoryItem $record, string $state): string => sprintf(
                        '<span class="fi-color fi-color-%s fi-text-color-600 dark:fi-text-color-400">%s</span>%s',
                        e(self::categoryColor($record)),
                        e(substr($state, 0, 2)),
                        e(substr($state, 2)),
                    )),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(['name', 'description', 'brand', 'model_spec'])
                    ->wrap()
                    ->grow()
                    ->color(fn (InventoryItem $record): ?string => $record->is_active ? null : 'gray'),
                TextColumn::make('uom.code')
                    ->label('UoM'),
                TextColumn::make('on_hand')
                    ->label('On Hand')
                    ->state(fn (InventoryItem $record): string => (string) $record->stock->sum('on_hand'))
                    ->numeric(),
                TextColumn::make('reserved')
                    ->label('Reserved')
                    ->state(fn (InventoryItem $record): string => (string) $record->stock->sum('reserved'))
                    ->numeric(),
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (InventoryItem $record): string => (string) ($record->stock->sum('on_hand') - $record->stock->sum('reserved')))
                    ->numeric()
                    ->weight('bold')
                    ->color(fn (InventoryItem $record): string => self::severityFor($record)['color']),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->multiple()
                    ->options(fn () => InventoryItemCategory::query()->pluck('name', 'id')),
                SelectFilter::make('default_supplier_id')
                    ->label('Supplier')
                    ->options(fn () => InventorySupplier::query()->pluck('name', 'id')),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (InventoryItem $record): bool => ItemResource::canDelete($record)),
                ]),
            ])
            ->emptyStateHeading('No items yet')
            ->emptyStateDescription('Add your first item to start tracking stock.')
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Add New Item')
                    ->visible(fn (): bool => ItemResource::canCreate()),
            ]);
    }
}
