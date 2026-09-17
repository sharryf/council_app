<?php

namespace App\Filament\Assets\Resources\Assets\Pages;

use App\Enums\AssetLifecycleStatus;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetCategory;
use App\Models\AssetHistory;
use App\Services\Assets\AssetTagGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * asset_tag/public_token are never client-supplied (spec section
     * 3.3/2.3); 'photo' isn't a real column on Asset (it becomes an
     * asset_attachments row below) so it's pulled out before create —
     * same pattern as GoodsReceiptResource::createAction()'s
     * 'attachments' handling.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $photoPath = $data['photo'] ?? null;
        unset($data['photo']);

        abort_unless(filled($photoPath), 422, 'A photo is required.');

        $generator = app(AssetTagGenerator::class);

        $classCode = AssetCategory::find($data['category_id'] ?? null)?->asset_class_code ?? 'GEN';
        $purchaseDate = filled($data['purchase_date'] ?? null) ? Carbon::parse($data['purchase_date']) : null;
        $numbers = $generator->generate($purchaseDate, $classCode);

        $data['main_inventory_no'] = $numbers['main_inventory_no'];
        $data['main_sequence'] = $numbers['main_sequence'];
        $data['asset_tag'] = $numbers['asset_tag'];
        $data['public_token'] = $generator->newPublicToken();
        $data['lifecycle_status'] = AssetLifecycleStatus::Draft->value;
        $data['created_by'] = auth()->id();

        return DB::transaction(function () use ($data, $photoPath): Asset {
            /** @var Asset $asset */
            $asset = Asset::create($data);

            $attachment = AssetAttachment::create([
                'asset_id' => $asset->id,
                'kind' => 'photo',
                'file_path' => $photoPath,
                'file_name' => basename($photoPath),
                'mime_type' => Storage::disk('local')->mimeType($photoPath) ?: 'application/octet-stream',
                'file_size' => Storage::disk('local')->size($photoPath),
                'uploaded_by' => auth()->id(),
            ]);

            $asset->update(['photo_attachment_id' => $attachment->id]);

            AssetHistory::record($asset->id, 'created', "Asset created as {$asset->asset_tag} (Draft).");

            return $asset;
        });
    }
}
