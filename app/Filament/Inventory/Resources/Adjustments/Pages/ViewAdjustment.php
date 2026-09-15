<?php

namespace App\Filament\Inventory\Resources\Adjustments\Pages;

use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewAdjustment extends ViewRecord
{
    protected static string $resource = AdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('slip')
                ->label('Adjustment Slip')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (): string => route('inventory.adjustments.slip', $this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->record->posted_at)),
            EditAction::make()
                ->visible(fn (): bool => AdjustmentResource::canEdit($this->record)),
            AdjustmentResource::submitForApprovalAction(),
            AdjustmentResource::approveAction(),
            AdjustmentResource::rejectAction(),
            AdjustmentResource::cancelAction(),
            AdjustmentResource::reverseAction(),
        ];
    }
}
