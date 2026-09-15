<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A user's capability level within one module (see config/modules.php),
 * on top of the module's own visibility — every user has at least
 * Viewer in every module by default (see User::roleFor()).
 *
 * Ranked low to high: Editor implies Viewer, Approver implies Editor.
 * Use atLeast() rather than comparing cases directly so a future
 * Resource's gate ("must be Editor or above") stays correct if a level
 * is ever inserted between existing ones.
 */
enum ModuleAccessLevel: string implements HasColor, HasLabel
{
    case Viewer = 'viewer';
    case Editor = 'editor';
    case Approver = 'approver';

    public function rank(): int
    {
        return match ($this) {
            self::Viewer => 1,
            self::Editor => 2,
            self::Approver => 3,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Viewer => 'Viewer',
            self::Editor => 'Editor',
            self::Approver => 'Approver',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Viewer => 'muted',
            self::Editor => 'primary',
            self::Approver => 'accent',
        };
    }
}
