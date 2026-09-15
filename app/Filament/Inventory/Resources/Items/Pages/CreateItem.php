<?php

namespace App\Filament\Inventory\Resources\Items\Pages;

use App\Filament\Inventory\Resources\Items\ItemResource;
use App\Models\InventoryAuditLog;
use App\Services\Inventory\ItemCreationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateItem extends CreateRecord
{
    protected static string $resource = ItemResource::class;

    /**
     * The actual creation (auto-code + stock-row bootstrap) is
     * delegated to ItemCreationService, shared with the "create new
     * item inline" option on GRN lines — see that service's own
     * comment. Overriding handleRecordCreation() rather than
     * mutateFormDataBeforeCreate()+afterCreate() since this page's
     * default record creation needs to be replaced entirely, not
     * adjusted around the edges.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $data['created_by'] = auth()->id();

        $item = app(ItemCreationService::class)->create($data);

        InventoryAuditLog::write('ITEM', $item->id, 'CREATE', null, ['code' => $item->code, 'name' => $item->name]);

        return $item;
    }
}
