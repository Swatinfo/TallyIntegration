# Modules/Tally/scripts/

Operational scripts for the Tally module. Scripts live **inside the module** so they travel with it — when you copy `Modules/Tally/` into any Laravel project, these come along automatically.

---

## `tally-smoke-test.sh` — end-to-end API exercise

Calls every one of the 44 `/api/tally/*` endpoints against a running Laravel + TallyPrime stack, creates realistic software-company data, and verifies every response. **Probes Tally health before every API call** — aborts cleanly if Tally becomes unreachable mid-run. All activity is logged to `storage/logs/tally/tally-DD-MM-YYYY.log` (one file per day, appended across runs — auto-created).

### Prerequisites

| Tool | Why |
|---|---|
| `bash` | Git Bash on Windows is fine |
| `curl` | HTTP calls |
| `jq` | JSON parsing (hard requirement) — on Git Bash: `scoop install jq` |
| `php` | `php artisan tinker` bootstraps the auth user + token |
| **Laravel running** | `php artisan serve` on `http://127.0.0.1:8000` |
| **Migrations applied** | `php artisan migrate` — 10 Tally tables must exist |
| **TallyPrime reachable** | `curl http://localhost:9000` returns a response |

No user, no token, no connection row needed beforehand — the script bootstraps everything.

### Required Tally setup (one-time) — create "SwatTech Demo" company

**The smoke test refuses to run unless a dedicated company is loaded in Tally.** This is the primary safety barrier — every XML envelope pins requests to this company via `<SVCURRENTCOMPANY>`, so data in any other loaded company is never touched.

Do this once before your first run:

1. Open **TallyPrime**
2. Gateway of Tally → **Alt+F3** → **Create Company**
3. Company Name: **`SwatTech Demo`** (accept defaults for the rest)
4. Gateway of Tally → **F1 (Select Company)** → pick `SwatTech Demo`

Verify from a shell:
```bash
curl -X POST http://localhost:9000 \
  -H "Content-Type: text/xml" \
  --data '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Collection</TYPE><ID>List of Companies</ID></HEADER></ENVELOPE>' \
  | grep -oE '<NAME>[^<]+</NAME>'
```

`SwatTech Demo` should appear in the output.

If you want to use a different name, pass `--company="Your Company Name"` to the script. The same rule applies — it must already be created and loaded.

### Quick run

```bash
bash Modules/Tally/scripts/tally-smoke-test.sh
```

### Flags

| Flag | Default | Meaning |
|---|---|---|
| *(none)* | — | Interactive prompt if demo data exists |
| `--clean` | off | Delete `-DEMO-`-prefixed entities first |
| `--keep` | off | Keep existing data; tolerate `already exists` errors |
| `--phase=<name>` | `all` | Run one phase only: `connections\|mnc\|masters\|groups\|stock-groups\|units\|cost-centres\|currencies\|godowns\|voucher-types\|stock-categories\|price-lists\|cost-categories\|employee-categories\|employee-groups\|employees\|attendance-types\|ledgers\|stock\|vouchers\|inventory-ops\|manufacturing\|banking\|recurring\|workflow\|reports\|sync\|audit\|observability\|integration\|permissions`. `masters` is a meta-phase that runs every master-data phase in dependency order (MNC setup, account groups, stock-side masters, optional F11-gated masters, Phase 9N cost/payroll masters, ledgers, stock items). |
| `--stop-after-phase=<n>` | — | Stop after phase N (`0`, `0a`, `1`..`10`, or a letter-suffixed phase like `2b`, `3j`, `7b`) |
| `--dry-run` | off | Log what would be called; make no HTTP requests |
| `--no-fail-fast` | fail-fast ON | Keep going through every phase even on failures |
| `--conn=<code>` | `DEMO` | Connection code to create/use |
| `--company=<name>` | `SwatTech Demo` | Target Tally company. Must already be loaded in Tally. |
| `--no-bootstrap` | bootstrap ON | Skip tinker; use `$TALLY_API_TOKEN` from env |
| `--no-prune-tokens` | prune ON | Keep smoke-test tokens older than 7 days |
| `--force-conflict` | off | Deliberately create a sync conflict to exercise resolve endpoint |
| `-h`, `--help` | — | Print usage |

### Environment overrides

Either export in your shell or copy `.smoke.env.example` → `Modules/Tally/scripts/.smoke.env` and edit:

```bash
LARAVEL_BASE_URL=https://tallyintegration.test   # default; override for php artisan serve
CURL_INSECURE=1                                   # only if your .test cert isn't trusted
TALLY_HOST=localhost
TALLY_PORT=9000
TALLY_COMPANY="SwatTech Demo"
CONN_CODE=DEMO
SMOKE_USER_EMAIL=smoke-test@local
DEMO_PREFIX=-DEMO-
LOG_DIR=/custom/log/path
TALLY_API_TOKEN=...          # optional — overrides bootstrap
```

`Modules/Tally/scripts/.smoke.env` is gitignored.

### Phases

Execution order: **masters first (all of them), then transactional/related data, then reads + sync + admin, then teardown.**

| # | Name | Block | What it does |
|---|---|---|---|
| 0 | Preflight | setup | `curl`/`jq`/`php` present, Tally reachable, Laravel reachable, module enabled |
| 0a | Auth bootstrap | setup | Creates `smoke-test@local` user, grants all 6 `TallyPermission` values, mints a fresh Sanctum token (named `smoke-test-<timestamp>`) |
| 1 | Cleanup | setup | Optional — delete `-DEMO-`-prefixed entities. Prompts if neither `--clean` nor `--keep` passed |
| 2 | Connections | setup | Seeds a `DEMO` connection row **only if missing**; then list/show/update/test/discover/health/metrics/global-health |
| 2b | MNC setup | **masters** | Organizations / Companies / Branches CRUD (DB-side masters used by consolidated reports) |
| 3 | Groups | **masters** | Account groups — create/list/show/update |
| 3b | Stock groups | **masters** | Phase 9A |
| 3c | Units | **masters** | Phase 9A |
| 3d | Cost centres | **masters** | Phase 9A — optional (F11 Cost Centres) |
| 3e | Currencies | **masters** | Phase 9B — optional (F11 Multi-Currency) |
| 3f | Godowns | **masters** | Phase 9B — optional (F11 Multiple Godowns) |
| 3g | Voucher types | **masters** | Phase 9B — custom types |
| 3h | Stock categories | **masters** | Phase 9F — optional (F11 Stock Categories) |
| 3i | Price lists | **masters** | Phase 9F — optional (F11 Multiple Price Levels) |
| 3j | Cost categories | **masters** | Phase 9N — optional (F11 Cost Categories) |
| 3k | Employee categories | **masters** | Phase 9N — optional (F11 Payroll) |
| 3l | Employee groups | **masters** | Phase 9N — optional (depends on 3k) |
| 3m | Employees | **masters** | Phase 9N — optional (depends on 3k + 3l) |
| 3n | Attendance types | **masters** | Phase 9N — optional (F11 Payroll) |
| 4 | Ledgers | **masters** | Creates ledgers referencing Tally's reserved groups directly; list/search/show/update/delete |
| 5 | Stock items | **masters** | Creates stock items referencing units + stock-groups; list/show/update |
| 6 | Vouchers | **related** | Creates one of every `VoucherType` + batch + GST/IGST/multi-currency scenarios; list/show/alter/cancel/delete |
| 6b | Inventory ops | **related** | Phase 9F — stock transfers, physical stock, SO/PO/DN |
| 6c | Manufacturing | **related** | Phase 9G — BOM, Manufacturing Journal, Job Work In/Out |
| 8b | Banking | **related** | Phase 9D — BRS reports, statement import, auto-match, reconcile/unreconcile |
| 9c | Recurring vouchers | **related** | Phase 9L |
| 9d | Draft workflow | **related** | Phase 9J — draft → submit → reject/approve |
| 7 | Reports | reads | All report types (JSON) + CSV downloads to `storage/smoke-test/` |
| 7b | Consolidated reports | reads | Phase 9K — balance-sheet / P&L / trial-balance at org level (uses MNC hierarchy from phase 2b) |
| 8 | Sync | reads/admin | sync-from-tally, sync-to-tally, sync-full, stats, pending, conflicts, resolve (if any) |
| 9 | Audit | reads | Fetch `/audit-logs` — expect fresh rows from this run |
| 9b | Observability | reads | Phase 9C — stats, search, cache flush, circuit state, sync history/cancel |
| 9e | Integration | admin | Phase 9I — webhooks + test fire + deliveries log + voucher PDF |
| 9f | Permissions | admin | Negative tests — zero-permission user must receive 403 on protected routes |
| 2b-teardown | MNC teardown | teardown | Reverse-cascades DELETE for org/company/branch rows created THIS run (runs after consolidated reports) |
| 10 | Teardown | teardown | Prunes smoke-test tokens older than 7 days |

### Endpoint coverage

All 44 endpoints are hit at least once. The conditional endpoint `POST /sync/{sync}/resolve` is exercised automatically if a conflict is present, or via `--force-conflict`.

### Sample data (all `-DEMO-`-prefixed)

- **Groups:** Software Customers, Cloud Vendors, SaaS Revenue, Consulting Revenue, Cloud Hosting, Dev Tools & Subs, Employee Salaries, Office Operations
- **Ledgers:** Acme Corp, TechNova Pvt Ltd, Global Retail Inc, NorthStar LLC, AWS India, Google Cloud, GitHub, SaaS Subscription, Consulting Fees, AWS Hosting, JetBrains Licenses, Salary - Engineers, Office Rent, HDFC Current A/c, Cash in Hand
- **Stock items:** SKU-PRO Annual, SKU-ENT Annual, Analytics Add-on
- **Vouchers:** 1× each — Sales (×3), Purchase, Payment, Receipt, Journal, Contra, CreditNote, DebitNote

Details: `Modules/Tally/scripts/lib/fixtures.sh`.

### Logging

**Path:** `storage/logs/tally/tally-DD-MM-YYYY.log` (directory + file auto-created).

**Format:**
```
[2026-04-17 14:32:05.123] INFO  === Tally Smoke Test Run Started (PID=12345) ===
[2026-04-17 14:32:05.300] PHASE [0a] Auth bootstrap
[2026-04-17 14:32:05.305] CALL  POST http://127.0.0.1:8000/api/tally/connections
[2026-04-17 14:32:05.312] >>>   {"name":"Demo HQ","code":"DEMO",...}
[2026-04-17 14:32:05.485] <<<   HTTP 200 OK  {"success":true,"data":{"id":1,...}}
[2026-04-17 14:32:05.486] PASS  POST /connections (seed)
...
[2026-04-17 14:35:10.912] SUMMARY total=101 passed=101 failed=0 elapsed=185s status=PASSED
```

One line per event. Request and response bodies truncated at 8 KB.

### Idempotent re-runs (lookup-then-create) — 2026-04-19

Every phase is now safe to re-run. Records are not blindly POSTed; the script first looks them up, reuses existing rows, and creates only on miss.

- **Tally masters** (groups, ledgers, stock items, units, godowns, …) — `_create_many` calls `ensure_tally_master`: GET `/{conn}/{entity}/{name}` first, POST only on 404. After every successful create it RE-FETCHES by name to confirm the row actually landed in Tally — silent-fail creates (caused by missing reference masters in the payload) are caught immediately and logged with the response body excerpt.
- **DB-backed entities** (organizations, companies, branches, webhooks, recurring vouchers) — `ensure_db_entity` filters the list endpoint by `code` / `name` / etc., reuses on hit, POSTs on miss. Tracks `ENSURE_PATH=found|created` so teardown only deletes rows THIS run created.
- **Parent-master prechecks** — before bulk-creating ledgers, stock items, or vouchers, the script verifies every PARENT / BASEUNITS / LEDGERNAME / PARTYLEDGERNAME referenced by the fixtures actually exists in Tally. Missing references are flagged loudly with the list of names — no more silent creates that cascade into "Could not find ledger" import errors downstream.
- **No more phase-level early returns** — when `org_id` / `wh_id` / `rec_id` / `draft_id` come back empty from a duplicate-tolerated POST, the script falls back to a list lookup and continues; sub-steps are never skipped en masse because of one missing id.
- **Failure diagnostics** — `_handle_failure` now prints a 500-char body excerpt plus a `HINT:` line whenever Tally rejects a referenced master (`Could not find …`, `LINEERROR`, etc.).

### Real-world scenario coverage

Beyond the 44 endpoints, the script deliberately exercises common accounting scenarios a real software company runs into:

- **GST-compliant sales** — CGST+SGST split for intra-state, IGST for inter-state (18% standard SaaS rate)
- **Sales with inventory** — selling a stock item (`-DEMO- SKU-PRO Annual` × 2) with an inventory entry line
- **Multi-currency sale** — USD invoice to `-DEMO- Global Retail Inc` with forex rate
- **Purchase with input GST credit** — CGST+SGST captured on the purchase side
- **Receipt / Payment with bill allocation** — tagging the amount to a specific invoice via `BILLALLOCATIONS.LIST`
- **Journal for depreciation** — no-party internal adjustment
- **Ledger regrouping** — moving an account between groups (change `PARENT`)
- **GSTIN + credit-limit update** — `PATCH` partial update
- **HSN code patch** on a stock item — common GST-compliance chore
- **Pagination + sort + multi-term search** on ledger lists
- **Metrics over all 3 periods** — 1h, 24h, 7d
- **CSV exports** — trial balance, profit-and-loss, and ledger statement
- **Audit-log filters** — by action, by object_type, by connection code
- **PATCH variant** on connection, ledger, voucher, stock-item endpoints (not just PUT)

### Coverage gaps (roadmap status)

Items closed by **Phase 9A** (2026-04-17) are now exercised. Remaining gaps are tracked in `.docs/product-roadmap.md`.

| Area | Status |
|---|---|
| Stock Groups CRUD | ✅ **Covered — Phase 9A** (`StockGroupController`) |
| Units CRUD | ✅ **Covered — Phase 9A** (`UnitController`) |
| Cost Centres CRUD | ✅ **Covered — Phase 9A** (`CostCenterController`) |
| Batch voucher create | ✅ **Covered — Phase 9A** (`POST /{c}/vouchers/batch`) |
| Companies list endpoint | ✅ **Covered — Phase 9A** (`GET /connections/{id}/companies`) |
| Permission negative tests (403) | ✅ **Covered** — `phase_9f_permissions` creates a zero-permission user and asserts 403 on protected routes |
| Currencies / Godowns / Voucher Types CRUD | ✅ **Covered — Phase 9B** |
| Additional reports (Cash Book / Sales Register / Purchase Register / Aging / Cash Flow / Funds Flow / Receipts & Payments / Stock Movement) | ✅ **Covered — Phase 9B** |
| Observability (stats / search / cache flush / circuit state / audit detail+CSV / sync history / sync-show / sync-cancel / bulk resolve) | ✅ **Covered — Phase 9C** |
| Recurring vouchers (scheduled auto-post, daily/weekly/monthly/quarterly/yearly) | ✅ **Covered — Phase 9L** |
| Draft voucher workflow (maker-checker, approval thresholds, rejection reasons) | ✅ **Covered — Phase 9J** |
| Manufacturing (BOM + Manufacturing Journal + Job Work In/Out) | ✅ **Covered — Phase 9G** |
| MNC hierarchy (Organizations / Companies / Branches) + consolidated reports | ✅ **Covered — Phase 9Z + 9K** |
| Integration glue (webhooks + CSV import + attachments + PDF + email) | ✅ **Covered — Phase 9I (mpdf)** |
| GSTR-1/3B / E-Invoice / E-Way Bill / TDS | **Phase 9E** — pending, external GSP dep |
| BRS / Cheque register / Post-dated cheques / Bank feed CSV import / Auto-match | ✅ **Covered — Phase 9D** |
| Sales Order / PO / Delivery Note / GRN / Stock Transfer / Physical Stock / Price Lists / Stock Categories | ✅ **Covered — Phase 9F** |
| CSV import / Webhooks / PDF / Email / Attachments | **Phase 9I** — pending |
| Manufacturing / Payroll / Multi-company consolidation | **Phase 9G/H/K** — pending |

### Tiered rate limiting

The script's bootstrapped token is named `smoke-test-<timestamp>`. Token names with the prefixes `smoke-test-` / `internal-` / `system-` land in the **internal tier** (6000/min writes, effectively uncapped), so running the full 165-endpoint smoke test does not trip the Laravel rate limiter.

If you override the token via `TALLY_API_TOKEN` env var (bypass bootstrap), **name your token with one of those prefixes** or you'll hit 429. Full tier table: `Modules/Tally/docs/CONFIGURATION.md` § Rate limiting.

### Per-call health probe

Before **every** API call to Laravel, the script first probes TallyPrime directly (`curl http://host:port`) to confirm Tally is still up. This is independent of Laravel — it guarantees cascading failures are caught immediately rather than showing up as HTTP 5xx noise.

- **On success:** silent (keeps the log tidy). Probe count rolls up in the final summary.
- **On failure:** aborts with exit code `11`, skipping the rest of the run.
- **Timeout:** 3 seconds (override with `HEALTH_PROBE_TIMEOUT` env var).
- **Dry-run:** skipped.

### Exit codes

| Code | Meaning |
|---|---|
| 0 | All phases passed |
| 1 | Preflight failure (missing tool, Tally unreachable, Laravel down, target company not loaded) |
| 2 | Auth bootstrap failure (tinker error, token parse error) |
| 10 | First API failure (with `--fail-fast` — the default) |
| 10 + N | With `--no-fail-fast`, N = number of failed API calls (capped at 255) |
| 11 | Mid-run health probe to Tally failed (Tally went down during the run) |

### Examples

```bash
# Standard run (prompts if demo data exists)
bash Modules/Tally/scripts/tally-smoke-test.sh

# Full automated run, always wiping demo data first
bash Modules/Tally/scripts/tally-smoke-test.sh --clean

# Just check the masters pipeline
bash Modules/Tally/scripts/tally-smoke-test.sh --phase=masters

# Dry-run to preview every URL + payload the script would send
bash Modules/Tally/scripts/tally-smoke-test.sh --dry-run

# Keep going even on failures — useful when debugging many broken endpoints
bash Modules/Tally/scripts/tally-smoke-test.sh --no-fail-fast --clean

# Use an externally-supplied token instead of minting one
TALLY_API_TOKEN="1|yourtoken..." bash Modules/Tally/scripts/tally-smoke-test.sh --no-bootstrap
```

### Safety guarantees

- Only deletes entities whose names start with `-DEMO-`.
- Never drops the Tally company.
- Never truncates Laravel tables.
- Never touches default Tally ledgers (Cash, P&L A/c, etc.).
- `smoke-test@local` is a dedicated user — real admin accounts are never used.

### Troubleshooting

Symptom-first playbook: `Modules/Tally/docs/TROUBLESHOOTING.md` § "Smoke test fails".

Quick first checks:
- Preflight fails → missing tool, Tally off, or Laravel not running. Error message points at the problem.
- Bootstrap fails → your app's `User` model probably doesn't use `HasApiTokens`. See `Modules/Tally/docs/CONFIGURATION.md` § Sanctum.
- Every API returns 403 → the smoke-test user wasn't granted `tally_permissions`. Re-run — bootstrap is idempotent.
