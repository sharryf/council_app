<?php

namespace App\Filament\Bureau\Widgets;

use App\Enums\BureauRole;
use App\Models\BureauUserRole;
use App\Models\User;
use App\Support\ThaanaTransliterator;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * One row per user with access to the bureau module (see
 * User::canAccessModule()); `admin` (system administration — see
 * UserResource::canAccess()) users are excluded because their Bureau
 * roles, like every module's, are set from the Users page's own
 * "Module Roles" section instead — one place for a System Admin to see
 * and set everything about a user at once, rather than hunting across
 * four separate per-module pages. Same shape as
 * App\Filament\Resources\DocumentSigning\Documents\Widgets\DocumentSigningRolesTable
 * — a table column can't hold a multi-select control, so each row's
 * roles are edited via a modal instead.
 */
class BureauRolesTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected function getTableHeading(): string
    {
        return __('bureau.roles_page.heading');
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(
                // No explicit orderBy — same as UsersTable (the main
                // admin Users list), which also leaves the query
                // unordered so both lists fall back to the same natural
                // row order. The roster was seeded in a deliberate
                // order (President/Councillors/etc in real-world
                // sequence); an alphabetical ->orderBy('name') here
                // would scramble that against the main list.
                User::query()
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'))
                    ->where(fn ($query) => $query
                        ->whereNull('module_access')
                        ->orWhereJsonContains('module_access', 'bureau')),
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('bureau.roles_page.field.name'))
                    ->getStateUsing(fn (User $record): string => filled($record->name_dv) ? $record->name_dv : ThaanaTransliterator::transliterate($record->name)),
                TextColumn::make('position')
                    ->label(__('bureau.roles_page.field.position'))
                    ->getStateUsing(function (User $record): ?string {
                        if (filled($record->position_dv)) {
                            return $record->position_dv;
                        }

                        return filled($record->position) ? ThaanaTransliterator::transliterate($record->position) : null;
                    })
                    ->placeholder('—'),
                TextColumn::make('roles')
                    ->label(__('bureau.roles_page.field.roles'))
                    ->badge()
                    ->getStateUsing(fn (User $record): array => $record->bureauRoleList())
                    ->placeholder(__('bureau.roles_page.placeholder.none')),
            ])
            ->recordActions([
                Action::make('editRoles')
                    ->label(__('bureau.roles_page.actions.edit_roles'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('')
                            ->options(collect(BureauRole::cases())
                                ->mapWithKeys(fn (BureauRole $role): array => [$role->value => $role->getLabel()])
                                ->all())
                            ->columns(2),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'roles' => $record->bureauRoles->pluck('role')->map(fn (BureauRole $role): string => $role->value)->all(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $selected = collect($data['roles'] ?? []);

                        $record->bureauRoles()
                            ->whereNotIn('role', $selected->all())
                            ->delete();

                        foreach ($selected as $role) {
                            BureauUserRole::query()->firstOrCreate([
                                'user_id' => $record->id,
                                'role' => $role,
                            ]);
                        }

                        Notification::make()->title('Roles updated')->success()->send();
                    }),
            ]);
    }
}
