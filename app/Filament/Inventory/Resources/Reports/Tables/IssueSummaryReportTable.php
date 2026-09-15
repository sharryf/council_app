<?php

namespace App\Filament\Inventory\Resources\Reports\Tables;

use App\Enums\InventoryIssueRequestStatus;
use App\Models\InventoryIssueRequest;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IssueSummaryReportTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(InventoryIssueRequest::query()->with(['requester', 'location', 'approver'])->withCount('lines')->latest('request_date'))
            ->columns([
                TextColumn::make('request_no')->label('Request No')->searchable(),
                TextColumn::make('request_date')->label('Date')->date(),
                TextColumn::make('requester.name')->label('Requester'),
                TextColumn::make('purpose')->label('Purpose')->limit(40),
                TextColumn::make('lines_count')->label('Lines'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('approver.name')->label('Approver')->placeholder('—'),
                TextColumn::make('issued_at')->label('Issued Date')->date()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InventoryIssueRequestStatus::class),
                Filter::make('request_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('request_date', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('request_date', '<=', $date))),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No requests in this range')
            ->emptyStateDescription('Try widening the date range or clearing a filter.')
            ->emptyStateIcon(Heroicon::OutlinedArrowUpTray);
    }
}
