<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Document Signing's own role vocabulary — unrelated to
 * App\Enums\ModuleAccessLevel (the generic Viewer/Editor/Approver
 * ranking other, not-yet-built modules still use). These four are not
 * ranked: a user holds any combination of them (see
 * App\Models\DocumentSigningUserRole), not a single "level".
 *
 * - Admin: manage this module's user roles and its Organization
 *   settings (the stamp). Does not by itself grant document
 *   create/sign/view — pair with the other roles as needed.
 * - Editor: create/upload documents for signing.
 * - Signee: sign/reject documents they're listed as a signer on.
 * - Viewer: see every uploaded document, not just their own.
 *
 * The system-wide `admin` spatie role (see RoleSeeder) implicitly has
 * every one of these — see User::hasDocumentSigningRole().
 */
enum DocumentSigningRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Signee = 'signee';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Signee => 'Signee',
            self::Viewer => 'Viewer',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'accent',
            self::Editor => 'primary',
            self::Signee => 'primary',
            self::Viewer => 'muted',
        };
    }
}
