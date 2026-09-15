<?php

namespace App\Filament\Bureau\Resources\Meetings\Tables;

use App\Enums\BureauMeetingStatus;
use App\Filament\Bureau\Resources\Meetings\MeetingResource;
use App\Models\BureauMeeting;
use App\Support\DhivehiDate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeetingsTable
{
    /**
     * Same rounded/padded/ring look as Filament's own card panel style
     * (see the vendor fi-ta-panel CSS), applied to the whole record row
     * — not just the column content — so the action buttons render
     * inside the same card boundary instead of trailing outside it.
     */
    private const CARD_CLASSES = 'rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 ring-inset dark:bg-white/5 dark:ring-white/10';

    /**
     * Added on top of CARD_CLASSES for whichever meeting
     * BureauMeeting::nextUpcoming() currently returns — "Always [the]
     * next upcoming meeting will be displayed at home" applies here
     * too, not just the dashboard widget.
     */
    private const NEXT_UPCOMING_CLASSES = 'ring-2 ring-primary-600 bg-primary-50 dark:bg-primary-500/10 dark:ring-primary-400';

    public static function configure(Table $table): Table
    {
        $nextUpcomingMeetingId = BureauMeeting::nextUpcoming()?->id;

        return $table
            // meeting_number is two separate counters (public/private —
            // see BureauSettings::nextMeetingNumberFor()) that don't
            // always track scheduled_at chronologically (an emergency
            // meeting can be numbered and dated independently of the
            // regular sequence) — term_number first since meeting_number
            // isn't reset between terms today, but would still need to
            // rank correctly if it ever is.
            ->modifyQueryUsing(fn ($query) => $query->withCount(['attendees', 'agendaItems'])->with('creator')->orderByDesc('term_number')->orderByDesc('meeting_number'))
            ->recordClasses(fn (BureauMeeting $record): string => $record->id === $nextUpcomingMeetingId
                ? self::CARD_CLASSES.' '.self::NEXT_UPCOMING_CLASSES
                : self::CARD_CLASSES)
            ->columns([
                Stack::make([
                    // Name first, type badge second. Every column
                    // defaults to ->grow() (flex:1) inside a layout
                    // like this, so without disabling it on the badge
                    // both columns fight for equal width and the badge
                    // ends up stranded mid-row — grow(false) here lets
                    // the name's own flex:1 consume the rest, pushing
                    // the badge to the true corner (left in RTL).
                    Split::make([
                        TextColumn::make('name')
                            ->label(__('bureau.meeting.field.name'))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->searchable()
                            ->wrap(),
                        TextColumn::make('type_label')
                            ->label(__('bureau.meeting.field.type'))
                            ->badge()
                            ->color(fn (BureauMeeting $record): string => match ($record->type) {
                                'public' => 'info',
                                'private' => 'warning',
                                default => 'gray',
                            })
                            ->placeholder('—')
                            ->grow(false),
                    ]),
                    TextColumn::make('status')
                        ->label(__('bureau.meeting.field.status'))
                        ->badge(),
                    // Grid instead of Split for the meta rows — Split
                    // sizes each column to its own content, so a longer
                    // date or place on one card shifts where the next
                    // field starts compared to other cards. Grid gives
                    // every column an equal, fixed-width track instead,
                    // so date/place/attendees/agenda/creator all start
                    // at the same X position on every card.
                    Grid::make(2)
                        ->schema([
                            TextColumn::make('scheduled_at')
                                ->label(__('bureau.meeting.field.scheduled_at'))
                                ->formatStateUsing(fn ($state): string => DhivehiDate::html($state))
                                ->icon(Heroicon::OutlinedCalendarDays)
                                ->color('gray')
                                ->html(),
                            TextColumn::make('place')
                                ->label(__('bureau.meeting.field.place'))
                                ->icon(Heroicon::OutlinedMapPin)
                                ->color('gray')
                                ->placeholder('—'),
                        ]),
                    Grid::make(3)
                        ->schema([
                            TextColumn::make('attendees_count')
                                ->label(__('bureau.meeting.field.attendees'))
                                ->formatStateUsing(fn ($state): string => "{$state} ".__('bureau.meeting.field.attendees'))
                                ->icon(Heroicon::OutlinedUsers)
                                ->color('gray'),
                            TextColumn::make('agenda_items_count')
                                ->label(__('bureau.meeting.field.agenda_items'))
                                ->formatStateUsing(fn ($state): string => "{$state} ".__('bureau.meeting.field.agenda_items'))
                                ->icon(Heroicon::OutlinedClipboardDocumentList)
                                ->color('gray'),
                            TextColumn::make('creator.name')
                                ->label(__('bureau.meeting.field.created_by'))
                                ->icon(Heroicon::OutlinedUser)
                                ->size(TextSize::ExtraSmall)
                                ->color('gray'),
                        ]),
                ])->space(2),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('bureau.meeting.field.status'))
                    ->options(collect(BureauMeetingStatus::cases())->mapWithKeys(
                        fn (BureauMeetingStatus $status): array => [$status->value => $status->getLabel()],
                    )),
                Filter::make('type')
                    ->label(__('bureau.meeting.field.type'))
                    ->form([
                        ToggleButtons::make('type')
                            ->hiddenLabel()
                            ->options(BureauMeeting::TYPES)
                            ->inline(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['type'] ?? null,
                        fn (Builder $query, string $type): Builder => $query->where('type', $type),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['type'] ?? null)
                        ? BureauMeeting::TYPES[$data['type']]
                        : null),
            ])
            // AboveContent (tried first) permanently occupies a full-width
            // card at the top of the page regardless of how few filters
            // exist — with just two here, that card was mostly empty
            // space no matter how its internal grid columns were tuned.
            // Dropdown collapses the same two filters behind a small
            // trigger button + badge, popping open a compact panel only
            // when needed — Filament's own standard, more compact layout.
            ->filtersLayout(FiltersLayout::Dropdown)
            ->deferFilters(false)
            ->recordActions([
                // See AgendaItemsTable's own comment — EditAction needs
                // an explicit visible() since it doesn't automatically
                // enforce MeetingResource::canEdit() without a
                // registered Laravel Policy. Edit and Approve share a
                // button shape and color here — the list's two primary
                // actions. The rest live behind a single dropdown
                // trigger: Filament renders actions inline beside the
                // card content (not stacked below it), so a varying
                // number of visible action buttons per record shrinks
                // the content area by a different amount on every card
                // and throws off the grid above — one fixed-width
                // dropdown keeps that footprint constant regardless of
                // how many of Minutes/Send-for-approval/Reject apply to
                // a given meeting.
                EditAction::make()
                    ->label(__('bureau.meeting.actions.edit'))
                    ->visible(fn (BureauMeeting $record): bool => MeetingResource::canEdit($record))
                    ->button()
                    ->color('primary'),
                MeetingResource::approveAction()->button(),
                ActionGroup::make([
                    MeetingResource::minutesAction(),
                    MeetingResource::sendForApprovalAction(),
                    MeetingResource::rejectAction(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('bureau.meeting.actions.delete'))
                        ->authorizeIndividualRecords(fn (BureauMeeting $record): bool => MeetingResource::canDelete($record)),
                ]),
            ]);
    }
}
