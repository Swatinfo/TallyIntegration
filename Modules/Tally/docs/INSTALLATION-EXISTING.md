# Installation — Existing Laravel Project

Drop the Tally module into an **already-running** Laravel 11+ application. This guide assumes your app has traffic, migrations, and users — so each step is designed to avoid conflicting with what's already there.

> Starting from zero? Use [INSTALLATION-FRESH.md](INSTALLATION-FRESH.md) instead.

---

## 1. Verify compatibility

Run in your existing project:

```bash
php --version               # must be >= 8.4
php artisan --version       # must be Laravel 11 or newer (13 recommended)
composer show | grep -E "nwidart|sanctum"
```

| Dependency | Required | How to add if missing |
|---|---|---|
| `nwidart/laravel-modules` | v13 | `composer require nwidart/laravel-modules:^13.0` |
| `laravel/sanctum` | any | `composer require laravel/sanctum && php artisan install:api` |
| `users` table | must exist | Part of default Laravel — likely already there |
| Queue driver | database / redis / sqs | See your existing `QUEUE_CONNECTION` in `.env` |

---

## 2. Check for naming conflicts BEFORE copying

The module creates these tables:

```
tally_connections, tally_ledgers, tally_vouchers, tally_groups,
tally_stock_items, tally_syncs, tally_audit_logs, tally_response_metrics
```

And adds one column:

```
users.tally_permissions  (json, nullable, after remember_token)
```

Quick check:

```bash
php artisan db:table tally_connections  # should error "Table not found"
php artisan tinker
>>> Schema::hasColumn('users', 'tally_permissions');  // should be false
```

If any of these already exist, **stop** and reconcile before proceeding.

Also check route name collisions:

```bash
php artisan route:list | grep -E "^.*tally\."
```

If anything is already named `tally.*`, you'll need to namespace-isolate — contact the module maintainer.

---

## 3. Copy the module

From this repository, copy `Modules/Tally/` into your project's `Modules/` directory.

```bash
# macOS / Linux
cp -R /path/to/TallyIntegration/Modules/Tally /path/to/your-app/Modules/Tally

# Windows PowerShell
Copy-Item "F:\G Drive\Projects\TallyIntegration\Modules\Tally" "C:\path\to\your-app\Modules\Tally" -Recurse
```

Verify `your-app/Modules/Tally/module.json` exists.

---

## 4. Merge PSR-4 autoload entries

Open `your-app/composer.json`. Under `autoload.psr-4`, add (without removing existing keys):

```json
"Modules\\Tally\\": "Modules/Tally/app/",
"Modules\\Tally\\Database\\Factories\\": "Modules/Tally/database/factories/",
"Modules\\Tally\\Database\\Seeders\\": "Modules/Tally/database/seeders/"
```

If you already have a top-level `Modules\\` alias (e.g. `"Modules\\": "Modules/"`), the Tally-specific entries above are still needed — they point `Modules/Tally/app/` at the `Modules\Tally` namespace (the module uses an `app/` subdirectory, not a flat layout).

Then:

```bash
composer dump-autoload
```

---

## 5. Enable the module

```bash
php artisan module:list                 # "Tally" should appear (likely disabled)
php artisan module:enable Tally
php artisan module:list                 # status → enabled
```

If `module:list` doesn't show Tally at all, publish the modules config:

```bash
php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"
```

---

## 6. Run migrations

```bash
php artisan migrate
```

Expected migrations to run (10):

```
2026_04_16_064355_create_tally_connections_table
2026_04_16_085707_add_tally_permissions_to_users_table
2026_04_16_091306_create_tally_audit_logs_table
2026_04_16_091856_create_tally_response_metrics_table
2026_04_16_103825_add_sync_tracking_to_tally_connections_table
2026_04_16_105856_create_tally_ledgers_table
2026_04_16_105856_create_tally_vouchers_table
2026_04_16_105857_create_tally_groups_table
2026_04_16_105857_create_tally_stock_items_table
2026_04_16_110133_create_tally_syncs_table
```

If your production DB is large and you want to preview first:

```bash
php artisan migrate --pretend
```

---

## 7. Add `TALLY_*` to `.env`

Append (don't overwrite existing keys):

```env
TALLY_HOST=your.tally.server
TALLY_PORT=9000
TALLY_COMPANY=
TALLY_TIMEOUT=30
TALLY_LOG_REQUESTS=true
TALLY_CACHE_ENABLED=true
TALLY_CACHE_TTL=300
TALLY_CIRCUIT_BREAKER=true
```

Full reference + optional keys: [CONFIGURATION.md](CONFIGURATION.md).

---

## 8. Optional: publish the module config

Only needed if you want to override defaults project-wide (env vars usually suffice):

```bash
php artisan vendor:publish --tag=tally-config
```

Creates `config/tally.php` in your app. Edit there; the module falls back to its internal copy otherwise.

---

## 9. Set up queue + schedule

The module registers these scheduled tasks (see `Modules/Tally/app/Providers/TallyServiceProvider.php`):

| Cadence | Job |
|---|---|
| every 5 min | `HealthCheckJob` |
| every 5 min | `ProcessConflictsJob` (per active connection) |
| every 10 min | `SyncFromTallyJob` + `SyncToTallyJob` (per active connection) |
| hourly | `SyncAllConnectionsJob` |

If your project already runs `php artisan schedule:run` via cron, they will pick up automatically. Otherwise:

```
* * * * * cd /path/to/your-app && php artisan schedule:run >> /dev/null 2>&1
```

Queue workers:

```bash
php artisan queue:work --queue=default
```

---

## 10. Grant `tally_permissions` to existing users

Run once per user who needs access:

```php
// tinker or a seeder
use App\Models\User;
use Modules\Tally\Enums\TallyPermission;

$admin = User::where('email', 'admin@yourcompany.com')->firstOrFail();
$admin->tally_permissions = [
    TallyPermission::ViewMasters->value,
    TallyPermission::ManageMasters->value,
    TallyPermission::ViewVouchers->value,
    TallyPermission::ManageVouchers->value,
    TallyPermission::ViewReports->value,
    TallyPermission::ManageConnections->value,
];
$admin->save();
```

Users with `tally_permissions = null` (the migration default) will be blocked from every Tally route with `403 Forbidden`.

---

## 11. Verify nothing else broke

```bash
php artisan route:list                  # your existing routes still there?
php artisan config:clear && php artisan cache:clear
php artisan test                        # your existing test suite still passes?
php artisan route:list --path=api/tally # 44 tally routes appear
```

Smoke test:

```bash
TOKEN=$(php artisan tinker --execute='echo App\Models\User::find(1)->createToken("smoke")->plainTextToken;')
curl http://127.0.0.1:8000/api/tally/health -H "Authorization: Bearer $TOKEN"
```

Expected `{ "success": true, "data": { "connected": true, ... } }`.

If `connected: false`, the problem is between Laravel and TallyPrime — see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

---

## 12. Rollback plan

If the install goes wrong and you need to revert cleanly:

```bash
php artisan migrate:rollback --step=10     # rolls back the 10 Tally migrations
php artisan module:disable Tally
# remove the "Modules\\Tally\\..." PSR-4 entries from composer.json
composer dump-autoload
# remove Modules/Tally/ directory
# remove TALLY_* from .env
```

Tables dropped in rollback: all 8 `tally_*` tables + the `users.tally_permissions` column.

---

## Common adjustments

- **Different users table name?** Edit `2026_04_16_085707_add_tally_permissions_to_users_table.php` and `2026_04_16_110133_create_tally_syncs_table.php` (the `resolved_by` FK).
- **No Sanctum?** Replace `auth:sanctum` globally in `Modules/Tally/routes/api.php` with your auth middleware.
- **Custom queue name?** Add `->onQueue('tally')` to the job dispatches in `TallyServiceProvider` and run a dedicated worker.

---

## Next steps

- Configure further: [CONFIGURATION.md](CONFIGURATION.md)
- Register your first connection + create a voucher: [QUICK-START.md](QUICK-START.md)
- Browse the REST API: [API-USAGE.md](API-USAGE.md)
