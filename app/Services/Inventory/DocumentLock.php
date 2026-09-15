<?php

namespace App\Services\Inventory;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * The Inventory module's stand-in for spec 20's "idempotency keys" —
 * every workflow mutation here is a Filament Action/Livewire call, not
 * a REST endpoint a client attaches a header to, so there's no request
 * to key on. What actually needs to be prevented is the same document
 * being posted/approved/issued twice from two near-simultaneous clicks
 * before the first round-trip re-renders the button. StockMovementService's
 * row lock already protects the stock balance; this protects the
 * document-level bookkeeping (issued_qty, status, etc.) that gets read
 * before that lock is ever taken.
 *
 * once() is non-blocking (block(0)) on purpose: a second overlapping
 * call should fail fast with "try again," not queue up and silently
 * replay the same action after the first commits.
 */
class DocumentLock
{
    public static function once(string $key, Closure $callback): void
    {
        try {
            Cache::lock("inventory-doc:{$key}", 10)->block(0, $callback);
        } catch (LockTimeoutException) {
            Notification::make()
                ->title('This document is already being processed — please wait a moment and check its status.')
                ->warning()
                ->send();
        }
    }
}
