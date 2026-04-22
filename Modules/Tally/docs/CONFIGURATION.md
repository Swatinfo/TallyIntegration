# Configuration

Everything configurable in the Tally module. Env vars are preferred; edit `config/tally.php` (publish first) only when you need project-wide overrides that differ from environment.

---

## Demo sandbox (one-time setup)

The `tally:demo` command exercises every capability against a dedicated **`SwatTech Demo`** company. Before running `tally:demo seed` you must create this company inside TallyPrime:

1. Open TallyPrime → **File → Create Company**.
2. Name it exactly `SwatTech Demo` (case-sensitive, no trailing spaces).
3. Leave all other fields at defaults or set per your locale.
4. Back in your Laravel app: `php artisan tally:demo seed`.

The seeder aborts loudly if the company is missing. No other company is ever touched — the command refuses to run unless the `DEMO` connection row's `company_name` exactly matches `SwatTech Demo` and every outbound XML carries the matching `<SVCURRENTCOMPANY>` tag (enforced by `DemoGuard`).

### Request/response log path

Every Tally API call is logged to:
```
storage/logs/tally/tally-DD-MM-YYYY.log
```

The folder and today's file are created automatically on the first Tally call (handled by `Modules\Tally\Logging\TallyLogChannel`). Override level via `TALLY_LOG_LEVEL` env var; disable entirely with `TALLY_LOG_REQUESTS=false`.

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

    // BinaryXML (default) matches official Demo Samples. Flip to SysName
    // if Tally resets the connection mid-response on Object (single-entity) exports.
    'object_export_format' => env('TALLY_OBJECT_EXPORT_FORMAT', 'BinaryXML'),
];
```

Note: `logging.channel`, `cache.prefix`, `circuit_breaker.failure_threshold`, and `circuit_breaker.recovery_timeout` are **not** exposed as env vars — change them in the published config.

---

## Workflow / approval thresholds *(Phase 9J)*

Added to `config/tally.php`:

```php
'workflow' => [
    'enabled' => env('TALLY_WORKFLOW_ENABLED', true),
    'approval_thresholds' => [
        ['type' => 'Payment', 'amount' => 100000],
        ['type' => 'Journal', 'amount' => 250000],
    ],
    'require_distinct_approver' => true,
],
```

- **Empty `approval_thresholds`** → every draft requires an explicit approver.
- **Non-empty** → drafts below every matching rule's threshold auto-approve + push on submit (single-call flow).
- **`require_distinct_approver: true`** → the user who submitted cannot also approve (enforces maker-checker segregation).

Grant `ApproveVouchers` separately from `ManageVouchers` to split roles.

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

The `users.tally_permissions` column stores a JSON array of `TallyPermission` enum values. Nine permissions exist:

| Permission | Value | Grants |
|---|---|---|
| `ViewMasters` | `view_masters` | `GET /{conn}/ledgers|groups|stock-items|stock-groups|units|cost-centres|currencies|godowns|voucher-types|stock-categories|price-lists` |
| `ManageMasters` | `manage_masters` | `POST/PUT/PATCH/DELETE` on all masters + BOM + cache flush |
| `ViewVouchers` | `view_vouchers` | `GET /{conn}/vouchers` + voucher PDF |
| `ManageVouchers` | `manage_vouchers` | `POST/PUT/PATCH/DELETE` on vouchers + batch import + bank reconcile + stock-transfers + physical-stock + manufacturing + job-work + draft-voucher create/edit/submit |
| `ViewReports` | `view_reports` | `GET /{conn}/reports/{type}` (all 18 report types) |
| `ManageConnections` | `manage_connections` | `/connections/*`, `/audit-logs`, sync routes, MNC hierarchy CRUD, consolidated reports, recurring vouchers, circuit state |
| `ApproveVouchers` *(9J)* | `approve_vouchers` | `POST /connections/{id}/draft-vouchers/{id}/{approve,reject}` |
| `ManageIntegrations` *(9I)* | `manage_integrations` | Webhooks CRUD, CSV imports, voucher attachments |
| `SendInvoices` *(9I)* | `send_invoices` | `POST /{conn}/vouchers/{id}/email` |

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

### Tiered limits (shipped)

`App\Providers\AppServiceProvider::boot()` defines **tiered** limits based on the Sanctum token's `name` field plus **per-connection keying** so one busy Tally instance doesn't starve another.

| Token name prefix | Tier | `tally-api` | `tally-write` | `tally-reports` |
|---|---|---|---|---|
| `smoke-test-*`, `internal-*`, `system-*` | **internal** | 6000/min | 6000/min | 600/min |
| `batch-*`, `sync-*` | **batch** | 1200/min | 600/min | 120/min |
| Anything else / anonymous | **standard** | 120/min | 60/min | 20/min |

**Key structure:**
- Authenticated routes: `tally:{tier}:user:{id}[:conn:{code}]`
- Unauthenticated routes: `tally:{tier}:ip:{addr}`
- Routes with a `{connection}` segment include the connection code in the key, so each Tally instance has its own bucket.

### Naming tokens

When you mint a Sanctum token, pick the name prefix that matches the traffic class:

```php
// Interactive / UI token — default standard tier.
$user->createToken('ui-dashboard')->plainTextToken;

// CSV bulk import / month-end close — batch tier.
$user->createToken('batch-month-end-'.now()->format('Ym'))->plainTextToken;

// Internal service / cron / CI — internal tier (effectively unlimited).
$user->createToken('internal-cron-sync')->plainTextToken;

// Smoke test — bootstrap does this automatically.
// Name pattern: smoke-test-<timestamp>
```

### Why tiered?

- **UI traffic** (human editing one record at a time): 60 writes/min is plenty; guards against runaway scripts.
- **Batch loads** (month-end: 1000 vouchers in 10 min = 100/min): `batch-*` tokens get 600 writes/min = 10/sec.
- **Internal jobs** (sync workers, smoke tests, CI): `internal-*` tokens effectively uncapped.
- **Per-connection keying**: if branch A is doing a huge sync, branch B's UI is unaffected.

The **circuit breaker** (see `Modules/Tally/app/Services/CircuitBreaker.php`) is the safety net if Tally itself slows down — the rate limiter protects Laravel, the breaker protects Tally.

To change the tier caps, edit the `LIMITS` constant in `AppServiceProvider`.

Without these limiters defined, Laravel will 500 on any Tally request.

---

## Queue

Jobs that push to queue (9 total): `SyncFromTallyJob`, `SyncToTallyJob`, `SyncMastersJob`, `SyncAllConnectionsJob`, `HealthCheckJob`, `ProcessConflictsJob`, `BulkVoucherImportJob`, `ProcessRecurringVouchersJob` (9L), `ProcessImportJob` (9I), `DeliverWebhookJob` (9I — self-reschedules on failure with exponential backoff).

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

## Integration glue *(Phase 9I)*

Added to `config/tally.php`:

```php
'integration' => [
    'pdf' => [
        'driver' => env('TALLY_PDF_DRIVER', 'mpdf'),      // currently only mpdf is wired
        'paper' => env('TALLY_PDF_PAPER', 'A4'),
    ],
    'mail' => [
        'from_address' => env('TALLY_MAIL_FROM', env('MAIL_FROM_ADDRESS', 'accounts@example.com')),
        'from_name' => env('TALLY_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Accounts')),
    ],
    'attachments' => [
        'disk' => env('TALLY_ATTACHMENT_DISK', 'local'),
        'max_size_kb' => 10240,
        'allowed_mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'xlsx', 'docx', 'txt', 'csv'],
    ],
    'webhooks' => [
        'max_attempts' => 5,
        'backoff_seconds' => [60, 300, 900, 3600, 14400],  // 1min → 4hr
        'timeout_seconds' => 10,
        'queue' => env('TALLY_WEBHOOK_QUEUE', 'default'),
    ],
    'imports' => [
        'disk' => env('TALLY_IMPORT_DISK', 'local'),
        'queue' => env('TALLY_IMPORT_QUEUE', 'default'),
        'chunk_size' => 100,
    ],
],
```

- **PDF:** mpdf (v8.3) is the shipped driver. `storage/app/mpdf-tmp/` must be writable on first render (Laravel creates it automatically via `storage/app/*`).
- **Mail:** reads your app-level `MAIL_MAILER` — `log` is fine for development, switch to `smtp` / `ses` / `postmark` for production.
- **Attachments disk:** `local` by default (stores in `storage/app/tally/attachments/{connection_id}/{master_id}/`). For multi-server deployments, switch to `s3` or `gcs` via `TALLY_ATTACHMENT_DISK`.
- **Webhook backoff:** five attempts over ~4 hours by default. A delivery row is created per attempt in `tally_webhook_deliveries` with `status` = `pending` / `delivered` / `failed`.
- **Import chunk size:** rows processed in batches of 100 by default; tune for memory vs throughput on big files.

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
