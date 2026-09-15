<?php

namespace App\Filament\Bureau\Pages;

use App\Enums\BureauRole;
use App\Models\BureauSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Module-Admin-only (same access rule as BureauRoles) — sets the
 * council's currently active term number and the next meeting number
 * to assign for each meeting type, all of which MeetingForm/CreateMeeting
 * read so nobody has to pick them per meeting (see App\Models\BureauSettings).
 *
 * Public and emergency meetings are numbered in separate sequences, so
 * there are two counters. Both are plain editable counters rather than
 * derived from existing bureau_meetings rows, specifically so the
 * Module Admin can set them to whatever comes after meetings that
 * already happened before this system existed, and reset them to 1
 * when a new term starts (alongside bumping current_term_number here
 * too).
 */
class BureauMeetingSettings extends Page
{
    protected static ?string $moduleKey = 'bureau';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 11;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('bureau.meeting_settings.nav_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('bureau.settings_nav_group');
    }

    public function getTitle(): string
    {
        return __('bureau.meeting_settings.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->canAccessModule(static::$moduleKey)
            && $user->hasBureauRole(BureauRole::ModuleAdmin);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $settings = BureauSettings::current();

        $this->form->fill([
            'current_term_number' => $settings->current_term_number,
            'next_public_meeting_number' => $settings->next_public_meeting_number,
            'next_private_meeting_number' => $settings->next_private_meeting_number,
            'pdf_header_text' => $settings->pdf_header_text,
            'pdf_footer_text' => $settings->pdf_footer_text,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('current_term_number')
                    ->label(__('bureau.meeting_settings.field.current_term_number'))
                    ->helperText(__('bureau.meeting_settings.field.current_term_number_help'))
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                TextInput::make('next_public_meeting_number')
                    ->label(__('bureau.meeting_settings.field.next_public_meeting_number'))
                    ->helperText(__('bureau.meeting_settings.field.next_meeting_number_help'))
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                TextInput::make('next_private_meeting_number')
                    ->label(__('bureau.meeting_settings.field.next_private_meeting_number'))
                    ->helperText(__('bureau.meeting_settings.field.next_meeting_number_help'))
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                Section::make(__('bureau.meeting_settings.pdf_section_title'))
                    ->description(__('bureau.meeting_settings.pdf_section_description'))
                    ->schema([
                        Textarea::make('pdf_header_text')
                            ->label(__('bureau.meeting_settings.field.pdf_header_text'))
                            ->helperText(__('bureau.meeting_settings.field.pdf_header_text_help'))
                            ->rows(2),
                        Textarea::make('pdf_footer_text')
                            ->label(__('bureau.meeting_settings.field.pdf_footer_text'))
                            ->helperText(__('bureau.meeting_settings.field.pdf_footer_text_help'))
                            ->rows(2),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('bureau.meeting_settings.actions.save'))
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        BureauSettings::current()->update($data);

        Notification::make()->title(__('bureau.meeting_settings.notifications.updated'))->success()->send();
    }
}
