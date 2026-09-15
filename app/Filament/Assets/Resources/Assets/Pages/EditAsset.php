<?php

namespace App\Filament\Assets\Resources\Assets\Pages;

use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetCategory;
use App\Models\AssetHistory;
use App\Services\Assets\AssetTagGenerator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    /**
     * Human-readable labels for asset_history's field_name column — the
     * spec wants the timeline readable without joins (section 3.7).
     */
    private const FIELD_LABELS = [
        'name' => 'Name',
        'category_id' => 'Category',
        'brand' => 'Brand',
        'model' => 'Model',
        'serial_number' => 'Serial number',
        'description' => 'Description',
        'purchase_date' => 'Purchase date',
        'purchase_price' => 'Purchase price',
        'vendor' => 'Vendor',
        'status' => 'Status',
        'po_number' => 'PO number',
        'voucher_number' => 'Voucher number',
        'donation_reference_no' => 'Donation document ref. no.',
        'asset_type' => 'Asset type',
    ];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => AssetResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Asset $record */
        $newPhotoPath = $data['photo'] ?? null;
        unset($data['photo']);

        $data['fund_code'] = app(AssetTagGenerator::class)->fundCodeFromPoNumber($data['po_number'] ?? null);

        $before = $record->only(array_keys(self::FIELD_LABELS));

        return DB::transaction(function () use ($record, $data, $before, $newPhotoPath): Asset {
            $record->update($data);

            foreach (self::FIELD_LABELS as $field => $label) {
                $old = $before[$field] ?? null;
                $new = $record->{$field};

                // category_id renders as its path (e.g. "Furniture >
                // Chairs"), not a bare id, so the timeline reads
                // without a join to a possibly-deleted category.
                if ($field === 'category_id') {
                    if ((string) $old === (string) $new) {
                        continue;
                    }

                    $old = $old ? AssetCategory::find($old)?->path() : null;
                    $new = $record->category?->path();
                } elseif ((string) $old === (string) $new) {
                    continue;
                }

                AssetHistory::recordFieldChange($record->id, $label, $old !== null ? (string) $old : null, $new !== null ? (string) $new : null);
            }

            if (filled($newPhotoPath) && $newPhotoPath !== $record->photoAttachment?->file_path) {
                $record->photoAttachment?->delete();

                $attachment = AssetAttachment::create([
                    'asset_id' => $record->id,
                    'kind' => 'photo',
                    'file_path' => $newPhotoPath,
                    'file_name' => basename($newPhotoPath),
                    'mime_type' => Storage::disk('local')->mimeType($newPhotoPath) ?: 'application/octet-stream',
                    'file_size' => Storage::disk('local')->size($newPhotoPath),
                    'uploaded_by' => auth()->id(),
                ]);

                $record->update(['photo_attachment_id' => $attachment->id]);

                AssetHistory::record($record->id, 'photo_replaced');
            }

            return $record;
        });
    }
}
