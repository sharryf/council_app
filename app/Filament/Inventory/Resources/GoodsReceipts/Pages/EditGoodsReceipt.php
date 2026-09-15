<?php

namespace App\Filament\Inventory\Resources\GoodsReceipts\Pages;

use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\InventoryAttachment;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditGoodsReceipt extends EditRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => GoodsReceiptResource::canDelete($this->record)),
            GoodsReceiptResource::postAction(),
        ];
    }

    /**
     * 'attachments' isn't a real column (see
     * InventoryGoodsReceipt::attachments()) — seed the FileUpload
     * field's initial state from the existing inventory_attachments
     * rows' paths.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['attachments'] = $this->record->attachments()->pluck('file_path')->all();

        return $data;
    }

    /**
     * Reconciles the FileUpload's full path list against what's
     * already stored: creates a row for each newly added path, deletes
     * the row (and file) for each one the Stock Admin removed. Existing
     * paths in both sets are left untouched.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $newPaths = $data['attachments'] ?? [];
        unset($data['attachments']);

        $existing = $this->record->attachments()->get();
        $existingPaths = $existing->pluck('file_path')->all();

        foreach ($existing as $attachment) {
            if (! in_array($attachment->file_path, $newPaths, true)) {
                Storage::disk('local')->delete($attachment->file_path);
                $attachment->delete();
            }
        }

        foreach (array_diff($newPaths, $existingPaths) as $path) {
            InventoryAttachment::create([
                'entity_type' => 'GRN',
                'entity_id' => $this->record->id,
                'file_name' => basename($path),
                'file_path' => $path,
                'file_size' => Storage::disk('local')->size($path),
                'mime_type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
            ]);
        }

        return $data;
    }
}
