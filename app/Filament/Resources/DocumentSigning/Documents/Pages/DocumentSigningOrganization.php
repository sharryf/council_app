<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Pages;

use App\Enums\DocumentSigningRole;
use App\Models\DocumentSigningOrganization as DocumentSigningOrganizationModel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Admin-only (see App\Enums\DocumentSigningRole). Manages the module's
 * two organization stamp slots (see App\Models\DocumentSigningOrganization)
 * — the wizard lets the uploader pick between them when placing a stamp
 * on a document.
 */
class DocumentSigningOrganization extends Page
{
    protected static ?string $moduleKey = 'document-signing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Organization';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Organization';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->canAccessModule(static::$moduleKey)
            && $user->hasDocumentSigningRole(DocumentSigningRole::Admin);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $organization = DocumentSigningOrganizationModel::current();

        $this->form->fill([
            'stamp_label' => $organization->stamp_label,
            'stamp_label_2' => $organization->stamp_label_2,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stamp 1')
                    ->schema([
                        TextInput::make('stamp_label')
                            ->label('Label')
                            ->placeholder('Stamp 1')
                            ->helperText('Shown to uploaders when they choose which stamp to place.')
                            ->maxLength(255),
                        View::make('filament.pages.partials.stamp-preview')
                            ->viewData(['dataUri' => static::stampDataUri(1)]),
                        FileUpload::make('stamp')
                            ->label('Upload a new stamp image')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->disk('local')
                            ->directory('document-signing/organization')
                            ->visibility('private'),
                    ]),

                Section::make('Stamp 2')
                    ->schema([
                        TextInput::make('stamp_label_2')
                            ->label('Label')
                            ->placeholder('Stamp 2')
                            ->helperText('Shown to uploaders when they choose which stamp to place.')
                            ->maxLength(255),
                        View::make('filament.pages.partials.stamp-preview')
                            ->viewData(['dataUri' => static::stampDataUri(2)]),
                        FileUpload::make('stamp_2')
                            ->label('Upload a new stamp image')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->disk('local')
                            ->directory('document-signing/organization')
                            ->visibility('private'),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        ActionsComponent::make([
                            Action::make('save')
                                ->label('Save changes')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $organization = DocumentSigningOrganizationModel::current();

        $updates = [
            'stamp_label' => filled($data['stamp_label'] ?? null) ? $data['stamp_label'] : null,
            'stamp_label_2' => filled($data['stamp_label_2'] ?? null) ? $data['stamp_label_2'] : null,
        ];

        $oldPaths = [];

        if (filled($data['stamp'] ?? null)) {
            $oldPaths[] = $organization->stamp_path;
            $updates['stamp_path'] = $data['stamp'];
        }

        if (filled($data['stamp_2'] ?? null)) {
            $oldPaths[] = $organization->stamp_path_2;
            $updates['stamp_path_2'] = $data['stamp_2'];
        }

        $organization->update($updates);

        foreach (array_filter($oldPaths) as $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->form->fill([
            'stamp_label' => $organization->stamp_label,
            'stamp_label_2' => $organization->stamp_label_2,
        ]);

        Notification::make()->title('Organization stamps updated')->success()->send();
    }

    private static function stampDataUri(int $slot): ?string
    {
        $organization = DocumentSigningOrganizationModel::current();

        if (! $organization->hasStampInSlot($slot)) {
            return null;
        }

        $disk = Storage::disk('local');
        $path = $organization->stampPathForSlot($slot);
        $mime = $disk->mimeType($path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode($disk->get($path));
    }
}
