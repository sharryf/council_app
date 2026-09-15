<?php

namespace App\Filament\Inventory\Resources\Adjustments\Schemas;

use App\Enums\InventoryAdjustmentType;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryItemStock;
use App\Models\InventoryLocation;
use App\Models\InventorySetting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class AdjustmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('adjustment_date')
                    ->label('Adjustment Date')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
                Hidden::make('location_id')
                    ->default(fn () => InventoryLocation::query()->where('is_default', true)->value('id')),
                Select::make('adjustment_type')
                    ->label('Type')
                    ->options(InventoryAdjustmentType::class)
                    ->live()
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),

                FileUpload::make('attachment_path')
                    ->label('Attachment')
                    ->disk('local')
                    ->directory('inventory/adjustments')
                    ->visibility('private')
                    ->maxSize((int) (InventorySetting::get('max_attachment_mb') ?: 10) * 1024),

                Actions::make([
                    // Not restricted to visible-only-for-Stock-Take —
                    // seeding lines from current on_hand is a
                    // reasonable starting point for any adjustment
                    // type, and a Get()-based visible() on a
                    // schema-level Action (outside any field) wasn't
                    // reliably reactive to sibling field changes in
                    // this Filament version, so the simpler
                    // always-shown version is what's built.
                    Action::make('prefillStockTake')
                        ->label('Pre-fill Items for Stock Take')
                        ->color('gray')
                        ->schema([
                            Select::make('category_id')
                                ->label('Category')
                                ->options(fn () => InventoryItemCategory::query()->where('is_active', true)->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (array $data, Get $get, Set $set): void {
                            $locationId = $get('location_id');

                            // Keyed by UUID, matching the Repeater's own
                            // internal item-key format — a plain
                            // sequential array set programmatically
                            // doesn't integrate cleanly with its
                            // existing add/remove bookkeeping.
                            $lines = InventoryItem::query()
                                ->where('category_id', $data['category_id'])
                                ->where('is_active', true)
                                ->where('is_stock_tracked', true)
                                ->with('stock')
                                ->get()
                                ->mapWithKeys(function (InventoryItem $item) use ($locationId): array {
                                    $stock = $item->stock->firstWhere('location_id', $locationId);
                                    $qty = Number::format((float) ($stock?->on_hand ?? 0));

                                    return [(string) \Illuminate\Support\Str::uuid() => [
                                        'item_id' => $item->id,
                                        'system_qty' => $qty,
                                        'counted_qty' => $qty,
                                        'difference_qty' => '0',
                                    ]];
                                })
                                ->all();

                            $set('lines', $lines);
                        }),
                ]),

                Repeater::make('lines')
                    ->relationship()
                    ->orderColumn('line_no')
                    ->label('Items')
                    ->addActionLabel('Add item')
                    ->minItems(1)
                    ->table([
                        TableColumn::make('Item')->markAsRequired(),
                        TableColumn::make('System Qty')->width('120px'),
                        TableColumn::make('Counted Qty')->markAsRequired()->width('120px'),
                        TableColumn::make('Difference')->width('120px'),
                    ])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->options(fn () => InventoryItem::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                $qty = '0';

                                if ($state) {
                                    $locationId = $get('../../location_id');
                                    $stock = InventoryItemStock::query()->where('item_id', $state)->where('location_id', $locationId)->first();
                                    $qty = Number::format((float) ($stock?->on_hand ?? 0));
                                }

                                $set('system_qty', $qty);
                                $set('counted_qty', $qty);
                                $set('difference_qty', '0');
                            }),
                        TextInput::make('system_qty')
                            ->label('System Qty')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('counted_qty')
                            ->label('Counted Qty')
                            ->numeric()
                            ->step(fn (Get $get): string => self::stepFor($get('item_id')))
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                                'difference_qty',
                                Number::format((float) bcsub((string) ($get('counted_qty') ?: '0'), (string) ($get('system_qty') ?: '0'), 3)),
                            )),
                        TextInput::make('difference_qty')
                            ->label('Difference')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ]);
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
