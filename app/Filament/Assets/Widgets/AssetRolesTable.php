<?php

namespace App\Filament\Assets\Widgets;

use App\Enums\AssetRole;
use App\Models\AssetUserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * One row per user with access to the assets module (see
 * User::canAccessModule()); `admin` users are excluded because their
 * roles are set from the Users page's "Module Roles" section instead —
 * same reasoning and pattern as
 * App\Filament\Bureau\Widgets\BureauRolesTable / App\Filament\Resources\DocumentSigning\Documents\Widgets\DocumentSigningRolesTable.
 */
class AssetRolesTable extends TableWidget
{
    protected static ?string $heading = 'Assets — user roles';

    protected static bool $isLazy = false;

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(
                User::query()
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'))
                    ->where(fn ($query) => $query
                        ->whereNull('module_access')
                        ->orWhereJsonContains('module_access', 'assets'))
                    ->orderBy('name'),
            )
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('position')->placeholder('—'),
                TextColumn::make('email')->label('Email address'),
                TextColumn::make('roles')
                    ->label('Roles')
                    ->badge()
                    ->getStateUsing(fn (User $record): array => $record->assetRoleList())
                    ->placeholder('None'),
            ])
            ->recordActions([
                Action::make('editRoles')
                    ->label('Edit roles')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('')
                            ->options(collect(AssetRole::cases())
                                ->mapWithKeys(fn (AssetRole $role): array => [$role->value => $role->getLabel()])
                                ->all())
                            ->columns(2),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'roles' => $record->assetRoles->pluck('role')->map(fn (AssetRole $role): string => $role->value)->all(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $selected = collect($data['roles'] ?? []);

                        $record->assetRoles()
                            ->whereNotIn('role', $selected->all())
                            ->delete();

                        foreach ($selected as $role) {
                            AssetUserRole::query()->firstOrCreate([
                                'user_id' => $record->id,
                                'role' => $role,
                            ]);
                        }

                        Notification::make()->title('Roles updated')->success()->send();
                    }),
            ]);
    }
}
