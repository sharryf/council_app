<?php

namespace App\Filament\Assets\Resources\Assets\Pages;

use App\Enums\AssetEditRequestStatus;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetEditRequest;
use App\Models\AssetHistory;
use App\Services\Assets\AssetNotifier;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => AssetResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Draft: applies immediately, exactly as before. Posted: nothing is
     * saved here at all — instead an AssetEditRequest captures the
     * reason and every changed field (photo included, keyed as
     * 'photo'), and a Manager's later approval
     * (AssetEditRequestResource::approveAction()) is what actually
     * calls Asset::update() / Asset::replacePhoto() and logs the diff.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Asset $record */
        $reason = $data['edit_reason'] ?? null;
        unset($data['edit_reason']);

        $newPhotoPath = $data['photo'] ?? null;
        unset($data['photo']);

        if ($record->isPosted()) {
            return $this->submitEditRequest($record, $data, $reason, $newPhotoPath);
        }

        $before = $record->only(array_keys(Asset::EDITABLE_FIELD_LABELS));

        return DB::transaction(function () use ($record, $data, $before, $newPhotoPath): Asset {
            $record->update($data);
            $record->recordFieldChanges($before);

            if (filled($newPhotoPath) && $newPhotoPath !== $record->photoAttachment?->file_path) {
                $record->replacePhoto($newPhotoPath);
            }

            return $record;
        });
    }

    private function submitEditRequest(Asset $record, array $data, ?string $reason, ?string $newPhotoPath = null): Asset
    {
        // Defense in depth — the Edit button on ViewAsset is already
        // hidden once a request is pending, but this re-checks against
        // the database at submit time in case another tab got there
        // first (same "refresh and recheck" shape used for transfers/
        // maintenance elsewhere in this module).
        if ($record->fresh()->hasPendingEditRequest()) {
            Notification::make()
                ->title('This asset already has an edit request awaiting a Manager\'s decision.')
                ->danger()
                ->send();

            return $record;
        }

        $before = $record->only(array_keys(Asset::EDITABLE_FIELD_LABELS));

        // $before holds cast values (a Carbon date, a decimal string,
        // an enum) straight off the model, while $data is the form's
        // raw submitted strings — comparing them directly would flag
        // every date/price field as "changed" even when untouched.
        // Running $data through the same cast pipeline on an unsaved
        // clone makes both sides comparable, without touching $record.
        $candidate = (clone $record)->forceFill($data);

        $proposedChanges = [];

        foreach (array_keys(Asset::EDITABLE_FIELD_LABELS) as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if (Asset::scalarize($before[$field] ?? null) !== Asset::scalarize($candidate->{$field})) {
                $proposedChanges[$field] = $data[$field];
            }
        }

        // Not a real column, so it never goes through the scalarize
        // diff above — a new upload always counts as a proposed change.
        if (filled($newPhotoPath)) {
            $proposedChanges['photo'] = $newPhotoPath;
        }

        if ($proposedChanges === []) {
            Notification::make()->title('No changes to submit.')->warning()->send();

            return $record;
        }

        $request = DB::transaction(function () use ($record, $proposedChanges, $reason): AssetEditRequest {
            $request = AssetEditRequest::create([
                'asset_id' => $record->id,
                'requested_by' => auth()->id(),
                'requested_at' => now(),
                'reason' => $reason,
                'proposed_changes' => $proposedChanges,
                'status' => AssetEditRequestStatus::Pending,
            ]);

            // The dedicated Edit Requests card was folded into this
            // History log, so the fields touched need to be named here
            // too — the note is all this event type shows.
            AssetHistory::record(
                $record->id,
                'edit_requested',
                "{$reason} (fields: {$request->fieldsSummary()})",
                'asset_edit_request',
                $request->id,
            );

            return $request;
        });

        app(AssetNotifier::class)->editRequested($request->fresh(['asset', 'requestedBy']));

        Notification::make()->title('Edit submitted for Manager approval.')->success()->send();

        return $record;
    }
}
