<?php

namespace App\Filament\Resources\Users\Concerns;

use App\Models\User;

/**
 * Shared by CreateUser and EditUser (different base classes, so a
 * trait rather than shared inheritance) to move the virtual `is_admin`
 * form field — see UserForm — between the form and its real storage,
 * the `admin` spatie role, and to normalize `module_access`.
 *
 * `module_access` is a real User column (JSON array of module keys, or
 * null meaning "every module") so it doesn't need extraction like
 * is_admin does — but the form always submits an explicit array (every
 * box either checked or not), so "every module currently checked" is
 * normalized back to null here. That keeps a user who's never been
 * deliberately restricted seeing new modules automatically as they're
 * added to config/modules.php, rather than freezing them to today's
 * module list the first time an admin merely opens and saves their
 * form.
 */
trait InteractsWithModuleAccess
{
    protected bool $pendingIsAdmin = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractModuleLevelFields(array $data): array
    {
        $this->pendingIsAdmin = (bool) ($data['is_admin'] ?? false);
        unset($data['is_admin']);

        $allModules = array_keys(config('modules'));
        $selected = $data['module_access'] ?? [];

        $data['module_access'] = array_diff($allModules, $selected) === [] ? null : array_values($selected);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillModuleLevelFields(array $data, User $record): array
    {
        $data['is_admin'] = $record->hasRole('admin');
        $data['module_access'] = $record->module_access ?? array_keys(config('modules'));

        return $data;
    }

    protected function persistModuleLevels(User $user): void
    {
        $user->syncRoles($this->pendingIsAdmin ? ['admin'] : []);
    }
}
