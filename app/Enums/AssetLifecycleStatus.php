<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Separate from AssetStatus (the asset's physical condition — In Use,
 * Damaged, etc.) — this tracks the *register entry's* own state: a
 * newly created or bulk-imported asset starts as a freely-editable
 * Draft, and an Admin explicitly Posts it to lock it in. Once Posted,
 * further edits go through AssetEditRequest + Manager approval instead
 * of a direct save (request: "should be locked for editing... needs
 * approval from manager").
 */
enum AssetLifecycleStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Posted => 'success',
        };
    }
}
