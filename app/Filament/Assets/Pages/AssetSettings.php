<?php

namespace App\Filament\Assets\Pages;

use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Models\AssetSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A single setting so far — the council code used to build every
 * asset's MainInventoryNo/ItemInventoryNo (see AssetTagGenerator) — so
 * this is a fixed-field form over asset_settings rather than
 * InventorySettingsPage's dynamically-built-from-rows one. Admin only,
 * same as every other Settings page in this panel.
 */
class AssetSettings extends Page
{
    use HasAssetRoleAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'General';

    protected static ?int $navigationSort = 40;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return self::userIsAssetAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'General Settings';
    }

    public function mount(): void
    {
        $this->form->fill([
            'council_code' => AssetSetting::get('council_code'),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Register numbering')
                ->description('Used to build every asset\'s inventory number, e.g. 317-23-Z550-1-1.')
                ->schema([
                    TextInput::make('council_code')
                        ->label('Council code')
                        ->helperText('The code your council is assigned in the government asset register, e.g. "317".')
                        ->required()
                        ->maxLength(20),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        AssetSetting::set('council_code', $state['council_code']);

        Notification::make()->title('Settings updated')->success()->send();
    }
}
