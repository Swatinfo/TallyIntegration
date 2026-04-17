<?php

namespace Modules\Tally\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Tally\Exceptions\TallyConnectionException;

class CircuitBreaker
{
    public function isAvailable(string $connectionCode): bool
    {
        if (! config('tally.circuit_breaker.enabled', true)) {
            return true;
        }

        $state = $this->getState($connectionCode);

        if ($state === 'closed') {
            return true;
        }

        if ($state === 'open') {
            $recoveryTimeout = config('tally.circuit_breaker.recovery_timeout', 60);
            $openedAt = Cache::get("tally:cb:{$connectionCode}:opened_at", 0);

            if (time() - $openedAt >= $recoveryTimeout) {
                $this->setState($connectionCode, 'half-open');

                return true; // Allow one probe
            }

            return false;
        }

        // half-open: allow one request through
        return true;
    }

    public function recordSuccess(string $connectionCode): void
    {
        Cache::forget("tally:cb:{$connectionCode}:failures");
        $this->setState($connectionCode, 'closed');
    }

    public function recordFailure(string $connectionCode): void
    {
        $threshold = config('tally.circuit_breaker.failure_threshold', 5);
        $key = "tally:cb:{$connectionCode}:failures";

        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, 3600);

        if ($failures >= $threshold) {
            $this->setState($connectionCode, 'open');
            Cache::put("tally:cb:{$connectionCode}:opened_at", time(), 3600);
        }
    }

    public function getState(string $connectionCode): string
    {
        return Cache::get("tally:cb:{$connectionCode}:state", 'closed');
    }

    public function assertAvailable(string $connectionCode): void
    {
        if (! $this->isAvailable($connectionCode)) {
            throw new TallyConnectionException(
                "Circuit breaker is open for connection '{$connectionCode}'. Too many failures — try again later.",
                connectionCode: $connectionCode,
            );
        }
    }

    private function setState(string $connectionCode, string $state): void
    {
        Cache::put("tally:cb:{$connectionCode}:state", $state, 3600);
    }
}
