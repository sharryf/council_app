<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Schemas;

use App\Enums\SignatureType;
use App\Models\Document;
use App\Models\DocumentSigner;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')
                            ->getStateUsing(fn (Document $record): string => $record->displayTitle()),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('uploader.name')->label('Uploaded by'),
                        TextEntry::make('signing_mode'),
                        TextEntry::make('created_at')->label('Uploaded')->dateTime(),
                        TextEntry::make('signed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('rejection_reason')
                            ->label('Rejection reason')
                            ->columnSpanFull()
                            ->visible(fn (?string $state): bool => filled($state)),
                        TextEntry::make('void_reason')
                            ->label(fn (Document $record): string => 'Voided by '.($record->voidedBy?->name ?? 'unknown').' — reason')
                            ->columnSpanFull()
                            ->visible(fn (?string $state): bool => filled($state)),
                    ]),

                Section::make('Signers')
                    ->schema([
                        RepeatableEntry::make('signers')
                            ->label('')
                            ->schema([
                                TextEntry::make('user.name')->label('Signer'),
                                TextEntry::make('role_label')->label('Role'),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('signed_at')->dateTime()->placeholder('Not yet signed'),
                                TextEntry::make('rejection_reason')
                                    ->label('Rejection reason')
                                    ->visible(fn (?string $state): bool => filled($state)),
                                ImageEntry::make('signature_value')
                                    ->label('Signature')
                                    ->disk('local')
                                    ->height(60)
                                    ->visible(fn (DocumentSigner $record): bool => $record->signature_type?->isImageBased() ?? false),
                                TextEntry::make('signature_value')
                                    ->label('Signature')
                                    ->fontFamily('serif')
                                    ->size('lg')
                                    ->visible(fn (DocumentSigner $record): bool => $record->signature_type === SignatureType::Typed),
                                TextEntry::make('hash')
                                    ->label('Verification hash (SHA-256)')
                                    ->fontFamily('mono')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
