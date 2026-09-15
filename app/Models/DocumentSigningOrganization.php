<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for the Document Signing module — always id 1,
 * fetched/created via current(). Holds up to two named organization
 * stamp images (e.g. two different offices' stamps) — the uploader
 * picks which one to place per document in the create wizard (see
 * App\Models\DocumentStamp::stamp_slot) — managed from the
 * Organization settings page.
 */
#[Fillable(['stamp_path', 'stamp_label', 'stamp_path_2', 'stamp_label_2'])]
class DocumentSigningOrganization extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function hasStampInSlot(int $slot): bool
    {
        return filled($this->stampPathForSlot($slot));
    }

    public function stampPathForSlot(int $slot): ?string
    {
        return match ($slot) {
            1 => $this->stamp_path,
            2 => $this->stamp_path_2,
            default => null,
        };
    }

    public function stampLabelForSlot(int $slot): string
    {
        $label = match ($slot) {
            1 => $this->stamp_label,
            2 => $this->stamp_label_2,
            default => null,
        };

        return filled($label) ? $label : "Stamp {$slot}";
    }

    /**
     * @return array<int, array{slot: int, label: string, path: string}>
     */
    public function availableStamps(): array
    {
        $stamps = [];

        foreach ([1, 2] as $slot) {
            if ($this->hasStampInSlot($slot)) {
                $stamps[] = [
                    'slot' => $slot,
                    'label' => $this->stampLabelForSlot($slot),
                    'path' => $this->stampPathForSlot($slot),
                ];
            }
        }

        return $stamps;
    }
}
