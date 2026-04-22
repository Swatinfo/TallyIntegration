# Troubleshooting

Symptom-first guide for the most common problems.

**First-line diagnostics:**

```bash
tail -f storage/logs/tally/tally-$(date +%d-%m-%Y).log   # per-request Tally log (auto-created)
tail -f storage/logs/laravel.log                         # general Laravel errors
php artisan route:list --path=api/tally                  # confirm routes loaded
php artisan module:list                                  # confirm Tally is enabled
php artisan tally:demo status                            # show demo sandbox state
```

---

## tally:demo fails with "Company 'SwatTech Demo' not found in Tally"

**Cause:** the seeder verifies the demo company is loaded in TallyPrime before doing anything. Companies can only be created inside the Tally GUI — the XML API cannot create them.

**Fix:** Open TallyPrime → **File → Create Company** → name it **exactly** `SwatTech Demo` (case-sensitive, no trailing spaces). Then re-run `php artisan tally:demo seed`.

---

## tally:demo fails with "Safety guard tripped"

**Cause:** `DemoGuard` or `DemoEnvironment` refused to proceed — one of:
- The `DEMO` connection row's `company_name` is not exactly `SwatTech Demo`.
- An XML about to be sent is missing the `<SVCURRENTCOMPANY>SwatTech Demo</SVCURRENTCOMPANY>` tag.
- A `Delete`/`Cancel` action targeted an entity whose name/number doesn't carry the demo prefix.

**Fix:** the error message names the exact check that failed. In practice it means the DB state drifted from what the seeder expects — run `php artisan tally:demo fresh --execute` (dry-run first) to wipe and rebuild.

---

## Smoke test `scripts/tally-smoke-test.sh` fails "token file not found"

**Cause:** you haven't run `php artisan tally:demo seed` yet (or you ran `tally:demo reset --execute`, which cleared the vault).

**Fix:** `php artisan tally:demo seed` — writes the plaintext Sanctum token to `storage/app/tally-demo/token.txt`. The script reads it on every run.

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

## 15. Smoke test fails

`Modules/Tally/scripts/tally-smoke-test.sh` fails. Narrow down by phase — the script prints and logs the phase heading before each step.

| Failing phase | Likely cause |
|---|---|
| `[PHASE 0]` Preflight | Missing `curl`/`jq`/`php`, Tally off, Laravel not serving, or module not enabled. Error message names which one. |
| `[PHASE 0]` Target company not loaded | The smoke test refuses to run unless `SwatTech Demo` (or `--company=<name>`) is a loaded company in Tally. Open TallyPrime → Alt+F3 → Create Company → `SwatTech Demo`, then F1 to select it. See [TALLY-SETUP.md § 6b](TALLY-SETUP.md). |
| `[PHASE 2]` Existing connection targets wrong company | A `DEMO` connection row already exists but points at a different company. Either pass `--conn=SAFE` to create a fresh row or delete the existing one. The script refuses to silently write into the wrong company. |
| Mid-run exit code **11** | Pre-call health probe to Tally failed — TallyPrime stopped responding during the run. Check Tally didn't close, the company didn't get unloaded, and the network is stable. Re-run; probes happen before every API call. |
| `[PHASE 0a]` Auth bootstrap | `User` model missing `HasApiTokens` trait, `users` table missing `tally_permissions` column (migration not run), or tinker crashed. Run `php artisan migrate` and verify `App\Models\User` uses `Laravel\Sanctum\HasApiTokens`. |
| `[PHASE 1]` Cleanup | Deletes are best-effort; real failures here are rare. If it hangs, a voucher is blocking — check Tally GUI. |
| `[PHASE 2]` Connections | `auth:sanctum` rejecting the token, or DB doesn't have the `tally_connections` table (migrations not run). |
| `[PHASE 3-5]` Masters | `Invalid Parent Group` — the `PARENT` on a DEMO ledger references a group that doesn't exist in Tally yet. Ensure Group phase ran first. Or the target Tally company isn't the one Tally is actively showing. |
| `[PHASE 6]` Vouchers | `Unknown LedgerName` — the ledger phase didn't run or failed. Re-run with `--phase=all --clean`. |
| `[PHASE 7]` Reports | Usually timeout — increase `REQUEST_TIMEOUT` env var. |
| `[PHASE 8]` Sync | Jobs dispatched but queue worker isn't running. Start `php artisan queue:work`. |

Quick diagnostics:

```bash
# See where the last run broke
tail -n 50 storage/logs/tally/tally-$(date +%d-%m-%Y).log

# Re-run just the phase that failed
bash Modules/Tally/scripts/tally-smoke-test.sh --phase=vouchers --keep

# Keep going through every phase to see the full failure surface
bash Modules/Tally/scripts/tally-smoke-test.sh --no-fail-fast
```

---

## 16. HTTP 429 "Too Many Attempts"

Laravel's rate limiter fired. Rate limits are **tiered by Sanctum token name prefix** — the token you're using lands in the wrong tier.

| Your token name starts with… | Tier | tally-api | tally-write | tally-reports |
|---|---|---|---|---|
| `smoke-test-*`, `internal-*`, `system-*` | internal | 6000/min | 6000/min | 600/min |
| `batch-*`, `sync-*` | batch | 1200/min | 600/min | 120/min |
| anything else | standard | 120/min | 60/min | 20/min |

**Fixes (in order of preference):**

1. **Name your token to match your use case** — CSV bulk import → `batch-month-end`, server-to-server cron → `internal-cron-sync`. See `CONFIGURATION.md` § Rate limiting.
2. **Tune the caps** — edit the `LIMITS` constant in `app/Providers/AppServiceProvider.php`.
3. **Add per-connection room** — the limiter already keys by `{connection}` when present, so each Tally instance gets its own bucket. Spreading load across connections helps automatically.

The circuit breaker is a separate concern — see § 3.

---

## 17. SSL / TLS errors on `.test` domain (`curl: (60) SSL certificate problem`)

Your local cert isn't trusted by curl. Confirm with:

```bash
curl -sk -o /dev/null -w "%{http_code}\n" https://tallyintegration.test   # works?
```

If `200`, the fix is to set `CURL_INSECURE=1` for the smoke test:

```bash
echo 'CURL_INSECURE=1' > Modules/Tally/scripts/.smoke.env
```

Or pass inline: `CURL_INSECURE=1 bash Modules/Tally/scripts/tally-smoke-test.sh`.

Production fix: install Herd/Valet's root CA into the system trust store so curl accepts it natively.

---

## 18. "Record already exists" warnings during smoke test

The smoke test tolerates any "already exists" / "has already been taken" / "duplicate entry" response automatically — no action needed. These show as yellow warnings (`!`) with label *"tolerated — record already exists, skipped"*, count as PASS, and don't stop the run.

If you want a clean slate: `bash Modules/Tally/scripts/tally-smoke-test.sh --clean` wipes `-DEMO-`-prefixed entities first.

---

## 19. mpdf errors — `tempDir` or font issues

Voucher PDF rendering (Phase 9I) uses mpdf with temp dir `storage/app/mpdf-tmp/`. If you see `Could not open file` or `temp directory not writable`:

```bash
mkdir -p storage/app/mpdf-tmp
chmod -R 775 storage/app/mpdf-tmp
```

For font errors (`font not found`), mpdf bundles its own font set and defaults to `dejavusans` in `PdfService`. If you customise fonts, install the font files into `vendor/mpdf/mpdf/src/Mpdf/ttfonts/`.

---

## 20. TallyPrime crashes (memory-access violation) on master list endpoints

**Symptom:** TallyPrime shows a Windows "access violation" dialog and stops responding; subsequent calls return `HTTP 503` with `Cannot connect to TallyPrime` / `cURL 56: Recv failure`.

**Cause:** `EXPLODEFLAG=Yes` on a Collection export that contains rows referencing other rows by name causes Tally to recursively inline those references. Deep or circular chains (compound units, stock items with multi-unit conversions, nested voucher-type parents) overflow Tally's internal buffer.

**Fix:** The following list endpoints pin `EXPLODEFLAG=No` and pass an explicit `FETCHLIST`:
- `UnitService::list()` → `NAME, SYMBOL, ISSIMPLEUNIT, BASEUNITS, CONVERSION, ADDITIONALUNITS`
- `StockItemService::list()` → `NAME, PARENT, CATEGORY, BASEUNITS, ADDITIONALUNITS`
- `StockGroupService::list()` → `NAME, PARENT, BASEUNITS`
- `VoucherTypeService::list()` → `NAME, PARENT, NUMBERINGMETHOD`

If you add a new master list endpoint and its rows reference other rows by name, keep `explode: false` on the builder call and declare a minimal `fetchFields`.

**After a crash:** Relaunch TallyPrime manually and re-open the target company. The module cannot auto-recover because the Tally process is dead.

---

## 21. Master response fields come back empty (PARENT, ISREVENUE, BASEUNITS…)

**Symptom:** Clients see `{"PARENT":{"@attributes":{"TYPE":"String"}}}` without the actual value. Affected every master list / detail response.

**Cause:** Tally stamps `TYPE="String"` / `TYPE="Logical"` on leaf elements. `TallyXmlParser::xmlToArray()` previously dropped text content when an element also had attributes.

**Fix:** The parser now preserves the text under a `#text` key, so `PARENT` returns as:
```json
{"@attributes": {"TYPE": "String"}, "#text": "Primary"}
```
If your integration reads these fields via a string cast on the array, update it to `$row['PARENT']['#text'] ?? $row['PARENT']` (the second branch catches the rare case where an element has only text).

---

## 22. TallyPrime resets the connection on `GET /{conn}/{entity}/{name}`

**Symptom:** Object exports (single-entity fetch) hang for the full timeout then fail with `cURL 56: Recv failure`. The collection list endpoint works.

**Cause:** Observed on some Tally builds when `SVEXPORTFORMAT=BinaryXML` is combined with specific subtypes (notably Group).

**Workaround:** Flip to plain XML:
```
TALLY_OBJECT_EXPORT_FORMAT=SysName
```
This switches Object exports to `<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>`. Keep `BinaryXML` (the default) unless you actually hit the crash — plain XML responses are larger.

---

## 23. URL path param rejected with HTTP 422 "dangerous XML content"

**Symptom:** `GET /{conn}/ledgers/<something>` returns `{"success": false, "message": "The name path parameter contains potentially dangerous XML content."}`.

**Cause:** `GuardTallyPathParams` middleware matched an XML-envelope token (`<!DOCTYPE`, `<ENVELOPE`, `<?xml`, `<TALLYMESSAGE`, `<![CDATA[`, etc.) in the `{name}`, `{masterID}` or `{type}` path param. This mirrors the `SafeXmlString` rule that guards POST bodies.

**Fix:** URL-encode master names normally (`Test%20Company`, `Cash%20%26%20Bank`). If a legitimate name contains a matched substring, the workaround is to rename the master in Tally — the guard is intentionally strict because these values are embedded directly in outbound XML.

---

## 24. Master import returns success:false with "Failed to create" but no specific error

**Symptoms:** A master CRUD endpoint returns HTTP 422 with body like:
```json
{"success":false,"data":{"created":0,"errors":1,"exceptions":1,"line_error":null},"message":"Failed to create currency"}
```

`exceptions=1` with `line_error=null` means TallyPrime swallowed the import silently. The most common cause is that the master depends on a **TallyPrime company feature flag that is OFF**.

**Fix — enable the relevant feature in TallyPrime:**

| Master type | F11 feature to enable |
|---|---|
| Currency | Accounting Features → Multi-Currency |
| Cost Centre | Accounting Features → Cost Centres / More than ONE Cost Category |
| Godown | Inventory Features → Multiple Godowns/Locations |
| Stock Category | Inventory Features → Stock Categories |
| Price Level | Inventory Features → Multiple Price Levels |
| GST ledger / output / input tax | Statutory & Taxation → Goods and Services Tax (GST) |

How to enable:
1. In TallyPrime → Gateway of Tally → press **F11** (Features)
2. Pick the appropriate features section (Accounting / Inventory / Statutory)
3. Toggle the relevant flag to **Yes**
4. Save (Ctrl+A) and re-run the import

The smoke test treats currencies / cost-centres / godowns / stock-categories / price-lists as **optional masters**. The harness LOOKS UP each name first via the safe inline-TDL collection; if absent, it **skips the POST entirely** because TallyPrime has been observed to hard-crash (not just fail gracefully) on a feature-disabled import. Skipped optional masters count as a PASS in the summary.

Re-run after enabling the feature in F11 to actually exercise the create/show/list endpoints. Until then the smoke test will continue past the optional phases without touching Tally's import handler.

---

## 25. Anything else

- Read the relevant request/response pair in `storage/logs/tally.log`.
- Cross-check the XML format against `.docs/Demo Samples/` — canonical truth.
- `php artisan tinker` is your friend for prodding services directly:
  ```php
  app(\Modules\Tally\Services\TallyCompanyService::class)->getAlterIds();
  ```
- Still stuck: capture the request XML, response XML, and the stack trace — that's what a maintainer needs to debug.
