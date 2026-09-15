<?php

namespace App\Filament\Inventory\Resources\Adjustments\Schemas;

use App\Models\InventoryStockAdjustment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdjustmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Adjustment')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('adjustment_no')->label('Adjustment No'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('adjustment_type')->label('Type')->badge(),
                        TextEntry::make('adjustment_date')->label('Date')->date(),
                        TextEntry::make('createdBy.name')->label('Created By')->placeholder('—'),
                        TextEntry::make('reason')->label('Reason')->columnSpanFull(),
                    ]),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Code'),
                                TableColumn::make('Name'),
                                TableColumn::make('System Qty'),
                                TableColumn::make('Counted Qty'),
                                TableColumn::make('Difference'),
                            ])
                            ->schema([
                                TextEntry::make('item.code')->label('Code'),
                                TextEntry::make('item.name')->label('Name'),
                                TextEntry::make('system_qty')->label('System Qty')->numeric(),
                                TextEntry::make('counted_qty')->label('Counted Qty')->numeric(),
                                TextEntry::make('difference_qty')->label('Difference')
                                    ->numeric()
                                    ->color(fn ($state): string => match (true) {
                                        bccomp((string) $state, '0', 3) > 0 => 'success',
                                        bccomp((string) $state, '0', 3) < 0 => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),

                Section::make('Approval & Posting')
                    ->columns(4)
                    ->visible(fn (InventoryStockAdjustment $record): bool => filled($record->approved_at))
                    ->schema([
                        TextEntry::make('approvedBy.name')->label('Approved By'),
                        TextEntry::make('approved_at')->label('Approved At')->dateTime(),
                        TextEntry::make('postedBy.name')->label('Posted By'),
                        TextEntry::make('posted_at')->label('Posted At')->dateTime(),
                    ]),
            ]);
    }
}
