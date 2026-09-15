<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Schemas;

use App\Enums\DocumentSigningRole;
use App\Enums\SigningMode;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                FileUpload::make('file_path')
                    ->label('Document')
                    ->disk('local')
                    ->directory('documents/uploads')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->storeFileNamesIn('file_original_name')
                    ->maxSize(20 * 1024)
                    ->required()
                    ->helperText('PDF only, up to 20MB. Convert Word documents to PDF before uploading — see the module\'s README note on why.')
                    ->columnSpanFull(),

                ToggleButtons::make('signing_mode')
                    ->label('Signing order')
                    ->options(SigningMode::class)
                    ->default(SigningMode::Sequential)
                    ->inline()
                    ->required()
                    ->columnSpanFull(),

                Repeater::make('signers')
                    ->relationship()
                    ->label('Required signers')
                    ->orderColumn('order')
                    ->addActionLabel('Add signer')
                    ->minItems(1)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('user_id')
                            ->label('Signer')
                            ->options(fn (): array => User::all()
                                ->filter(fn (User $user): bool => $user->hasDocumentSigningRole(DocumentSigningRole::Signee))
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->helperText('Only users with the Signee role can be picked.')
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('role_label')
                            ->label('Role / title for this signature')
                            ->default('Signer')
                            ->placeholder('e.g. Section Head')
                            ->required(),
                    ])
                    ->helperText('When "Sequential" is selected above, signers act in this order — drag rows to reorder. In "Parallel" mode this order is ignored and everyone can sign as soon as the document is submitted.'),
            ]);
    }
}
