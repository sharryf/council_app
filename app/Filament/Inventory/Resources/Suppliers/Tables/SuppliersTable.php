<?php

namespace App\Filament\Inventory\Resources\Suppliers\Tables;

use App\Filament\Inventory\Resources\Suppliers\SupplierResource;
use App\Models\InventorySupplier;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderBy('name'))
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('contact_person')->label('Contact')->placeholder('—'),
                TextColumn::make('phone')->label('Phone')->placeholder('—'),
                TextColumn::make('email')->label('Email')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (InventorySupplier $record): bool => SupplierResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (InventorySupplier $record): bool => SupplierResource::canDelete($record))
                        // Filament's own default wording here ("You don't
                        // have permission to delete :count") would be
                        // actively misleading — this isn't a permissions
                        // problem, the supplier just has receipts against
                        // it. Name the real reason instead.
                        ->missingBulkAuthorizationFailureNotificationMessage(
                            fn (int $failureCount): string => trans_choice(
                                '{1} :count supplier has received deliveries and can\'t be deleted — turn its "Active" toggle off instead.|[2,*] :count suppliers have received deliveries and can\'t be deleted — turn their "Active" toggle off instead.',
                                $failureCount,
                                ['count' => $failureCount],
                            ),
                        ),
                ]),
            ]);
    }
}
