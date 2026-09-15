<?php

namespace App\Services\Inventory;

use App\Models\InventoryNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Atomic document-number generator — GRN-2026-0001, IR-2026-0001,
 * ADJ-2026-0001, RET-2026-0001, MOV-2026-000123 (6-digit padding),
 * resetting each January (see spec Appendix B).
 *
 * Unlike BureauSettings::nextMeetingNumberFor()/incrementMeetingNumberFor()
 * (a read then a separate, unlocked increment — a real race window
 * between the two), next() does the whole
 * find-or-create-this-year's-row -> lockForUpdate() -> increment -> format
 * sequence inside one DB::transaction(), so two concurrent callers for
 * the same doc_type/year always serialize instead of racing.
 */
class InventorySequenceService
{
    private const PREFIXES = [
        'GRN' => 'GRN',
        'IR' => 'IR',
        'ADJ' => 'ADJ',
        'RET' => 'RET',
        'MOV' => 'MOV',
    ];

    private const PADDING = [
        'MOV' => 6,
    ];

    /**
     * Item codes aren't year-scoped ({CATEGORY}-0001, not
     * {CATEGORY}-2026-0001 — spec 6.3) — reuses the same locked-row
     * counter as next() via a "ITEM:{category}" doc_type and a sentinel
     * year of 0, just formatted without the year segment.
     */
    public function nextItemCode(string $categoryCode): string
    {
        return DB::transaction(function () use ($categoryCode): string {
            $docType = "ITEM:{$categoryCode}";

            InventoryNumberSequence::query()->firstOrCreate(
                ['doc_type' => $docType, 'year' => 0],
                ['prefix' => $categoryCode, 'last_number' => 0, 'padding' => 4],
            );

            /** @var InventoryNumberSequence $sequence */
            $sequence = InventoryNumberSequence::query()
                ->where('doc_type', $docType)
                ->where('year', 0)
                ->lockForUpdate()
                ->first();

            $sequence->increment('last_number');

            $number = str_pad((string) $sequence->last_number, $sequence->padding, '0', STR_PAD_LEFT);

            return "{$sequence->prefix}-{$number}";
        });
    }

    public function next(string $docType, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = self::PREFIXES[$docType] ?? $docType;
        $padding = self::PADDING[$docType] ?? 4;

        return DB::transaction(function () use ($docType, $year, $prefix, $padding): string {
            // The row may not exist yet for this doc_type/year — create
            // it first (outside any lock, harmless if two callers race
            // here since the unique index on [doc_type, year] makes one
            // of them fail silently via firstOrCreate's own retry), then
            // re-select it WITH a lock before incrementing.
            InventoryNumberSequence::query()->firstOrCreate(
                ['doc_type' => $docType, 'year' => $year],
                ['prefix' => $prefix, 'last_number' => 0, 'padding' => $padding],
            );

            /** @var InventoryNumberSequence $sequence */
            $sequence = InventoryNumberSequence::query()
                ->where('doc_type', $docType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $sequence->increment('last_number');

            $number = str_pad((string) $sequence->last_number, $sequence->padding, '0', STR_PAD_LEFT);

            return "{$sequence->prefix}-{$year}-{$number}";
        });
    }
}
