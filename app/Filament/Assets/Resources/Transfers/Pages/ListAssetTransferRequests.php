<?php

namespace App\Filament\Assets\Resources\Transfers\Pages;

use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Resources\Transfers\AssetTransferRequestResource;
use App\Models\AssetTransferRequest;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class ListAssetTransferRequests extends ListRecords
{
    protected static string $resource = AssetTransferRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => route('assets.export.transfers')),
        ];
    }

    /**
     * Same shape as Inventory's ListAdjustments — a "Pending" tab lets
     * the asset dashboard's approvals queue deep-link straight into it.
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        if (AssetTransferRequestResource::userIsAssetManager()) {
            $tabs = ['pending' => Tab::make('Pending')
                ->query(fn ($query) => $query->where('status', AssetTransferStatus::Pending))
                ->badge(fn () => AssetTransferRequest::query()->where('status', AssetTransferStatus::Pending)->count())
                ->badgeColor('warning'),
                ...$tabs];
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return AssetTransferRequestResource::userIsAssetManager() ? 'pending' : 'all';
    }
}
