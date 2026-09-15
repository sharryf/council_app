<?php

namespace App\Models;

use App\Enums\BureauAgendaItemKind;
use App\Enums\BureauMeetingStatus;
use App\Enums\BureauRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

#[Fillable([
    'name', 'term_number', 'meeting_number', 'type', 'scheduled_at', 'place', 'status',
    'agenda_pdf_path', 'meeting_request_pdf_path',
    'rejection_reason', 'created_by', 'reviewed_by', 'reviewed_at',
])]
class BureauMeeting extends Model
{
    use HasFactory;

    /**
     * Every council meeting name follows this fixed council-wide
     * sentence — only the term number, meeting number, and type
     * (see MeetingForm's type Select) actually vary per meeting.
     */
    public const NAME_PREFIX = 'މާޅޮސްމަޑުލު ދެކުނުބުރީ ދޮންފަނު ކައުންސިލްގެ';

    /**
     * Pre-filled into the place field on create (see MeetingForm) —
     * the council's usual meeting hall, editable for the rare meeting
     * held elsewhere.
     */
    public const DEFAULT_PLACE = 'މާޅޮސްމަޑުލު ދެކުނުބުރީ ދޮންފަނު ކައުންސިލްގެ ޖަލްސާ ކުރާ މާލަމް';

    public const TYPES = [
        'public' => 'އާއްމު ބައްދަލުވުން',
        'private' => 'ކުއްލި ބައްދަލުވުން',
    ];

    public static function buildName(int $termNumber, int $meetingNumber, ?string $type): string
    {
        $typeLabel = self::TYPES[$type] ?? '';

        return trim(self::NAME_PREFIX." {$termNumber} ވަނަ ދައުރުގެ {$meetingNumber} ވަނަ {$typeLabel}");
    }

    /**
     * "Always [the] next upcoming meeting will be displayed at home" —
     * shared by NextMeetingWidget (the dashboard card) and
     * MeetingsTable (which highlights the same meeting in the list) so
     * the two never disagree on which one that is. Only counts
     * Scheduled meetings (i.e. approved by the President), since a
     * Draft/PendingApproval one isn't a real commitment yet.
     */
    public static function nextUpcoming(): ?self
    {
        return self::query()
            ->where('status', BureauMeetingStatus::Scheduled)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->first();
    }

    protected function casts(): array
    {
        return [
            'status' => BureauMeetingStatus::class,
            'scheduled_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getTypeLabelAttribute(): ?string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attendees(): BelongsToMany
    {
        // Explicit pivot keys — Laravel's default convention would
        // expect bureau_meeting_id, but the migration names it
        // meeting_id (matching the same column name already used on
        // bureau_agenda_items for the same relationship).
        return $this->belongsToMany(User::class, 'bureau_meeting_attendees', 'meeting_id', 'user_id');
    }

    /**
     * Approved agenda items auto-attached at creation time (see
     * CreateMeeting) — this is a HasMany, not a pivot, since an agenda
     * item belongs to at most one meeting (see the migration adding
     * meeting_id to bureau_agenda_items).
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(BureauAgendaItem::class, 'meeting_id');
    }

    public function hasAgendaPdf(): bool
    {
        return filled($this->agenda_pdf_path);
    }

    public function hasMeetingRequestPdf(): bool
    {
        return filled($this->meeting_request_pdf_path);
    }

    public function minutes(): HasOne
    {
        return $this->hasOne(BureauMeetingMinutes::class, 'meeting_id');
    }

    /**
     * The agenda grouped into the four sub-headings shown on both the
     * View Meeting page and the agenda PDF (see MeetingInfolist and
     * bureau.pdf.meeting-agenda) — procedural Agenda/Minutes Passing
     * items first, then regular items bucketed by the creator's bureau
     * role, mirroring AgendaItemsTable's own creator-prefix logic
     * (President / Councillor / Bureau Admin+Participant). A creator
     * with no assigned bureau role (shouldn't happen outside test data,
     * since AgendaItemResource::canCreate() requires one) falls back to
     * the Bureau Admin group rather than silently disappearing.
     *
     * Numbered "1.1", "1.2", … within each group, restarting per group;
     * groups themselves are numbered by their position in this array
     * (1–4) by the caller.
     *
     * @return array<int, array{heading: string, items: array<int, array{number: string, item: BureauAgendaItem}>}>
     */
    public function groupedAgendaItems(): array
    {
        $items = $this->agendaItems;

        $buckets = [
            'passing' => $items->filter(fn (BureauAgendaItem $item): bool => in_array(
                $item->kind,
                [BureauAgendaItemKind::AgendaPassing, BureauAgendaItemKind::MinutesPassing],
                true,
            ))->sortBy(fn (BureauAgendaItem $item): int => match ($item->kind) {
                BureauAgendaItemKind::AgendaPassing => 0,
                BureauAgendaItemKind::MinutesPassing => 1,
                default => 2,
            }),
            'president' => $items->filter(fn (BureauAgendaItem $item): bool => $item->kind === BureauAgendaItemKind::Regular
                && self::creatorRole($item) === BureauRole::President),
            'councillor' => $items->filter(fn (BureauAgendaItem $item): bool => $item->kind === BureauAgendaItemKind::Regular
                && self::creatorRole($item) === BureauRole::Councillor),
            'bureau' => $items->filter(fn (BureauAgendaItem $item): bool => $item->kind === BureauAgendaItemKind::Regular
                && ! in_array(self::creatorRole($item), [BureauRole::President, BureauRole::Councillor], true)),
        ];

        $groupNumber = 0;

        return collect($buckets)
            ->map(function (Collection $groupItems, string $key) use (&$groupNumber): array {
                $groupNumber++;

                return [
                    'heading' => __('bureau.agenda.group.'.$key),
                    'items' => $groupItems->values()->map(fn (BureauAgendaItem $item, int $index): array => [
                        'number' => "{$groupNumber}.".($index + 1),
                        'item' => $item,
                    ])->all(),
                ];
            })
            ->values()
            ->all();
    }

    private static function creatorRole(BureauAgendaItem $item): ?BureauRole
    {
        return $item->creator?->bureauRoles->first()?->role;
    }
}
