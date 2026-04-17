# Services Reference

All services in `Modules/Tally/app/Services/`. Namespace: `Modules\Tally\Services\`.

## Core Services

### TallyHttpClient

**File**: `Modules/Tally/app/Services/TallyHttpClient.php`
**Binding**: `bind()` — one instance per connection. Default from config, overridden by middleware.

| Method | Params | Returns | Description |
|--------|--------|---------|-------------|
| `__construct` | `string $host, int $port, string $company, int $timeout` | | |
| `fromConfig` (static) | | `static` | Creates client from `config('tally.*')` |
| `sendXml` | `string $xml` | `string` | POST XML to Tally, returns response XML. Throws RuntimeException |
| `isConnected` | | `bool` | Tests connectivity |
| `getCompanies` | | `array<string>` | Lists companies loaded in Tally |
| `getUrl` | | `string` | Returns `http://host:port` |
| `getCompany` | | `string` | Returns configured company name |

### TallyConnectionManager

**File**: `Modules/Tally/app/Services/TallyConnectionManager.php`
**Binding**: `singleton`

| Method | Params | Returns | Description |
|--------|--------|---------|-------------|
| `resolve` | `string $code` | `TallyHttpClient` | Looks up connection by code, caches client |
| `fromConnection` | `TallyConnection $connection` | `TallyHttpClient` | Creates client from model |
| `flush` | `?string $code = null` | `void` | Clears cached clients (all or specific) |

### TallyXmlBuilder (Static)

**File**: `Modules/Tally/app/Services/TallyXmlBuilder.php`

| Method | Type | ID | Description |
|--------|------|-----|-------------|
| `buildExportRequest` | Data | Report name | Report exports (Balance Sheet, Trial Balance, etc.) |
| `buildCollectionExportRequest` | Collection | Collection name | List exports (Ledger, StockItem, etc.) |
| `buildObjectExportRequest` | Object | Object name | Single entity by name |
| `buildImportMasterRequest` | Data | All Masters | Create/alter/delete master objects |
| `buildImportVoucherRequest` | Data | Vouchers | Single voucher import |
| `buildBatchImportVoucherRequest` | Data | Vouchers | Batch voucher import |
| `buildCancelVoucherRequest` | Data | Vouchers | Cancel with attribute format |
| `buildDeleteVoucherRequest` | Data | Vouchers | Delete with attribute format |
| `buildDeleteMasterRequest` | Data | All Masters | Shortcut for master deletion |
| `buildFunctionExportRequest` | Function | Function name | Invoke Tally built-in functions ($$SystemPeriodFrom, etc.) |
| `buildAlterIdQueryRequest` | Data (TDL) | TallySyncReport | Query AltMstId/AltVchId for incremental sync |
| `arrayToXml` | — | — | Converts PHP array to XML tags |
| `escapeXml` | — | — | XML-safe string encoding |

### TallyXmlParser (Static)

**File**: `Modules/Tally/app/Services/TallyXmlParser.php`

| Method | Returns | Description |
|--------|---------|-------------|
| `parse(string $xml)` | `array` | Parse XML to array |
| `parseImportResult(string $xml)` | `array` | Extract {created, altered, deleted, errors, ...} |
| `isImportSuccessful(string $xml)` | `bool` | Errors=0 and something changed |
| `extractCollection(string $xml, string $type)` | `array` | Extract list from collection export |
| `extractObject(string $xml, string $type)` | `?array` | Extract single object from object export |
| `extractReport(string $xml)` | `array` | Extract report data |
| `extractCompanyList(string $xml)` | `array<string>` | Extract company names |
| `extractErrors(string $xml)` | `array` | Extract LINEERROR messages |
| `isSuccessResponse(string $xml)` | `bool` | Check STATUS=1 header |

## Company Service

**File**: `Modules/Tally/app/Services/TallyCompanyService.php`

| Method | Returns | Description |
|--------|---------|-------------|
| `getAlterIds()` | `array{master_id: int, voucher_id: int}` | Get current AlterIDs from Tally for incremental sync |
| `hasChangedSince(int $lastMasterId, int $lastVoucherId)` | `array{masters_changed, vouchers_changed, current_master_id, current_voucher_id}` | Check if data changed since last sync |
| `callFunction(string $name, array $params)` | `string` | Invoke any Tally built-in function ($$SystemPeriodFrom, $$NumStockItems, etc.) |
| `getFinancialYearPeriod()` | `array{from: string, to: string}` | Get active financial year dates from Tally |

## Master Services (6 services, identical CRUD pattern)

All in `Modules/Tally/app/Services/Masters/`. All inject `TallyHttpClient` via constructor.

| Service | File | Object Type | Collection ID |
|---------|------|-------------|---------------|
| LedgerService | `Masters/LedgerService.php` | LEDGER | Ledger |
| GroupService | `Masters/GroupService.php` | GROUP | Group |
| StockItemService | `Masters/StockItemService.php` | STOCKITEM | Stock Item |
| StockGroupService | `Masters/StockGroupService.php` | STOCKGROUP | Stock Group |
| UnitService | `Masters/UnitService.php` | UNIT | Unit |
| CostCenterService | `Masters/CostCenterService.php` | COSTCENTRE | Cost Centre |

**Common methods** (all 6 services):

| Method | Returns | XML Method Used |
|--------|---------|----------------|
| `list()` | `array` | `buildCollectionExportRequest` |
| `get(string $name)` | `?array` | `buildObjectExportRequest` |
| `create(array $data)` | `array` (import result) | `buildImportMasterRequest` (Create) |
| `update(string $name, array $data)` | `array` | `buildImportMasterRequest` (Alter) |
| `delete(string $name)` | `array` | `buildDeleteMasterRequest` |

## Voucher Service

**File**: `Modules/Tally/app/Services/Vouchers/VoucherService.php`
**Enum**: `Modules/Tally/app/Services/Vouchers/VoucherType.php` — Sales, Purchase, Payment, Receipt, Journal, Contra, CreditNote, DebitNote

| Method | Returns | Description |
|--------|---------|-------------|
| `list(VoucherType, ?from, ?to, ?batchSize)` | `array` | List vouchers by type/date range. With batchSize, splits into monthly requests for large datasets |
| `get(string $masterID)` | `?array` | Get single voucher |
| `create(VoucherType, array $data)` | `array` | Create voucher |
| `createBatch(VoucherType, array $vouchers)` | `array` | Batch create |
| `alter(string $masterID, VoucherType, array $data)` | `array` | Modify voucher |
| `cancel(string $date, string $voucherNumber, VoucherType, ?narration)` | `array` | Cancel (audit-friendly) |
| `delete(string $date, string $voucherNumber, VoucherType)` | `array` | Delete permanently |
| `createSales(date, party, sales, amount, ?number, ?narration, ?inventory)` | `array` | Convenience |
| `createPurchase(date, party, purchase, amount, ?number, ?narration, ?inventory)` | `array` | Convenience |
| `createPayment(date, paymentLedger, party, amount, ?number, ?narration)` | `array` | Convenience |
| `createReceipt(date, receivingLedger, party, amount, ?number, ?narration)` | `array` | Convenience |
| `createJournal(date, debitEntries, creditEntries, ?number, ?narration)` | `array` | Convenience |

## Report Service

**File**: `Modules/Tally/app/Services/Reports/ReportService.php`

| Method | Params | Report ID |
|--------|--------|-----------|
| `balanceSheet(?date)` | date: YYYYMMDD | Balance Sheet |
| `profitAndLoss(from, to)` | dates: YYYYMMDD | Profit and Loss A/c |
| `trialBalance(?date)` | date: YYYYMMDD | Trial Balance |
| `ledgerReport(ledgerName, from, to)` | | Ledger Vouchers |
| `outstandings(type)` | 'receivable' or 'payable' | Bills Receivable / Bills Payable |
| `stockSummary()` | | Stock Summary |
| `dayBook(date)` | date: YYYYMMDD | Day Book |
