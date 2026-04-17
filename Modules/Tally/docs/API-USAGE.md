# API Usage

Every REST endpoint with a curl example and a PHP service-layer equivalent. For the XML envelopes these calls produce, see `.docs/tally-api-reference.md`.

**All routes:** prefix `/api/tally/`, require `Authorization: Bearer {sanctum-token}`, return `{ success, data, message }`.

Throughout this doc: `$TOKEN` is your Sanctum token, `$BASE = http://127.0.0.1:8000/api/tally`, `{conn}` is a connection code (e.g. `MUM`).

---

## 1. Health

### Global health (uses `.env` defaults)

```bash
curl $BASE/health -H "Authorization: Bearer $TOKEN"
```

### Per-connection health

```bash
curl $BASE/{conn}/health -H "Authorization: Bearer $TOKEN"
# or by id:
curl $BASE/connections/1/health -H "Authorization: Bearer $TOKEN"
```

PHP:
```php
use Modules\Tally\Services\TallyHttpClient;
$ok = app(TallyHttpClient::class)->isConnected();
```

---

## 2. Connection management

### List

```bash
curl $BASE/connections -H "Authorization: Bearer $TOKEN"
```

### Create

```bash
curl -X POST $BASE/connections -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mumbai HQ",
    "code": "MUM",
    "host": "192.168.1.10",
    "port": 9000,
    "company_name": "ABC Enterprises"
  }'
```

### Show / Update / Delete

```bash
curl $BASE/connections/1 -H "Authorization: Bearer $TOKEN"
curl -X PUT $BASE/connections/1 -H "Authorization: Bearer $TOKEN" -d '{"timeout":45}'
curl -X DELETE $BASE/connections/1 -H "Authorization: Bearer $TOKEN"
```

### Discover companies

```bash
curl -X POST $BASE/connections/1/discover -H "Authorization: Bearer $TOKEN"
```

### Test ad-hoc (without saving)

```bash
curl -X POST $BASE/connections/test \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"host":"tally-server","port":9000,"timeout":10}'
```

### Metrics

```bash
curl "$BASE/connections/1/metrics?period=24h" -H "Authorization: Bearer $TOKEN"
# period ∈ 1h, 24h, 7d
```

PHP:
```php
use Modules\Tally\Services\MetricsCollector;
$stats = app(MetricsCollector::class)->getStats($connectionId, '24h');
// { total, avg_ms, p95_ms, max_ms, error_rate }
```

---

## 3. Ledgers (per connection)

### List

```bash
curl "$BASE/{conn}/ledgers?per_page=50&search=Cash&sort_by=NAME" \
  -H "Authorization: Bearer $TOKEN"
```

### Show

```bash
curl $BASE/{conn}/ledgers/Cash -H "Authorization: Bearer $TOKEN"
# URL-encode if name has spaces: ledgers/Sundry%20Debtors
```

### Create

```bash
curl -X POST $BASE/{conn}/ledgers -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "NAME": "Customer ABC",
    "PARENT": "Sundry Debtors",
    "OPENINGBALANCE": "0",
    "EMAIL": "abc@example.com",
    "PARTYGSTIN": "27ABCDE1234F1Z5"
  }'
```

PHP:
```php
app(\Modules\Tally\Services\Masters\LedgerService::class)->create([
    'NAME' => 'Customer ABC', 'PARENT' => 'Sundry Debtors',
]);
```

### Update (Alter)

```bash
curl -X PUT $BASE/{conn}/ledgers/Customer%20ABC \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"EMAIL":"new@example.com"}'
```

### Delete

```bash
curl -X DELETE $BASE/{conn}/ledgers/Customer%20ABC \
  -H "Authorization: Bearer $TOKEN"
```

---

## 4. Groups (per connection)

Same shape as ledgers. Routes: `GET/POST /{conn}/groups`, `GET/PUT/DELETE /{conn}/groups/{name}`.

```bash
curl -X POST $BASE/{conn}/groups -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"Export Customers","PARENT":"Sundry Debtors"}'
```

PHP: `app(\Modules\Tally\Services\Masters\GroupService::class)`.

---

## 5. Stock items (per connection)

Routes: `GET/POST /{conn}/stock-items`, `GET/PUT/DELETE /{conn}/stock-items/{name}`.

```bash
curl -X POST $BASE/{conn}/stock-items -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "NAME": "Widget Pro",
    "PARENT": "Finished Goods",
    "BASEUNITS": "Nos",
    "OPENINGBALANCE": "100 Nos",
    "OPENINGRATE": "250/Nos",
    "HASBATCHES": "No"
  }'
```

PHP: `app(\Modules\Tally\Services\Masters\StockItemService::class)`.

---

## 6. Vouchers (per connection)

### List

```bash
curl "$BASE/{conn}/vouchers?type=Sales&from_date=20260101&to_date=20261231" \
  -H "Authorization: Bearer $TOKEN"
```

`type` ∈ `Sales`, `Purchase`, `Payment`, `Receipt`, `Journal`, `Contra`, `CreditNote`, `DebitNote`.

### Show

```bash
curl $BASE/{conn}/vouchers/1305 -H "Authorization: Bearer $TOKEN"
```

### Create

```bash
curl -X POST $BASE/{conn}/vouchers -H "Authorization: Bearer $TOKEN" \
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

PHP helper (no XML/data juggling):
```php
use Modules\Tally\Services\Vouchers\VoucherService;

app(VoucherService::class)->createSales(
    date: '20260416',
    partyLedger: 'Customer ABC',
    salesLedger: 'Sales Account',
    amount: 10000.00,
    voucherNumber: 'INV-001',
);
```

### Update (Alter)

```bash
curl -X PUT $BASE/{conn}/vouchers/1305 -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type":"Sales","data":{"NARRATION":"Corrected"}}'
```

### Cancel

```bash
curl -X DELETE $BASE/{conn}/vouchers/1305 -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "date": "16-Apr-2026",
    "voucher_number": "INV-001",
    "action": "cancel",
    "narration": "Cancelled due to incorrect amount"
  }'
```

### Delete

```bash
curl -X DELETE $BASE/{conn}/vouchers/1305 -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "date": "16-Apr-2026",
    "voucher_number": "INV-001",
    "action": "delete"
  }'
```

### Batch create (PHP only)

```php
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

app(VoucherService::class)->createBatch(VoucherType::Sales, $vouchers);
```

---

## 7. Reports

```bash
curl "$BASE/{conn}/reports/balance-sheet?date=20260331"       -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/profit-and-loss?from=20250401&to=20260331" -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/trial-balance?date=20260331"       -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/ledger?ledger=Cash&from=20250401&to=20260331" -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/outstandings?type=receivable"      -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/outstandings?type=payable"         -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/stock-summary"                     -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/day-book?date=20260416"            -H "Authorization: Bearer $TOKEN"
```

### CSV export

Add `?format=csv` or `Accept: text/csv`:

```bash
curl "$BASE/{conn}/reports/balance-sheet?date=20260331&format=csv" \
  -H "Authorization: Bearer $TOKEN" -o bs.csv
```

PHP:
```php
app(\Modules\Tally\Services\Reports\ReportService::class)->balanceSheet('20260331');
```

---

## 8. Sync

### Stats

```bash
curl $BASE/connections/1/sync-stats -H "Authorization: Bearer $TOKEN"
# { pending: 12, in_progress: 0, completed: 4508, failed: 2, conflict: 1 }
```

### Pending (paginated)

```bash
curl "$BASE/connections/1/sync-pending?limit=50" -H "Authorization: Bearer $TOKEN"
```

### Conflicts

```bash
curl $BASE/connections/1/sync-conflicts -H "Authorization: Bearer $TOKEN"
```

### Resolve a conflict

```bash
curl -X POST $BASE/sync/42/resolve -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"strategy":"erp_wins"}'
```

`strategy` ∈ `erp_wins`, `tally_wins`, `newest_wins`, `merge`, `manual`.

### Trigger sync

```bash
curl -X POST $BASE/connections/1/sync-from-tally -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/connections/1/sync-to-tally   -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/connections/1/sync-full       -H "Authorization: Bearer $TOKEN"
```

---

## 9. Audit logs

```bash
curl "$BASE/audit-logs?per_page=50" -H "Authorization: Bearer $TOKEN"
```

Filters: `connection=MUM`, `action=create|alter|delete|cancel`, `object_type=LEDGER|GROUP|STOCKITEM|VOUCHER`, `user_id=1`.

---

## Error response format

```json
{
  "success": false,
  "data": null,
  "message": "Tally returned LINEERROR: Invalid Parent Group",
  "errors": ["Invalid Parent Group"]
}
```

| HTTP status | When |
|---|---|
| 401 | Missing / invalid Sanctum token |
| 403 | User lacks `tally_permissions` |
| 404 | Unknown connection code, missing entity, unknown report type |
| 422 | Validation failure (Form Request) |
| 422 | Tally returned `LINEERROR` |
| 503 | Tally unreachable or circuit breaker open |

See also: [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

---

## Console commands

```bash
php artisan tally:health                 # health of every active connection
php artisan tally:health MUM             # health of one connection
php artisan tally:sync MUM               # sync all master types
php artisan tally:sync MUM --type=ledger # sync only ledgers
```

---

## Full route map

`.claude/routes-reference.md` has the canonical URI / controller / name / middleware table for every route.
