# Tally Integration Guide

## Overview

This Laravel app integrates with TallyPrime via its HTTP/XML API. TallyPrime runs as an HTTP server on a configurable port (default 9000). The app sends XML POST requests and receives XML responses.

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

## Environment Configuration

```env
TALLY_HOST=localhost        # TallyPrime server hostname/IP
TALLY_PORT=9000             # TallyPrime HTTP port
TALLY_COMPANY=MyCompany     # Target company name (empty = active company)
TALLY_TIMEOUT=30            # HTTP timeout in seconds
```

## Architecture

### Request Flow

```
Controller (JSON) → Service → TallyXmlBuilder (XML) → TallyHttpClient (HTTP POST) → TallyPrime
TallyPrime → XML Response → TallyXmlParser (Array) → Service → Controller (JSON)
```

### Core Classes

| Class | Purpose |
|-------|---------|
| `TallyHttpClient` | Sends XML to Tally via HTTP POST. One instance per connection. |
| `TallyConnectionManager` | Singleton. Resolves connection code → cached `TallyHttpClient` instance |
| `TallyXmlBuilder` | Static. Builds XML envelopes for import/export requests |
| `TallyXmlParser` | Static. Parses XML responses into PHP arrays |
| `TallyConnection` (model) | Eloquent model for `tally_connections` table |
| `ResolveTallyConnection` (middleware) | Reads `{connection}` route param, binds correct client |

### Service Classes

All services accept constructor-injected `TallyHttpClient` and follow the same CRUD pattern:

**Masters** (`app/Services/Tally/Masters/`):
- `LedgerService` — Account ledgers (Sundry Debtors, Creditors, Bank, Cash, etc.)
- `GroupService` — Account groups (Current Assets, Direct Expenses, etc.)
- `StockItemService` — Inventory items
- `StockGroupService` — Stock item categories
- `UnitService` — Measurement units (Nos, Kgs, Ltrs, etc.)
- `CostCenterService` — Cost/profit centers

**Vouchers** (`app/Services/Tally/Vouchers/`):
- `VoucherService` — Create, list, alter, delete vouchers of any type
- `VoucherType` — PHP enum: Sales, Purchase, Payment, Receipt, Journal, Contra, Credit Note, Debit Note

**Reports** (`app/Services/Tally/Reports/`):
- `ReportService` — Balance Sheet, Profit & Loss, Trial Balance, Ledger statements, Outstandings, Stock Summary, Day Book

## Usage Examples

### Creating a Ledger

```php
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
$voucherService = app(VoucherService::class);

$result = $voucherService->createSales(
    date: '20260416',
    partyLedger: 'Customer ABC',
    salesLedger: 'Sales Account',
    amount: 10000.00,
    narration: 'Invoice #001',
);
```

### Fetching Reports

```php
$reportService = app(ReportService::class);

$balanceSheet = $reportService->balanceSheet('20260331');
$profitLoss = $reportService->profitAndLoss('20250401', '20260331');
$trialBalance = $reportService->trialBalance();
```

## Multi-Company / Multi-Location

### Overview

The app supports multiple TallyPrime instances (different offices/locations) and multiple companies within each instance. Connections are stored in the `tally_connections` database table.

### tally_connections Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Display name ("Mumbai HQ", "Delhi Branch") |
| `code` | string (unique) | Short code used in URLs ("MUM", "DEL", "PUN") |
| `host` | string | TallyPrime hostname/IP |
| `port` | smallint | TallyPrime port (default 9000) |
| `company_name` | string | Target company name in that Tally instance |
| `timeout` | smallint | HTTP timeout in seconds |
| `is_active` | boolean | Whether this connection is usable |

### How It Works

1. **Register a connection**: `POST /api/tally/connections` with host, port, company, code
2. **Use in API calls**: All Tally endpoints are prefixed with `/{connection_code}/`
3. **Middleware resolves**: `ResolveTallyConnection` looks up the code, creates a `TallyHttpClient` for that connection, and binds it to the container for the request
4. **Services work unchanged**: They receive the correct `TallyHttpClient` via dependency injection

### Example Setup

```
POST /api/tally/connections
{ "name": "Mumbai HQ", "code": "MUM", "host": "192.168.1.10", "port": 9000, "company_name": "ABC Enterprises" }

POST /api/tally/connections
{ "name": "Delhi Branch", "code": "DEL", "host": "192.168.1.20", "port": 9000, "company_name": "ABC Delhi" }
```

Then use: `GET /api/tally/MUM/ledgers` or `GET /api/tally/DEL/vouchers?type=Sales`

## API Endpoints

### Connection Management
```
GET    /api/tally/connections                  → List all connections
POST   /api/tally/connections                  → Create { name, code, host, port, company_name }
GET    /api/tally/connections/{id}             → Get connection details
PUT    /api/tally/connections/{id}             → Update connection
DELETE /api/tally/connections/{id}             → Delete connection
GET    /api/tally/connections/{id}/health      → Health check for connection
```

### Default Health Check
```
GET /api/tally/health
→ Uses .env config (TALLY_HOST, TALLY_PORT)
```

### Per-Connection Operations (replace {conn} with connection code like MUM, DEL)

#### Masters
```
GET    /api/tally/{conn}/ledgers              → List all ledgers
POST   /api/tally/{conn}/ledgers              → Create ledger { NAME, PARENT, OPENINGBALANCE }
GET    /api/tally/{conn}/ledgers/{name}       → Get single ledger
PUT    /api/tally/{conn}/ledgers/{name}       → Update ledger
DELETE /api/tally/{conn}/ledgers/{name}       → Delete ledger
```

Same pattern for `/{conn}/groups` and `/{conn}/stock-items`.

#### Vouchers
```
GET    /api/tally/{conn}/vouchers?type=Sales&from_date=20260101&to_date=20261231
POST   /api/tally/{conn}/vouchers            → { type: "Sales", data: { DATE, PARTYLEDGERNAME, ... } }
GET    /api/tally/{conn}/vouchers/{masterID}
PUT    /api/tally/{conn}/vouchers/{masterID}  → { type: "Sales", data: { ... } }
DELETE /api/tally/{conn}/vouchers/{masterID}  → { type: "Sales" }
```

#### Reports
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

- **Connection error**: `RuntimeException` with message about Tally not running
- **Import errors**: `parseImportResult()` returns `errors` count > 0
- **Invalid XML**: `RuntimeException` from XML parsing failure

All API endpoints return consistent JSON: `{ success: bool, data: mixed, message: string }`.

## Date Format

TallyPrime uses **YYYYMMDD** format for all dates (e.g., `20260416` for April 16, 2026).

## Tally Object Names

Tally identifies objects by **name** (case-insensitive), not by numeric ID. Ledger names, group names, stock item names must exactly match what exists in Tally.
