<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Tally rate-limiter setup — tiered by token name prefix (Option A) and
 * keyed per-connection for routes under /{connection}/* (Option G).
 *
 * Tiering by Sanctum token name:
 *   - smoke-test-* / internal-* / system-*   → internal (effectively unlimited)
 *   - batch-* / sync-*                        → batch (fat pipe for month-end loads)
 *   - anything else / anonymous               → standard (the default guardrail)
 *
 * Keying:
 *   - Routes with a {connection} segment add the connection code to the key so
 *     one busy Tally instance doesn't starve another.
 *   - Fallback key: user id, then IP.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Per-tier per-group limits (requests per minute).
     *
     * @var array<string, array<string, int>>
     */
    private const LIMITS = [
        'internal' => ['tally-api' => 6000, 'tally-write' => 6000, 'tally-reports' => 600],
        'batch' => ['tally-api' => 1200, 'tally-write' => 600, 'tally-reports' => 120],
        'standard' => ['tally-api' => 120, 'tally-write' => 60, 'tally-reports' => 20],
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('tally-api', fn (Request $request) => $this->limitFor($request, 'tally-api'));
        RateLimiter::for('tally-write', fn (Request $request) => $this->limitFor($request, 'tally-write'));
        RateLimiter::for('tally-reports', fn (Request $request) => $this->limitFor($request, 'tally-reports'));
    }

    private function limitFor(Request $request, string $group): Limit
    {
        $tier = $this->tierFor($request);
        $perMinute = self::LIMITS[$tier][$group];
        $key = $this->keyFor($request, $tier);

        return Limit::perMinute($perMinute)->by($key);
    }

    /**
     * Classify the request by Sanctum token name prefix.
     */
    private function tierFor(Request $request): string
    {
        $tokenName = (string) ($request->user()?->currentAccessToken()?->name ?? '');

        if ($tokenName === '') {
            return 'standard';
        }

        foreach (['smoke-test-', 'internal-', 'system-'] as $prefix) {
            if (str_starts_with($tokenName, $prefix)) {
                return 'internal';
            }
        }

        foreach (['batch-', 'sync-'] as $prefix) {
            if (str_starts_with($tokenName, $prefix)) {
                return 'batch';
            }
        }

        return 'standard';
    }

    /**
     * Build the rate-limit key: tier:user[:connection] or tier:ip.
     */
    private function keyFor(Request $request, string $tier): string
    {
        $base = $request->user()?->id
            ? 'user:'.$request->user()->id
            : 'ip:'.$request->ip();

        // Per-connection scoping for routes under /{connection}/* or /connections/{connection}/*.
        $conn = $request->route('connection');
        if ($conn && is_string($conn)) {
            return "tally:{$tier}:{$base}:conn:{$conn}";
        }

        return "tally:{$tier}:{$base}";
    }
}
