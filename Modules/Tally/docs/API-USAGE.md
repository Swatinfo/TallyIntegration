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

### Companies (list) — *Phase 9A*

Simpler read-only variant of `/discover`:

```bash
curl $BASE/connections/1/companies -H "Authorization: Bearer $TOKEN"
# data: ["ABC Enterprises", "SwatTech Demo", ...]
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

# Filter by parent group ("Pull Ledgers of Group")
curl "$BASE/{conn}/ledgers?parent=Sundry%20Debtors" -H "Authorization: Bearer $TOKEN"
```

The `?parent=<exact name>` filter is also available on `groups`, `stock-items`, and `stock-groups`. Stock-groups additionally support `?zero_balance=true`. All filter params compose with `?per_page`, `?page`, `?search`, `?sort_by`, `?sort_dir`.

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

## 4b. Stock Groups (per connection) — *Phase 9A*

```bash
curl "$BASE/{conn}/stock-groups" -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/stock-groups/-DEMO-%20Software%20Licenses" -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/{conn}/stock-groups -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"Software Licenses","PARENT":"Primary"}'
```

Routes: `GET/POST /{conn}/stock-groups`, `GET/PUT/PATCH/DELETE /{conn}/stock-groups/{name}`.

PHP: `app(\Modules\Tally\Services\Masters\StockGroupService::class)`.

## 4c. Units (per connection) — *Phase 9A*

```bash
curl "$BASE/{conn}/units" -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/{conn}/units -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"Nos","ISSIMPLEUNIT":"Yes"}'
```

Routes: `GET/POST /{conn}/units`, `GET/PUT/PATCH/DELETE /{conn}/units/{name}`.

PHP: `app(\Modules\Tally\Services\Masters\UnitService::class)`.

## 4d. Cost Centres (per connection) — *Phase 9A*

```bash
curl "$BASE/{conn}/cost-centres" -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/{conn}/cost-centres -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"Engineering","PARENT":""}'
```

Routes: `GET/POST /{conn}/cost-centres`, `GET/PUT/PATCH/DELETE /{conn}/cost-centres/{name}`.

PHP: `app(\Modules\Tally\Services\Masters\CostCenterService::class)`.

## 4e. Currencies (per connection) — *Phase 9B*

```bash
curl "$BASE/{conn}/currencies" -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/{conn}/currencies -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"USD","MAILINGNAME":"US Dollars","FORMALNAME":"US Dollar","ISSUFFIX":"No","DECIMALPLACES":2,"DECIMALSYMBOL":"."}'
```

Routes: `GET/POST /{conn}/currencies`, `GET/PUT/PATCH/DELETE /{conn}/currencies/{name}`.

## 4f. Godowns (per connection) — *Phase 9B*

```bash
curl "$BASE/{conn}/godowns" -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/{conn}/godowns -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"Mumbai Warehouse","PARENT":"Primary","ADDRESS":"Mumbai","STORAGETYPE":"Our Godown"}'
```

Routes: `GET/POST /{conn}/godowns`, `GET/PUT/PATCH/DELETE /{conn}/godowns/{name}`.

## 4g. Voucher Types (per connection) — *Phase 9B*

```bash
curl "$BASE/{conn}/voucher-types" -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/{conn}/voucher-types -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"Export Sale","PARENT":"Sales","ABBR":"ES","NUMBERINGMETHOD":"Automatic"}'
```

Routes: `GET/POST /{conn}/voucher-types`, `GET/PUT/PATCH/DELETE /{conn}/voucher-types/{name}`.

## 4h. Stock Categories (per connection) — *Phase 9F*

Alternate classification axis for stock items (brand / tier / size).

```bash
curl "$BASE/{conn}/stock-categories" -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/{conn}/stock-categories -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"Enterprise Tier","PARENT":"Primary"}'
```

Routes: `GET/POST /{conn}/stock-categories`, `GET/PUT/PATCH/DELETE /{conn}/stock-categories/{name}`.

## 4i. Price Lists (per connection) — *Phase 9F*

Tally calls these Price Levels. Each represents a customer-tier rate sheet.

```bash
curl "$BASE/{conn}/price-lists" -H "Authorization: Bearer $TOKEN"
curl -X POST $BASE/{conn}/price-lists -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"NAME":"Wholesale","USEFORGROUPS":"No"}'
```

Routes: `GET/POST /{conn}/price-lists`, `GET/PUT/PATCH/DELETE /{conn}/price-lists/{name}`.

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

### Batch create — REST endpoint (*Phase 9A*)

```bash
curl -X POST $BASE/{conn}/vouchers/batch -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Journal",
    "vouchers": [
      { "DATE":"20260421", "VOUCHERNUMBER":"BATCH-001", "NARRATION":"...", "ALLLEDGERENTRIES.LIST":[...] },
      { "DATE":"20260421", "VOUCHERNUMBER":"BATCH-002", "NARRATION":"...", "ALLLEDGERENTRIES.LIST":[...] }
    ]
  }'
```

Creates all vouchers in ONE Tally request (faster + atomic). Response rolls up `created`, `altered`, `errors` across the batch.

PHP equivalent:
```php
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

app(VoucherService::class)->createBatch(VoucherType::Sales, $vouchers);
```

---

## 7. Reports

```bash
# Core reports (Phase 1)
curl "$BASE/{conn}/reports/balance-sheet?date=20260331"                                -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/profit-and-loss?from=20250401&to=20260331"                  -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/trial-balance?date=20260331"                                -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/ledger?ledger=Cash&from=20250401&to=20260331"               -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/outstandings?type=receivable"                               -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/outstandings?type=payable"                                  -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/stock-summary"                                              -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/day-book?date=20260416"                                     -H "Authorization: Bearer $TOKEN"

# Phase 9B additions
curl "$BASE/{conn}/reports/cash-book?ledger=HDFC%20Current%20A%2Fc&from=20250401&to=20260331" -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/sales-register?from=20250401&to=20260331"                   -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/purchase-register?from=20250401&to=20260331"                -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/aging?type=receivable&as_of=20260331"                       -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/cash-flow?from=20250401&to=20260331"                        -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/funds-flow?from=20250401&to=20260331"                       -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/receipts-payments?from=20250401&to=20260331"                -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/stock-movement?stock_item=SKU-PRO&from=20250401&to=20260331" -H "Authorization: Bearer $TOKEN"
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

## 7b. Inventory operations — *Phase 9F*

### Stock transfer between godowns

```bash
curl -X POST $BASE/{conn}/stock-transfers -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "date":"20260420",
    "from_godown":"Mumbai Warehouse",
    "to_godown":"Pune Warehouse",
    "stock_item":"SKU-PRO Annual",
    "quantity":2,
    "unit":"Nos",
    "rate":48000
  }'
```

Builds a `Stock Journal` voucher with two inventory entries (source negative / destination positive) and appropriate `BATCHALLOCATIONS.LIST`.

### Physical stock adjustment

```bash
curl -X POST $BASE/{conn}/physical-stock -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "date":"20260430",
    "godown":"Mumbai Warehouse",
    "stock_item":"SKU-PRO Annual",
    "counted_quantity":10,
    "unit":"Nos"
  }'
```

### New voucher types — order processing + dispatch (via existing `/vouchers`)

The `VoucherType` enum now accepts 9 new values. Use them with `POST /{conn}/vouchers`:

| `type` value | Tally name | Typical use |
|---|---|---|
| `SalesOrder` | Sales Order | Pre-invoice customer order |
| `PurchaseOrder` | Purchase Order | Issue PO to vendor |
| `Quotation` | Quotation | Pre-sale price quote |
| `DeliveryNote` | Delivery Note | Goods out, not yet invoiced |
| `ReceiptNote` | Receipt Note | Goods in, not yet billed |
| `RejectionIn` | Rejections In | Return from customer |
| `RejectionOut` | Rejections Out | Return to vendor |
| `StockJournal` | Stock Journal | Raw internal movement (use `/stock-transfers` for godown-to-godown) |
| `PhysicalStock` | Physical Stock | Count adjustment (use `/physical-stock` for convenience) |

Example — Sales Order:
```bash
curl -X POST $BASE/{conn}/vouchers -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type":"SalesOrder",
    "data":{
      "DATE":"20260420","VOUCHERTYPENAME":"Sales Order","VOUCHERNUMBER":"SO-0001",
      "PARTYLEDGERNAME":"Acme Corp",
      "ALLINVENTORYENTRIES.LIST":[
        {"STOCKITEMNAME":"SKU-PRO","ACTUALQTY":"5 Nos","BILLEDQTY":"5 Nos","RATE":"48000/Nos","AMOUNT":"240000.00"}
      ]
    }
  }'
```

## 7c. Manufacturing — *Phase 9G*

### BOM (Bill of Materials)

Stored on the finished stock item's `COMPONENTLIST.LIST`:

```bash
# Read
curl "$BASE/{conn}/stock-items/SKU-ENT%20Annual/bom" -H "Authorization: Bearer $TOKEN"

# Set / replace
curl -X PUT "$BASE/{conn}/stock-items/SKU-ENT%20Annual/bom" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "components":[
      {"name":"SKU-PRO Annual","qty":1,"unit":"Nos"},
      {"name":"Analytics Add-on","qty":1,"unit":"Nos"}
    ]
  }'
```

### Manufacturing voucher

Assembles a finished item from raw components. Builds the consumption + production lines automatically with `BATCHALLOCATIONS.LIST`.

```bash
curl -X POST "$BASE/{conn}/manufacturing" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "date":"20260425",
    "product_item":"SKU-ENT Annual",
    "product_qty":3,
    "unit":"Nos",
    "product_godown":"Mumbai Warehouse",
    "components":[
      {"name":"SKU-PRO Annual","qty":3,"unit":"Nos","godown":"Mumbai Warehouse"},
      {"name":"Analytics Add-on","qty":3,"unit":"Nos","godown":"Mumbai Warehouse"}
    ],
    "voucher_number":"MFG-0001",
    "narration":"Bundle assembly"
  }'
```

### Job Work (Out / In)

```bash
curl -X POST "$BASE/{conn}/job-work-out" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"date":"20260425","job_worker_ledger":"XYZ Fabricators","stock_item":"SKU-PRO","quantity":5,"unit":"Nos"}'

curl -X POST "$BASE/{conn}/job-work-in" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"date":"20260428","job_worker_ledger":"XYZ Fabricators","stock_item":"SKU-PRO","quantity":5,"unit":"Nos"}'
```

All three endpoints use `VoucherType::ManufacturingJournal`, `JobWorkOutOrder`, `JobWorkInOrder` respectively. You can also call `POST /{conn}/vouchers` directly with those types if you need finer control over the payload.

## 8b. Banking — *Phase 9D*

### Reports (dispatched on existing `/{c}/reports/{type}`)

```bash
curl "$BASE/{conn}/reports/bank-reconciliation?bank=HDFC%20Current%20A%2Fc&from=20250401&to=20260331" -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/cheque-register?from=20250401&to=20260331"                                  -H "Authorization: Bearer $TOKEN"
curl "$BASE/{conn}/reports/post-dated-cheques?from=20250401&to=20260331"                               -H "Authorization: Bearer $TOKEN"
```

### Mark / clear reconciliation

```bash
curl -X POST $BASE/{conn}/bank/reconcile -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "voucher_number":"PMT-0001",
    "voucher_date":"20260416",
    "voucher_type":"Payment",
    "statement_date":"16-Apr-2026",
    "bank_ledger":"HDFC Current A/c"
  }'

curl -X POST $BASE/{conn}/bank/unreconcile -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"voucher_number":"PMT-0001","voucher_date":"20260416","voucher_type":"Payment","bank_ledger":"HDFC Current A/c"}'
```

### Upload bank statement CSV

Either multipart file upload:
```bash
curl -X POST $BASE/{conn}/bank/import-statement -H "Authorization: Bearer $TOKEN" \
  -F 'statement_file=@path/to/statement.csv'
```

Or inline CSV string:
```bash
curl -X POST $BASE/{conn}/bank/import-statement -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"csv":"date,description,amount,reference\n16-Apr-2026,AWS,-45000,PMT-0001"}'
```

Expected CSV headers (case-insensitive, any subset): `date, description, debit, credit, amount, reference, cheque_number`.

### Auto-match statement rows to vouchers

```bash
curl -X POST $BASE/{conn}/bank/auto-match -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "bank_ledger":"HDFC Current A/c",
    "from_date":"20260401","to_date":"20260430",
    "rows":[{"date":"20260416","amount":-45000}],
    "date_tolerance_days":3
  }'
# Returns: { total_candidates, matches: [{statement, voucher, confidence}] }
```

### Batch reconcile

```bash
curl -X POST $BASE/{conn}/bank/batch-reconcile -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"entries":[
    {"voucher_number":"PMT-0001","voucher_date":"20260416","voucher_type":"Payment","statement_date":"16-Apr-2026","bank_ledger":"HDFC Current A/c"},
    {"voucher_number":"RCT-0001","voucher_date":"20260417","voucher_type":"Receipt","statement_date":"17-Apr-2026","bank_ledger":"HDFC Current A/c"}
  ]}'
# Returns 207 Multi-Status if any entry failed.
```

## 9. Audit logs

```bash
curl "$BASE/audit-logs?per_page=50" -H "Authorization: Bearer $TOKEN"

# Phase 9C — single record with full request/response payloads
curl "$BASE/audit-logs/123" -H "Authorization: Bearer $TOKEN"

# Phase 9C — CSV export (same filters as index)
curl "$BASE/audit-logs/export?action=create" -H "Authorization: Bearer $TOKEN" -o audit.csv
```

Filters: `connection=MUM`, `action=create|alter|delete|cancel`, `object_type=LEDGER|GROUP|STOCKITEM|VOUCHER`, `user_id=1`.

## 2b. MNC hierarchy + consolidation — *Phase 9Z + 9K*

For groups that own multiple legal companies (and branches) across Tally instances.

### Organizations / Companies / Branches

```bash
# Organization (top-level MNC group)
curl -X POST "$BASE/organizations" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"SwatTech Group","code":"SWAT","country":"IN","base_currency":"INR"}'

# Company under an org
curl -X POST "$BASE/companies" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tally_organization_id":1,"name":"SwatTech India","code":"SWATIN","gstin":"27ABCDE1234F1Z5"}'

# Branch under a company
curl -X POST "$BASE/branches" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tally_company_id":1,"name":"Mumbai HQ","code":"MUMHQ","city":"Mumbai"}'

# Filtering
curl "$BASE/companies?organization_id=1"     -H "Authorization: Bearer $TOKEN"
curl "$BASE/branches?company_id=1"            -H "Authorization: Bearer $TOKEN"
```

Each supports full CRUD: `GET|POST /organizations`, `GET|PUT|PATCH|DELETE /organizations/{id}` (same shape for companies, branches).

### Linking connections to the hierarchy

A `tally_connections` row gets three new nullable FKs: `tally_organization_id`, `tally_company_id`, `tally_branch_id`. Set them when creating/updating a connection:

```bash
curl -X PATCH "$BASE/connections/1" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tally_organization_id":1,"tally_company_id":1,"tally_branch_id":1}'
```

Existing connections keep all three null — backwards-compatible.

### Consolidated reports

Roll up Balance Sheet / P&L / Trial Balance across every active connection under an organization:

```bash
curl "$BASE/organizations/1/consolidated/balance-sheet?date=20260331"                       -H "Authorization: Bearer $TOKEN"
curl "$BASE/organizations/1/consolidated/profit-and-loss?from=20250401&to=20260331"         -H "Authorization: Bearer $TOKEN"
curl "$BASE/organizations/1/consolidated/trial-balance?date=20260331"                       -H "Authorization: Bearer $TOKEN"
```

Response shape: `{ organization, connection_count, successful, breakdown: [...] }`. A failing connection shows up as `{success: false, error: "..."}` inside `breakdown[]` without aborting the overall call.

## 9a. Draft vouchers / workflow — *Phase 9J*

Maker-checker workflow for vouchers. Drafts live in our DB; pushed to Tally only after approval.

### Create a draft (maker)

```bash
curl -X POST "$BASE/connections/1/draft-vouchers" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "voucher_type":"Payment",
    "amount":150000,
    "narration":"Vendor payment — AWS",
    "voucher_data":{
      "DATE":"20260420","VOUCHERTYPENAME":"Payment","VOUCHERNUMBER":"PMT-0099",
      "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"AWS India","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"150000.00"},
        {"LEDGERNAME":"HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-150000.00"}
      ]
    }
  }'
```

### State transitions

```bash
# Maker submits
curl -X POST "$BASE/connections/1/draft-vouchers/42/submit" -H "Authorization: Bearer $TOKEN"

# Checker approves (different user if require_distinct_approver=true). Pushes to Tally.
curl -X POST "$BASE/connections/1/draft-vouchers/42/approve" -H "Authorization: Bearer $TOKEN"

# Or checker rejects with a reason
curl -X POST "$BASE/connections/1/draft-vouchers/42/reject" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason":"GL code is wrong — should be Marketing, not AWS Hosting"}'
```

### Status flow

```
draft ─(submit)─→ submitted ─(approve)─→ approved ─(auto)─→ pushed (done)
                         └─(reject)────→ rejected (terminal)
```

- `draft` — editable/deletable; maker can still change `voucher_data`, `amount`, `narration`
- `submitted` — locked for editing; waiting for approver
- `approved` — transient; immediately followed by Tally push
- `pushed` — voucher is live in Tally; `tally_master_id` populated
- `rejected` — terminal; inspect `rejection_reason`

### Auto-approval below threshold

Configure in `config/tally.php`:

```php
'workflow' => [
    'approval_thresholds' => [
        ['type' => 'Payment', 'amount' => 100000],  // Payments ≥ 1L need checker
        ['type' => 'Journal', 'amount' => 250000],
    ],
],
```

A draft under every matching rule's threshold **auto-approves on submit and pushes immediately** — single-call flow for low-risk vouchers.

### List / filter / show / update / delete

```bash
curl "$BASE/connections/1/draft-vouchers?status=submitted"                -H "Authorization: Bearer $TOKEN"
curl "$BASE/connections/1/draft-vouchers?created_by=7"                    -H "Authorization: Bearer $TOKEN"
curl "$BASE/connections/1/draft-vouchers/42"                              -H "Authorization: Bearer $TOKEN"
curl -X PATCH "$BASE/connections/1/draft-vouchers/42" \
  -d '{"narration":"Updated note"}' -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json"
curl -X DELETE "$BASE/connections/1/draft-vouchers/42"                    -H "Authorization: Bearer $TOKEN"
```

Update + delete only work while `status=draft`. After submission the draft is locked.

### Permissions

| Action | Permission |
|---|---|
| Create / read / update / delete / submit | `manage_vouchers` |
| Approve / reject | **`approve_vouchers`** (new in 9J) |

Grant both to an admin, or split for true maker-checker segregation.

## 9b. Recurring vouchers — *Phase 9L*

Schedule voucher templates that auto-post on a daily / weekly / monthly / quarterly / yearly cadence. Fires via `ProcessRecurringVouchersJob` at 00:30 daily.

### Create

```bash
curl -X POST "$BASE/connections/1/recurring-vouchers" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Monthly Office Rent",
    "voucher_type":"Payment",
    "frequency":"monthly",
    "day_of_month":1,
    "start_date":"2026-05-01",
    "end_date":"2027-04-01",
    "is_active":true,
    "voucher_template":{
      "VOUCHERTYPENAME":"Payment",
      "VOUCHERNUMBER":"RENT-AUTO",
      "NARRATION":"Auto-posted monthly office rent",
      "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"Office Rent","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"25000.00"},
        {"LEDGERNAME":"HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-25000.00"}
      ]
    }
  }'
```

The `DATE` field is injected automatically at fire time from `next_run_at` — don't put it in the template.

### List / show / update / delete / manual run

```bash
curl "$BASE/connections/1/recurring-vouchers?per_page=20"                      -H "Authorization: Bearer $TOKEN"
curl "$BASE/connections/1/recurring-vouchers/42"                               -H "Authorization: Bearer $TOKEN"
curl -X PATCH "$BASE/connections/1/recurring-vouchers/42" -d '{"is_active":false}' \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json"
curl -X DELETE "$BASE/connections/1/recurring-vouchers/42"                     -H "Authorization: Bearer $TOKEN"

# Manual fire — advances next_run_at as if the scheduler ran it today
curl -X POST "$BASE/connections/1/recurring-vouchers/42/run"                   -H "Authorization: Bearer $TOKEN"
```

### Frequency options

| `frequency` | Required day-of fields |
|---|---|
| `daily` | — |
| `weekly` | `day_of_week` (0=Sun … 6=Sat) |
| `monthly` | `day_of_month` (1-28) |
| `quarterly` | `day_of_month` (1-28) |
| `yearly` | `day_of_month` (1-28) |

`day_of_month` is clamped to 28 to avoid Feb-30 edge cases.

## 10a. Integration glue — *Phase 9I*

### Webhooks

Register an outbound webhook, receive a POST with HMAC-SHA256 signature on every subscribed event.

```bash
# Create (secret returned only on this call — save it)
curl -X POST "$BASE/webhooks" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Slack","url":"https://hooks.slack.com/...","events":["voucher.created","sync.completed"]}'

# CRUD + test + view delivery log
curl "$BASE/webhooks" -H "Authorization: Bearer $TOKEN"
curl -X POST "$BASE/webhooks/1/test" -H "Authorization: Bearer $TOKEN"
curl "$BASE/webhooks/1/deliveries?per_page=20" -H "Authorization: Bearer $TOKEN"
```

**Events:** `master.{created,updated,deleted}`, `voucher.{created,altered,cancelled}`, `sync.completed`, `connection.health_changed`. Use `*` for all.

**Verify signature (receiver side):** header `X-Tally-Signature: sha256=<hex>` → `hash_hmac('sha256', $rawBody, $secret)`.

### CSV master imports

```bash
curl -X POST "$BASE/connections/1/import/ledger" -H "Authorization: Bearer $TOKEN" -F 'file=@ledgers.csv'
curl "$BASE/import-jobs/5" -H "Authorization: Bearer $TOKEN"
```

Supported entities: `ledger`, `group`, `stock-item`. CSV headers must match the `Store*Request` field names (e.g. `NAME,PARENT,OPENINGBALANCE`).

### Voucher attachments

```bash
curl -X POST "$BASE/connections/1/vouchers/1305/attachments" -H "Authorization: Bearer $TOKEN" -F 'file=@scan.pdf'
curl "$BASE/connections/1/vouchers/1305/attachments"        -H "Authorization: Bearer $TOKEN"
curl "$BASE/attachments/42/download" -H "Authorization: Bearer $TOKEN" -o scan.pdf
curl -X DELETE "$BASE/attachments/42" -H "Authorization: Bearer $TOKEN"
```

### Voucher PDF + email (mpdf)

```bash
curl "$BASE/MUM/vouchers/1305/pdf" -H "Authorization: Bearer $TOKEN" -o voucher.pdf

curl -X POST "$BASE/MUM/vouchers/1305/email" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"to":"billing@acme.example","subject":"Invoice","body":"See attached."}'
```

**Permissions:**
- Webhooks / imports / attachments → `manage_integrations`
- Voucher PDF → `view_vouchers`
- Email voucher → **`send_invoices`** (new permission)

## 10. Operations & observability — *Phase 9C*

### Dashboard counts

```bash
curl "$BASE/{conn}/stats" -H "Authorization: Bearer $TOKEN"
# data: { ledgers: 143, groups: 28, stock_items: 12, stock_groups: 3, units: 6, cost_centres: 3, currencies: 2, godowns: 2, voucher_types: 5 }
```

### Cross-master search

```bash
curl "$BASE/{conn}/search?q=acme&limit=5" -H "Authorization: Bearer $TOKEN"
# data: { query, ledgers: [...], groups: [...], stock_items: [...] }
```

### Cache flush

```bash
curl -X POST "$BASE/{conn}/cache/flush" -H "Authorization: Bearer $TOKEN"
```

### Circuit breaker state

```bash
curl "$BASE/connections/1/circuit-state" -H "Authorization: Bearer $TOKEN"
# data: { connection: "MUM", state: "closed|open|half-open", available: true|false }
```

### Sync history / single record / cancel / bulk resolve

```bash
curl "$BASE/connections/1/sync-history?per_page=20"           -H "Authorization: Bearer $TOKEN"
curl "$BASE/sync/42"                                          -H "Authorization: Bearer $TOKEN"
curl -X POST "$BASE/sync/42/cancel"                           -H "Authorization: Bearer $TOKEN"
curl -X POST "$BASE/connections/1/sync/resolve-all" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"strategy":"erp_wins"}'
```

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

## Smoke-test your install

To exercise **every endpoint** in this doc in one go with realistic software-company data:

```bash
bash Modules/Tally/scripts/tally-smoke-test.sh
```

Creates a `smoke-test@local` user automatically, mints a fresh Sanctum token, then runs through all 44 endpoints. Probes Tally health before every call. Logs every request/response to `storage/logs/tally/tally-DD-MM-YYYY.log`. See `Modules/Tally/scripts/README.md` for flags.

## Full route map

`.claude/routes-reference.md` has the canonical URI / controller / name / middleware table for every route.
