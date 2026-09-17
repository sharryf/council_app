<?php

namespace App\Filament\Assets\Resources\Assets\Tables;

use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'category.parent',
                'room.building',
                'transferRequests' => fn ($q) => $q->where('status', AssetTransferStatus::Pending),
            ]))
            ->recordUrl(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record]))
            ->columns([
                // Tag, Name, Category, Asset Class Code, Location,
                // Status are the only columns visible by default —
                // everything else (photo, register status, transfer
                // flag, purchase date, type) is still available via the
                // column toggle, just not shown up front.
                ImageColumn::make('photo_attachment_id')
                    ->label('')
                    ->getStateUsing(fn (Asset $record): ?string => $record->photo_attachment_id
                        ? route('assets.attachments.show', $record->photo_attachment_id)
                        : null)
                    ->size(40)
                    ->circular(false)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('asset_tag')->label('Tag')->searchable()->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(['name', 'serial_number'])
                    ->sortable()
                    ->wrap(),
                TextColumn::make('category.parent.name')
                    ->label('Category')
                    ->formatStateUsing(fn (Asset $record): string => $record->category?->parent?->name ?? $record->category?->name ?? '—'),
                TextColumn::make('category.asset_class_code')
                    ->label('Code')
                    ->placeholder('—'),
                TextColumn::make('room.building.name')
                    ->label('Location')
                    ->formatStateUsing(fn (Asset $record): string => $record->room?->building?->name ?? '—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('lifecycle_status')
                    ->label('Register')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transfer_pending')
                    ->label('')
                    ->state(fn (Asset $record): ?string => $record->hasPendingTransfer() ? 'Transfer Pending' : null)
                    ->badge()
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchase_date')
                    ->label('Purchased')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('asset_type')
                    ->label('Type')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => AssetCategory::query()->orderBy('name')->pluck('name', 'id'))
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(AssetStatus::cases())->mapWithKeys(fn (AssetStatus $status): array => [$status->value => $status->getLabel()]))
                    ->multiple(),
                SelectFilter::make('lifecycle_status')
                    ->label('Register status')
                    ->options(collect(AssetLifecycleStatus::cases())->mapWithKeys(fn (AssetLifecycleStatus $s): array => [$s->value => $s->getLabel()])),
                SelectFilter::make('room.building_id')
                    ->label('Building')
                    ->options(fn () => AssetBuilding::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q) => $q->whereHas('room', fn ($r) => $r->where('building_id', $data['value'])),
                    )),
                Filter::make('purchase_date')
                    ->schema([
                        DatePicker::make('purchased_from'),
                        DatePicker::make('purchased_to'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['purchased_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('purchase_date', '>=', $date))
                        ->when($data['purchased_to'] ?? null, fn (Builder $q, $date) => $q->whereDate('purchase_date', '<=', $date))),
            ])
            ->persistFiltersInSession()
            ->toolbarActions([
                BulkActionGroup::make([
                    // Bulk label printing matters when tagging ~500
                    // assets initially (spec section 6.7) — every
                    // AssetRole may print labels, not just Admin.
                    //
                    // Deliberately ->action() + redirect(), not
                    // ->url()->openUrlInNewTab() — a bulk action's
                    // ->url() is evaluated once at table render time,
                    // before any checkbox is ticked, so it can't see
                    // the live selection (confirmed live: it always
                    // resolved to an empty id list). ->action() runs at
                    // click time with the real selection instead.
                    BulkAction::make('printLabels')
                        ->label('Print Labels')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->color('gray')
                        ->accessSelectedRecords()
                        ->action(function (Collection $records) {
                            return redirect(route('assets.labels.bulk', ['ids' => $records->pluck('id')->implode(',')]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (Asset $record): bool => AssetResource::canDelete($record)),
                ]),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100]);
    }
}
