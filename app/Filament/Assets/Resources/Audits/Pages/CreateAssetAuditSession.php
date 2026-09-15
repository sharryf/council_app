<?php

namespace App\Filament\Assets\Resources\Audits\Pages;

use App\Enums\AssetAuditScopeType;
use App\Enums\AssetAuditSessionStatus;
use App\Enums\AssetStatus;
use App\Filament\Assets\Resources\Audits\AssetAuditSessionResource;
use App\Models\Asset;
use App\Models\AssetAuditItem;
use App\Models\AssetAuditSession;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateAssetAuditSession extends CreateRecord
{
    protected static string $resource = AssetAuditSessionResource::class;

    /**
     * Only one in-progress session at a time (implementation plan
     * section 3.8/8.20) — checked here, defensively, not just relied
     * on as a UI assumption.
     */
    protected function handleRecordCreation(array $data): Model
    {
        if (AssetAuditSession::query()->where('status', AssetAuditSessionStatus::InProgress)->exists()) {
            Notification::make()
                ->title('An audit session is already in progress.')
                ->body('Close it before starting a new one.')
                ->danger()
                ->send();

            $this->halt();
        }

        $scopeType = AssetAuditScopeType::from($data['scope_type']);

        return DB::transaction(function () use ($data, $scopeType): AssetAuditSession {
            /** @var AssetAuditSession $session */
            $session = AssetAuditSession::create([
                'name' => $data['name'],
                'scope_type' => $scopeType,
                'scope_building_id' => $scopeType === AssetAuditScopeType::Building ? $data['scope_building_id'] : null,
                'scope_room_id' => $scopeType === AssetAuditScopeType::Room ? $data['scope_room_id'] : null,
                'status' => AssetAuditSessionStatus::InProgress,
                'started_by' => auth()->id(),
                'started_at' => now(),
            ]);

            // Scope snapshot, frozen at start — assets created after
            // this point are not added (spec section 3.8). Disposed
            // assets are excluded; Lost assets are included (finding
            // one is a valid, useful audit outcome).
            $assets = Asset::query()
                ->where('status', '!=', AssetStatus::Disposed)
                ->when($scopeType === AssetAuditScopeType::Building, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('building_id', $data['scope_building_id'])))
                ->when($scopeType === AssetAuditScopeType::Room, fn ($q) => $q->where('room_id', $data['scope_room_id']))
                ->get(['id', 'room_id']);

            $now = now();
            $rows = $assets->map(fn (Asset $asset): array => [
                'session_id' => $session->id,
                'asset_id' => $asset->id,
                'expected_room_id' => $asset->room_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows !== []) {
                AssetAuditItem::query()->insert($rows);
            }

            return $session;
        });
    }
}
