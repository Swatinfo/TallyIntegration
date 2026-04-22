# Quick Start (10 minutes)

Goal: go from a fresh install to **creating a ledger and a sales voucher** in TallyPrime via the REST API.

## Fastest path — 2 commands

```bash
# 1. Create "SwatTech Demo" in TallyPrime: File → Create Company → name it exactly "SwatTech Demo"
# 2. Seed the sandbox (idempotent, safe to re-run):
php artisan tally:demo seed
```

This creates the demo user (`demo@tally.test`), the `DEMO` connection row, prints a Sanctum token,
and populates 14 ledgers, 4 stock items, 5 groups, and 18 vouchers (one per voucher type) inside
`SwatTech Demo` in Tally. Then:

```bash
# Run full end-to-end test of every module capability:
php artisan tally:demo test

# Or hit every API endpoint via curl (uses the persisted token):
./scripts/tally-smoke-test.sh
```

Read on for the manual step-by-step walkthrough if you prefer.

---

**Prerequisites:** you've completed [INSTALLATION-FRESH.md](INSTALLATION-FRESH.md) or [INSTALLATION-EXISTING.md](INSTALLATION-EXISTING.md), and [TALLY-SETUP.md](TALLY-SETUP.md). Laravel is running on `127.0.0.1:8000`. TallyPrime is reachable at `localhost:9000`. You have a Sanctum token.

```bash
TOKEN="your-sanctum-token"
BASE="http://127.0.0.1:8000/api/tally"
```

---

## Step 1 — Confirm Tally is reachable

```bash
curl $BASE/health -H "Authorization: Bearer $TOKEN"
```

Expect:
```json
{
  "success": true,
  "data": { "connected": true, "url": "http://localhost:9000", "companies": ["ABC Enterprises"] },
  "message": "Tally is reachable"
}
```

If `connected: false` → [TROUBLESHOOTING.md](TROUBLESHOOTING.md) → section "Health check fails".

---

## Step 2 — Register a connection

Each real Tally instance becomes a row in `tally_connections` with a short **code** (e.g. `MUM`) used in subsequent URLs.

```bash
curl -X POST $BASE/connections \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mumbai HQ",
    "code": "MUM",
    "host": "localhost",
    "port": 9000,
    "company_name": "ABC Enterprises",
    "timeout": 30,
    "is_active": true
  }'
```

Response:
```json
{
  "success": true,
  "data": { "id": 1, "code": "MUM", ... },
  "message": "Connection created"
}
```

---

## Step 3 — Verify the connection

```bash
curl $BASE/connections/1/health -H "Authorization: Bearer $TOKEN"
```

And list loaded companies through that specific connection:

```bash
curl -X POST $BASE/connections/1/discover -H "Authorization: Bearer $TOKEN"
```

---

## Step 4 — List existing ledgers

```bash
curl "$BASE/MUM/ledgers?per_page=20" -H "Authorization: Bearer $TOKEN"
```

Paginated response:
```json
{
  "success": true,
  "data": [
    { "NAME": "Cash", "PARENT": "Cash-in-hand", "CLOSINGBALANCE": "18352572.24" },
    { "NAME": "SBI Current A/c", "PARENT": "Bank Accounts", ... }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 143, "last_page": 8 }
}
```

Look up a single ledger by name:

```bash
curl "$BASE/MUM/ledgers/Cash" -H "Authorization: Bearer $TOKEN"
```

---

## Step 5 — Create a customer ledger

```bash
curl -X POST $BASE/MUM/ledgers \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "NAME": "Customer ABC",
    "PARENT": "Sundry Debtors",
    "OPENINGBALANCE": "0",
    "EMAIL": "abc@example.com",
    "LEDGERPHONE": "+91-9999999999"
  }'
```

Successful response carries the Tally import result:
```json
{
  "success": true,
  "data": { "created": 1, "altered": 0, "errors": 0, "lastmid": "1503", ... },
  "message": "Ledger created"
}
```

If `errors > 0`, inspect `data.errors` for the `LINEERROR` messages from Tally.

---

## Step 6 — Create a sales voucher

```bash
curl -X POST $BASE/MUM/vouchers \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416",
      "VOUCHERTYPENAME": "Sales",
      "VOUCHERNUMBER": "INV-001",
      "PARTYLEDGERNAME": "Customer ABC",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Customer ABC",  "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "10000.00" },
        { "LEDGERNAME": "Sales Account", "ISDEEMEDPOSITIVE": "No",  "AMOUNT": "-10000.00" }
      ]
    }
  }'
```

Response:
```json
{
  "success": true,
  "data": { "created": 1, "errors": 0, "lastvchid": "1306", ... },
  "message": "Voucher created"
}
```

### Prefer a simpler call

Use the PHP helper in your own code — same result, much less JSON:

```php
use Modules\Tally\Services\Vouchers\VoucherService;

$result = app(VoucherService::class)->createSales(
    date: '20260416',
    partyLedger: 'Customer ABC',
    salesLedger: 'Sales Account',
    amount: 10000.00,
    voucherNumber: 'INV-001',
    narration: 'Invoice INV-001',
);
```

---

## Step 7 — Fetch a report

Balance sheet as JSON:
```bash
curl "$BASE/MUM/reports/balance-sheet?date=20260331" \
  -H "Authorization: Bearer $TOKEN"
```

Profit & Loss as CSV:
```bash
curl "$BASE/MUM/reports/profit-and-loss?from=20250401&to=20260331&format=csv" \
  -H "Authorization: Bearer $TOKEN" \
  -o pnl.csv
```

---

## Step 8 — Trigger a sync

Pull the latest masters from Tally into the local mirror tables (`tally_ledgers`, `tally_groups`, `tally_stock_items`):

```bash
curl -X POST $BASE/connections/1/sync-from-tally -H "Authorization: Bearer $TOKEN"
```

Push pending local changes up to Tally:
```bash
curl -X POST $BASE/connections/1/sync-to-tally -H "Authorization: Bearer $TOKEN"
```

Watch progress:
```bash
curl $BASE/connections/1/sync-stats   -H "Authorization: Bearer $TOKEN"
curl $BASE/connections/1/sync-pending -H "Authorization: Bearer $TOKEN"
```

---

## Step 9 — Inspect the audit log

Every create/alter/delete/cancel is logged:

```bash
curl "$BASE/audit-logs?per_page=10" -H "Authorization: Bearer $TOKEN"
```

Filter by action or object type:

```bash
curl "$BASE/audit-logs?action=create&object_type=VOUCHER" -H "Authorization: Bearer $TOKEN"
```

---

## What just happened under the hood

1. Laravel received your JSON and validated it through a Form Request (`StoreLedgerRequest`, `StoreVoucherRequest`, …).
2. `ResolveTallyConnection` middleware looked up `MUM` in `tally_connections` and rebound a `TallyHttpClient` in the container.
3. `LedgerService` (or `VoucherService`) called `TallyXmlBuilder::buildImportMasterRequest()` / `buildImportVoucherRequest()`.
4. `TallyHttpClient` POSTed the XML to `http://localhost:9000`.
5. Tally replied with `<IMPORTRESULT>…`; `TallyXmlParser::parseImportResult()` converted it to an array.
6. `AuditLogger` wrote a row to `tally_audit_logs`; an event (`TallyMasterCreated` / `TallyVoucherCreated`) was dispatched.
7. Controller wrapped the result in `{ success, data, message }` and returned.

---

## Shortcut — run the smoke test

If you'd rather verify everything at once instead of stepping through manually:

```bash
bash Modules/Tally/scripts/tally-smoke-test.sh
```

Creates a user + token automatically, runs through every endpoint, logs to `storage/logs/tally/tally-DD-MM-YYYY.log`. See `Modules/Tally/scripts/README.md`.

**One-time prerequisite:** create a dedicated Tally company named `SwatTech Demo` (Alt+F3 → Create Company). The smoke test refuses to run otherwise. Every request is pinned to that company — data in any other loaded company is never touched. Detail: [TALLY-SETUP.md § 6b](TALLY-SETUP.md).

## Next

- Call every endpoint: [API-USAGE.md](API-USAGE.md)
- Tune queues, caching, rate limits: [CONFIGURATION.md](CONFIGURATION.md)
- Something broke: [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
