<?php

namespace App\Filament\Inventory\Resources\Returns\Tables;

use App\Enums\InventoryReturnStatus;
use App\Filament\Inventory\Resources\Returns\ReturnResource;
use App\Models\InventoryStockReturn;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class ReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['issueRequest', 'lines.item'])->withCount('lines')->latest('return_date'))
            ->recordUrl(fn (InventoryStockReturn $record): string => ReturnResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('return_no')
                    ->label('Return No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('return_date')->label('Date')->date()->sortable(),
                TextColumn::make('issueRequest.request_no')->label('Source Request')->placeholder('—'),
                TextColumn::make('lines_count')->label('Items'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InventoryReturnStatus::class),
            ])
            ->recordActions([
                Action::make('previewItems')
                    ->label('')
                    ->icon(Heroicon::OutlinedEye)
                    ->tooltip('Preview items')
                    ->color('gray')
                    ->modalHeading(fn (InventoryStockReturn $record): string => "Items — {$record->return_no}")
                    ->modalContent(fn (InventoryStockReturn $record): HtmlString => new HtmlString(
                        '<ul class="fi-ta-preview-items">'
                        .$record->lines->map(fn ($line): string => '<li>'.e(Number::format((float) $line->quantity).'× '.$line->item->name).'</li>')->implode('')
                        .'</ul>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (InventoryStockReturn $record): bool => ReturnResource::canDelete($record)),
                ]),
            ])
            ->emptyStateHeading('No returns yet')
            ->emptyStateDescription('Record items coming back to store here.')
            ->emptyStateIcon(Heroicon::OutlinedArrowUturnLeft)
            ->emptyStateActions([
                ReturnResource::createAction()
                    ->visible(fn (): bool => ReturnResource::canCreate()),
            ]);
    }
}
