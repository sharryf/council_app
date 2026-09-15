<?php

namespace App\Filament\Inventory\Resources\GoodsReceipts\Tables;

use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventorySupplier;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GoodsReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'location'])->withCount('lines')->orderByDesc('grn_no'))
            ->recordUrl(fn (InventoryGoodsReceipt $record): string => GoodsReceiptResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('grn_no')
                    ->label('GRN No')
                    ->searchable(['grn_no', 'invoice_no', 'po_no', 'delivery_note_no'])
                    ->sortable(),
                TextColumn::make('receipt_date')->label('Receipt Date')->date()->sortable(),
                TextColumn::make('supplier.name')->label('Supplier'),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->state(fn (InventoryGoodsReceipt $record): string => $record->invoice_no ?: ($record->po_no ?: ($record->delivery_note_no ?: '—'))),
                TextColumn::make('lines_count')->label('Lines'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => InventorySupplier::query()->pluck('name', 'id')),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(\App\Enums\InventoryGoodsReceiptStatus::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (InventoryGoodsReceipt $record): bool => GoodsReceiptResource::canDelete($record)),
                ]),
            ])
            ->emptyStateHeading('No goods receipts yet')
            ->emptyStateDescription('Receive your first delivery to bring stock in.')
            ->emptyStateIcon(Heroicon::OutlinedInboxArrowDown)
            ->emptyStateActions([
                GoodsReceiptResource::createAction()
                    ->visible(fn (): bool => GoodsReceiptResource::canCreate()),
            ]);
    }
}
