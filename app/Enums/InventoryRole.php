<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Inventory's own role vocabulary — same not-ranked, multi-role-per-user
 * shape as App\Enums\BureauRole/DocumentSigningRole (see
 * App\Models\InventoryUserRole): a user holds any combination of these
 * four.
 *
 * - User: everyone with any inventory role implicitly has this —
 *   browse the catalogue, raise issue requests, see their own requests.
 * - Approver: approve or reject issue requests.
 * - StockAdmin: receive stock, issue approved requests, manage items
 *   and reference data, post adjustments and returns.
 * - Admin: everything, plus role assignment and settings.
 *
 * The system-wide `admin` spatie role implicitly has every one of
 * these — see User::hasInventoryRole().
 */
enum InventoryRole: string implements HasColor, HasLabel
{
    case User = 'user';
    case Approver = 'approver';
    case StockAdmin = 'stock_admin';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Approver => 'Approver',
            self::StockAdmin => 'Stock Admin',
            self::Admin => 'Admin',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::User => 'gray',
            self::Approver => 'accent',
            self::StockAdmin => 'primary',
            self::Admin => 'danger',
        };
    }
}
