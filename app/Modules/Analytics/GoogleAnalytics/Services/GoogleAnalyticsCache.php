<?php

namespace App\Modules\Analytics\GoogleAnalytics\Services;

use App\Modules\Analytics\GoogleAnalytics\Enums\GaCacheTier;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Wraps Laravel's cache with a tiered TTL strategy.
 *
 * The TTL is decided by how recent the data is — recent data changes,
 * historical data does not. This is the difference vs. Clarity's daily
 * snapshot model: GA data is fetched on-demand, but cached so we don't
 * hit the API on every UI render.
 */
class GoogleAnalyticsCache
{
    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function remember(string $key, GaCacheTier $tier, callable $callback): mixed
    {
        return Cache::remember($key, $tier->ttlSeconds(), $callback);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * Resolve which TTL tier applies to a date range based on its end date.
     *
     * - includes today  -> Today (data still aggregating)
     * - only yesterday  -> Yesterday (mostly stable)
     * - ends 2..7 days ago -> Recent
     * - ends 8+ days ago   -> Historical
     */
    public function tierFor(DateRange $range): GaCacheTier
    {
        if ($range->includesToday()) {
            return GaCacheTier::Today;
        }
        if ($range->isOnlyYesterday()) {
            return GaCacheTier::Yesterday;
        }
        if ($range->endsAtLeastDaysAgo(8)) {
            return GaCacheTier::Historical;
        }
        return GaCacheTier::Recent;
    }

    public function keyFor(string $property, string $kind, string $signature): string
    {
        return "ga:{$this->shortProperty($property)}:{$kind}:{$signature}";
    }

    /**
     * Drop every cached entry for a given property. Used by the manual "Refresh"
     * button on the GA pages — clears all kinds (overview, top-pages, age,
     * resolution, etc.) for this property so the next render re-fetches fresh.
     *
     * Implementation note: the database cache driver stores entries keyed by
     * "{prefix}{key}". We use a LIKE query with a leading wildcard to match
     * regardless of prefix configuration. The "ga:" namespace is specific
     * enough that this won't touch unrelated cache entries.
     *
     * For non-database cache stores this is a no-op fallback (returns 0). We
     * could implement Redis SCAN-based deletion if we ever switch backends.
     */
    public function forgetForProperty(string $property): int
    {
        if (config('cache.default') !== 'database') {
            return 0;
        }

        $needle = "ga:{$this->shortProperty($property)}:";
        return DB::table(config('cache.stores.database.table', 'cache'))
            ->where('key', 'like', "%{$needle}%")
            ->delete();
    }

    private function shortProperty(string $property): string
    {
        return str_starts_with($property, 'properties/')
            ? substr($property, 11)
            : $property;
    }
}
