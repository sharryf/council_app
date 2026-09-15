<?php

namespace App\Services\Assets;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Guards against two near-simultaneous clicks double-approving/closing
 * the same transfer or maintenance record before the first round-trip
 * re-renders the button — same shape and same non-blocking block(0) as
 * App\Services\Inventory\DocumentLock, kept as its own small copy
 * rather than a cross-module dependency (same reasoning as
 * AssetTagGenerator not reusing InventorySequenceService).
 */
class AssetLock
{
    public static function once(string $key, Closure $callback): void
    {
        try {
            Cache::lock("asset-lock:{$key}", 10)->block(0, $callback);
        } catch (LockTimeoutException) {
            Notification::make()
                ->title('This record is already being processed — please wait a moment and check its status.')
                ->warning()
                ->send();
        }
    }
}
