<?php

namespace App\Filament\Inventory\Resources\GoodsReceipts\Schemas;

use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Filament\Inventory\Resources\Suppliers\Schemas\SupplierForm;
use App\Models\InventorySetting;
use App\Models\InventorySupplier;
use App\Models\InventoryUnitOfMeasure;
use App\Services\Inventory\ItemCreationService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class GoodsReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('receipt_date')
                    ->label('Receipt Date')
                    ->default(now())
                    ->maxDate(now()) // BR-25: receipt date may not be in the future
                    ->required(),
                Hidden::make('location_id')
                    ->default(fn () => InventoryLocation::query()->where('is_default', true)->value('id')),
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => InventorySupplier::query()->where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->createOptionForm([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(20)
                            ->unique('inventory_suppliers', 'code')
                            ->default(fn (): string => SupplierForm::suggestNextCode())
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : $state),
                        TextInput::make('name')->label('Name')->required()->maxLength(150),
                    ])
                    ->createOptionUsing(fn (array $data): int => InventorySupplier::create($data)->id),

                Fieldset::make('Invoice')
                    ->columns(2)
                    ->schema([
                        TextInput::make('invoice_no')
                            ->label('Invoice No')
                            ->live(onBlur: true)
                            ->hint(fn (Get $get, ?InventoryGoodsReceipt $record): ?string => self::duplicateInvoiceWarning($get('invoice_no'), $get('supplier_id'), $record))
                            ->hintColor('warning')
                            ->hintIcon(fn (Get $get, ?InventoryGoodsReceipt $record): ?Heroicon => filled(self::duplicateInvoiceWarning($get('invoice_no'), $get('supplier_id'), $record)) ? Heroicon::OutlinedExclamationTriangle : null),
                        DatePicker::make('invoice_date')->label('Invoice Date'),
                    ]),

                Repeater::make('lines')
                    ->relationship()
                    ->orderColumn('line_no')
                    ->label('Items')
                    ->addActionLabel('Add item')
                    ->minItems(1)
                    ->table([
                        TableColumn::make('Item')->markAsRequired(),
                        TableColumn::make('Quantity')->markAsRequired()->width('120px'),
                    ])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->options(fn () => InventoryItem::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            // Spec 10.7: "the storekeeper *will* receive
                            // something not yet in the catalogue" —
                            // ItemCreationService is what CreateItem
                            // itself uses, so an inline-created item is
                            // immediately usable by
                            // StockMovementService::record() (it needs
                            // an inventory_item_stock row to exist).
                            ->createOptionForm([
                                TextInput::make('name')->label('Name')->required()->maxLength(150),
                                Select::make('category_id')
                                    ->label('Category')
                                    ->options(fn () => InventoryItemCategory::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->required(),
                                Select::make('uom_id')
                                    ->label('Unit of measure')
                                    ->options(fn () => InventoryUnitOfMeasure::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->required(),
                            ])
                            ->createOptionUsing(fn (array $data): int => app(ItemCreationService::class)->create([
                                ...$data,
                                'created_by' => auth()->id(),
                            ])->id),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->step(fn (Get $get): string => self::stepFor($get('item_id')))
                            ->minValue(fn (Get $get): string => self::stepFor($get('item_id')))
                            ->required(),
                    ]),

                Textarea::make('remarks')
                    ->label('Remarks')
                    ->rows(2)
                    ->columnSpanFull(),

                FileUpload::make('attachments')
                    ->label('Attachments')
                    ->multiple()
                    ->disk('local')
                    ->directory('inventory/grns')
                    ->visibility('private')
                    ->maxSize((int) (InventorySetting::get('max_attachment_mb') ?: 10) * 1024)
                    // No attachments column on this model — extracted
                    // in GoodsReceiptResource::createAction() and
                    // turned into inventory_attachments rows instead
                    // (see InventoryGoodsReceipt::attachments()).
                    ->columnSpanFull()
                    ->helperText('A scan or photo of the invoice.'),
            ]);
    }

    /**
     * Spec's 'allow_duplicate_invoice' setting, read as a soft warning
     * rather than a hard block — a supplier legitimately re-sending the
     * same invoice number happens, so this flags it for the storekeeper
     * to double-check rather than stopping them from receiving stock.
     * Scoped to the same supplier only, since two different suppliers
     * numbering their own invoices "1001" isn't a real duplicate.
     */
    private static function duplicateInvoiceWarning(?string $invoiceNo, ?int $supplierId, ?InventoryGoodsReceipt $record): ?string
    {
        if (InventorySetting::getBool('allow_duplicate_invoice') || blank($invoiceNo) || blank($supplierId)) {
            return null;
        }

        $exists = InventoryGoodsReceipt::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_no', $invoiceNo)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->exists();

        return $exists ? 'This invoice number has already been recorded for this supplier.' : null;
    }

    /**
     * Matches the same fix on the Issue Request/Issue Goods forms —
     * the up/down arrows should move by whole units for a UoM like PC
     * (0 decimal places) instead of always nudging by 0.001.
     */
    private static function stepFor(?int $itemId): string
    {
        $decimals = $itemId ? (InventoryItem::find($itemId)?->uom?->decimal_places ?? 0) : 0;

        return bcdiv('1', bcpow('10', (string) $decimals), $decimals);
    }
}
