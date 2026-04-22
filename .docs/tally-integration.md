# Tally Integration Guide

**Last verified:** 2026-04-17

## Overview

Self-contained Laravel module (`Modules/Tally/`) for integrating with TallyPrime via HTTP/XML API. Namespace: `Modules\Tally\*`. Portable — copy the module into any Laravel project with `nwidart/laravel-modules` installed.

For step-by-step install, see:
- `Modules/Tally/docs/INSTALLATION-FRESH.md` — fresh Laravel 13 project
- `Modules/Tally/docs/INSTALLATION-EXISTING.md` — drop-in to existing Laravel app
- `Modules/Tally/docs/TALLY-SETUP.md` — TallyPrime-side setup (port 9000)

## Prerequisites

1. **TallyPrime running** on a reachable host/port (default `localhost:9000`)
2. **At least one company loaded** in TallyPrime
3. **Laravel 11+** host application (Laravel 13 recommended) with Sanctum + a queue driver

### Works with all Tally editions

| Edition | API access | Notes |
|---------|-----------|-------|
| TallyPrime (Standalone / Silver) | `localhost` or LAN IP | Single user — API may conflict with GUI |
| TallyPrime Server (Gold) | Dedicated server IP | Multi-user, always-on — best for integration |
| TallyPrime Cloud Access | Cloud VM IP (needs tunnel) | Remote desktop on OCI — identical API inside the VM |

XML protocol is identical across editions; only the host/port differs.

## Environment

```env
TALLY_HOST=localhost
TALLY_PORT=9000
TALLY_COMPANY=
TALLY_TIMEOUT=30
TALLY_LOG_REQUESTS=true
TALLY_CACHE_ENABLED=true
TALLY_CACHE_TTL=300
TALLY_CIRCUIT_BREAKER=true
```

All keys are read by `Modules/Tally/config/config.php` and exposed as `config('tally.*')`.

## Module Structure

```
Modules/Tally/
├── app/
│   ├── Console/                  # tally:health, tally:sync
│   ├── Enums/                    # TallyPermission (9 cases)
│   ├── Events/                   # 8 events + 1 listener (DispatchWebhooksOnTallyEvent)
│   ├── Exceptions/               # 5 custom exceptions
│   ├── Http/
│   │   ├── Controllers/          # ~31 controllers
│   │   ├── Middleware/           # CheckTallyPermission, ResolveTallyConnection
│   │   └── Requests/             # Form Requests (one per mutating controller) + SafeXmlString rule
│   ├── Jobs/                     # 9 queued jobs
│   ├── Listeners/                # DispatchWebhooksOnTallyEvent
│   ├── Models/                   # 13 Eloquent models
│   ├── Providers/                # Tally / Route / Event service providers
│   ├── Rules/                    # SafeXmlString
│   └── Services/
│       ├── TallyHttpClient.php
│       ├── TallyXmlBuilder.php
│       ├── TallyXmlParser.php
│       ├── TallyConnectionManager.php
│       ├── TallyCompanyService.php
│       ├── TallyRequestLogger.php
│       ├── AuditLogger.php
│       ├── MetricsCollector.php
│       ├── CircuitBreaker.php
│       ├── SyncTracker.php
│       ├── WorkflowService.php       # Phase 9J — maker-checker state machine
│       ├── RecurringVoucherService.php # Phase 9L
│       ├── Masters/                 # 11 CRUD services (Ledger/Group/StockItem/StockGroup/Unit/CostCentre/Currency/Godown/VoucherType/StockCategory/PriceList)
│       ├── Vouchers/                # VoucherService + VoucherType enum (20 cases)
│       ├── Reports/                 # ReportService (18 report types)
│       ├── Banking/                 # Phase 9D — BankingService
│       ├── Manufacturing/           # Phase 9G — ManufacturingService
│       ├── Consolidation/           # Phase 9K — ConsolidationService
│       ├── Integration/             # Phase 9I — Pdf/Mail/Attachment/Import/WebhookDispatcher
│       └── Concerns/                # CachesMasterData, PaginatesResults
├── config/config.php             # published as config('tally.*')
├── database/
│   ├── factories/                # TallyConnectionFactory
│   └── migrations/               # 20 migrations (17 tables)
├── docs/                         # module setup guides (see list above)
├── routes/api.php                # 165 route registrations under /api/tally/
└── module.json                   # nwidart manifest
```

## Architecture

### Request flow (synchronous path)

```
Controller → Form Request → Service → TallyXmlBuilder → TallyHttpClient → TallyPrime :9000
                                                                               │
                                ◄── TallyXmlParser ◄── XML response ◄────────────┘
                                          │
                                Service → JSON response ({success, data, message})
```

All writes fire an **Event** and an **Audit Log** row. All requests record a **MetricsCollector** row.

### Multi-connection resolution

- Routes under `/api/tally/{connection}/...` run through `ResolveTallyConnection` middleware.
- The middleware:
  1. Looks up the **connection code** (e.g. `MUM`) in `tally_connections`.
  2. Uses `TallyConnectionManager` to return (or build + cache) a `TallyHttpClient`.
  3. Rebinds that client in the container so every downstream service resolves the right instance.

Routes under `/api/tally/connections/...` use the `TallyConnection` model directly (management endpoints, no client binding).

### Core services

See `.claude/services-reference.md` for full method signatures. High level:

| Class | Role |
|---|---|
| `TallyHttpClient` | Low-level HTTP POST to Tally |
| `TallyConnectionManager` | Singleton cache of clients keyed by connection code |
| `TallyXmlBuilder` | Static — builds the four Tally request formats (Data / Collection / Object / Import) plus Function + AlterID |
| `TallyXmlParser` | Static — parses responses (import result, collection, object, errors) |
| `TallyCompanyService` | AlterID, Function calls, FY detection |
| `CircuitBreaker` | Fail-fast on repeated connection errors |
| `AuditLogger` | Writes `tally_audit_logs` |
| `MetricsCollector` | Writes `tally_response_metrics` |
| `SyncTracker` | Manages `tally_syncs` rows + conflict state |

### Master services (all share the same shape)

11 services: `LedgerService`, `GroupService`, `StockItemService`, `StockGroupService`, `UnitService`, `CostCenterService`, `CurrencyService`, `GodownService`, `VoucherTypeService`, `StockCategoryService`, `PriceListService`.
Each exposes `list()`, `get($name)`, `create($data)`, `update($name, $data)`, `delete($name)`.
All use the `CachesMasterData` trait and emit `TallyMaster*` events.

### Voucher service

`VoucherService` covers every voucher type via the `VoucherType` enum (20 cases):

| Group | Cases |
|---|---|
| Core accounting | `Sales`, `Purchase`, `Payment`, `Receipt`, `Journal`, `Contra`, `CreditNote`, `DebitNote` |
| Order processing & dispatch (Phase 9F) | `SalesOrder`, `PurchaseOrder`, `Quotation`, `DeliveryNote`, `ReceiptNote`, `RejectionIn`, `RejectionOut`, `StockJournal`, `PhysicalStock` |
| Manufacturing (Phase 9G) | `ManufacturingJournal`, `JobWorkInOrder`, `JobWorkOutOrder` |

Helpers: `createSales/createPurchase/createPayment/createReceipt/createJournal/createStockTransfer/createPhysicalStock`. Batch + cancel + delete supported.

### Report service

18 report types available via `GET /{conn}/reports/{type}`:

| Group | Types |
|---|---|
| Core | `balance-sheet`, `profit-and-loss`, `trial-balance`, `ledger`, `outstandings`, `stock-summary`, `day-book` |
| Management (Phase 9B) | `cash-book`, `sales-register`, `purchase-register`, `aging`, `cash-flow`, `funds-flow`, `receipts-payments`, `stock-movement` |
| Banking (Phase 9D) | `bank-reconciliation`, `cheque-register`, `post-dated-cheques` |

Dates are `YYYYMMDD`. Add `?format=csv` or `Accept: text/csv` for streaming CSV export.

### Additional services (Phase 9D/9G/9I/9J/9K/9L)

| Service | Role |
|---|---|
| `BankingService` | Reconciliation (ALTER voucher with `BANKERDATE`), cheque tracking, CSV bank feed parser, amount+date match |
| `ManufacturingService` | BOM read/write, Manufacturing Journal assembly, Job Work In/Out |
| `ConsolidationService` | Fans out a report across every active connection in a `TallyOrganization`; failure-tolerant |
| `WorkflowService` | Draft voucher state machine (`draft → submitted → approved → pushed` / `→ rejected`) + auto-approval threshold |
| `RecurringVoucherService` | Advances scheduled voucher templates; `fire()` injects `DATE` and dispatches via `VoucherService::create` |
| `PdfService` | HTML → PDF via mpdf (A4 default, configurable) |
| `MailService` | Voucher PDF attachment via Laravel Mail |
| `AttachmentService` | Upload/download/delete voucher attachments on any configured disk |
| `ImportService` | CSV bulk-import of masters (ledger/group/stock-item) |
| `WebhookDispatcher` | HMAC-SHA256 signed POSTs with exponential backoff retry |

## Bidirectional Sync Engine

Each syncable local record has a `tally_syncs` row (`entity_type` + `entity_id`).

| Job | Direction | Trigger |
|---|---|---|
| `SyncFromTallyJob` | Tally → local DB | Scheduler every 10 min + `POST /connections/{id}/sync-from-tally` |
| `SyncToTallyJob` | Local DB → Tally | Scheduler every 10 min + `POST /connections/{id}/sync-to-tally` |
| `ProcessConflictsJob` | Applies resolution strategy | Scheduler every 5 min |
| `SyncAllConnectionsJob` | Dispatch master sync per connection | Hourly |
| `SyncMastersJob` | Single-connection master refresh | `tally:sync` command + scheduled dispatch |
| `HealthCheckJob` | Health probe per connection | Every 5 min |
| `BulkVoucherImportJob` | Ad-hoc batch voucher push | Manual |

### Incremental sync (AlterID)

TallyPrime increments `$AltMstId` / `$AltVchId` on every change. `TallyCompanyService::getAlterIds()` and `hasChangedSince()` let sync jobs skip entirely when nothing has changed — near-free hourly sync for stable companies.

```php
$company = app(TallyCompanyService::class);
$ids = $company->getAlterIds();               // ['master_id' => 1502, 'voucher_id' => 3200]
$changes = $company->hasChangedSince(1500, 3200);
```

### Conflict detection + resolution

A conflict is recorded when both local and Tally hashes have changed since last sync. Strategies (set on `tally_syncs.resolution_strategy`):

| Strategy | Behaviour |
|---|---|
| `erp_wins` | Push local → Tally |
| `tally_wins` | Pull Tally → local |
| `newest_wins` | Compare `updated_at` timestamps |
| `merge` | Custom merge (caller-supplied) |
| `manual` | Operator decides |

Resolve via `POST /api/tally/sync/{sync}/resolve { "strategy": "erp_wins" }`.

## Security

- **Auth:** every route requires `auth:sanctum` (see CONFIGURATION.md).
- **Permissions:** per-group `CheckTallyPermission` middleware reads `users.tally_permissions` (JSON). 9 values available: `view_masters`, `manage_masters`, `view_vouchers`, `manage_vouchers`, `view_reports`, `manage_connections`, `approve_vouchers` (9J), `manage_integrations` (9I), `send_invoices` (9I).
- **Rate limiting:** three throttle groups — `tally-api` (all), `tally-write` (master/voucher writes), `tally-reports` (reports) — **tiered by Sanctum token name prefix**: `smoke-test-*` / `internal-*` / `system-*` → internal tier (6000/min writes), `batch-*` / `sync-*` → batch tier (600/min writes), anything else → standard tier (60/min writes). Additionally **keyed per connection** for routes under `/{connection}/*` so one busy Tally instance does not share a bucket with others. Full table in `Modules/Tally/docs/CONFIGURATION.md`.
- **Input safety:** `SafeXmlString` rule rejects any field containing `<!DOCTYPE`, `<!ENTITY`, `<![CDATA[`, `<?xml`, or Tally envelope tags. All user input is escaped via `TallyXmlBuilder::escapeXml()` before hitting the wire.

## MNC hierarchy (Phase 9Z) + Consolidation (Phase 9K)

Three new tables layered above `tally_connections`:

- **`tally_organizations`** — top-level MNC / group entity (e.g. "Acme Group")
- **`tally_companies`** — legal entity within a group (e.g. "Acme India Pvt Ltd", "Acme UK Ltd")
- **`tally_branches`** — optional location under a company

`tally_connections` carries three new nullable FK columns so existing rows continue to work without a hierarchy. New connections can opt in.

`ConsolidationService` + `ConsolidatedReportController` fan out Balance Sheet / P&L / Trial Balance across every active connection in an organization and return a packaged envelope with per-connection breakdown. A single failing connection is captured in the breakdown without aborting the whole rollup.

## Workflow (Phase 9J)

Draft vouchers live in the module's DB until approved, then get pushed to Tally. State machine: `draft → submitted → approved → pushed` or `→ rejected`. Thresholds in `config('tally.workflow.approval_thresholds')` decide which submissions auto-approve vs require a checker. Self-approval is blocked by default via `require_distinct_approver`.

## Recurring vouchers (Phase 9L)

`tally_recurring_vouchers` holds scheduled voucher templates (daily / weekly / monthly / quarterly / yearly). `ProcessRecurringVouchersJob` runs daily at **00:30** via the module's scheduler, fires each due template via `VoucherService::create`, and advances `next_run_at`.

## Integration glue (Phase 9I)

- **Webhooks** — outbound POST with HMAC-SHA256 signature on every Tally event; 5-attempt exponential backoff (`60s, 5m, 15m, 1h, 4h`)
- **CSV master imports** — `POST /connections/{id}/import/{entity}` queues a `ProcessImportJob`; track progress via `GET /import-jobs/{id}`
- **Voucher attachments** — store supporting files (PDFs, scans) on any configured Laravel disk (local / S3 / GCS)
- **PDF invoices** — mpdf-powered; `GET /{conn}/vouchers/{id}/pdf`
- **Email voucher** — `POST /{conn}/vouchers/{id}/email` attaches the PDF and sends via the configured mailer

## Observability

## Observability

| Mechanism | What it gives you |
|---|---|
| `TallyRequestLogger` | Every request/response (channel: `tally`, truncated to `logging.max_body_size`) |
| `AuditLogger` → `tally_audit_logs` | Who changed what, when, with what payload and response |
| `MetricsCollector` → `tally_response_metrics` | Per-endpoint response time + status |
| `/connections/{id}/health` | On-demand health + company list |
| `/connections/{id}/metrics` | Aggregated stats over `1h` / `24h` / `7d` |
| `/connections/{id}/circuit-state` | Breaker state + availability (Phase 9C) |
| `/{conn}/stats`, `/{conn}/search`, `/{conn}/cache/flush` | Dashboard counts, cross-master search, cache invalidation (Phase 9C) |
| `/audit-logs/{id}`, `/audit-logs/export` | Single-record detail + CSV export (Phase 9C) |
| `CircuitBreaker` | Opens after 5 failures, auto-probes after 60s |

## Usage examples

### Register a connection

```http
POST /api/tally/connections
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Mumbai HQ",
  "code": "MUM",
  "host": "192.168.1.10",
  "port": 9000,
  "company_name": "ABC Enterprises"
}
```

### Create a ledger

```php
use Modules\Tally\Services\Masters\LedgerService;

$result = app(LedgerService::class)->create([
    'NAME' => 'Customer ABC',
    'PARENT' => 'Sundry Debtors',
    'OPENINGBALANCE' => '0',
]);
// ['created' => 1, 'altered' => 0, 'errors' => 0, ...]
```

### Create a sales voucher

```php
use Modules\Tally\Services\Vouchers\VoucherService;

app(VoucherService::class)->createSales(
    date: '20260416',
    partyLedger: 'Customer ABC',
    salesLedger: 'Sales Account',
    amount: 10000.00,
    voucherNumber: '001',
    narration: 'Invoice #001',
);
```

### Cancel a voucher

```php
use Modules\Tally\Services\Vouchers\VoucherType;

app(VoucherService::class)->cancel(
    date: '16-Apr-2026',
    voucherNumber: '001',
    type: VoucherType::Sales,
    narration: 'Cancelled',
);
```

### Fetch reports

```php
use Modules\Tally\Services\Reports\ReportService;

$rs = app(ReportService::class);
$rs->balanceSheet('20260331');
$rs->profitAndLoss('20250401', '20260331');
$rs->dayBook('20260416');
```

## API endpoints (summary)

Full table in `.claude/routes-reference.md`. Grouped here by purpose:

- **Connections (management):** `/connections`, `/connections/{id}/health|metrics|discover`, `/connections/test`
- **Sync:** `/connections/{id}/sync-stats|sync-pending|sync-conflicts|sync-from-tally|sync-to-tally|sync-full`, `/sync/{sync}/resolve`
- **Audit:** `/audit-logs`
- **Per-connection (prefix `/{conn}/`):** `ledgers`, `groups`, `stock-items`, `vouchers`, `reports/{type}`, `health`

All responses: `{ success: bool, data: mixed, message: string }`.

## Key conventions

- **Date format:** `YYYYMMDD` for most operations. Voucher cancel/delete attributes use `DD-Mon-YYYY`.
- **Object identity:** Tally identifies entities by **name** (case-insensitive), not numeric ID.
- **Amount signs:** debit = `ISDEEMEDPOSITIVE=Yes`, credit = `ISDEEMEDPOSITIVE=No`. Sign semantics differ per voucher type — see `.docs/tally-api-reference.md`.
- **Voucher type:** `VOUCHERTYPENAME` (child element) for creation. `VCHTYPE` (attribute) for cancel/delete only.
- **XML header:** `<VERSION>1</VERSION>` + `<TYPE>` + `<ID>`. All Data/Collection exports include `<EXPLODEFLAG>Yes</EXPLODEFLAG>`.

## Portability

```bash
composer require nwidart/laravel-modules
# Copy Modules/Tally/ into the target project
# composer.json autoload.psr-4:
#   "Modules\\Tally\\": "Modules/Tally/app/"
#   "Modules\\Tally\\Database\\Factories\\": "Modules/Tally/database/factories/"
composer dump-autoload
php artisan module:enable Tally
php artisan migrate
# Add TALLY_* to .env
```

Detailed walkthrough: `Modules/Tally/docs/INSTALLATION-EXISTING.md`.
