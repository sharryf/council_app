<?php

namespace App\Filament\Assets\Imports;

use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\AssetRoom;
use App\Services\Assets\AssetTagGenerator;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\Rule;

/**
 * Initial bulk upload (Settings → Bulk Upload) — every row always
 * creates a brand-new Asset (there's no pre-existing key a CSV row
 * could match against), landing as Draft exactly like one created
 * through the form, so it's freely editable until someone Posts it.
 *
 * Building/Room are resolved together by hand rather than through
 * Filament's built-in single-relationship column resolution, since a
 * room name alone isn't unique across buildings (e.g. more than one
 * "Store") — both a Building and a Room column are required, matched
 * as a pair.
 */
class AssetImporter extends Importer
{
    protected static ?string $model = Asset::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Name')
                ->requiredMapping()
                ->rules(['required', 'max:200']),
            ImportColumn::make('category')
                ->label('Asset Class Code')
                ->relationship(resolveUsing: 'asset_class_code')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('building')
                ->label('Building')
                ->requiredMapping()
                ->rules(['required'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('room')
                ->label('Room')
                ->requiredMapping()
                ->rules(['required'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('brand')->label('Brand'),
            ImportColumn::make('model')->label('Model'),
            ImportColumn::make('serial_number')->label('Serial Number'),
            ImportColumn::make('description')->label('Description'),
            ImportColumn::make('purchase_date')->label('Purchase Date'),
            ImportColumn::make('purchase_price')->label('Purchase Price')->numeric(),
            ImportColumn::make('vendor')->label('Vendor'),
            ImportColumn::make('asset_type')
                ->label('Asset Type')
                ->rules([Rule::in(['purchased', 'donated'])]),
            ImportColumn::make('fund_code')
                ->label('Fund Code')
                ->relationship(resolveUsing: 'code'),
            ImportColumn::make('po_number')->label('PO Number'),
            ImportColumn::make('voucher_number')->label('Voucher Number'),
            ImportColumn::make('donation_reference_no')->label('Donation Reference No.'),
            ImportColumn::make('status')
                ->label('Status')
                ->rules([Rule::in(array_map(fn (AssetStatus $s): string => $s->value, AssetStatus::cases()))]),
        ];
    }

    public function resolveRecord(): Asset
    {
        return new Asset;
    }

    public function saveRecord(): void
    {
        $room = AssetRoom::query()
            ->whereHas('building', fn ($q) => $q->where('name', $this->data['building']))
            ->where('name', $this->data['room'])
            ->first();

        if (! $room) {
            throw new RowImportFailedException(
                "No room \"{$this->data['room']}\" in building \"{$this->data['building']}\".",
            );
        }

        $this->record->room_id = $room->id;
        $this->record->asset_type ??= 'purchased';
        $this->record->status ??= AssetStatus::InUse->value;

        $generator = app(AssetTagGenerator::class);
        $classCode = $this->record->category?->asset_class_code ?? 'GEN';
        $numbers = $generator->generate($this->record->purchase_date, $classCode);

        $this->record->main_inventory_no = $numbers['main_inventory_no'];
        $this->record->main_sequence = $numbers['main_sequence'];
        $this->record->asset_tag = $numbers['asset_tag'];
        $this->record->public_token = $generator->newPublicToken();
        $this->record->lifecycle_status = AssetLifecycleStatus::Draft->value;
        $this->record->created_by = auth()->id();

        parent::saveRecord();

        AssetHistory::record($this->record->id, 'created', "Imported as {$this->record->asset_tag} (Draft).");
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your asset import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported as drafts — post each one once it\'s reviewed.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
