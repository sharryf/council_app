<?php

namespace App\Filament\Inventory\Resources\IssueRequests\Schemas;

use App\Enums\InventoryPriority;
use App\Filament\Inventory\Resources\Recipients\Schemas\RecipientForm;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class IssueRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('recipient_id')
                    ->label('Issuing To')
                    ->helperText('Only needed when requesting on behalf of someone else — leave blank if you\'re collecting it yourself.')
                    ->relationship('recipient', 'name', fn ($query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema): Schema => RecipientForm::configure($schema))
                    ->createOptionAction(fn (Action $action): Action => $action->modalHeading('Add recipient'))
                    ->columnSpanFull(),

                Textarea::make('purpose')
                    ->label('Purpose')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),

                // Location and Priority still exist on the model (and
                // still drive availability lookups/dashboard sorting),
                // just no longer asked for on this form — a single-
                // office setup (spec assumption A1) doesn't need a
                // picker on every request, so both quietly default
                // instead: Location to the default store, Priority to
                // Normal.
                Hidden::make('location_id')
                    ->default(fn () => InventoryLocation::query()->where('is_default', true)->value('id')),
                Hidden::make('priority')
                    ->default(InventoryPriority::Normal),

                Repeater::make('lines')
                    ->relationship()
                    ->orderColumn('line_no')
                    ->label('Items')
                    ->addActionLabel('Add item')
                    ->minItems(1)
                    ->table([
                        TableColumn::make('Item')->markAsRequired(),
                        TableColumn::make('Quantity')->markAsRequired()->width('160px'),
                    ])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->options(fn (Get $get) => self::itemOptions($get('../../location_id')))
                            ->searchable()
                            ->required()
                            ->live()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('requested_qty')
                            ->label('Quantity')
                            ->numeric()
                            ->step(fn (Get $get): string => self::stepFor($get('item_id')))
                            ->minValue(fn (Get $get): string => self::stepFor($get('item_id')))
                            ->required()
                            ->suffix(fn (Get $get): ?string => self::uomCodeFor($get('item_id'))),
                    ]),

                Textarea::make('remarks')
                    ->label('Remarks')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function itemOptions(?int $locationId): array
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->with('uom')
            ->get()
            ->mapWithKeys(fn (InventoryItem $item): array => [
                $item->id => sprintf('%s (%s) — %s %s available', $item->name, $item->code, self::availableAt($item->id, $locationId), $item->uom?->code),
            ])
            ->all();
    }

    /**
     * Matches the "Issue Now" stepper's fix on the Issue Goods page —
     * the up/down arrows should move by whole units for a UoM like PC
     * (0 decimal places) instead of always nudging by 0.001.
     */
    private static function stepFor(?int $itemId): string
    {
        $decimals = $itemId ? (InventoryItem::find($itemId)?->uom?->decimal_places ?? 0) : 0;

        return bcdiv('1', bcpow('10', (string) $decimals), $decimals);
    }

    private static function uomCodeFor(?int $itemId): ?string
    {
        return $itemId ? InventoryItem::find($itemId)?->uom?->code : null;
    }

    private static function availableAt(?int $itemId, ?int $locationId): string
    {
        if (! $itemId) {
            return '0';
        }

        $stock = \App\Models\InventoryItemStock::query()->where('item_id', $itemId)
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->get();

        return (string) $stock->sum('available');
    }
}
