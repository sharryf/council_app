<?php

namespace App\Filament\Inventory\Resources\IssueRequests\Schemas;

use App\Models\InventoryIssueReceipt;
use App\Models\InventoryIssueRequest;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class IssueRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Code'),
                                TableColumn::make('Name'),
                                TableColumn::make('Requested'),
                                TableColumn::make('Approved'),
                                TableColumn::make('Issued'),
                                TableColumn::make('Status'),
                            ])
                            ->schema([
                                TextEntry::make('item.code')->label('Code'),
                                TextEntry::make('item.name')->label('Name'),
                                TextEntry::make('requested_qty')->numeric()->label('Requested'),
                                TextEntry::make('approved_qty')->numeric()->placeholder('—')->label('Approved'),
                                TextEntry::make('issued_qty')->numeric()->label('Issued'),
                                TextEntry::make('line_status')->badge()->label('Status'),
                            ]),
                    ]),

                Section::make('Request')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('request_no')->label('Request No'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('requester.name')->label('Requested By'),
                        TextEntry::make('recipient.name')->label('Issuing To')->placeholder('Requester (self)'),
                        TextEntry::make('purpose')->label('Purpose')->columnSpanFull(),
                        TextEntry::make('remarks')->label('Remarks')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Approval & Issue')
                    ->columns(4)
                    ->visible(fn (InventoryIssueRequest $record): bool => filled($record->approved_at) || filled($record->rejected_at) || filled($record->issued_at))
                    ->schema([
                        TextEntry::make('approver.name')->label('Approver')->placeholder('—'),
                        TextEntry::make('decision_at')
                            ->label('Decision At')
                            ->state(fn (InventoryIssueRequest $record) => $record->approved_at ?? $record->rejected_at)
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('issuedByUser.name')->label('Issued By')->placeholder('—'),
                        TextEntry::make('issued_at')->label('Issued At')->dateTime()->placeholder('—'),
                        TextEntry::make('approval_remarks')->label('Approval Remarks')->placeholder('—')->columnSpan(2),
                        TextEntry::make('rejection_reason')->label('Rejection Reason')->placeholder('—')->columnSpan(2)
                            ->visible(fn (InventoryIssueRequest $record): bool => filled($record->rejection_reason)),
                    ]),

                Section::make('Receipts')
                    ->visible(fn (InventoryIssueRequest $record): bool => $record->receipts->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('receipts')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Received By'),
                                TableColumn::make('Items'),
                                TableColumn::make('Issued By'),
                                TableColumn::make('Issued At'),
                            ])
                            ->schema([
                                TextEntry::make('received_by_name')->label('Received By'),
                                TextEntry::make('items')
                                    ->label('Items')
                                    ->state(fn (InventoryIssueReceipt $record): string => $record->movements
                                        ->map(fn ($movement) => Number::format((float) $movement->quantity).'× '.$movement->item->code)
                                        ->implode(', ')),
                                TextEntry::make('issuedByUser.name')->label('Issued By'),
                                TextEntry::make('issued_at')->dateTime()->label('Issued At'),
                            ]),
                    ]),

                Section::make('Cancellation')
                    ->columns(3)
                    ->visible(fn (InventoryIssueRequest $record): bool => filled($record->cancelled_at))
                    ->schema([
                        TextEntry::make('cancelledBy.name')->label('Cancelled By')->placeholder('—'),
                        TextEntry::make('cancelled_at')->label('Cancelled At')->dateTime()->placeholder('—'),
                        TextEntry::make('cancellation_reason')->label('Reason')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Attachments')
                    ->visible(fn (InventoryIssueRequest $record): bool => $record->attachments->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('file_name')
                                    ->label('File')
                                    ->url(fn ($record): string => route('inventory.attachments.show', $record))
                                    ->openUrlInNewTab(),
                            ]),
                    ]),

                Section::make('Approval History')
                    ->visible(fn (InventoryIssueRequest $record): bool => $record->approvalActions->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('approvalActions')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Action'),
                                TableColumn::make('By'),
                                TableColumn::make('At'),
                                TableColumn::make('Remarks'),
                            ])
                            ->schema([
                                TextEntry::make('action')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'SUBMITTED' => 'info',
                                        'APPROVED' => 'warning',
                                        'ISSUED' => 'success',
                                        'REJECTED', 'CANCELLED' => 'danger',
                                        'RETURNED_FOR_EDIT' => 'gray',
                                        default => 'gray',
                                    })
                                    ->label('Action'),
                                TextEntry::make('actionBy.name')->label('By'),
                                TextEntry::make('action_at')->dateTime()->label('At'),
                                TextEntry::make('remarks')->placeholder('—')->label('Remarks'),
                            ]),
                    ]),
            ]);
    }
}
