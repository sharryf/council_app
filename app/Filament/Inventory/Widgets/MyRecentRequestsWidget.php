<?php

namespace App\Filament\Inventory\Widgets;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Models\InventoryIssueRequest;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Spec 10.8's "all users" widget — my recent requests, latest 5, with
 * status badges.
 */
class MyRecentRequestsWidget extends TableWidget
{
    use HasInventoryRoleAccess;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userHasAnyInventoryRole();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('My Recent Requests')
            ->query(
                InventoryIssueRequest::query()
                    ->where('requested_by', auth()->id())
                    ->withCount('lines')
                    ->latest('request_date')
                    ->limit(5),
            )
            ->columns([
                TextColumn::make('request_no')->label('Request No'),
                TextColumn::make('request_date')->label('Date')->date(),
                TextColumn::make('purpose')->label('Purpose')->limit(40),
                TextColumn::make('lines_count')->label('Lines'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->url(fn (InventoryIssueRequest $record): string => IssueRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
