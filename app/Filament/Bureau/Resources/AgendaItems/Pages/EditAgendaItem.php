<?php

namespace App\Filament\Bureau\Resources\AgendaItems\Pages;

use App\Enums\BureauAgendaStatus;
use App\Filament\Bureau\Resources\AgendaItems\AgendaItemResource;
use App\Filament\Bureau\Resources\AgendaItems\Concerns\InteractsWithAttachment;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Only reachable while Entered or Approved, by the item's own creator
 * or a Bureau Admin (see AgendaItemResource::canEdit()) — the
 * President's role is approve/reject, which lives on the table's row
 * actions instead (see AgendaItemsTable), not here, since the
 * President is neither the creator nor (necessarily) an Admin.
 */
class EditAgendaItem extends EditRecord
{
    use InteractsWithAttachment;

    protected static string $resource = AgendaItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('bureau.agenda.actions.delete')),
        ];
    }

    /**
     * Editing an already-Approved item sends it back to Entered so the
     * President reviews the edited version rather than the approval
     * silently carrying over to changed content.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->extractAttachmentFields($data);

        if ($this->record->status === BureauAgendaStatus::Approved) {
            $data['status'] = BureauAgendaStatus::Entered;
            $data['reviewed_by'] = null;
            $data['reviewed_at'] = null;
        }

        return $data;
    }
}
