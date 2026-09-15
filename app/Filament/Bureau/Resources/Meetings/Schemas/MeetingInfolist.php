<?php

namespace App\Filament\Bureau\Resources\Meetings\Schemas;

use App\Models\BureauAgendaItem;
use App\Models\BureauMeeting;
use App\Models\User;
use App\Support\DhivehiDate;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MeetingInfolist
{
    /**
     * Maps BureauMeetingStatus::getColor()'s semantic key to the
     * panel's own palette (see BureauPanelProvider::colors()) — the
     * badge is hand-rendered (not Filament's ->badge()) so it can be a
     * fully rounded pill with no accompanying field label, per the
     * label-free details card design.
     */
    private const STATUS_BADGE_COLORS = [
        'primary' => ['bg' => '#0E7A82', 'text' => '#FFFFFF'],
        'accent' => ['bg' => '#B8934A', 'text' => '#FFFFFF'],
        'muted' => ['bg' => '#BFE0DE', 'text' => '#0E7A82'],
        'danger' => ['bg' => '#DC2626', 'text' => '#FFFFFF'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Details card — fully custom markup (not Filament's
                // Section/TextEntry grid) since the design brief calls
                // for zero field labels: only spacing, dividers, and
                // type-weight contrast may convey what each value is.
                // The meeting name itself doubles as the card's header,
                // so there's no separate page-level heading above it.
                TextEntry::make('name')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->html()
                    ->formatStateUsing(fn (BureauMeeting $record): HtmlString => self::detailsCard($record)),

                // Participants card — this meeting's actual attendees
                // (drawn from the role-filtered attendees Select on the
                // form, so in practice councilors + the President), as
                // a table of Dhivehi name + position rather than the
                // English name/email pair.
                Section::make(__('bureau.meeting.field.attendees_heading'))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'bdc-plain-section'])
                    ->schema([
                        RepeatableEntry::make('attendees')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('bureau.meeting.field.participant_name')),
                                TableColumn::make(__('bureau.meeting.field.participant_position')),
                            ])
                            ->schema([
                                TextEntry::make('name_dv')
                                    ->label(__('bureau.meeting.field.participant_name'))
                                    ->formatStateUsing(fn (User $record): string => $record->name_dv ?: $record->name),
                                TextEntry::make('position_dv')
                                    ->label(__('bureau.meeting.field.participant_position'))
                                    ->formatStateUsing(fn (User $record): string => $record->position_dv ?: ($record->position ?: '—')),
                            ]),
                    ]),

                // Agenda card — grouped into the four sub-headings the
                // council actually uses (procedural passing items, then
                // regular items by who submitted them), numbered
                // "1.1", "1.2", … within each group — see
                // BureauMeeting::groupedAgendaItems(). Same grouping
                // feeds the agenda PDF (bureau.pdf.meeting-agenda).
                Section::make(__('bureau.meeting.field.agenda_items'))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'bdc-plain-section'])
                    ->schema([
                        // Named away from the 'agendaItems' relation on
                        // purpose — a TextEntry bound to a HasMany name
                        // resolves its state to the whole Collection,
                        // which Filament then treats as a multi-value
                        // list and renders formatStateUsing() once per
                        // model (comma-joined) instead of once overall.
                        // ->state() with an unrelated name sidesteps
                        // that entirely.
                        TextEntry::make('agenda_groups')
                            ->hiddenLabel()
                            ->html()
                            ->state(fn (BureauMeeting $record): HtmlString => self::agendaGroups($record)),
                    ]),

                // Attachments card — every file attached to one of this
                // meeting's agenda items, gathered in one place instead
                // of having to open each agenda item individually.
                Section::make(__('bureau.meeting.field.attachments'))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'bdc-plain-section'])
                    ->visible(fn (BureauMeeting $record): bool => $record->agendaItems->contains(
                        fn (BureauAgendaItem $item): bool => $item->hasAttachment(),
                    ))
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->hiddenLabel()
                            ->state(fn (BureauMeeting $record) => $record->agendaItems
                                ->filter(fn (BureauAgendaItem $item): bool => $item->hasAttachment())
                                ->values())
                            ->table([
                                TableColumn::make(__('bureau.agenda.field.details')),
                                TableColumn::make(__('bureau.agenda.field.attachment')),
                            ])
                            ->schema([
                                TextEntry::make('details')
                                    ->label(__('bureau.agenda.field.details'))
                                    ->formatStateUsing(fn (BureauAgendaItem $record): string => $record->shortLabel()),
                                TextEntry::make('attachment_original_name')
                                    ->label(__('bureau.agenda.field.attachment'))
                                    ->url(fn (BureauAgendaItem $record): string => route('bureau.agenda-items.attachment', $record))
                                    ->openUrlInNewTab(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Minimal 14×14 stroke icons (Feather-icon paths — calendar,
     * map-pin, tag) used purely as visual context next to a value,
     * never as a label. currentColor lets .bdc-icon's CSS color
     * (light-mode gray, dark-mode lighter gray) drive them.
     */
    private const ICONS = [
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'tag' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
    ];

    /**
     * Hand-rendered details card — every value drawn from $record with
     * no field label anywhere; meaning comes from the header/badge/grid
     * structure, icon context, and type-weight contrast alone. Uses
     * inline styles (not Tailwind utility classes) because Filament's
     * CSS bundle is precompiled from Filament's own known class list —
     * arbitrary classes written here wouldn't be in it.
     */
    private static function detailsCard(BureauMeeting $record): HtmlString
    {
        $badgeColor = self::STATUS_BADGE_COLORS[$record->status->getColor()] ?? self::STATUS_BADGE_COLORS['muted'];

        $badgeCell = '<div class="bdc-cell"><span class="bdc-badge">'.e($record->status->getLabel()).'</span></div>';
        $dateCell = self::iconCell('calendar', DhivehiDate::format($record->scheduled_at), 'bdc-timestamp');
        $typeCell = filled($record->type_label) ? self::iconCell('tag', $record->type_label, 'bdc-primary') : '';
        $placeCell = filled($record->place) ? self::iconCell('pin', $record->place, 'bdc-primary') : '';

        $extraBlocks = '';

        if (filled($record->rejection_reason)) {
            $extraBlocks .= '<div class="bdc-block bdc-rejection">'.e($record->rejection_reason).'</div>';
        }

        $pdfLinks = array_filter([
            $record->hasMeetingRequestPdf() ? ['request', __('bureau.meeting.field.meeting_request_pdf')] : null,
            $record->hasAgendaPdf() ? ['agenda', __('bureau.meeting.field.agenda_pdf')] : null,
        ]);

        if ($pdfLinks) {
            $linkCells = '';

            foreach ($pdfLinks as [$type, $label]) {
                $url = route('bureau.meetings.pdf', ['meeting' => $record, 'type' => $type]);
                $linkCells .= '<a class="bdc-cell bdc-link" href="'.e($url).'" target="_blank" rel="noopener">'.e($label).'</a>';
            }

            $extraBlocks .= '<div class="bdc-grid">'.$linkCells.'</div>';
        }

        $name = e($record->name);

        return new HtmlString(<<<HTML
            <style>
                .bdc {
                    border: 1px solid #e5e7eb;
                    border-radius: 0.75rem;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
                    background: #ffffff;
                    overflow: hidden;
                }
                .bdc-header {
                    padding: 1rem 1.25rem;
                    background: #f9fafb;
                    border-bottom: 1px solid #f0f0f0;
                    font-family: 'Mv Galan Normal', 'Faruma', sans-serif;
                    font-weight: 700;
                    font-size: 1.25rem;
                    text-align: center;
                    color: #1f2937;
                }
                .bdc-body {
                    padding: 1rem 1.25rem 1.25rem;
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                }
                .bdc-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 0.75rem;
                }
                .bdc-cell {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.4rem;
                    background: #f9fafb;
                    border-radius: 0.625rem;
                    padding: 0.625rem 0.75rem;
                }
                .bdc-icon {
                    flex-shrink: 0;
                    color: #9ca3af;
                }
                .bdc-badge {
                    display: inline-block;
                    padding: 0.3rem 1.1rem;
                    border-radius: 9999px;
                    font-size: 0.8125rem;
                    font-weight: 600;
                    background: {$badgeColor['bg']};
                    color: {$badgeColor['text']};
                }
                .bdc-primary {
                    font-size: 0.9375rem;
                    font-weight: 600;
                    color: #1f2937;
                }
                .bdc-timestamp {
                    font-size: 0.8125rem;
                    font-weight: 500;
                    color: #6b7280;
                }
                .bdc-block {
                    background: #f9fafb;
                    border-radius: 0.625rem;
                    padding: 0.625rem 0.75rem;
                    text-align: center;
                    font-size: 0.8125rem;
                }
                .bdc-rejection {
                    color: #dc2626;
                    font-weight: 500;
                }
                .bdc-link {
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: #0e7a82;
                    text-decoration: none;
                }
                .bdc-link:hover {
                    text-decoration: underline;
                    background: #f0fdfa;
                }
                @media (max-width: 480px) {
                    .bdc-grid {
                        grid-template-columns: 1fr;
                    }
                }
                html.dark .bdc {
                    background: #18181b;
                    border-color: rgba(255, 255, 255, 0.1);
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
                }
                html.dark .bdc-header {
                    background: rgba(255, 255, 255, 0.03);
                    border-bottom-color: rgba(255, 255, 255, 0.08);
                    color: #f4f4f5;
                }
                html.dark .bdc-cell,
                html.dark .bdc-block {
                    background: rgba(255, 255, 255, 0.04);
                }
                html.dark .bdc-icon {
                    color: #71717a;
                }
                html.dark .bdc-primary {
                    color: #f4f4f5;
                }
                html.dark .bdc-timestamp {
                    color: #a1a1aa;
                }
                html.dark .bdc-link {
                    color: #5eead4;
                }
                html.dark .bdc-link:hover {
                    background: rgba(94, 234, 212, 0.08);
                }
                /* Applied to the Participants/Agenda/Attachments
                 * Sections below (via ->extraAttributes()) so their
                 * card shells match the Details card's header fill —
                 * everything else (rounded-xl, shadow, ring border,
                 * bold heading font) is already Filament's own Section
                 * default, so only the header background needs adding. */
                .bdc-plain-section .fi-section-header {
                    background: #f9fafb;
                }
                html.dark .bdc-plain-section .fi-section-header {
                    background: rgba(255, 255, 255, 0.03);
                }
            </style>
            <div class="bdc" dir="rtl">
                <div class="bdc-header">{$name}</div>
                <div class="bdc-body">
                    <div class="bdc-grid">
                        {$badgeCell}
                        {$dateCell}
                    </div>
                    <div class="bdc-grid">
                        {$typeCell}
                        {$placeCell}
                    </div>
                    {$extraBlocks}
                </div>
            </div>
            HTML);
    }

    private static function iconCell(string $icon, string $value, string $textClass): string
    {
        $svg = '<svg class="bdc-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.self::ICONS[$icon].'</svg>';

        return '<div class="bdc-cell">'.$svg.'<span class="'.$textClass.'">'.e($value).'</span></div>';
    }

    /**
     * Renders BureauMeeting::groupedAgendaItems() as numbered
     * sub-headings — same grouping/numbering the agenda PDF uses (see
     * resources/views/bureau/pdf/meeting-agenda.blade.php), so the two
     * never drift apart. Groups with no items are skipped entirely.
     */
    private static function agendaGroups(BureauMeeting $record): HtmlString
    {
        $groups = $record->groupedAgendaItems();
        $groupsHtml = '';
        $groupNumber = 0;

        foreach ($groups as $group) {
            $groupNumber++;

            if ($group['items'] === []) {
                continue;
            }

            $itemsHtml = '';

            foreach ($group['items'] as $entry) {
                $itemsHtml .= '<div class="bdc-agenda-item">'
                    .'<span class="bdc-agenda-number">'.e($entry['number']).'</span>'
                    .'<span class="bdc-agenda-text">'.e($entry['item']->details).'</span>'
                    .'</div>';
            }

            $groupsHtml .= '<div class="bdc-agenda-group">'
                .'<div class="bdc-agenda-group-heading">'.$groupNumber.'. '.e($group['heading']).'</div>'
                .$itemsHtml
                .'</div>';
        }

        if ($groupsHtml === '') {
            $groupsHtml = '<div class="bdc-agenda-empty">—</div>';
        }

        return new HtmlString(<<<HTML
            <style>
                .bdc-agenda-group {
                    margin-bottom: 1rem;
                }
                .bdc-agenda-group:last-child {
                    margin-bottom: 0;
                }
                .bdc-agenda-group-heading {
                    font-family: 'Mv Galan Normal', 'Faruma', sans-serif;
                    font-weight: 700;
                    font-size: 0.9375rem;
                    color: #0e7a82;
                    margin-bottom: 0.5rem;
                }
                .bdc-agenda-item {
                    display: flex;
                    align-items: flex-start;
                    gap: 0.5rem;
                    background: #f9fafb;
                    border-radius: 0.625rem;
                    padding: 0.625rem 0.75rem;
                    margin-bottom: 0.5rem;
                }
                .bdc-agenda-item:last-child {
                    margin-bottom: 0;
                }
                .bdc-agenda-number {
                    flex-shrink: 0;
                    font-size: 0.75rem;
                    font-weight: 600;
                    color: #6b7280;
                    background: #ffffff;
                    border-radius: 9999px;
                    padding: 0.1rem 0.5rem;
                    font-variant-numeric: tabular-nums;
                }
                .bdc-agenda-text {
                    font-size: 0.875rem;
                    color: #1f2937;
                    flex: 1;
                }
                .bdc-agenda-empty {
                    text-align: center;
                    color: #9ca3af;
                    font-size: 0.875rem;
                    padding: 0.5rem;
                }
                html.dark .bdc-agenda-group-heading {
                    color: #5eead4;
                }
                html.dark .bdc-agenda-item {
                    background: rgba(255, 255, 255, 0.04);
                }
                html.dark .bdc-agenda-number {
                    background: rgba(255, 255, 255, 0.08);
                    color: #a1a1aa;
                }
                html.dark .bdc-agenda-text {
                    color: #f4f4f5;
                }
                html.dark .bdc-agenda-empty {
                    color: #71717a;
                }
            </style>
            <div dir="rtl">{$groupsHtml}</div>
            HTML);
    }
}
