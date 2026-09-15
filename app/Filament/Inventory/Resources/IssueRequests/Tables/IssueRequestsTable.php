<?php

namespace App\Filament\Inventory\Resources\IssueRequests\Tables;

use App\Enums\InventoryIssueRequestStatus;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Models\InventoryIssueRequest;
use App\Models\InventorySetting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class IssueRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['requester'])->withCount('lines')->orderByDesc('request_no'))
            ->recordUrl(fn (InventoryIssueRequest $record): string => IssueRequestResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('request_no')
                    ->label('Request No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_date')->label('Date')->date()->sortable(),
                TextColumn::make('age')
                    ->label('Age')
                    ->state(fn (InventoryIssueRequest $record): string => $record->request_date->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true))
                    ->badge()
                    ->color(fn (InventoryIssueRequest $record): string => self::isOverdue($record) ? 'danger' : 'gray'),
                TextColumn::make('requester.name')->label('Requester'),
                TextColumn::make('purpose')->label('Purpose')->limit(40),
                TextColumn::make('lines_count')->label('Items'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InventoryIssueRequestStatus::class),
                SelectFilter::make('requested_by')
                    ->label('Requester')
                    ->relationship('requester', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('request_date')
                    ->label('Date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('request_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('request_date', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'From '.Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (InventoryIssueRequest $record): bool => IssueRequestResource::canDelete($record)),
                ]),
            ])
            ->emptyStateHeading('Nothing here')
            ->emptyStateDescription('Requests you raise, or requests waiting on you, will show up here.')
            ->emptyStateIcon(Heroicon::OutlinedArrowUpTray)
            ->emptyStateActions([
                IssueRequestResource::createAction()
                    ->visible(fn (): bool => IssueRequestResource::canCreate()),
            ]);
    }

    /**
     * Only flags requests still mid-workflow — once issued, rejected,
     * cancelled, or expired, its age no longer means anything needs
     * attention. Same approval_reminder_days threshold
     * PendingApprovalWidget already uses, so the two stay consistent.
     */
    private static function isOverdue(InventoryIssueRequest $record): bool
    {
        if (! in_array($record->status, [
            InventoryIssueRequestStatus::Submitted,
            InventoryIssueRequestStatus::Approved,
            InventoryIssueRequestStatus::PartiallyIssued,
        ], true)) {
            return false;
        }

        $reminderDays = (int) (InventorySetting::get('approval_reminder_days') ?: 2);

        return $record->request_date->diffInDays(now()) >= $reminderDays;
    }
}
