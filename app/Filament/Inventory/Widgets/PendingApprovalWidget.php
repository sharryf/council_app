<?php

namespace App\Filament\Inventory\Widgets;

use App\Enums\InventoryIssueRequestStatus;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Models\InventoryIssueRequest;
use App\Models\InventorySetting;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Spec 10.8's "pending approval visible for each approver" requirement
 * — the module's own primary widget, so it's listed first in
 * Dashboard::getWidgets() and deliberately never cached (every other
 * dashboard widget here is, per spec's caching rule, but an approver
 * acting on stale counts is exactly the failure mode that rule exists
 * to avoid for this one widget).
 */
class PendingApprovalWidget extends TableWidget
{
    use HasInventoryRoleAccess;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userIsApproverOrAbove();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pending My Approval')
            ->query(
                InventoryIssueRequest::query()
                    ->where('status', InventoryIssueRequestStatus::Submitted)
                    ->with(['requester', 'lines.item.stock'])
                    ->withCount('lines')
                    ->oldest('submitted_at'),
            )
            ->columns([
                TextColumn::make('request_no')->label('Request No'),
                TextColumn::make('requester.name')->label('Requester'),
                TextColumn::make('lines_count')->label('Items'),
                TextColumn::make('priority')->label('Priority')->badge(),
                TextColumn::make('submitted_at')
                    ->label('Age')
                    ->state(fn (InventoryIssueRequest $record): string => $record->submitted_at?->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) ?? '—')
                    ->badge()
                    ->color(fn (InventoryIssueRequest $record): string => $this->isOverdue($record) ? 'danger' : 'gray'),
                TextColumn::make('needs_decision')
                    ->label('')
                    ->state(fn (InventoryIssueRequest $record): ?string => $this->needsDecision($record) ? 'Needs decision' : null)
                    ->badge()
                    ->color('warning')
                    ->placeholder(''),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->url(fn (InventoryIssueRequest $record): string => IssueRequestResource::getUrl('view', ['record' => $record])),
                IssueRequestResource::approveAction(),
                IssueRequestResource::rejectAction(),
            ])
            ->paginated([5, 10, 25]);
    }

    private function isOverdue(InventoryIssueRequest $record): bool
    {
        if (! $record->submitted_at) {
            return false;
        }

        $reminderDays = (int) (InventorySetting::get('approval_reminder_days') ?: 2);

        return $record->submitted_at->diffInDays(now()) >= $reminderDays;
    }

    /**
     * Flags a request needing a real decision (not a rubber stamp) —
     * any line whose requested_qty already exceeds current available
     * stock (spec 10.8).
     */
    private function needsDecision(InventoryIssueRequest $record): bool
    {
        return $record->lines->contains(function ($line): bool {
            $available = bcsub((string) $line->item->stock->sum('on_hand'), (string) $line->item->stock->sum('reserved'), 3);

            return bccomp((string) $line->requested_qty, $available, 3) > 0;
        });
    }
}
