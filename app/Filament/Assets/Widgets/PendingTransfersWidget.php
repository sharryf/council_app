<?php

namespace App\Filament\Assets\Widgets;

use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Resources\Transfers\AssetTransferRequestResource;
use App\Models\AssetTransferRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Spec section 6.1's "Pending Approvals" — one of the two queues.
 * Reuses AssetTransferRequestResource's own approve/reject actions
 * rather than duplicating the logic — their existing ->visible() gates
 * already give Managers actionable buttons and everyone else a
 * read-only row, exactly matching the spec's role split for free.
 */
class PendingTransfersWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Transfers';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return AssetTransferRequestResource::userHasAnyAssetRole();
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(AssetTransferRequest::query()->where('status', AssetTransferStatus::Pending)->with(['asset', 'requestedBy', 'toRoom.building']))
            ->columns([
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('requestedBy.name')->label('Requested by'),
                TextColumn::make('requested_at')->label('Date')->date(),
            ])
            ->recordActions([
                AssetTransferRequestResource::approveAction(),
                AssetTransferRequestResource::rejectAction(),
            ])
            ->emptyStateHeading('No pending transfers')
            ->paginated(false);
    }
}
