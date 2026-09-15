<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Tables;

use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Filament\Resources\DocumentSigning\Documents\DocumentResource;
use App\Models\Document;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['uploader', 'signers']))
            ->defaultSort('created_at', 'desc')
            // No ViewAction button — the title (and the rest of the row)
            // already opens the same page. Set explicitly rather than
            // relying on Filament's own fallback (which, without a
            // registered 'view' action, would default to a registered
            // and visible 'edit' action's URL instead for an editable
            // Draft — pinning this avoids that edge case entirely.
            ->recordUrl(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (Document $record): string => $record->displayTitle())
                    ->searchable(['title', 'file_original_name'])
                    ->weight('semibold'),
                TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('progress')
                    ->label('Signatures')
                    ->getStateUsing(fn (Document $record): string => $record->status === DocumentStatus::Rejected
                        ? '—'
                        : "{$record->signedCount()}/{$record->totalSignersCount()} signed"),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DocumentStatus::class),
                Filter::make('assigned_to_me')
                    ->label('Assigned to me (pending)')
                    ->query(fn ($query) => $query
                        ->where('status', DocumentStatus::Pending)
                        ->whereHas('signers', fn ($q) => $q
                            ->where('user_id', auth()->id())
                            ->where('status', SignerStatus::Pending))),
                Filter::make('uploaded_by_me')
                    ->label('Uploaded by me')
                    ->query(fn ($query) => $query->where('uploaded_by', auth()->id())),
            ])
            ->recordActions([
                DocumentResource::previewAction(),
                DocumentResource::editAction(),
                DocumentResource::submitAction(),
                DocumentResource::signAction(),
                DocumentResource::rejectAction(),
                DocumentResource::voidAction(),
                DocumentResource::downloadOriginalAction(),
                DocumentResource::downloadSignedAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DocumentResource::deleteBulkAction(),
                ]),
            ]);
    }
}
