<?php

namespace App\Filament\Assets\Widgets;

use App\Enums\AssetAuditSessionStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Audits\AssetAuditSessionResource;
use App\Models\AssetAuditItem;
use App\Models\AssetAuditSession;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

/**
 * Spec section 6.1: "Open audit session banner, if one is in progress,
 * with progress (e.g. '48 of 120 verified')." Renders nothing at all
 * when there's no in-progress session — not an empty card. Multiple
 * sessions may be in progress at once (no longer capped at one — see
 * CreateAssetAuditSession), so this lists every one of them rather
 * than assuming a single result.
 */
class OpenAuditWidget extends Widget
{
    use HasAssetRoleAccess;

    protected string $view = 'filament.assets.widgets.open-audit';

    protected int|string|array $columnSpan = 'full';

    // Lazy wire:init loading doesn't reliably fire in this environment
    // — same gotcha already documented on every other dashboard widget
    // in this app (see InventoryPanelProvider's own comment on it).
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userHasAnyAssetRole();
    }

    /**
     * @return Collection<int, AssetAuditSession>
     */
    public function getSessions(): Collection
    {
        return AssetAuditSession::query()->where('status', AssetAuditSessionStatus::InProgress)->get();
    }

    /**
     * @return array{verified: int, total: int}
     */
    public function getProgress(AssetAuditSession $session): array
    {
        return [
            'verified' => AssetAuditItem::where('session_id', $session->id)->whereNotNull('verified_at')->count(),
            'total' => AssetAuditItem::where('session_id', $session->id)->count(),
        ];
    }

    public function getSessionUrl(AssetAuditSession $session): string
    {
        return AssetAuditSessionResource::getUrl('view', ['record' => $session]);
    }
}
