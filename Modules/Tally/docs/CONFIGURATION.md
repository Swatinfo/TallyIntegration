# Configuration

Everything configurable in the Tally module. Env vars are preferred; edit `config/tally.php` (publish first) only when you need project-wide overrides that differ from environment.

---

## Environment variables

| Env var | Default | Used by |
|---|---|---|
| `TALLY_HOST` | `localhost` | Default `TallyHttpClient` when no per-connection override |
| `TALLY_PORT` | `9000` | Default port |
| `TALLY_COMPANY` | *(empty)* | Default company name |
| `TALLY_TIMEOUT` | `30` | HTTP timeout (seconds) |
| `TALLY_LOG_REQUESTS` | `true` | `TallyRequestLogger` — log every request/response |
| `TALLY_CACHE_ENABLED` | `true` | `CachesMasterData` trait |
| `TALLY_CACHE_TTL` | `300` | Master cache TTL in seconds |
| `TALLY_CIRCUIT_BREAKER` | `true` | `CircuitBreaker` fail-fast |

Everything in `Modules/Tally/config/config.php` reads from these.

---

## `config/tally.php` (published copy)

```bash
php artisan vendor:publish --tag=tally-config
```

Then edit `config/tally.php`:

```php
return [
    'name' => 'Tally',

    'host'    => env('TALLY_HOST', 'localhost'),
    'port'    => env('TALLY_PORT', 9000),
    'company' => env('TALLY_COMPANY', ''),
    'timeout' => env('TALLY_TIMEOUT', 30),

    'logging' => [
        'enabled'       => env('TALLY_LOG_REQUESTS', true),
        'channel'       => 'tally',
        'max_body_size' => 10240,              // truncate long XML bodies in logs
    ],

    'cache' => [
        'enabled' => env('TALLY_CACHE_ENABLED', true),
        'ttl'     => env('TALLY_CACHE_TTL', 300),
        'prefix'  => 'tally',
    ],

    'circuit_breaker' => [
        'enabled'           => env('TALLY_CIRCUIT_BREAKER', true),
        'failure_threshold' => 5,
        'recovery_timeout'  => 60,             // seconds before half-open probe
    ],
];
```

Note: `logging.channel`, `cache.prefix`, `circuit_breaker.failure_threshold`, and `circuit_breaker.recovery_timeout` are **not** exposed as env vars — change them in the published config.

---

## Logging channel (`tally`)

Add a `tally` channel to `config/logging.php`:

```php
'channels' => [
    // ... existing channels

    'tally' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/tally.log'),
        'level'  => env('LOG_LEVEL', 'debug'),
        'days'   => 14,
    ],
],
```

Without this, `TallyRequestLogger` falls back to the default channel.

---

## Sanctum

Every Tally route uses `auth:sanctum`. Ensure Sanctum is installed and `App\Models\User` uses `Laravel\Sanctum\HasApiTokens`.

```bash
composer require laravel/sanctum
php artisan install:api
```

If the module routes return `401 Unauthorized`, check:

```php
// app/Models/User.php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasApiTokens, /* other traits */;
}
```

Issue tokens with `$user->createToken('api')->plainTextToken`.

---

## Permissions

The `users.tally_permissions` column stores a JSON array of `TallyPermission` enum values. Six permissions exist:

| Permission | Value | Grants |
|---|---|---|
| `ViewMasters` | `view_masters` | `GET /{conn}/ledgers|groups|stock-items` |
| `ManageMasters` | `manage_masters` | `POST/PUT/PATCH/DELETE` on masters |
| `ViewVouchers` | `view_vouchers` | `GET /{conn}/vouchers` |
| `ManageVouchers` | `manage_vouchers` | `POST/PUT/PATCH/DELETE` on vouchers |
| `ViewReports` | `view_reports` | `GET /{conn}/reports/{type}` |
| `ManageConnections` | `manage_connections` | `/connections/*`, `/audit-logs`, all sync routes |

Grant in code:

```php
use Modules\Tally\Enums\TallyPermission;

$user->tally_permissions = [
    TallyPermission::ViewMasters->value,
    TallyPermission::ViewReports->value,
];
$user->save();
```

`null` = no Tally access. An empty array `[]` = also no access.

---

## Rate limiting

Three named throttle groups are referenced in routes:

| Name | Applied to |
|---|---|
| `tally-api` | All Tally routes |
| `tally-write` | Master + voucher writes |
| `tally-reports` | `reports/{type}` |

Define them in your `App\Providers\AppServiceProvider::boot()` (or `RouteServiceProvider`):

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

RateLimiter::for('tally-api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
RateLimiter::for('tally-write', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
RateLimiter::for('tally-reports', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
```

Without these limiters defined, Laravel will 500 on any Tally request.

---

## Queue

Jobs that push to queue: `SyncFromTallyJob`, `SyncToTallyJob`, `SyncMastersJob`, `SyncAllConnectionsJob`, `HealthCheckJob`, `ProcessConflictsJob`, `BulkVoucherImportJob`.

Recommended setup:

```env
QUEUE_CONNECTION=redis      # or database, sqs
```

With `database` driver:

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```

Recommended: run a dedicated worker for Tally so heavy voucher imports don't starve other queues. First, update the jobs (or override) to dispatch on a `tally` queue, then:

```bash
php artisan queue:work --queue=tally,default
```

---

## Schedule

Add a single cron entry so Laravel's scheduler runs every minute:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The module's `TallyServiceProvider::configureSchedules()` registers (and the scheduler then invokes):

| Cadence | Job |
|---|---|
| every 5 min | `HealthCheckJob` |
| every 5 min | `ProcessConflictsJob` per active connection |
| every 10 min | `SyncFromTallyJob` + `SyncToTallyJob` per active connection |
| hourly | `SyncAllConnectionsJob` |

Disable any of these by editing `Modules/Tally/app/Providers/TallyServiceProvider.php`.

---

## Circuit breaker tuning

The circuit breaker kicks in per connection code. Defaults:

| Setting | Default | Meaning |
|---|---|---|
| `failure_threshold` | 5 | Consecutive failures before opening |
| `recovery_timeout` | 60 | Seconds before first half-open probe |

To tune: publish the config (`vendor:publish --tag=tally-config`) and edit `circuit_breaker` in `config/tally.php`.

To disable entirely: `TALLY_CIRCUIT_BREAKER=false`.

---

## Multiple Tally instances

Each row in `tally_connections` is its own instance. Env `TALLY_HOST/PORT/COMPANY` are used only for:

- `/api/tally/health` (the unauthenticated-at-the-connection-level health check)
- `TallyHttpClient::fromConfig()` when no connection context is present

All real work should go through `/api/tally/{code}/...` routes so the correct per-connection client is resolved.

---

## Cache driver

`CachesMasterData` uses Laravel's default cache store. For multi-tenant / multi-connection installations, prefer Redis (`CACHE_DRIVER=redis`) so invalidation is atomic. The file driver works but invalidation is slower.

---

## Full reference

See `.claude/services-reference.md` for every method signature, and `.claude/routes-reference.md` for the middleware stack of each route.
