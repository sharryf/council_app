<?php

namespace App\Filament\Inventory\Resources\Adjustments\Pages;

use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdjustment extends EditRecord
{
    protected static string $resource = AdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => AdjustmentResource::canDelete($this->record)),
            AdjustmentResource::submitForApprovalAction(),
        ];
    }
}
