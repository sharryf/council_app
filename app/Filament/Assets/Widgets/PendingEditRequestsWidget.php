<?php

namespace App\Filament\Assets\Widgets;

use App\Enums\AssetEditRequestStatus;
use App\Filament\Assets\Resources\EditRequests\AssetEditRequestResource;
use App\Models\AssetEditRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PendingEditRequestsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Edit Requests';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return AssetEditRequestResource::userHasAnyAssetRole();
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(AssetEditRequest::query()->where('status', AssetEditRequestStatus::Pending)->with(['asset', 'requestedBy']))
            ->columns([
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('requestedBy.name')->label('Requested by'),
                TextColumn::make('requested_at')->label('Date')->date(),
            ])
            ->recordActions([
                AssetEditRequestResource::approveAction(),
                AssetEditRequestResource::rejectAction(),
            ])
            ->emptyStateHeading('No pending edit requests')
            ->paginated(false);
    }
}
