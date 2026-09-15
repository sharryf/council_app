<?php

namespace App\Filament\Inventory\Widgets;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Models\InventoryItem;
use App\Services\Inventory\ReorderCalculationService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Spec 10.8's "second explicit requirement" — items needing reorder,
 * with severity/shortfall/suggested-qty/days-of-cover from
 * ReorderCalculationService (spec 7.2/7.3). Cached 60s per spec's
 * dashboard caching rule (this widget, unlike PendingApprovalWidget,
 * isn't the kind of thing where a minute-old count causes a real
 * mistake).
 */
class ReorderAlertsWidget extends TableWidget
{
    use HasInventoryRoleAccess;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public function table(Table $table): Table
    {
        $items = $this->cachedCandidates();

        return $table
            ->heading('Items Requiring Reorder')
            ->records(fn (): array => $items->all())
            ->columns([
                TextColumn::make('code')->label('Code'),
                TextColumn::make('name')->label('Name'),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (InventoryItem $record): string => app(ReorderCalculationService::class)->availableFor($record))
                    ->numeric(),
                TextInputColumn::make('reorder_level')
                    ->label('Reorder Level')
                    ->type('number')
                    ->rules(['numeric', 'min:0'])
                    ->updateStateUsing(function (InventoryItem $record, $state) {
                        $record->update(['reorder_level' => $state]);
                        Cache::forget('inventory.dashboard.reorder-candidate-ids');

                        return $state;
                    }),
                TextColumn::make('shortfall')
                    ->label('Shortfall')
                    ->state(function (InventoryItem $record) {
                        $available = app(ReorderCalculationService::class)->availableFor($record);

                        return bcsub((string) $record->reorder_level, $available, 3);
                    })
                    ->numeric(),
                TextColumn::make('suggested_qty')
                    ->label('Suggested Qty')
                    ->state(function (InventoryItem $record) {
                        $service = app(ReorderCalculationService::class);

                        return $service->suggestedQty($record, $service->availableFor($record));
                    })
                    ->numeric(),
                TextColumn::make('days_of_cover')
                    ->label('Days of Cover')
                    ->state(function (InventoryItem $record) {
                        $service = app(ReorderCalculationService::class);

                        return $service->daysOfCover($record, $service->availableFor($record)) ?? '—';
                    }),
                TextColumn::make('defaultSupplier.name')->label('Supplier')->placeholder('—'),
                TextColumn::make('last_received_date')->label('Last Received')->date()->placeholder('—'),
                TextColumn::make('status')
                    ->label('Severity')
                    ->badge()
                    ->state(function (InventoryItem $record): string {
                        $service = app(ReorderCalculationService::class);

                        return $service->severityForAvailable($service->availableFor($record), (string) $record->reorder_level)['label'];
                    })
                    ->color(function (InventoryItem $record): string {
                        $service = app(ReorderCalculationService::class);

                        return $service->severityForAvailable($service->availableFor($record), (string) $record->reorder_level)['color'];
                    }),
            ])
            ->headerActions([
                // A plain action->url() to a real download route, not
                // ->action(closure) — Filament's action-dispatch
                // discards a closure's return value (actions are for
                // side-effects/notifications), so a StreamedResponse
                // returned from ->action() never reaches the browser as
                // a download. Same authenticated-route shape as
                // InventoryAttachmentController/IssueSlipController.
                Action::make('exportBuyingList')
                    ->label('Export Buying List')
                    ->url(route('inventory.reorder.buying-list'))
                    ->openUrlInNewTab(),
            ])
            ->paginated([10, 25, 50]);
    }

    /**
     * Caches only the (trivially serializable) candidate item IDs, not
     * the Eloquent Collection itself — caching a Collection of models
     * with eager-loaded relations through the database/redis cache
     * stores hits a PHP unserialize()/class-autoloading ordering issue
     * that the array cache store (used in tests) never surfaces, so
     * this only broke live. Re-hydrates full models (with the same
     * eager loads the widget's columns need) from the cached ID list,
     * preserving ReorderCalculationService's own severity/days-of-cover
     * sort order.
     *
     * @return Collection<int, InventoryItem>
     */
    private function cachedCandidates(): Collection
    {
        $ids = Cache::remember(
            'inventory.dashboard.reorder-candidate-ids',
            60,
            fn (): array => app(ReorderCalculationService::class)->candidateItems()->pluck('id')->all(),
        );

        $items = InventoryItem::query()
            ->with(['category', 'uom', 'defaultSupplier', 'stock'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)->map(fn (int $id) => $items->get($id))->filter()->values();
    }
}
