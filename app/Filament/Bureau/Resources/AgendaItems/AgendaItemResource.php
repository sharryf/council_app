<?php

namespace App\Filament\Bureau\Resources\AgendaItems;

use App\Enums\BureauAgendaStatus;
use App\Enums\BureauRole;
use App\Filament\Bureau\Resources\AgendaItems\Pages\CreateAgendaItem;
use App\Filament\Bureau\Resources\AgendaItems\Pages\EditAgendaItem;
use App\Filament\Bureau\Resources\AgendaItems\Pages\ListAgendaItems;
use App\Filament\Bureau\Resources\AgendaItems\Schemas\AgendaItemForm;
use App\Filament\Bureau\Resources\AgendaItems\Tables\AgendaItemsTable;
use App\Models\BureauAgendaItem;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Agenda — see the module's own README section for the full workflow.
 * Any bureau participant (any BureauRole) can see the shared list;
 * a Councillor, Bureau Admin, or Participant may propose an item (see
 * App\Enums\BureauRole) — the President's role here is approve/reject
 * only, not proposing items themselves (see approveAction()/
 * rejectAction() below, reused by both the table and the Edit page).
 */
class AgendaItemResource extends Resource
{
    protected static ?string $model = BureauAgendaItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getNavigationLabel(): string
    {
        return __('bureau.agenda.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('bureau.agenda.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bureau.agenda.plural_label');
    }

    /**
     * Any bureau participant may see the shared agenda list — Staff
     * included, since "view without acting" is exactly their role (see
     * App\Enums\BureauRole).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && collect(BureauRole::cases())->contains(fn (BureauRole $role): bool => $user->hasBureauRole($role));
    }

    /**
     * President, Councillor, Bureau Admin, and Participant may all
     * propose items (see App\Enums\BureauRole). Items created by the
     * President auto-approve; others stay in Entered status for review.
     */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasBureauRole(BureauRole::President)
            || $user->hasBureauRole(BureauRole::Councillor)
            || $user->hasBureauRole(BureauRole::BureauAdmin)
            || $user->hasBureauRole(BureauRole::Participant)
        );
    }

    /**
     * Editable by whoever proposed it up until it's added to a meeting
     * — Entered and Approved are both still editable (editing an
     * Approved item sends it back to Entered for the President to
     * re-review, see EditAgendaItem::mutateFormDataBeforeSave()); once
     * it's AddedToMeeting or Rejected, it's terminal. The President's
     * role here is approve/reject (see approveAction()/rejectAction()),
     * not editing someone else's proposal. No BureauRole grants
     * override rights over another user's item (the permission table
     * doesn't list one) — only the creator may edit it, full stop.
     */
    public static function canEdit(Model $record): bool
    {
        /** @var BureauAgendaItem $record */
        $user = auth()->user();

        return $user
            && in_array($record->status, [BureauAgendaStatus::Entered, BureauAgendaStatus::Approved], true)
            && $record->created_by === $user->id;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var BureauAgendaItem $record */
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Creator can delete Entered/Approved items
        if (in_array($record->status, [BureauAgendaStatus::Entered, BureauAgendaStatus::Approved], true)
            && $record->created_by === $user->id) {
            return true;
        }

        // BureauAdmin can also delete Rejected/AddedToMeeting items
        if (in_array($record->status, [BureauAgendaStatus::Rejected, BureauAgendaStatus::AddedToMeeting], true)
            && $user->hasBureauRole(BureauRole::BureauAdmin)) {
            return true;
        }

        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AgendaItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgendaItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgendaItems::route('/'),
            'create' => CreateAgendaItem::route('/create'),
            'edit' => EditAgendaItem::route('/{record}/edit'),
        ];
    }

    /**
     * Reused by both AgendaItemsTable (row action) and EditAgendaItem
     * (header action) — same reasoning as Document Signing's
     * signAction()/voidAction() living on the Resource rather than
     * duplicated per page.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('bureau.agenda.actions.approve'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (BureauAgendaItem $record): bool => static::isReviewable($record))
            ->action(function (BureauAgendaItem $record): void {
                $record->update([
                    'status' => BureauAgendaStatus::Approved,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                Notification::make()->title(__('bureau.agenda.status.approved'))->success()->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('bureau.agenda.actions.reject'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (BureauAgendaItem $record): bool => static::isReviewable($record))
            ->schema([
                Textarea::make('reason')
                    ->label(__('bureau.agenda.field.rejection_reason'))
                    ->required(),
            ])
            ->action(function (BureauAgendaItem $record, array $data): void {
                $record->update([
                    'status' => BureauAgendaStatus::Rejected,
                    'rejection_reason' => $data['reason'],
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                Notification::make()->title(__('bureau.agenda.status.rejected'))->warning()->send();
            });
    }

    private static function isReviewable(BureauAgendaItem $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $record->status === BureauAgendaStatus::Entered
            && $user
            && $user->hasBureauRole(BureauRole::President);
    }
}
