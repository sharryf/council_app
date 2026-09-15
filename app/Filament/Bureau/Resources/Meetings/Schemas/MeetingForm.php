<?php

namespace App\Filament\Bureau\Resources\Meetings\Schemas;

use App\Enums\BureauMeetingStatus;
use App\Enums\BureauRole;
use App\Models\BureauAgendaItem;
use App\Models\BureauMeeting;
use App\Models\BureauSettings;
use App\Models\User;
use App\Support\DhivehiDate;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class MeetingForm
{
    /**
     * Who can be marked as an attendee — every Bureau role except
     * Staff (view-only, doesn't attend/vote — see App\Enums\BureauRole).
     */
    private const ATTENDEE_ROLES = [
        BureauRole::President,
        BureauRole::Councillor,
        BureauRole::ModuleAdmin,
        BureauRole::BureauAdmin,
        BureauRole::Participant,
    ];

    /**
     * Today if it's already Wednesday, otherwise the next one.
     */
    private static function upcomingWednesday(): Carbon
    {
        return now()->isWednesday() ? now() : now()->next(Carbon::WEDNESDAY);
    }

    /**
     * Recombines the segmented day/month/year/time fields into a single
     * scheduled_at value and strips them from $data, since none of the
     * four are BureauMeeting columns — used by both
     * CreateMeeting::mutateFormDataBeforeCreate() and
     * EditMeeting::mutateFormDataBeforeSave().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function combineScheduledAt(array $data): array
    {
        $data['scheduled_at'] = Carbon::createFromDate(
            (int) $data['scheduled_year'],
            (int) $data['scheduled_month'],
            (int) $data['scheduled_day'],
        )->setTimeFromTimeString($data['scheduled_time']);

        unset($data['scheduled_day'], $data['scheduled_month'], $data['scheduled_year'], $data['scheduled_time']);

        return $data;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Term number comes from the module's settings, and the
                // meeting number is that type's next number in its own
                // sequence (public and emergency meetings are numbered
                // separately, see BureauSettings) — neither is
                // user-editable here. An existing record (editing)
                // keeps its own numbers rather than recalculating them.
                // ->live() on the type Select below makes this update
                // the instant the type changes.
                Placeholder::make('name_preview')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->content(function ($get, ?BureauMeeting $record): HtmlString {
                        $settings = BureauSettings::current();
                        $type = $get('type');

                        $name = BureauMeeting::buildName(
                            $record?->term_number ?? $settings->current_term_number,
                            $record?->meeting_number ?? $settings->nextMeetingNumberFor($type),
                            $type,
                        );

                        return new HtmlString(
                            '<div dir="rtl" style="text-align: center; font-size: 1.5rem; font-weight: 700;">'.e($name).'</div>',
                        );
                    }),
                Select::make('type')
                    ->label(__('bureau.meeting.field.type'))
                    ->options(BureauMeeting::TYPES)
                    ->default('public')
                    ->placeholder(null)
                    ->required()
                    ->live(),
                // A segmented day/month/year/time input instead of
                // Filament's own calendar picker — that picker is built
                // on dayjs, which has no Dhivehi locale, so its month
                // names and displayed text can only ever render in
                // English. These four are combined back into
                // scheduled_at in CreateMeeting/EditMeeting's mutate
                // methods (see MeetingForm::upcomingWednesday() for the
                // shared default). The ->default() closures below only
                // ever apply on create — Filament's edit-form fill
                // doesn't consult field defaults for keys missing from
                // the record, so EditMeeting::mutateFormDataBeforeFill()
                // populates these four explicitly from the record's own
                // scheduled_at instead.
                Fieldset::make(__('bureau.meeting.field.scheduled_at'))
                    ->columns(4)
                    ->schema([
                        TextInput::make('scheduled_day')
                            ->label(__('bureau.meeting.field.scheduled_day'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->default(fn (): int => self::upcomingWednesday()->day)
                            ->required()
                            ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                $month = (int) ($get('scheduled_month') ?: 1);
                                $year = (int) ($get('scheduled_year') ?: now()->year);

                                if ((int) $value > Carbon::create($year, $month, 1)->daysInMonth) {
                                    $fail(__('bureau.meeting.field.scheduled_day_invalid'));
                                }
                            }),
                        Select::make('scheduled_month')
                            ->label(__('bureau.meeting.field.scheduled_month'))
                            ->options(DhivehiDate::MONTHS)
                            ->default(fn (): int => self::upcomingWednesday()->month)
                            ->placeholder(null)
                            ->required()
                            ->live(),
                        TextInput::make('scheduled_year')
                            ->label(__('bureau.meeting.field.scheduled_year'))
                            ->numeric()
                            ->minValue(now()->year)
                            ->default(fn (): int => self::upcomingWednesday()->year)
                            ->required()
                            ->live(),
                        TextInput::make('scheduled_time')
                            ->label(__('bureau.meeting.field.scheduled_time'))
                            ->type('time')
                            ->default('10:15')
                            ->required(),
                    ]),
                TextInput::make('place')
                    ->label(__('bureau.meeting.field.place'))
                    ->default(BureauMeeting::DEFAULT_PLACE)
                    ->required(),
                Select::make('attendees')
                    ->label(__('bureau.meeting.field.attendees'))
                    ->relationship(
                        'attendees',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas(
                            'bureauRoles',
                            fn (Builder $query): Builder => $query->whereIn('role', self::ATTENDEE_ROLES),
                        ),
                    )
                    ->getOptionLabelFromRecordUsing(function (User $record): string {
                        $name = $record->name_dv ?: $record->name;
                        $position = $record->position_dv ?: $record->position;

                        return $position ? "{$name} — {$position}" : $name;
                    })
                    ->multiple()
                    ->searchable()
                    ->searchPrompt('ހޯދާ')
                    ->preload()
                    ->placeholder(null)
                    ->required(),
                // Every currently-approved-and-unclaimed agenda item is
                // preselected (see CreateMeeting::afterCreate(), which
                // reads this field's value rather than blindly grabbing
                // everything availableForMeeting() returns) — the
                // Bureau Admin can deselect any that shouldn't go on
                // this particular meeting's agenda. Create-only: an
                // existing meeting's agenda items are already attached,
                // and editing doesn't re-run the attach logic.
                Select::make('agenda_item_ids')
                    ->label(__('bureau.meeting.field.agenda_items'))
                    ->helperText(__('bureau.meeting.field.agenda_items_help'))
                    ->options(fn (): array => BureauAgendaItem::availableForMeeting()->pluck('details', 'id')->all())
                    ->default(fn (): array => BureauAgendaItem::availableForMeeting()->pluck('id')->all())
                    ->multiple()
                    ->searchable()
                    ->searchPrompt('ހޯދާ')
                    ->preload()
                    ->placeholder(null)
                    ->visible(fn (string $operation): bool => $operation === 'create'),
                // Both procedural items below are created (not offered
                // from a pool) in CreateMeeting::afterCreate() — see
                // App\Enums\BureauAgendaItemKind. Create-only, same
                // reasoning as agenda_item_ids above.
                Toggle::make('include_agenda_passing')
                    ->label(__('bureau.meeting.field.include_agenda_passing'))
                    ->default(true)
                    ->visible(fn (string $operation): bool => $operation === 'create'),
                Select::make('minutes_passing_meeting_ids')
                    ->label(__('bureau.meeting.field.minutes_passing_meetings'))
                    ->helperText(__('bureau.meeting.field.minutes_passing_meetings_help'))
                    ->options(fn (): array => self::pastMeetingsForMinutesPassing()->pluck('name', 'id')->all())
                    ->default(fn (): array => self::pastMeetingsForMinutesPassing()->take(1)->pluck('id')->all())
                    ->multiple()
                    ->searchable()
                    ->searchPrompt('ހޯދާ')
                    ->preload()
                    ->placeholder(null)
                    ->visible(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    /**
     * Past meetings eligible to have their minutes "passed" by a new
     * meeting — must already be held (Scheduled, not just Draft/Pending)
     * and in the past, most recent first (see
     * bureau.meeting.field.minutes_passing_meetings).
     */
    private static function pastMeetingsForMinutesPassing(): Collection
    {
        return BureauMeeting::query()
            ->where('status', BureauMeetingStatus::Scheduled)
            ->where('scheduled_at', '<', now())
            ->orderByDesc('scheduled_at')
            ->get();
    }
}
