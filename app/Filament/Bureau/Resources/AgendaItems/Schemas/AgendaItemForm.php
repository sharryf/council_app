<?php

namespace App\Filament\Bureau\Resources\AgendaItems\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AgendaItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('details')
                    ->label(__('bureau.agenda.field.details'))
                    ->required()
                    ->rows(6),
                FileUpload::make('attachment')
                    ->label(__('bureau.agenda.field.attachment'))
                    ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/png', 'image/jpeg'])
                    ->disk('local')
                    ->directory('bureau/agenda')
                    ->visibility('private')
                    // Keeps the on-disk filename a safe random one (see
                    // FileUpload::preserveFilenames()'s own security
                    // note about PHP execution risk from user-controlled
                    // filenames) while still capturing the true original
                    // name — extracted in mutateFormDataBeforeCreate()/
                    // Save() — purely for display/download purposes.
                    ->storeFileNamesIn('attachment_names'),
            ]);
    }
}
