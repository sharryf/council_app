<?php

namespace App\Filament\Assets\Resources\Assets\Schemas;

use App\Models\Asset;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // A product-card header: a large photo alongside the
                // asset's identity and lifecycle at a glance, before any
                // of the detail sections below.
                Section::make()
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('photo_attachment_id')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Asset $record): ?string => $record->photo_attachment_id
                                ? route('assets.attachments.show', $record->photo_attachment_id)
                                : null)
                            ->height(220)
                            ->columnSpan(1),
                        Grid::make(2)
                            ->columnSpan(2)
                            ->schema([
                                TextEntry::make('asset_tag')->label('Asset tag')->badge()->color('primary')->size('md'),
                                TextEntry::make('category.name')->label('Category')->formatStateUsing(fn (Asset $record): string => $record->category?->path() ?? '—'),
                                TextEntry::make('status')->label('Condition')->badge(),
                                TextEntry::make('lifecycle_status')->label('Register status')->badge(),
                                TextEntry::make('room.name')->label('Location')->formatStateUsing(fn (Asset $record): string => $record->room?->path() ?? '—'),
                                TextEntry::make('createdBy.name')->label('Posted by')->placeholder('—'),
                                TextEntry::make('transfer_pending')
                                    ->label('')
                                    ->state('Location Change Pending')
                                    ->badge()
                                    ->color('warning')
                                    ->visible(fn (Asset $record): bool => $record->hasPendingTransfer())
                                    ->columnSpanFull(),
                                TextEntry::make('edit_pending')
                                    ->label('')
                                    ->state('Edit Pending Approval')
                                    ->badge()
                                    ->color('warning')
                                    ->visible(fn (Asset $record): bool => $record->hasPendingEditRequest())
                                    ->columnSpanFull(),
                            ]),
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

                // Same compact log treatment as Maintenance/Location —
                // one line per document, not a bordered card per row.
                Section::make('Documents')
                    ->schema([
                        RepeatableEntry::make('documents')
                            ->hiddenLabel()
                            ->contained(false)
                            ->schema([
                                TextEntry::make('file_name')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn ($record): string => sprintf(
                                        '%s · %s',
                                        $record->created_at->format('j M Y'),
                                        $record->document_name ?: $record->file_name,
                                    ))
                                    ->size('sm')
                                    ->color('gray')
                                    ->url(fn ($record): string => route('assets.attachments.show', $record->id))
                                    ->openUrlInNewTab(),
                            ])
                            ->columns(1),
                    ])
                    ->visible(fn (Asset $record): bool => $record->documents()->exists()),

                // A plain log, same treatment as History below — one
                // line per record (Date, Description, Logged by,
                // Approval) instead of a bordered card per row.
                Section::make('Maintenance')
                    ->schema([
                        TextEntry::make('open_maintenance_notice')
                            ->hiddenLabel()
                            ->state(fn (Asset $record): ?string => $record->openMaintenanceRecord()
                                ? "Open record: {$record->openMaintenanceRecord()->description}"
                                : null)
                            ->badge()
                            ->color('warning')
                            ->visible(fn (Asset $record): bool => $record->openMaintenanceRecord() !== null),
                        TextEntry::make('maintenance_log')
                            ->hiddenLabel()
                            ->state(fn (Asset $record): array => $record->maintenanceRecords
                                ->map(fn ($entry): string => sprintf(
                                    '%s · %s · Logged by %s · %s',
                                    $entry->maintenance_date->format('j M Y'),
                                    $entry->description,
                                    $entry->recordedBy?->name ?? 'System',
                                    $entry->decisionSummary(),
                                ))
                                ->all())
                            ->listWithLineBreaks()
                            ->size('sm')
                            ->color('gray'),
                    ])
                    ->visible(fn (Asset $record): bool => $record->maintenanceRecords()->exists()),

                // Same compact log treatment as Maintenance — one line
                // per request (Date, From, To, Reason, Approved).
                Section::make('Location Changes')
                    ->schema([
                        TextEntry::make('location_log')
                            ->hiddenLabel()
                            ->state(fn (Asset $record): array => $record->transferRequests
                                ->map(fn ($entry): string => sprintf(
                                    '%s · %s → %s · %s · %s',
                                    $entry->requested_at->format('j M Y'),
                                    $entry->fromRoom?->name ?? '—',
                                    $entry->toRoom?->name ?? '—',
                                    $entry->reason ?: '—',
                                    $entry->decisionSummary(),
                                ))
                                ->all())
                            ->listWithLineBreaks()
                            ->size('sm')
                            ->color('gray'),
                    ])
                    ->visible(fn (Asset $record): bool => $record->transferRequests()->exists()),

                // Only the asset's own create/edit/post lifecycle —
                // maintenance and location changes have their own cards
                // above, so they're left out here to keep this scannable.
                Section::make('Edit History')
                    ->schema([
                        TextEntry::make('history_log')
                            ->hiddenLabel()
                            ->state(fn (Asset $record): array => $record->history
                                ->whereIn('event_type', [
                                    'created',
                                    'posted',
                                    'field_changed',
                                    'photo_replaced',
                                    'edit_requested',
                                    'edit_approved',
                                    'edit_rejected',
                                    'delete_requested',
                                    'delete_rejected',
                                ])
                                ->map(fn ($entry): string => sprintf(
                                    '%s · %s · %s',
                                    $entry->created_at->format('j M Y, H:i'),
                                    $entry->performedBy?->name ?? 'System',
                                    $entry->summary(),
                                ))
                                ->values()
                                ->all())
                            ->listWithLineBreaks()
                            ->size('sm')
                            ->color('gray'),
                    ]),
            ]);
    }
}
