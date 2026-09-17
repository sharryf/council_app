<?php

namespace App\Filament\Assets\Widgets;

use App\Enums\AssetDeleteRequestStatus;
use App\Filament\Assets\Resources\DeleteRequests\AssetDeleteRequestResource;
use App\Models\AssetDeleteRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PendingDeleteRequestsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Delete Requests';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return AssetDeleteRequestResource::userHasAnyAssetRole();
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(AssetDeleteRequest::query()->where('status', AssetDeleteRequestStatus::Pending)->with(['asset', 'requestedBy']))
            ->columns([
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('requestedBy.name')->label('Requested by'),
                TextColumn::make('requested_at')->label('Date')->date(),
            ])
            ->recordActions([
                AssetDeleteRequestResource::approveAction(),
                AssetDeleteRequestResource::rejectAction(),
            ])
            ->emptyStateHeading('No pending delete requests')
            ->paginated(false);
    }
}
