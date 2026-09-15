<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'data_type', 'category', 'description', 'updated_by'])]
class InventorySetting extends Model
{
    const CREATED_AT = null;

    private const CACHE_KEY = 'inventory.settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Spec 15's own warning: "reading a settings row on every request
     * will hurt." Every setting read in this module goes through here
     * (directly via get(), or via getBool()) rather than a raw query,
     * so a save anywhere — the Settings page, tinker, a seeder — just
     * has to save; it never has to remember to invalidate by hand.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * A plain key => value array, not a Collection of hydrated models —
     * caching Eloquent models through the database cache store hits a
     * PHP unserialize()/class-autoloading ordering issue (the same bug
     * Phase 6's ReorderAlertsWidget had caching a Collection of
     * InventoryItem), which the array cache store used in tests never
     * surfaces. Scalars have no such problem.
     *
     * @return array<string, string>
     */
    private static function allCached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::allCached()[$key] ?? $default;
    }

    /**
     * Values are stored as plain strings (see data_type) regardless of
     * their real type — this is the one place that knows how to read a
     * boolean-typed setting back out, so callers never have to remember
     * the 'true'/'false' string convention seeded in InventorySeeder.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : $value === 'true';
    }

    public static function timezone(): string
    {
        return self::get('timezone', 'Indian/Maldives');
    }

    /**
     * Storage (config('app.timezone')) stays UTC, and Filament's own
     * table/infolist columns already convert at display time via
     * FilamentTimezone (see AppServiceProvider::boot()) — but CSV/PDF
     * exports and the printable slips build their date strings by hand
     * outside that rendering path, so they'd otherwise show raw UTC.
     * This is the one place those call sites go through instead.
     */
    public static function localize(?\Carbon\CarbonInterface $date): ?\Carbon\CarbonInterface
    {
        return $date?->copy()->setTimezone(self::timezone());
    }
}
