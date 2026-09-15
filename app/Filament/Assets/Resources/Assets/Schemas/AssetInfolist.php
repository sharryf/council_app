<?php

namespace App\Filament\Assets\Resources\Assets\Schemas;

use App\Models\Asset;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        ImageEntry::make('photo_attachment_id')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Asset $record): ?string => $record->photo_attachment_id
                                ? route('assets.attachments.show', $record->photo_attachment_id)
                                : null)
                            ->height(120)
                            ->columnSpan(1),
                        TextEntry::make('name')->label('Name')->weight('bold')->size('lg')->columnSpan(3),
                        TextEntry::make('asset_tag')->label('Asset tag'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('category.name')->label('Category')->formatStateUsing(fn (Asset $record): string => $record->category?->path() ?? '—'),
                        TextEntry::make('room.name')->label('Location')->formatStateUsing(fn (Asset $record): string => $record->room?->path() ?? '—'),
                        TextEntry::make('transfer_pending')
                            ->label('')
                            ->state('Transfer Pending')
                            ->badge()
                            ->color('warning')
                            ->visible(fn (Asset $record): bool => $record->hasPendingTransfer())
                            ->columnSpanFull(),
                    ]),

                Section::make('Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('brand')->label('Brand')->placeholder('—'),
                        TextEntry::make('model')->label('Model')->placeholder('—'),
                        TextEntry::make('serial_number')->label('Serial number')->placeholder('—'),
                        TextEntry::make('purchase_date')->label('Purchase date')->date()->placeholder('—'),
                        TextEntry::make('purchase_price')->label('Purchase price')->money('MVR')->placeholder('—'),
                        TextEntry::make('vendor')->label('Vendor / Supplier')->placeholder('—'),
                        TextEntry::make('description')->label('Description / Notes')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('createdBy.name')->label('Added by')->placeholder('—'),
                    ]),

                Section::make('Register Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('main_inventory_no')->label('Main inventory no.')->placeholder('—'),
                        TextEntry::make('asset_type')->label('Asset type')->badge(),
                        TextEntry::make('fund_code')->label('Fund code')->placeholder('—'),
                        TextEntry::make('po_number')->label('PO number')->placeholder('—'),
                        TextEntry::make('voucher_number')->label('Voucher number')->placeholder('—'),
                        TextEntry::make('donation_reference_no')->label('Donation document ref. no.')->placeholder('—'),
                    ]),

                Section::make('Documents')
                    ->schema([
                        RepeatableEntry::make('documents')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('file_name')
                                    ->hiddenLabel()
                                    ->url(fn ($record): string => route('assets.attachments.show', $record->id))
                                    ->openUrlInNewTab(),
                            ])
                            ->columns(2),
                    ])
                    ->visible(fn (Asset $record): bool => $record->documents()->exists()),

                Section::make('Maintenance')
                    ->schema([
                        TextEntry::make('open_maintenance_notice')
                            ->hiddenLabel()
                            ->state(fn (Asset $record): ?string => $record->openMaintenanceRecord()
                                ? "Open record: {$record->openMaintenanceRecord()->description}"
                                : null)
                            ->badge()
                            ->color('warning')
                            ->visible(fn (Asset $record): bool => $record->openMaintenanceRecord() !== null)
                            ->columnSpanFull(),
                        RepeatableEntry::make('maintenanceRecords')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('maintenance_date')->label('Date')->date(),
                                TextEntry::make('description')->label('Description')->limit(40),
                                TextEntry::make('approval_status')->label('Approval')->badge(),
                                TextEntry::make('closed_at')->label('Closed')->dateTime()->placeholder('Open'),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn (Asset $record): bool => $record->maintenanceRecords()->exists()),

                Section::make('History')
                    ->schema([
                        RepeatableEntry::make('history')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('created_at')->label('When')->dateTime(),
                                TextEntry::make('performedBy.name')->label('By')->placeholder('System'),
                                TextEntry::make('event_type')->label('Event'),
                                TextEntry::make('field_name')->label('Field')->placeholder('—'),
                                TextEntry::make('old_value')->label('From')->placeholder('—'),
                                TextEntry::make('new_value')->label('To')->placeholder('—'),
                                TextEntry::make('note')->label('Note')->placeholder('—')->columnSpanFull(),
                            ])
                            ->columns(6),
                    ]),
            ]);
    }
}
