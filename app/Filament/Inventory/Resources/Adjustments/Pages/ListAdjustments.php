<?php

namespace App\Filament\Inventory\Resources\Adjustments\Pages;

use App\Enums\InventoryAdjustmentStatus;
use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use App\Models\InventoryStockAdjustment;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListAdjustments extends ListRecords
{
    protected static string $resource = AdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdjustmentResource::createAction(),
        ];
    }

    /**
     * A "Pending Approval" tab, same shape as ListIssueRequests' own —
     * lets the dashboard's combined approvals card deep-link straight
     * into it via ?tab=pending_approval, rather than only being able to
     * link the plain unfiltered list.
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        if (AdjustmentResource::userIsApproverOrAbove()) {
            $tabs = ['pending_approval' => Tab::make('Pending Approval')
                ->query(fn ($query) => $query->where('status', InventoryAdjustmentStatus::PendingApproval))
                ->badge(fn () => InventoryStockAdjustment::query()->where('status', InventoryAdjustmentStatus::PendingApproval)->count())
                ->badgeColor('warning'),
                ...$tabs];
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
