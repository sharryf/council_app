<?php

namespace App\Filament\Inventory\Resources\Returns\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReturnInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Return')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('return_no')->label('Return No'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('return_date')->label('Date')->date(),
                        TextEntry::make('issueRequest.request_no')->label('Source Request')->placeholder('—'),
                        TextEntry::make('returnedBy.name')->label('Returned By')->placeholder('—'),
                        TextEntry::make('receivedBy.name')->label('Received By')->placeholder('—'),
                        TextEntry::make('posted_at')->label('Posted At')->dateTime()->placeholder('—'),
                        TextEntry::make('reason')->label('Reason')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Code'),
                                TableColumn::make('Name'),
                                TableColumn::make('Quantity'),
                            ])
                            ->schema([
                                TextEntry::make('item.code')->label('Code'),
                                TextEntry::make('item.name')->label('Name'),
                                TextEntry::make('quantity')->numeric()->label('Quantity'),
                            ]),
                    ]),
            ]);
    }
}
