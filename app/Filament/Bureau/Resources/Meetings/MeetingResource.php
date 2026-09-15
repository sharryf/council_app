<?php

namespace App\Filament\Bureau\Resources\Meetings;

use App\Enums\BureauMeetingStatus;
use App\Enums\BureauRole;
use App\Filament\Bureau\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\Bureau\Resources\Meetings\Pages\EditMeeting;
use App\Filament\Bureau\Resources\Meetings\Pages\ListMeetings;
use App\Filament\Bureau\Resources\Meetings\Pages\RecordMinutes;
use App\Filament\Bureau\Resources\Meetings\Pages\ViewMeeting;
use App\Filament\Bureau\Resources\Meetings\Schemas\MeetingForm;
use App\Filament\Bureau\Resources\Meetings\Schemas\MeetingInfolist;
use App\Filament\Bureau\Resources\Meetings\Tables\MeetingsTable;
use App\Mail\Bureau\MeetingScheduledMail;
use App\Models\BureauMeeting;
use App\Models\User;
use App\Services\Bureau\MeetingAgendaPdfService;
use App\Services\Bureau\MeetingApprovalPacketPdfService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Meeting — see the module's own workflow: the President or a Bureau
 * Admin creates a meeting (approved agenda items auto-attach, see
 * CreateMeeting), sends it for the Council President's approval, and
 * once approved it's scheduled — every attendee gets an email with the
 * generated agenda PDF attached (see approveAction()).
 */
class MeetingResource extends Resource
{
    protected static ?string $model = BureauMeeting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function getNavigationLabel(): string
    {
        return __('bureau.meeting.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('bureau.meeting.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bureau.meeting.plural_label');
    }

    /**
     * Any bureau participant may see the shared meeting list — same
     * rule as AgendaItemResource::canAccess().
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && collect(BureauRole::cases())->contains(fn (BureauRole $role): bool => $user->hasBureauRole($role));
    }

    /**
     * The President and the Bureau Admin both "create meetings" per
     * the module's permission table (see App\Enums\BureauRole) — the
     * President additionally approves them, the Bureau Admin runs the
     * live minutes once scheduled.
     */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasBureauRole(BureauRole::President) || $user->hasBureauRole(BureauRole::BureauAdmin));
    }

    /**
     * Editable only while Draft or Rejected (i.e. not yet sent for
     * approval, or sent back) — and only by whoever may create
     * meetings in the first place.
     */
    public static function canEdit(Model $record): bool
    {
        /** @var BureauMeeting $record */
        $user = auth()->user();

        return $user
            && in_array($record->status, [BureauMeetingStatus::Draft, BureauMeetingStatus::Rejected], true)
            && static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return MeetingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MeetingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeetingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeetings::route('/'),
            'create' => CreateMeeting::route('/create'),
            'view' => ViewMeeting::route('/{record}'),
            'edit' => EditMeeting::route('/{record}/edit'),
            'minutes' => RecordMinutes::route('/{record}/minutes'),
        ];
    }

    /**
     * Visible once a meeting is Scheduled (agenda finalized, attendees
     * confirmed) to anyone who may act on its minutes — Bureau Admin to
     * record, President to approve, any attendee to review/edit once
     * it reaches Review, or anyone else to just read.
     */
    public static function minutesAction(): Action
    {
        return Action::make('minutes')
            ->label(__('bureau.minutes.label'))
            ->icon(Heroicon::OutlinedMicrophone)
            ->color('gray')
            ->url(fn (BureauMeeting $record): string => static::getUrl('minutes', ['record' => $record]))
            ->visible(fn (BureauMeeting $record): bool => $record->status === BureauMeetingStatus::Scheduled);
    }

    /**
     * President or Bureau Admin only, and only while Draft or Rejected
     * — moves it into the President's approval queue. Generates the
     * Meeting Request letter the President reviews alongside the
     * request — see MeetingApprovalPacketPdfService. The agenda itself
     * has only one PDF, generated later in approveAction() once
     * actually approved and emailed to attendees — not duplicated here
     * as a separate proposal draft.
     */
    public static function sendForApprovalAction(): Action
    {
        return Action::make('sendForApproval')
            ->label(__('bureau.meeting.actions.send_for_approval'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (BureauMeeting $record): bool => static::canEdit($record))
            ->action(function (BureauMeeting $record): void {
                $requestPdfPath = app(MeetingApprovalPacketPdfService::class)->generateRequest($record);

                $record->update([
                    'status' => BureauMeetingStatus::PendingApproval,
                    'meeting_request_pdf_path' => $requestPdfPath,
                ]);

                Notification::make()->title(__('bureau.meeting.status.pending_approval'))->success()->send();
            });
    }

    /**
     * Generates the agenda PDF and emails every attendee — see
     * MeetingAgendaPdfService and App\Mail\Bureau\MeetingScheduledMail.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('bureau.meeting.actions.approve'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (BureauMeeting $record): bool => static::isReviewable($record))
            ->action(function (BureauMeeting $record): void {
                $pdfPath = app(MeetingAgendaPdfService::class)->generate($record);

                $record->update([
                    'status' => BureauMeetingStatus::Scheduled,
                    'agenda_pdf_path' => $pdfPath,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                $record->loadMissing('attendees');

                foreach ($record->attendees as $attendee) {
                    /** @var User $attendee */
                    Mail::to($attendee)->send(new MeetingScheduledMail($record));
                }

                Notification::make()->title(__('bureau.meeting.status.scheduled'))->success()->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('bureau.meeting.actions.reject'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (BureauMeeting $record): bool => static::isReviewable($record))
            ->schema([
                Textarea::make('reason')
                    ->label(__('bureau.meeting.field.rejection_reason'))
                    ->required(),
            ])
            ->action(function (BureauMeeting $record, array $data): void {
                $record->update([
                    'status' => BureauMeetingStatus::Rejected,
                    'rejection_reason' => $data['reason'],
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                Notification::make()->title(__('bureau.meeting.status.rejected'))->warning()->send();
            });
    }

    private static function isReviewable(BureauMeeting $record): bool
    {
        $user = auth()->user();

        return $record->status === BureauMeetingStatus::PendingApproval
            && $user
            && $user->hasBureauRole(BureauRole::President);
    }
}
