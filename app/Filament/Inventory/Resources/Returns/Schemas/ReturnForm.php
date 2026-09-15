<?php

namespace App\Filament\Inventory\Resources\Returns\Schemas;

use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryReturnCondition;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('return_date')
                    ->label('Return Date')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
                Hidden::make('location_id')
                    ->default(fn () => InventoryLocation::query()->where('is_default', true)->value('id')),
                Select::make('issue_request_id')
                    ->label('Return From Request')
                    ->helperText('Optional — pre-fills lines and caps quantity at what was actually issued and not yet returned.')
                    ->options(fn () => InventoryIssueRequest::query()
                        ->whereIn('status', [InventoryIssueRequestStatus::Issued, InventoryIssueRequestStatus::PartiallyIssued])
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (InventoryIssueRequest $r): array => [$r->id => "{$r->request_no} — {$r->purpose}"]))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        if (! $state) {
                            return;
                        }

                        $request = InventoryIssueRequest::query()->with('lines.item')->find($state);
                        if (! $request) {
                            return;
                        }

                        $lines = $request->lines
                            ->filter(fn ($line): bool => bccomp((string) $line->issued_qty, (string) $line->returned_qty, 3) > 0)
                            ->mapWithKeys(function ($line): array {
                                $available = bcsub((string) $line->issued_qty, (string) $line->returned_qty, 3);

                                return [(string) Str::uuid() => [
                                    'item_id' => $line->item_id,
                                    'issue_line_id' => $line->id,
                                    'quantity' => Number::format((float) $available),
                                    'condition' => InventoryReturnCondition::Good->value,
                                ]];
                            })
                            ->all();

                        $set('lines', $lines);
                    }),
                Textarea::make('reason')
                    ->label('Reason')
                    ->rows(2)
                    ->columnSpanFull(),

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
                        Hidden::make('issue_line_id'),
                        Hidden::make('condition')->default(InventoryReturnCondition::Good),
                        Select::make('item_id')
                            ->label('Item')
                            ->options(fn () => InventoryItem::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->step(fn (Get $get): string => self::stepFor($get('item_id')))
                            ->minValue(fn (Get $get): string => self::stepFor($get('item_id')))
                            ->required()
                            ->hintIcon(
                                fn (Get $get): ?Heroicon => $get('issue_line_id') ? Heroicon::OutlinedInformationCircle : null,
                                tooltip: 'Capped at what remains to be returned on that request line.',
                            ),
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
