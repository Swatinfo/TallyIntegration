<?php

namespace Modules\Tally\Services\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;

trait CachesMasterData
{
    protected function cachedList(string $cacheKey, Closure $fetcher): array
    {
        if (! config('tally.cache.enabled', true)) {
            return $fetcher();
        }

        $ttl = config('tally.cache.ttl', 300);
        $prefix = config('tally.cache.prefix', 'tally');

        return Cache::remember("{$prefix}:{$cacheKey}", $ttl, $fetcher);
    }

    protected function cachedGet(string $cacheKey, Closure $fetcher): ?array
    {
        if (! config('tally.cache.enabled', true)) {
            return $fetcher();
        }

        $ttl = config('tally.cache.ttl', 300);
        $prefix = config('tally.cache.prefix', 'tally');
        $fullKey = "{$prefix}:{$cacheKey}";

        if (Cache::has($fullKey)) {
            return Cache::get($fullKey);
        }

        $result = $fetcher();

        if ($result !== null) {
            Cache::put($fullKey, $result, $ttl);
        }

        return $result;
    }

    protected function invalidateCache(string ...$keys): void
    {
        $prefix = config('tally.cache.prefix', 'tally');

        foreach ($keys as $key) {
            Cache::forget("{$prefix}:{$key}");
        }
    }
}
