<?php

namespace App\Filament\Inventory\Resources\IssueRequests\Pages;

use App\Enums\InventoryIssueRequestStatus;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Models\InventoryIssueRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Livewire\Attributes\Url;

/**
 * Spec 10.1's nav split (My Requests / Pending Approval / Ready to
 * Issue) as tabs on one list rather than three separate resources —
 * each Approver/Stock-Admin-only tab is simply omitted from the array
 * for a user without that role, rather than shown empty.
 */
class ListIssueRequests extends ListRecords
{
    protected static string $resource = IssueRequestResource::class;

    /**
     * Lets the dashboard's "My Drafts"/"My Pending Requests" cards deep-
     * link into the existing "My Requests" tab pre-narrowed to one
     * status, instead of adding a dedicated tab per status (which would
     * clutter the tab bar for every viewer). #[Url]-bound rather than
     * read from request()->query() directly, so it survives Livewire's
     * own AJAX round-trips (sorting, paginating) instead of only
     * applying on the very first page load.
     */
    #[Url(as: 'status')]
    public ?string $dashboardStatusFilter = null;

    protected function getHeaderActions(): array
    {
        return [
            IssueRequestResource::createAction(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'mine' => Tab::make('My Requests')
                ->query(function ($query) {
                    $query->where('requested_by', auth()->id());

                    if (filled($this->dashboardStatusFilter)) {
                        $query->where('status', $this->dashboardStatusFilter);
                    }

                    return $query;
                }),
            'all' => Tab::make('All'),
        ];

        if (IssueRequestResource::userIsApproverOrAbove()) {
            $tabs = ['pending_approval' => Tab::make('Pending Approval')
                ->query(fn ($query) => $query->where('status', InventoryIssueRequestStatus::Submitted))
                ->badge(fn () => InventoryIssueRequest::query()->where('status', InventoryIssueRequestStatus::Submitted)->count())
                // Matches InventoryIssueRequestStatus::Submitted's own
                // badge color, so the tab reads as the same "state" as
                // the rows inside it.
                ->badgeColor('info'),
                ...$tabs];
        }

        if (IssueRequestResource::userIsStockAdminOrAbove()) {
            $tabs = ['ready_to_issue' => Tab::make('Ready to Issue')
                ->query(fn ($query) => $query->whereIn('status', [InventoryIssueRequestStatus::Approved, InventoryIssueRequestStatus::PartiallyIssued]))
                ->badge(fn () => InventoryIssueRequest::query()->whereIn('status', [InventoryIssueRequestStatus::Approved, InventoryIssueRequestStatus::PartiallyIssued])->count())
                // Matches Approved/PartiallyIssued's own badge color.
                ->badgeColor('warning'),
                ...$tabs];
        }

        return $tabs;
    }

    /**
     * Everything at a glance by default — the role-gated "Ready to
     * Issue"/"Pending Approval" tabs stay first for quick access when
     * an admin does want to narrow down, but landing on an empty-
     * looking filtered view isn't the right first impression.
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
