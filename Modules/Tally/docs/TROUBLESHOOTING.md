# Troubleshooting

Symptom-first guide for the most common problems.

**First-line diagnostics:**

```bash
tail -f storage/logs/tally.log              # per-request Tally log
tail -f storage/logs/laravel.log            # general Laravel errors
php artisan route:list --path=api/tally     # confirm routes loaded
php artisan module:list                     # confirm Tally is enabled
```

---

## 1. Health check fails (`connected: false`)

**Symptom:** `GET /api/tally/health` returns `{ connected: false, error: "..." }`.

**Checks in order:**

1. **Is Tally actually listening?**
   ```bash
   curl -v http://<tally-host>:9000
   ```
   If this hangs or refuses — Tally is not listening. See [TALLY-SETUP.md](TALLY-SETUP.md) → "Enable ODBC / HTTP server".

2. **From the Laravel server specifically?**
   ```bash
   # Run ON the Laravel server, not your workstation
   telnet <tally-host> 9000
   ```
   If reachable from your laptop but not from Laravel — it's firewall / routing.

3. **Env vars match reality?**
   ```bash
   php artisan tinker
   >>> config('tally.host'); config('tally.port');
   ```

4. **Company loaded in Tally?** Gateway of Tally → Select Company. No company = no API responses.

5. **Port conflict?** Some other app (e.g. another Tally instance, Docker) may be bound to 9000. On Windows:
   ```powershell
   netstat -aon | Select-String "9000"
   ```

---

## 2. `Connection refused` / `Could not connect to Tally`

Wrapped as `TallyConnectionException`. Causes, ranked by frequency:

- Tally GUI not running (Standalone).
- Windows Firewall blocking inbound 9000 (Gold). Add rule:
  ```powershell
  New-NetFirewallRule -DisplayName "TallyPrime HTTP" -Direction Inbound `
    -Protocol TCP -LocalPort 9000 -Action Allow
  ```
- Wrong host/port in `tally_connections` row.
- Circuit breaker open after 5 prior failures — see section 8.

---

## 3. `Circuit breaker open`

`503` response with `message: "Tally connection unavailable (circuit open)"`.

**Why:** 5 consecutive failures on that connection code → the breaker opens for 60s, then probes.

**How to reset:** fix the underlying cause (Tally reachable? port right?), then either wait 60s or:

```php
app(\Modules\Tally\Services\CircuitBreaker::class)->recordSuccess('MUM');
```

Temporarily disable entirely: `TALLY_CIRCUIT_BREAKER=false`.

---

## 4. `401 Unauthorized` on every Tally route

Sanctum isn't recognising the token.

- Confirm `Authorization: Bearer {token}` header is being sent (check with `curl -v`).
- Confirm `App\Models\User` uses `Laravel\Sanctum\HasApiTokens`.
- Confirm the token is for the right user & hasn't been revoked (`personal_access_tokens` table).
- `php artisan install:api` never ran → no Sanctum config. See [CONFIGURATION.md](CONFIGURATION.md) → Sanctum.

---

## 5. `403 Forbidden` on a specific route

User is authenticated but missing the required `TallyPermission`.

Check current grants:
```php
User::find(1)->tally_permissions;   // null = no access
```

Grant:
```php
use Modules\Tally\Enums\TallyPermission;
$u->tally_permissions = [TallyPermission::ViewMasters->value, ...];
$u->save();
```

Mapping of routes → required permission is in `.claude/routes-reference.md`.

---

## 6. Tally returns `LINEERROR: ...`

`422` response with the error text. Tally rejected the import payload.

Common causes:

| Error fragment | Cause |
|---|---|
| `Invalid Parent Group` | `PARENT` value doesn't match an existing group exactly (case matters? trailing spaces?) |
| `Unknown LedgerName` | Referenced ledger doesn't exist in the company |
| `Amount is not balanced` | Sum of debits ≠ sum of credits |
| `Date is out of Financial Year` | Voucher date outside current FY (or wrong format) |
| `Duplicate VchNumber` | Voucher number already used and `IMPORTDUPS ≠ @@DUPCOMBINE` |
| `Voucher Type does not exist` | `VOUCHERTYPENAME` value not configured in the company |

Inspect the request XML in `storage/logs/tally.log` — `TallyRequestLogger` records both request and response.

---

## 7. XML rejected before it reaches Tally (`SafeXmlString` fails)

`422` validation error like `"NAME contains unsafe XML characters"`.

Any input containing `<!DOCTYPE`, `<!ENTITY`, `<![CDATA[`, `<?xml`, or Tally envelope tags is rejected up-front. This is intentional — it prevents XML injection.

If a legitimate name contains `<` or `>`, drop those characters client-side; Tally doesn't accept them either.

---

## 8. `migrate` fails on `tally_permissions` column

Your users table schema differs from stock Laravel.

- **Table isn't called `users`?** Edit `2026_04_16_085707_add_tally_permissions_to_users_table.php` (and `create_tally_syncs_table.php` where `resolved_by` FK references `users`).
- **Column already exists?** Drop it or `--pretend` first to see what the migration would do.

---

## 9. Route names collide with existing app routes

`Route [tally.ledgers.index] is already defined.`

If your app already uses the `tally.` name prefix, change it in `Modules/Tally/app/Providers/RouteServiceProvider.php`:

```php
Route::prefix('api/tally')->name('tally.')->middleware('api')->group(/* ... */);
```

Rename consistently — and update `.claude/routes-reference.md` + `Modules/Tally/docs/API-USAGE.md` to match.

---

## 10. Sync jobs not running

**Symptom:** `/sync-stats` always shows the same numbers; `last_synced_at` never updates.

Checklist:

1. Queue worker running? `php artisan queue:work` (or your supervisor/horizon).
2. Cron running scheduler? `* * * * * cd /path && php artisan schedule:run`.
3. Connection `is_active = 1`?
4. Check failed jobs: `php artisan queue:failed`.
5. Manually dispatch to bisect:
   ```bash
   curl -X POST $BASE/connections/1/sync-from-tally -H "Authorization: Bearer $TOKEN"
   # Watch storage/logs/tally.log for activity
   ```

---

## 11. Conflicts not resolving

**Symptom:** `/sync-conflicts` shows rows; they never clear.

Conflicts require **explicit resolution**. `ProcessConflictsJob` only applies a strategy that's already set on the row.

Resolve:
```bash
curl -X POST $BASE/sync/42/resolve -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"strategy":"erp_wins"}'
```

Then wait up to 5 minutes for `ProcessConflictsJob`, or dispatch it inline:
```php
\Modules\Tally\Jobs\ProcessConflictsJob::dispatchSync('MUM');
```

---

## 12. Cache stale after Tally-side changes

`CachesMasterData` TTL defaults to **300s**. If someone edits a ledger directly in Tally, the Laravel-side list can be 5 minutes out of date.

- Reduce TTL: `TALLY_CACHE_TTL=30`.
- Manually flush a specific key from tinker:
  ```php
  cache()->forget('tally:ledger:list');
  ```
- Or disable entirely: `TALLY_CACHE_ENABLED=false`.

---

## 13. Very slow voucher exports / Tally timeouts

For companies with 100K+ vouchers in a date range, Tally itself times out.

Use batched listing:
```php
app(\Modules\Tally\Services\Vouchers\VoucherService::class)
    ->list(VoucherType::Sales, '20250401', '20260331', batchSize: 5000);
```

This splits the range into monthly windows and merges results. Pairs well with `TALLY_TIMEOUT=60` during large syncs.

---

## 14. `nwidart` doesn't see the module

```bash
php artisan module:list   # empty, even though Modules/Tally/ exists
```

- Publish the nwidart config: `php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"`
- Confirm `composer.json` has `Modules\\Tally\\` → `Modules/Tally/app/` and you ran `composer dump-autoload`.
- Confirm `modules_statuses.json` exists at project root and includes `"Tally": true`. If not, `php artisan module:enable Tally`.

---

## 15. Anything else

- Read the relevant request/response pair in `storage/logs/tally.log`.
- Cross-check the XML format against `.docs/Demo Samples/` — canonical truth.
- `php artisan tinker` is your friend for prodding services directly:
  ```php
  app(\Modules\Tally\Services\TallyCompanyService::class)->getAlterIds();
  ```
- Still stuck: capture the request XML, response XML, and the stack trace — that's what a maintainer needs to debug.
