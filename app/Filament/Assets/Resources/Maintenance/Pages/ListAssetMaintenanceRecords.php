<?php

namespace App\Filament\Assets\Resources\Maintenance\Pages;

use App\Enums\AssetMaintenanceApprovalStatus;
use App\Filament\Assets\Resources\Maintenance\AssetMaintenanceRecordResource;
use App\Models\AssetMaintenanceRecord;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class ListAssetMaintenanceRecords extends ListRecords
{
    protected static string $resource = AssetMaintenanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => route('assets.export.maintenance')),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        if (AssetMaintenanceRecordResource::userIsAssetManager()) {
            $tabs = ['pending' => Tab::make('Pending Approval')
                ->query(fn ($query) => $query->where('approval_status', AssetMaintenanceApprovalStatus::Pending))
                ->badge(fn () => AssetMaintenanceRecord::query()->where('approval_status', AssetMaintenanceApprovalStatus::Pending)->count())
                ->badgeColor('warning'),
                ...$tabs];
        }

        if (AssetMaintenanceRecordResource::userIsAssetAdmin()) {
            $tabs['open'] = Tab::make('Open')
                ->query(fn ($query) => $query->whereNull('closed_at'))
                ->badge(fn () => AssetMaintenanceRecord::query()->whereNull('closed_at')->count())
                ->badgeColor('warning');
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return array_key_first($this->getTabs());
    }
}
