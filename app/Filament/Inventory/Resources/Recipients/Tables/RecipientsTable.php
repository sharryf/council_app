<?php

namespace App\Filament\Inventory\Resources\Recipients\Tables;

use App\Filament\Inventory\Resources\Recipients\RecipientResource;
use App\Models\InventoryRecipient;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderBy('name'))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('identification_no')->label('Identification No')->placeholder('—'),
                TextColumn::make('phone')->label('Phone')->placeholder('—'),
                TextColumn::make('department')->label('Department')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (InventoryRecipient $record): bool => RecipientResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (InventoryRecipient $record): bool => RecipientResource::canDelete($record))
                        ->missingBulkAuthorizationFailureNotificationMessage(
                            fn (int $failureCount): string => trans_choice(
                                '{1} :count recipient has received issued goods and can\'t be deleted — turn their "Active" toggle off instead.|[2,*] :count recipients have received issued goods and can\'t be deleted — turn their "Active" toggle off instead.',
                                $failureCount,
                                ['count' => $failureCount],
                            ),
                        ),
                ]),
            ]);
    }
}
