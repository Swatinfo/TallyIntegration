# Tally Integration Guide

## Overview

Self-contained Laravel module (`Modules/Tally/`) for integrating with TallyPrime via HTTP/XML API. Namespace: `Modules\Tally\*`. Portable — copy the module to any Laravel project with `nwidart/laravel-modules` installed.

## Prerequisites

1. **TallyPrime running in server mode** on a known port (default: 9000)
2. **At least one company loaded** in TallyPrime
3. **Network access** from Laravel server to TallyPrime instance

### TallyPrime Server Configuration

1. Open TallyPrime
2. Press `F12` (Configure) > `Connectivity`
3. Set **Tally.NET Server** to `Yes`
4. Set the **Port** (e.g., 9000)
5. Ensure a company is open/loaded

### Works with all Tally editions

| Edition | API Access | Notes |
|---------|-----------|-------|
| TallyPrime (Standalone/Silver) | localhost or LAN IP | Single user — API may conflict with GUI |
| TallyPrime Server (Gold) | Dedicated server IP | Multi-user, always-on, best for integration |
| TallyPrime Cloud Access | Cloud VM IP (needs tunnel) | Remote desktop on OCI — same API inside VM |

The XML API protocol is **identical** across all three. Only the host/port differs.

## Environment Configuration

```env
TALLY_HOST=localhost        # TallyPrime server hostname/IP
TALLY_PORT=9000             # TallyPrime HTTP port
TALLY_COMPANY=MyCompany     # Target company name (empty = active company)
TALLY_TIMEOUT=30            # HTTP timeout in seconds
```

## Module Structure

```
Modules/Tally/
├── app/
│   ├── Http/
│   │   ├── Controllers/           # 7 API controllers
│   │   └── Middleware/            # ResolveTallyConnection
│   ├── Models/                    # TallyConnection
│   ├── Providers/                 # TallyServiceProvider, RouteServiceProvider, EventServiceProvider
│   └── Services/
│       ├── TallyHttpClient.php    # HTTP POST to Tally
│       ├── TallyConnectionManager.php  # Multi-connection resolver
│       ├── TallyXmlBuilder.php    # Builds XML request envelopes
│       ├── TallyXmlParser.php     # Parses XML responses
│       ├── TallyCompanyService.php # AlterID queries, Function exports, FY detection
│       ├── Masters/               # 6 CRUD services
│       ├── Vouchers/              # VoucherService + VoucherType enum
│       └── Reports/               # ReportService
├── config/config.php              # Published as config('tally.*')
├── database/
│   ├── factories/                 # TallyConnectionFactory
│   └── migrations/                # tally_connections table
├── routes/api.php                 # 29 routes under /api/tally/
└── module.json                    # nwidart module manifest
```

## Architecture

### Request Flow

```
Controller (JSON) → Service → TallyXmlBuilder (XML) → TallyHttpClient (HTTP POST) → TallyPrime
TallyPrime → XML Response → TallyXmlParser (Array) → Service → Controller (JSON)
```

### Core Classes

| Class | Namespace | Purpose |
|-------|-----------|---------|
| `TallyHttpClient` | `Modules\Tally\Services` | HTTP POST to Tally. One instance per connection |
| `TallyConnectionManager` | `Modules\Tally\Services` | Singleton. Resolves code → cached client |
| `TallyXmlBuilder` | `Modules\Tally\Services` | Static. Builds XML envelopes (3 export types + import) |
| `TallyXmlParser` | `Modules\Tally\Services` | Static. Parses XML responses |
| `TallyConnection` | `Modules\Tally\Models` | Eloquent model for connections table |
| `ResolveTallyConnection` | `Modules\Tally\Http\Middleware` | Binds correct client per route |

### Service Classes

All services inject `TallyHttpClient` via constructor.

**Masters** (`Modules/Tally/app/Services/Masters/`):
- `LedgerService` — Account ledgers (Sundry Debtors, Creditors, Bank, Cash, etc.)
- `GroupService` — Account groups (Current Assets, Direct Expenses, etc.)
- `StockItemService` — Inventory items
- `StockGroupService` — Stock item categories
- `UnitService` — Measurement units (Nos, Kgs, Ltrs, etc.)
- `CostCenterService` — Cost/profit centers

**Vouchers** (`Modules/Tally/app/Services/Vouchers/`):
- `VoucherService` — Create, batch, list, alter, cancel, delete
- `VoucherType` — Enum: Sales, Purchase, Payment, Receipt, Journal, Contra, CreditNote, DebitNote

**Reports** (`Modules/Tally/app/Services/Reports/`):
- `ReportService` — Balance Sheet, P&L, Trial Balance, Ledger, Outstandings, Stock Summary, Day Book

## Incremental Sync (AlterID-Based)

TallyPrime tracks every change via AlterIDs — `AltMstId` increments when masters change, `AltVchId` when vouchers change. The module uses these to skip unnecessary syncs.

### How It Works

```
SyncMastersJob runs hourly →
  Queries Tally: "What's the current AltMstId?" →
  Compares with tally_connections.last_alter_master_id →
  If same → skip (zero data fetched) →
  If higher → full sync → update stored IDs
```

### PHP Usage

```php
use Modules\Tally\Services\TallyCompanyService;

$company = app(TallyCompanyService::class);

// Check if anything changed
$changes = $company->hasChangedSince(lastMasterId: 1500, lastVoucherId: 3200);
// ['masters_changed' => true, 'vouchers_changed' => false, 'current_master_id' => 1502, ...]

// Get raw AlterIDs
$ids = $company->getAlterIds();
// ['master_id' => 1502, 'voucher_id' => 3200]
```

### Force Sync (Skip AlterID Check)

```php
use Modules\Tally\Jobs\SyncMastersJob;

SyncMastersJob::dispatch('MUM', 'all', force: true);
```

## Function Exports

Invoke Tally built-in functions without fetching full datasets.

```php
$company = app(TallyCompanyService::class);

// Get financial year period
$period = $company->getFinancialYearPeriod();
// ['from' => '01-Apr-2025', 'to' => '31-Mar-2026']

// Call any Tally function
$result = $company->callFunction('$$NumStockItems');
// '42'
```

## Batch Voucher Listing

For companies with 100K+ transactions, split large exports into monthly batches:

```php
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

$vouchers = app(VoucherService::class);

// Single request (fine for <10K vouchers)
$sales = $vouchers->list(VoucherType::Sales, '20250401', '20260331');

// Monthly batches (for large datasets)
$sales = $vouchers->list(VoucherType::Sales, '20250401', '20260331', batchSize: 5000);
// Splits into 12 monthly requests, merges results automatically
```

## Usage Examples

### Creating a Ledger

```php
use Modules\Tally\Services\Masters\LedgerService;

$ledgerService = app(LedgerService::class);
$result = $ledgerService->create([
    'NAME' => 'Customer ABC',
    'PARENT' => 'Sundry Debtors',
    'OPENINGBALANCE' => '0',
]);
// $result = ['created' => 1, 'altered' => 0, 'errors' => 0, ...]
```

### Creating a Sales Voucher

```php
use Modules\Tally\Services\Vouchers\VoucherService;

$voucherService = app(VoucherService::class);
$result = $voucherService->createSales(
    date: '20260416',
    partyLedger: 'Customer ABC',
    salesLedger: 'Sales Account',
    amount: 10000.00,
    voucherNumber: '001',
    narration: 'Invoice #001',
);
```

### Cancelling a Voucher

```php
$result = $voucherService->cancel(
    date: '16-Apr-2026',
    voucherNumber: '001',
    type: VoucherType::Sales,
    narration: 'Cancelled due to incorrect amount',
);
```

### Fetching Reports

```php
use Modules\Tally\Services\Reports\ReportService;

$reportService = app(ReportService::class);
$balanceSheet = $reportService->balanceSheet('20260331');
$profitLoss = $reportService->profitAndLoss('20250401', '20260331');
```

## Multi-Company / Multi-Location

### tally_connections Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | |
| `name` | string | Display name ("Mumbai HQ") |
| `code` | string (unique) | URL code ("MUM") |
| `host` | string | TallyPrime hostname/IP |
| `port` | smallint | Port (default 9000) |
| `company_name` | string | Company in that Tally instance |
| `timeout` | smallint | HTTP timeout seconds |
| `is_active` | boolean | Whether usable |

### Setup

```
POST /api/tally/connections
{ "name": "Mumbai HQ", "code": "MUM", "host": "192.168.1.10", "port": 9000, "company_name": "ABC Enterprises" }
```

Then: `GET /api/tally/MUM/ledgers` or `POST /api/tally/MUM/vouchers`

### How It Works

1. Request hits `/api/tally/{connection}/...`
2. `ResolveTallyConnection` middleware looks up `code` in DB
3. Creates `TallyHttpClient` for that host/port/company
4. Rebinds in container — all injected services get the right client
5. Services work unchanged

## API Endpoints

### Connection Management
```
GET    /api/tally/connections                  → List all
POST   /api/tally/connections                  → Create { name, code, host, port, company_name }
GET    /api/tally/connections/{id}             → Get details
PUT    /api/tally/connections/{id}             → Update
DELETE /api/tally/connections/{id}             → Delete
GET    /api/tally/connections/{id}/health      → Health check
GET    /api/tally/health                       → Default health (.env config)
```

### Per-Connection — Masters
```
GET    /api/tally/{conn}/ledgers               → List ledgers
POST   /api/tally/{conn}/ledgers               → Create { NAME, PARENT, OPENINGBALANCE }
GET    /api/tally/{conn}/ledgers/{name}        → Get single (Object export)
PUT    /api/tally/{conn}/ledgers/{name}        → Update (Alter)
DELETE /api/tally/{conn}/ledgers/{name}        → Delete
```
Same pattern for `/groups`, `/stock-items`.

### Per-Connection — Vouchers
```
GET    /api/tally/{conn}/vouchers?type=Sales&from_date=20260101&to_date=20261231
POST   /api/tally/{conn}/vouchers              → { type: "Sales", data: { ... } }
GET    /api/tally/{conn}/vouchers/{masterID}
PUT    /api/tally/{conn}/vouchers/{masterID}   → { type: "Sales", data: { ... } }
DELETE /api/tally/{conn}/vouchers/{masterID}   → { type, date, voucher_number, action: "delete"|"cancel" }
```

### Per-Connection — Reports
```
GET /api/tally/{conn}/reports/balance-sheet?date=20260331
GET /api/tally/{conn}/reports/profit-and-loss?from=20250401&to=20260331
GET /api/tally/{conn}/reports/trial-balance?date=20260331
GET /api/tally/{conn}/reports/ledger?ledger=Cash&from=20250401&to=20260331
GET /api/tally/{conn}/reports/outstandings?type=receivable
GET /api/tally/{conn}/reports/stock-summary
GET /api/tally/{conn}/reports/day-book?date=20260416
```

## Error Handling

All endpoints return: `{ success: bool, data: mixed, message: string }`

| Scenario | Response |
|----------|----------|
| Tally unreachable | 503, connection error message |
| Import failed | 422, import result with errors count |
| Connection code not found | 404, from middleware |
| Invalid report type | 404, valid types listed in message |
| Invalid request | 422, Laravel validation errors |

## Key Conventions

- **Date format**: YYYYMMDD (e.g., `20260416`). Cancel/delete voucher dates use DD-Mon-YYYY (e.g., `16-Apr-2026`).
- **Object names**: Tally identifies everything by **name** (case-insensitive), not numeric ID.
- **Amount signs**: Debit entries = `ISDEEMEDPOSITIVE=Yes`, Credit entries = `ISDEEMEDPOSITIVE=No`. Sign varies by voucher type — see `.docs/tally-api-reference.md`.
- **Voucher type field**: Use `VOUCHERTYPENAME` (child element) for creation. Use `VCHTYPE` (attribute) for cancel/delete.

## Portability

```bash
# In a new Laravel project:
composer require nwidart/laravel-modules
# Copy Modules/Tally/ folder
# Add PSR-4 to composer.json autoload:
#   "Modules\\Tally\\": "Modules/Tally/app/"
#   "Modules\\Tally\\Database\\Factories\\": "Modules/Tally/database/factories/"
#   "Modules\\Tally\\Database\\Seeders\\": "Modules/Tally/database/seeders/"
composer dump-autoload
php artisan module:enable Tally
php artisan migrate
# Add TALLY_* to .env
```
