<?php

namespace App\Filament\Inventory\Resources\GoodsReceipts\Schemas;

use App\Models\InventoryGoodsReceipt;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GoodsReceiptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Receipt')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('grn_no')->label('GRN No'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('receipt_date')->label('Receipt Date')->date(),
                        TextEntry::make('location.name')->label('Location'),
                        TextEntry::make('supplier.name')->label('Supplier'),
                        TextEntry::make('invoice_no')->label('Invoice No')->placeholder('—'),
                        TextEntry::make('invoice_date')->label('Invoice Date')->date()->placeholder('—'),
                        TextEntry::make('remarks')->label('Remarks')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('reversal_reason')->label('Reversal Reason')->placeholder('—')
                            ->visible(fn (InventoryGoodsReceipt $record): bool => filled($record->reversal_reason))
                            ->columnSpanFull(),
                    ]),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Code'),
                                TableColumn::make('Name'),
                                TableColumn::make('Quantity'),
                                TableColumn::make('UoM'),
                            ])
                            ->schema([
                                TextEntry::make('item.code')->label('Code'),
                                TextEntry::make('item.name')->label('Name'),
                                TextEntry::make('quantity')->numeric()->label('Quantity'),
                                TextEntry::make('item.uom.code')->label('UoM'),
                            ]),
                    ]),

                Section::make('Attachments')
                    ->visible(fn (InventoryGoodsReceipt $record): bool => $record->attachments->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('file_name')
                                    ->label('File')
                                    ->url(fn ($record): string => route('inventory.attachments.show', $record))
                                    ->openUrlInNewTab(),
                            ]),
                    ]),
            ]);
    }
}
