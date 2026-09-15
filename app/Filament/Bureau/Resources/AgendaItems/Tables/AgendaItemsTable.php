<?php

namespace App\Filament\Bureau\Resources\AgendaItems\Tables;

use App\Enums\BureauAgendaItemKind;
use App\Enums\BureauAgendaStatus;
use App\Enums\BureauRole;
use App\Filament\Bureau\Resources\AgendaItems\AgendaItemResource;
use App\Models\BureauAgendaItem;
use App\Support\DhivehiDate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AgendaItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Agenda Passing / Minutes Passing items are procedural,
            // auto-generated per-meeting (see CreateMeeting::afterCreate())
            // rather than submitted through this table — they only ever
            // belong on their own meeting's agenda, not in the shared pool
            // of items people create/review/claim here.
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['creator', 'creator.bureauRoles', 'reviewer'])
                ->where('kind', BureauAgendaItemKind::Regular)
                ->latest())
            // Column order matches the module's RTL reading order:
            // item details, who created it, when, attachments, status.
            ->columns([
                TextColumn::make('details')
                    ->label(__('bureau.agenda.field.details'))
                    ->searchable()
                    ->wrap()
                    ->extraAttributes(['class' => 'flex-1']),
                TextColumn::make('creator.name')
                    ->label(__('bureau.agenda.field.created_by'))
                    ->formatStateUsing(function ($state, BureauAgendaItem $record) {
                        $creator = $record->creator;
                        if (!$creator) {
                            return $state;
                        }

                        $creatorRole = $creator->bureauRoles->first()?->role;
                        $creatorNameDv = $creator->name_dv ?? $state;

                        $rolePrefix = match ($creatorRole) {
                            BureauRole::President => 'ރިޔާސަތުން',
                            BureauRole::Councillor => $creator->position_dv ?? $creator->position ?? 'މެންބަރު',
                            BureauRole::BureauAdmin, BureauRole::Participant => 'އިދާރާއިން',
                            default => null,
                        };

                        if ($rolePrefix) {
                            return '<span dir="rtl">'.$rolePrefix.' '.$creatorNameDv.'</span>';
                        }

                        return $creatorNameDv;
                    })
                    ->html(),
                TextColumn::make('created_at')
                    ->label(__('bureau.agenda.field.created_at'))
                    ->formatStateUsing(fn ($state): string => DhivehiDate::html($state))
                    ->sortable()
                    ->html(),
                TextColumn::make('attachment_original_name')
                    ->label(__('bureau.agenda.field.attachment'))
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'max-w-[120px]'])
                    ->url(fn (BureauAgendaItem $record): ?string => $record->hasAttachment()
                        ? route('bureau.agenda-items.attachment', $record)
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('status')
                    ->label(__('bureau.agenda.field.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('bureau.agenda.field.status'))
                    ->options(collect(BureauAgendaStatus::cases())->mapWithKeys(
                        fn (BureauAgendaStatus $status): array => [$status->value => $status->getLabel()],
                    )),
            ])
            ->recordActions([
                // EditAction doesn't automatically enforce
                // AgendaItemResource::canEdit() (no Laravel Policy is
                // registered for BureauAgendaItem, which is the only
                // case Filament wires that up for free) — has to be
                // explicit, or an Approved/Rejected item's Edit button
                // stays clickable after review.
                EditAction::make()
                    ->label(__('bureau.agenda.actions.edit'))
                    ->visible(fn (BureauAgendaItem $record): bool => AgendaItemResource::canEdit($record)),
                AgendaItemResource::approveAction(),
                AgendaItemResource::rejectAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('bureau.agenda.actions.delete'))
                        ->authorizeIndividualRecords(fn (BureauAgendaItem $record): bool => AgendaItemResource::canDelete($record)),
                ]),
            ]);
    }
}
