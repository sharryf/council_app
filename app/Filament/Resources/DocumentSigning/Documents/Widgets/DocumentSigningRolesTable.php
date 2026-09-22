<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Widgets;

use App\Enums\DocumentSigningRole;
use App\Models\DocumentSigningUserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * One row per user with access to the document-signing module (see
 * User::canAccessModule()); `admin` users are excluded because their
 * roles are set from the Users page's "Module Roles" section instead —
 * see App\Filament\Bureau\Widgets\BureauRolesTable's own comment for
 * the full reasoning. Each row's roles are edited via a modal — a
 * table column can't hold a multi-select control the way
 * Filament\Tables\Columns\SelectColumn holds a single value, which is
 * why this doesn't reuse the SelectColumn-based pattern from
 * App\Filament\Widgets\Concerns\HasModuleRolesTable.
 */
class DocumentSigningRolesTable extends TableWidget
{
    protected static ?string $heading = 'Document Signing — user roles';

    // See App\Filament\Widgets\ModuleCardsWidget for the same override —
    // a lazy TableWidget also defers its inner table's own data load to
    // an unnecessary second round trip for a list this small.
    protected static bool $isLazy = false;

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(
                User::query()
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'))
                    ->where(fn ($query) => $query
                        ->whereNull('module_access')
                        ->orWhereJsonContains('module_access', 'document-signing'))
                    ->orderBy('name'),
            )
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('position')->placeholder('—'),
                TextColumn::make('email')->label('Email address'),
                TextColumn::make('roles')
                    ->label('Roles')
                    ->badge()
                    ->getStateUsing(fn (User $record): array => $record->documentSigningRoleList())
                    ->placeholder('None'),
            ])
            ->recordActions([
                Action::make('editRoles')
                    ->label('Edit roles')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('')
                            ->options(collect(DocumentSigningRole::cases())
                                ->mapWithKeys(fn (DocumentSigningRole $role): array => [$role->value => $role->getLabel()])
                                ->all())
                            ->columns(2),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'roles' => $record->documentSigningRoles->pluck('role')->map(fn (DocumentSigningRole $role): string => $role->value)->all(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $selected = collect($data['roles'] ?? []);

                        $record->documentSigningRoles()
                            ->whereNotIn('role', $selected->all())
                            ->delete();

                        foreach ($selected as $role) {
                            DocumentSigningUserRole::query()->firstOrCreate([
                                'user_id' => $record->id,
                                'role' => $role,
                            ]);
                        }

                        Notification::make()->title('Roles updated')->success()->send();
                    }),
            ]);
    }
}
