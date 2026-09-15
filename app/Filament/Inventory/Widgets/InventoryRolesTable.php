<?php

namespace App\Filament\Inventory\Widgets;

use App\Enums\InventoryRole;
use App\Models\InventoryAuditLog;
use App\Models\InventoryUserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Spec 10.9 — same shape as App\Filament\Bureau\Widgets\BureauRolesTable
 * (a table can't hold a multi-select control, so each row's roles are
 * edited via a modal), extended with the two pieces that widget doesn't
 * have: a bulk-assign action and an audit_log write on every change
 * (spec's explicit "every change writes to audit_log with old and new
 * role sets") — InventoryAuditLog's first real writer.
 */
class InventoryRolesTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected function getTableHeading(): string
    {
        return 'Users';
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(User::query()->with('inventoryRoles')->orderBy('name'))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('roles')
                    ->label('Current Roles')
                    ->badge()
                    ->getStateUsing(fn (User $record): array => collect($record->inventoryRoleList())->map(fn (InventoryRole $r): string => $r->getLabel())->all())
                    ->placeholder('None'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(InventoryRole::class)
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $role) => $q->whereHas('inventoryRoles', fn ($q2) => $q2->where('role', $role)),
                    )),
            ])
            ->recordActions([
                Action::make('editRoles')
                    ->label('Edit Roles')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('')
                            ->options(collect(InventoryRole::cases())->mapWithKeys(fn (InventoryRole $role): array => [$role->value => $role->getLabel()])->all())
                            ->columns(2),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'roles' => $record->inventoryRoles->pluck('role')->map(fn (InventoryRole $role): string => $role->value)->all(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $this->applyRoleChange($record, collect($data['roles'] ?? [])->map(fn (string $v): InventoryRole => InventoryRole::from($v)));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignRole')
                        ->label('Assign Role')
                        ->icon(Heroicon::OutlinedPlusCircle)
                        ->schema([
                            Select::make('role')
                                ->label('Role')
                                ->options(InventoryRole::class)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            // Select::options(InventoryRole::class) casts the
                            // field's own state to the enum instance directly
                            // in some contexts (e.g. bulk-action data) rather
                            // than its scalar value — accept either.
                            $role = $data['role'] instanceof InventoryRole ? $data['role'] : InventoryRole::from($data['role']);

                            foreach ($records as $record) {
                                /** @var User $record */
                                $current = $record->inventoryRoles->pluck('role');

                                if ($current->contains($role)) {
                                    continue;
                                }

                                $this->applyRoleChange($record, $current->push($role), silent: true);
                            }

                            Notification::make()->title('Role assigned')->success()->send();
                        }),
                ]),
            ]);
    }

    /**
     * Shared by the per-row edit dialog and the bulk-assign action —
     * guards (BR-30's spirit: never leave the module with zero Admins,
     * and never let someone strip their own Admin access), applies the
     * new role set, and writes the audit_log row. Silently no-ops (no
     * write, no audit row) if the guard blocks the change or nothing
     * actually changed.
     *
     * @param  \Illuminate\Support\Collection<int, InventoryRole>  $newRoles
     */
    private function applyRoleChange(User $record, $newRoles, bool $silent = false): void
    {
        $current = $record->inventoryRoles->pluck('role');
        $selected = $newRoles->unique()->values();

        $removingAdmin = $current->contains(InventoryRole::Admin) && ! $selected->contains(InventoryRole::Admin);

        if ($removingAdmin) {
            if ($record->id === auth()->id()) {
                Notification::make()->title("You can't remove your own Admin role.")->danger()->send();

                return;
            }

            $anotherAdminExists = InventoryUserRole::query()
                ->where('role', InventoryRole::Admin)
                ->where('user_id', '!=', $record->id)
                ->exists();

            if (! $anotherAdminExists) {
                Notification::make()->title('At least one user must hold the Admin role.')->danger()->send();

                return;
            }
        }

        $oldValues = $current->map(fn (InventoryRole $r): string => $r->value)->sort()->values()->all();
        $newValues = $selected->map(fn (InventoryRole $r): string => $r->value)->sort()->values()->all();

        if ($oldValues === $newValues) {
            return;
        }

        DB::transaction(function () use ($record, $selected) {
            $record->inventoryRoles()->whereNotIn('role', $selected->map(fn (InventoryRole $r): string => $r->value)->all())->delete();

            foreach ($selected as $role) {
                InventoryUserRole::query()->firstOrCreate(['user_id' => $record->id, 'role' => $role]);
            }
        });

        InventoryAuditLog::write('USER_ROLE', $record->id, 'ROLE_ASSIGN', ['roles' => $oldValues], ['roles' => $newValues]);

        $record->unsetRelation('inventoryRoles');

        if (! $silent) {
            Notification::make()->title('Roles updated')->success()->send();
        }
    }
}
