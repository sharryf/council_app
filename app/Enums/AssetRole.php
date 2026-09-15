<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Assets' own role vocabulary — unrelated to App\Enums\ModuleAccessLevel
 * (the generic Viewer/Editor/Approver ranking). These three are not
 * ranked: a user holds any combination of them (see
 * App\Models\AssetUserRole), same shape as
 * App\Enums\DocumentSigningRole/InventoryRole/BureauRole. Ranking would
 * be wrong here specifically because Manager can approve/reject
 * transfers and maintenance while Admin explicitly cannot — Admin isn't
 * "above" Manager, they're separate responsibilities a user may or may
 * not hold together (see the spec's confirmed self-approval design for
 * a user holding both).
 *
 * - Admin: full CRUD on assets, log maintenance, initiate transfers,
 *   manage categories/locations, manage this module's role assignments.
 * - Manager: approve/reject transfers and maintenance, start/close
 *   audit sessions, verify assets, review audit discrepancies.
 * - Viewer: read-only — search/view assets and history, export.
 *
 * The system-wide `admin` spatie role implicitly has every one of
 * these — see User::hasAssetRole().
 */
enum AssetRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Viewer => 'Viewer',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Manager => 'accent',
            self::Viewer => 'gray',
        };
    }
}
