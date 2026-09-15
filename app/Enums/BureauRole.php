<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Bureau's own role vocabulary — same not-ranked, multi-role-per-user
 * shape as App\Enums\DocumentSigningRole (see App\Models\BureauUserRole):
 * a user holds any combination of these six, each granting a distinct
 * slice of capability (redefined from the module's original 4-role
 * President/Councillor/Admin/Viewer set, per the permission table the
 * module owner supplied):
 *
 * - President: approves agenda items, creates and approves meetings,
 *   may speak and vote in meetings.
 * - Councillor: creates agenda items, may speak and vote in meetings.
 * - Participant: may speak in meetings (attends and addresses the
 *   council, but doesn't vote or hold any other capability here).
 * - BureauAdmin: creates meetings and runs the live minutes recording
 *   (the module's original "Bureau Admin" operational/secretarial
 *   role) — see RecordMinutes, which is the sole operator during a
 *   live meeting.
 * - Staff: view-only — sees agendas and meeting details, nothing else.
 * - ModuleAdmin: assigns Bureau roles to other users (see
 *   App\Filament\Bureau\Pages\BureauRoles) — deliberately split out
 *   from BureauAdmin, since running meetings and managing who holds
 *   which role are different responsibilities.
 *
 * "Simple majority of council membership" (see
 * App\Services\Bureau\DecisionVotingService and
 * User::bureauCouncilMembershipCount()) is measured against President +
 * Councillor specifically — the two roles whose description says "can
 * vote in the meetings" — not all six.
 *
 * The system-wide `admin` spatie role implicitly has every one of these
 * — see User::hasBureauRole().
 *
 * Labels come from lang/dv/bureau.php — see that file's own comment
 * for why they're centralized (provisional Dhivehi, not yet reviewed
 * by a native speaker).
 */
enum BureauRole: string implements HasColor, HasLabel
{
    case President = 'president';
    case Councillor = 'councillor';
    case Participant = 'participant';
    case BureauAdmin = 'bureau_admin';
    case Staff = 'staff';
    case ModuleAdmin = 'module_admin';

    public function getLabel(): string
    {
        return __('bureau.roles.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::President => 'accent',
            self::Councillor => 'primary',
            self::Participant => 'gray',
            self::BureauAdmin => 'primary',
            self::Staff => 'muted',
            self::ModuleAdmin => 'danger',
        };
    }
}
