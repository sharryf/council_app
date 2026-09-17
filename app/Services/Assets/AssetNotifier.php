<?php

namespace App\Services\Assets;

use App\Enums\AssetDeleteRequestStatus;
use App\Enums\AssetRole;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Models\AssetDeleteRequest;
use App\Models\AssetEditRequest;
use App\Models\AssetMaintenanceRecord;
use App\Models\AssetTransferRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Spec section 7's four notification triggers — in-app only, no email
 * (spec section 10: "In-app only"), unlike
 * App\Services\Inventory\InventoryNotifier which also sends
 * App\Notifications\InventoryAlert mail. Every method enforces "never
 * notify a user of their own action" before sending.
 */
class AssetNotifier
{
    public function transferRequested(AssetTransferRequest $request): void
    {
        $recipients = $this->usersWithRole(AssetRole::Manager)->reject(fn (User $u): bool => $u->id === $request->requested_by);

        $this->alert(
            $recipients,
            "New transfer request: {$request->asset->name}",
            "{$request->requestedBy->name} requested a transfer to {$request->toRoom->path()}.",
            AssetResource::getUrl('view', ['record' => $request->asset_id]),
        );
    }

    public function transferDecided(AssetTransferRequest $request): void
    {
        $requester = $request->requestedBy;

        if (! $requester || $requester->id === auth()->id()) {
            return;
        }

        $verb = $request->status->getLabel();

        $this->alert(
            collect([$requester]),
            "Your transfer request was {$verb}: {$request->asset->name}",
            $request->decision_note,
            AssetResource::getUrl('view', ['record' => $request->asset_id]),
        );
    }

    public function maintenanceLogged(AssetMaintenanceRecord $record): void
    {
        $recipients = $this->usersWithRole(AssetRole::Manager)->reject(fn (User $u): bool => $u->id === $record->recorded_by);

        $this->alert(
            $recipients,
            "Maintenance logged: {$record->asset->name}",
            "{$record->recordedBy->name} logged maintenance — awaiting approval.",
            AssetResource::getUrl('view', ['record' => $record->asset_id]),
        );
    }

    public function maintenanceDecided(AssetMaintenanceRecord $record): void
    {
        $recordedBy = $record->recordedBy;

        if (! $recordedBy || $recordedBy->id === auth()->id()) {
            return;
        }

        $verb = $record->approval_status->getLabel();

        $this->alert(
            collect([$recordedBy]),
            "Your maintenance record was {$verb}: {$record->asset->name}",
            $record->decision_note,
            AssetResource::getUrl('view', ['record' => $record->asset_id]),
        );
    }

    public function editRequested(AssetEditRequest $request): void
    {
        $recipients = $this->usersWithRole(AssetRole::Manager)->reject(fn (User $u): bool => $u->id === $request->requested_by);

        $this->alert(
            $recipients,
            "New edit request: {$request->asset->name}",
            "{$request->requestedBy->name} requested changes to ".$request->fieldsSummary().'.',
            AssetResource::getUrl('view', ['record' => $request->asset_id]),
        );
    }

    public function editDecided(AssetEditRequest $request): void
    {
        $requester = $request->requestedBy;

        if (! $requester || $requester->id === auth()->id()) {
            return;
        }

        $verb = $request->status->getLabel();

        $this->alert(
            collect([$requester]),
            "Your edit request was {$verb}: {$request->asset->name}",
            $request->review_note,
            AssetResource::getUrl('view', ['record' => $request->asset_id]),
        );
    }

    public function deleteRequested(AssetDeleteRequest $request): void
    {
        $recipients = $this->usersWithRole(AssetRole::Manager)->reject(fn (User $u): bool => $u->id === $request->requested_by);

        $this->alert(
            $recipients,
            "New deletion request: {$request->asset->name}",
            "{$request->requestedBy->name} requested to delete this asset.",
            AssetResource::getUrl('view', ['record' => $request->asset_id]),
        );
    }

    public function deleteDecided(AssetDeleteRequest $request): void
    {
        $requester = $request->requestedBy;

        if (! $requester || $requester->id === auth()->id()) {
            return;
        }

        $verb = $request->status->getLabel();

        $this->alert(
            collect([$requester]),
            "Your deletion request was {$verb}: {$request->asset->name}",
            $request->review_note,
            // No link once approved — the asset itself no longer exists
            // at that URL.
            $request->status === AssetDeleteRequestStatus::Approved ? null : AssetResource::getUrl('view', ['record' => $request->asset_id]),
        );
    }

    /**
     * Explicit AssetUserRole holders plus the system-wide spatie admin
     * role — same two groups hasAssetRole()'s own bypass treats as
     * equivalent.
     *
     * @return Collection<int, User>
     */
    private function usersWithRole(AssetRole $role): Collection
    {
        $explicit = User::query()->whereHas('assetRoles', fn ($q) => $q->where('role', $role))->get();
        $systemAdmins = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();

        return $explicit->merge($systemAdmins)->unique('id')->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function alert(Collection $recipients, string $title, ?string $body, ?string $url): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        // Not ->sendToDatabase() — same reasoning as InventoryNotifier's
        // own alert(): Filament's DatabaseNotification implements
        // ShouldQueue and this app has no queue worker, so sendNow()
        // delivers synchronously while reusing identical toDatabase()
        // formatting for the bell.
        $filamentNotification = Notification::make()
            ->title($title)
            ->body($body)
            ->when($url, fn (Notification $n) => $n->actions([
                Action::make('view')->label('View')->url($url),
            ]));

        NotificationFacade::sendNow($recipients, $filamentNotification->toDatabase());
    }
}
