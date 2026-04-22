# Services Reference

Canonical reference for every service class under `Modules/Tally/app/Services/`. Namespace: `Modules\Tally\Services\*`.

**Last verified:** 2026-04-20 (Phase 9N — TallyFieldRegistry + 5 new master endpoints)

---

## Core

### `TallyHttpClient`

Low-level HTTP transport to TallyPrime (port 9000 by default).

Constructor: `__construct(string $host, int $port, string $company = '', int $timeout = 30)`
Static factory: `fromConfig(): static`

| Method | Returns | Purpose |
|---|---|---|
| `sendXml(string $xml)` | `string` | POST XML → returns raw response XML. Throws `TallyConnectionException` on network error. Logs via `TallyRequestLogger`. |
| `isConnected()` | `bool` | Health probe (exports `List of Companies`). |
| `getCompanies()` | `array<string>` | Returns company names loaded in Tally. |
| `getCompany()` | `string` | Active default company. |
| `getUrl()` | `string` | Full Tally URL. |

### `TallyXmlBuilder` (all static)

Builds Tally-compliant XML envelopes. Canonical format verified against `.docs/Demo Samples/`.

| Method | Purpose |
|---|---|
| `buildExportRequest($reportName, $fetchFields, $filters, $company)` | Data export (reports, Balance Sheet, P&L, Trial Balance). |
| `buildCollectionExportRequest($collectionType, $fetchFields, $filters, $company, $explode=true)` | Built-in Collection export. Use **only** for collections TallyPrime ships with: `List of Ledgers`, `List of Groups`, `List of Stock Items`, `List of Stock Groups`. For every other master use `buildAdHocCollectionExportRequest` instead. Always spell with spaces (`List of Stock Categories`, NOT `List of StockCategories`). |
| `buildAdHocCollectionExportRequest($collectionName, $tallyType, $fetchFields, $company)` | Inline-TDL Collection export. Body carries `<TDL><TDLMESSAGE><COLLECTION>` defining the collection on the fly — **the only reliable way** to fetch Units, Cost Centres, Currencies, Godowns, Voucher Types, Stock Categories, Price Levels, since none have a built-in `List of X` in TallyPrime. **Two strict rules:** (1) `$tallyType` uses the **concatenated** form for multi-word masters (`CostCentre`, `VoucherType`, `StockCategory`, `PriceLevel`, `StockItem`, `StockGroup`). Confirmed against production TDL (`laxmantandon/tally_migration_tdl/send/*.txt` — `Type : StockItem`, `Type : StockGroup`). Object SUBTYPEs use spaces; TDL `<TYPE>` uses concatenated — they are different conventions for different XML contexts. (2) `$fetchFields` are emitted as one `<NATIVEMETHOD>` per field (not comma-separated `<FETCH>`) per Tally docs Sample 16; unknown method names crash Tally with a comma-FETCH but are silently tolerated as NATIVEMETHOD. Keep `$fetchFields` minimal — `['NAME']` is the safest universal set. |
| `buildObjectExportRequest($objectType, $objectName, $fetchFields, $company)` | Single object export (`BinaryXML` format). |
| `buildImportMasterRequest($objectType, $data, $action, $company)` | Master import — `LEDGER`, `GROUP`, `STOCKITEM`, `UNIT`, `STOCKGROUP`, `COSTCENTRE`. Action = `Create` / `Alter`. |
| `buildImportVoucherRequest($data, $action, $company)` | Single voucher import. |
| `buildBatchImportVoucherRequest($vouchers, $action, $company)` | Multiple vouchers in one `TALLYMESSAGE`. |
| `buildCancelVoucherRequest($date, $number, $type, $narration, $company)` | Cancel (attribute format: `ACTION=Cancel`). |
| `buildDeleteVoucherRequest($date, $number, $type, $company)` | Delete (attribute format: `ACTION=Delete`). |
| `buildFunctionExportRequest($functionName, $params)` | `TYPE=Function` — e.g. `$$SystemPeriodFrom`. |
| `buildAlterIdQueryRequest($company)` | TDL-based AlterID report for incremental sync. |
| `buildDeleteMasterRequest($objectType, $name, $company)` | Master delete (action `Delete`). |
| `buildCompanyAlterRequest($companyName, $companyData, $company)` *(Phase 9M)* | ALTER against the Company master — used for fields that live on Company (e.g. `PRICELEVELLIST.LIST`) rather than as standalone masters. |
| `withWebStatus($data, $status, $message, $docName)` *(Phase 9M)* | Append `WebStatus` / `WebStatus_Message` / `WebStatus_DocName` UDF entries to a master/voucher payload. Companion to the optional TDL file at `Modules/Tally/scripts/tdl/TallyModuleIntegration.txt`. Safe when TDL is not installed — Tally silently ignores unknown UDFs. |
| `arrayToXml(array $data)` | Recursively converts PHP array → XML. |
| `escapeXml(string $value)` | `htmlspecialchars` with `ENT_XML1`. |

**Key invariants** (from `tasks/lessons.md`):
- Header uses `<VERSION>1</VERSION>` + `<TYPE>` + `<ID>` (NOT `<TALLYREQUEST>Export Data</TALLYREQUEST>`).
- All Data/Collection exports include `<EXPLODEFLAG>Yes</EXPLODEFLAG>` (default) and `<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>`. Services that list rows referencing other rows by name opt out with `EXPLODEFLAG=No` + explicit `FETCHLIST` to avoid a memory-access violation in TallyPrime: `UnitService::list()`, `StockItemService::list()`, `StockGroupService::list()`, `VoucherTypeService::list()`.
- Object exports default to `<SVEXPORTFORMAT>BinaryXML</SVEXPORTFORMAT>`. Admins can flip to `$$SysName:XML` via `TALLY_OBJECT_EXPORT_FORMAT=SysName` when BinaryXML crashes Tally on specific subtypes (`config/config.php` → `object_export_format`).
- **Every master `get($name)` filters from the cached `list()` — no Object exports.** Object exports of `<SUBTYPE>X</SUBTYPE>` have proven unreliable across master types (Group / Stock Group hang at 30s; Unit crashes TallyPrime entirely on `Nos` lookup, reproduced 2026-04-19). Filter-from-list reuses the safe Collection export already wired into the index endpoint and is O(1) once cached. Migrated services: GroupService, StockGroupService, UnitService, StockItemService, VoucherTypeService, CostCenterService, CurrencyService, GodownService, StockCategoryService, PriceListService, LedgerService.
- `TallyXmlParser::xmlToArray()` preserves text content under a `#text` key when an element has both attributes and text; pure leaf elements still return a plain string. Tally stamps `TYPE="String"` / `TYPE="Logical"` on nearly every leaf, so this is essential for PARENT / ISREVENUE / BASEUNITS values to reach the client. Example: `<PARENT TYPE="String">Primary</PARENT>` → `['@attributes' => ['TYPE' => 'String'], '#text' => 'Primary']`.
- `TallyHttpClient::getCompanies()` and `isConnected()` intentionally send the List-of-Companies export with **no** `SVCURRENTCOMPANY` — pinning scopes the global collection away from the thing being asked.
- `TallyXmlBuilder::resolveDefaultCompany()` resolves the `<SVCURRENTCOMPANY>` pin from the request-scoped `TallyHttpClient` (rebound by `ResolveTallyConnection` middleware), falling back to `config('tally.company')`. All master/voucher/report calls get the right pin without threading `$company` through every call-site. Pass `company: ''` explicitly to skip the pin for global collections.
- Voucher creation uses `VOUCHERTYPENAME` child element, not `VCHTYPE` attribute.
- Voucher cancel/delete uses **attribute** format on `<VOUCHER>` (DATE, TAGNAME, TAGVALUE, VCHTYPE, ACTION).
- Voucher import uses `<TALLYREQUEST>Import</TALLYREQUEST>` + `<TYPE>Data</TYPE>` + `<ID>Vouchers</ID>`.

### `TallyXmlParser` (all static)

| Method | Returns | Purpose |
|---|---|---|
| `parse(string $xml)` | `array` | XML → nested array. Removes BOM, sanitizes. |
| `parseImportResult(string $xml)` | `array` | `{created, altered, deleted, errors, cancelled, lastvchid, lastmid, combined, ignored}`. |
| `isImportSuccessful(string $xml)` | `bool` | `errors == 0` and at least one create/alter/delete. |
| `extractCollection($xml, $objectType)` | `array` | Extract `LEDGER[]`, `GROUP[]`, etc. |
| `extractObject($xml, $objectType)` | `?array` | Extract single object. |
| `extractErrors(string $xml)` | `array` | `LINEERROR` nodes. |
| `extractCompanyList(string $xml)` | `array` | Company names. |
| `extractReport(string $xml)` | `array` | Raw report data. |
| `isSuccessResponse(string $xml)` | `bool` | `HEADER.STATUS == '1'`. |

### `TallyConnectionManager`

In-memory cache of `TallyHttpClient` instances per connection code.

| Method | Purpose |
|---|---|
| `resolve(string $code)` | Return (and cache) a `TallyHttpClient` for a connection code. |
| `fromConnection(TallyConnection $conn)` | Build a client from a model. |
| `flush(?string $code = null)` | Clear cache (one code or all). Called on update/delete. |

### `TallyRequestLogger`

Request/response logging to the `tally` log channel.

| Method | Purpose |
|---|---|
| `log($requestXml, $responseXml, $durationMs, $connectionCode)` | Log success; truncates bodies at `logging.max_body_size`. |
| `logError($requestXml, $error, $durationMs, $connectionCode)` | Log failure. |

### `TallyCompanyService`

High-level company/incremental-sync helpers built on `TallyHttpClient`.

| Method | Returns | Purpose |
|---|---|---|
| `getAlterIds()` | `['master_id' => int, 'voucher_id' => int]` | Query current AlterIDs via TDL. |
| `callFunction(string $name, array $params = [])` | `string` | Invoke a Tally function (e.g. `$$SystemPeriodFrom`). |
| `getFinancialYearPeriod()` | `['from' => 'YYYYMMDD', 'to' => 'YYYYMMDD']` | Auto-detect FY. |
| `hasChangedSince($lastMasterId, $lastVoucherId)` | `array` | `{master_changed, voucher_changed, current_master_id, current_voucher_id}`. |

### `AuditLogger`

| Method | Purpose |
|---|---|
| `log($action, $objectType, $objectName, $requestData, $responseData)` | Insert row into `tally_audit_logs` (resolves connection + user from current request). |

### `MetricsCollector`

| Method | Purpose |
|---|---|
| `record($endpoint, $responseTimeMs, $status, $connectionCode)` | Insert row into `tally_response_metrics`. |
| `getStats($connectionId, $period = '1h')` | Returns `{total, avg_ms, p95_ms, max_ms, error_rate}` for `1h`, `24h`, or `7d`. |

### `SyncTracker`

| Method | Purpose |
|---|---|
| `track($connectionId, $entityType, $entityId, $direction, $priority, $tallyName)` | Create/update a `tally_syncs` row. |
| `markDirty($connectionId, $entityType, $entityId, $localDataHash)` | Flag an entity pending if hash changed. |
| `detectChange(TallySync $sync, $currentLocalHash, $currentTallyHash)` | Returns `none`, `local_changed`, `tally_changed`, or `conflict`. |
| `getPending($connectionId, $limit = 50)` | Collection filtered by `isDueForRetry()`. |
| `getConflicts($connectionId)` | Collection of conflict rows. |
| `resolveConflict(TallySync $sync, $strategy, $resolvedBy)` | Mark pending with strategy. |
| `stats($connectionId)` | `TallySync::statsForConnection()` counts. |
| `static priorityForType($entityType, $voucherType)` | Priority heuristic: `Payment/Receipt` = critical, `Sales/Purchase` = high. |

### `CircuitBreaker`

Fail-fast cache-backed breaker (5 failures → open, 60s recovery). Wired into `TallyHttpClient::sendXml()` — every request calls `assertAvailable($code)` before the HTTP call and `recordSuccess` / `recordFailure` around it. A `TallyResponseException` (HTTP failure) or `TallyConnectionException` (cURL error / timeout / connect-reset) counts as a failure; after `failure_threshold` the breaker opens and subsequent calls short-circuit without waiting on the TCP timeout.

| Method | Purpose |
|---|---|
| `isAvailable(string $code)` | `true` when closed or half-open (probe). |
| `recordSuccess(string $code)` | Reset failure count, set `closed`. |
| `recordFailure(string $code)` | Increment; open at threshold. |
| `getState(string $code)` | Returns `closed` / `open` / `half-open`. |
| `assertAvailable(string $code)` | Throws `TallyConnectionException` if not available. |

---

## Masters (`Services/Masters/`)

All master services share the same shape (`list`, `get`, `create`, `update`, `delete`), use the `CachesMasterData` trait, and write an audit log on successful write. All are exposed via REST.

| Service | Controller | Tally object | Collection name | Cache key prefix |
|---|---|---|---|---|
| `LedgerService` | `LedgerController` | `LEDGER` | `List of Ledgers` | `ledger:*` |
| `GroupService` | `GroupController` | `GROUP` | `List of Groups` | `group:*` |
| `StockItemService` | `StockItemController` | `STOCKITEM` | `List of StockItems` | `stock-item:*` |
| `StockGroupService` | `StockGroupController` *(Phase 9A)* | `STOCKGROUP` | `List of Stock Groups` | `stockgroup:*` |
| `UnitService` | `UnitController` *(Phase 9A)* | `UNIT` | `List of Units` | `unit:*` |
| `CostCenterService` | `CostCenterController` *(Phase 9A)* | `COSTCENTRE` | `List of CostCentres` | `cost-centre:*` |
| `CurrencyService` | `CurrencyController` *(Phase 9B)* | `CURRENCY` | `List of Currencies` | `currency:*` |
| `GodownService` | `GodownController` *(Phase 9B)* | `GODOWN` | `List of Godowns` | `godown:*` |
| `VoucherTypeService` | `VoucherTypeController` *(Phase 9B)* | `VOUCHERTYPE` | `List of VoucherTypes` | `vouchertype:*` |
| `StockCategoryService` | `StockCategoryController` *(Phase 9F)* | `STOCKCATEGORY` | `List of StockCategories` | `stockcategory:*` |
| `PriceListService` | `PriceListController` *(Phase 9F / refactor Phase 9M)* | `COMPANY.PRICELEVELLIST` sub-list | Object export `Company` with `FETCH PRICELEVELLIST` | `pricelevel:*` — list/get via Company object; create via COMPANY ALTER (`buildCompanyAlterRequest`); update/delete return not-supported (Tally XML limitation). Confirmed against reference integrations (laxmantandon/express_tally, aadil-sengupta/Tally.Py both skip price levels). |
| `CostCategoryService` *(Phase 9N)* | `CostCategoryController` | `COSTCATEGORY` | inline TDL `CostCategory` | `costcategory:*` |
| `EmployeeGroupService` *(Phase 9N)* | `EmployeeGroupController` | `COSTCENTRE` (CATEGORY-flagged) | inline TDL `CostCentre` filtered by CATEGORY | `employeegroup:*` |
| `EmployeeCategoryService` *(Phase 9N)* | `EmployeeCategoryController` | `COSTCATEGORY` | inline TDL `CostCategory` | `employeecategory:*` |
| `EmployeeService` *(Phase 9N)* | `EmployeeController` | `EMPLOYEE` | inline TDL `Employee` | `employee:*` |
| `AttendanceTypeService` *(Phase 9N)* | `AttendanceTypeController` | `ATTENDANCETYPE` | inline TDL `AttendanceType` | `attendancetype:*` |

**Shared method signatures:**

```php
public function list(): array
public function get(string $name): ?array
public function create(array $data): array
public function update(string $name, array $data): array
public function delete(string $name): array
```

Return value of `create/update/delete` comes from `TallyXmlParser::parseImportResult()` — `{created, altered, deleted, errors, lastvchid, lastmid, ...}`.

---

## Vouchers (`Services/Vouchers/`)

### `VoucherType` (enum, string-backed)

Core: `Sales`, `Purchase`, `Payment`, `Receipt`, `Journal`, `Contra`, `CreditNote`, `DebitNote`.

Phase 9F: `SalesOrder`, `PurchaseOrder`, `Quotation`, `DeliveryNote`, `ReceiptNote`, `RejectionIn`, `RejectionOut`, `StockJournal`, `PhysicalStock` — all work via the existing `POST /{c}/vouchers` endpoint once the enum accepts them.

Phase 9G: `ManufacturingJournal`, `JobWorkInOrder`, `JobWorkOutOrder` — same pattern.

### `VoucherService`

| Method | Purpose |
|---|---|
| `list(VoucherType $type, ?string $fromDate, ?string $toDate, ?int $batchSize)` | List vouchers; splits into monthly batches when `batchSize` set. |
| `get(string $masterID)` | Fetch one by master ID. |
| `create(VoucherType $type, array $data)` | Single create. Auto-fills `PERSISTEDVIEW`+`OBJVIEW` to `Invoice Voucher View` when `$data['ISINVOICE']==='Yes'` (caller values preserved). |
| `createBatch(VoucherType $type, array $vouchers)` | Multiple in one request. Same invoice-mode auto-fill per row. |
| `alter(string $masterID, VoucherType $type, array $data)` | Alter. Same invoice-mode auto-fill. |
| `cancel(string $date, string $number, VoucherType $type, ?string $narration)` | Cancel (reversible). XML uses `TAGNAME="VoucherNumber"` (compressed, per Tally Cancel sample). |
| `delete(string $date, string $number, VoucherType $type)` | Permanent delete. XML uses `TAGNAME="Voucher Number"` (spaced, per Alter sample — no canonical Delete sample exists). |
| `createSales / createPurchase / createPayment / createReceipt / createJournal` | Convenience helpers with simplified args. |
| `createStockTransfer(...)` *(9F)* | Stock journal between godowns — two inventory entries (source − / destination +) with `BATCHALLOCATIONS.LIST`. |
| `createPhysicalStock(...)` *(9F)* | Physical stock voucher for inventory count adjustment. |

---

## Reports (`Services/Reports/`)

### `ReportService`

| Method | Tally report name |
|---|---|
| `balanceSheet(?string $date)` | `Balance Sheet` |
| `profitAndLoss(string $from, string $to)` | `Profit and Loss A/c` |
| `trialBalance(?string $date)` | `Trial Balance` |
| `ledgerReport(string $ledger, string $from, string $to)` | `Ledger Vouchers` |
| `outstandings(string $type = 'receivable')` | `Bills Receivable` / `Bills Payable` |
| `stockSummary()` | `Stock Summary` |
| `dayBook(string $date)` | `Day Book` |
| `cashBankBook(string $ledger, string $from, string $to)` *(9B)* | `Cash/Bank Book` |
| `salesRegister(string $from, string $to)` *(9B)* | `Voucher Register` filtered by `Sales` |
| `purchaseRegister(string $from, string $to)` *(9B)* | `Voucher Register` filtered by `Purchase` |
| `agingAnalysis(string $type, ?string $asOf)` *(9B)* | `Bills Receivable` / `Bills Payable` with `SHOWAGEWISE=Yes` |
| `cashFlow(string $from, string $to)` *(9B)* | `Cash Flow` |
| `fundsFlow(string $from, string $to)` *(9B)* | `Funds Flow` |
| `receiptsPayments(string $from, string $to)` *(9B)* | `Receipts and Payments` |
| `stockMovement(string $stockItem, string $from, string $to)` *(9B)* | `Stock Item Movement Analysis` |
| `bankReconciliation(string $bank, string $from, string $to)` *(9D)* | `Bank Reconciliation` |
| `chequeRegister(string $from, string $to)` *(9D)* | `Cheque Register` |
| `postDatedCheques(string $from, string $to)` *(9D)* | `Post-Dated Summary` |

Date format: `YYYYMMDD`.

---

## Integration (`Services/Integration/`) — *Phase 9I*

| Service | Purpose |
|---|---|
| `PdfService` | mpdf-based HTML → PDF. `renderVoucher($voucher, $companyName)` returns binary PDF. `htmlToPdf($html, $title)` for generic use. Uses `dejavusans` font, paper size from config. |
| `MailService` | Sends voucher PDF via Laravel's configured mailer. `sendVoucher($voucher, $recipients, $companyName)`. Recipients: `{to, cc?, bcc?, subject?, body?}`. |
| `AttachmentService` | Voucher attachments. `upload`, `list`, `stream`, `delete`. Uses disk from `tally.integration.attachments.disk`. |
| `ImportService` | Master CSV bulk-import. `queueImport($conn, $entity, UploadedFile, $userId)` → `TallyImportJob`. `run(TallyImportJob $job)` parses CSV and calls master services row-by-row. |
| `WebhookDispatcher` | `queue($endpoint, $event, $payload)` → `TallyWebhookDelivery`. `deliver($delivery)` POSTs with HMAC-SHA256 signature, handles exponential backoff based on `tally.integration.webhooks.backoff_seconds`. |

Events dispatched as webhooks (via `DispatchWebhooksOnTallyEvent` listener in `EventServiceProvider::$listen`):

| Event class | Webhook event name |
|---|---|
| `TallyMasterCreated` | `master.created` |
| `TallyMasterUpdated` | `master.updated` |
| `TallyMasterDeleted` | `master.deleted` |
| `TallyVoucherCreated` | `voucher.created` |
| `TallyVoucherAltered` | `voucher.altered` |
| `TallyVoucherCancelled` | `voucher.cancelled` |
| `TallySyncCompleted` | `sync.completed` |
| `TallyConnectionHealthChanged` | `connection.health_changed` |

Endpoints can subscribe to `'*'` to receive all events.

---

## Consolidation (`Services/Consolidation/`) — *Phase 9K*

### `ConsolidationService`

Fans out a report across every active connection belonging to a `TallyOrganization` and returns a packaged envelope with per-connection breakdown.

| Method | Purpose |
|---|---|
| `consolidatedBalanceSheet(TallyOrganization $org, ?string $date)` | Balance sheet across all org connections. |
| `consolidatedProfitAndLoss(TallyOrganization $org, string $from, string $to)` | P&L across all org connections. |
| `consolidatedTrialBalance(TallyOrganization $org, ?string $date)` | Trial balance across all org connections. |

Each return envelope:
```
{ organization: {id, code, name, base_currency},
  connection_count: N, successful: M,
  breakdown: [ { connection: {...}, success: bool, data?: report, error?: string }, ... ] }
```

A single failing connection does not abort the whole consolidation — it's captured in `breakdown[].error`.

---

## Manufacturing (`Services/Manufacturing/`) — *Phase 9G*

### `ManufacturingService`

BOM is stored on the finished stock item's `COMPONENTLIST.LIST` (not a separate master). Manufacturing/job-work vouchers delegate to `VoucherService::create` with the new enum cases.

| Method | Purpose |
|---|---|
| `getBom(string $finishedItem): ?array` | Read components from stock item's `COMPONENTLIST.LIST`; returns `[{name, qty, unit}]` or `null` if item not found. |
| `setBom(string $finishedItem, array $components): array` | ALTER stock item to set `COMPONENTLIST.LIST`. Audit-logged. |
| `createManufacturingVoucher(...)` | Manufacturing Journal — one production line (+ qty finished) + N consumption lines (− qty raw) with `BATCHALLOCATIONS.LIST` per component. |
| `createJobWorkOut(...)` | Job Work Out Order — goods sent to external processor. |
| `createJobWorkIn(...)` | Job Work In Order — processed goods received back. |

---

## Workflow / Approvals *(Phase 9J)*

### `WorkflowService`

State machine over `tally_draft_vouchers`. Drafts flow: `draft → submitted → approved → pushed` (or `→ rejected`).

| Method | Purpose |
|---|---|
| `requiresApproval(TallyDraftVoucher $draft): bool` | Checks `config('tally.workflow.approval_thresholds')`. Empty array = always require approval. |
| `submit($draft, ?int $userId)` | `draft → submitted`. If below all thresholds, auto-approves + pushes to Tally in one call. |
| `approve($draft, ?int $approverId, bool $autoApproved = false)` | `submitted → approved → pushed` via `VoucherService::create`. Blocks self-approval when `config('tally.workflow.require_distinct_approver')` is true (default). |
| `reject($draft, string $reason, ?int $rejectorId)` | Terminal `submitted → rejected` with reason. |

**Permissions:** maker uses `ManageVouchers`; approver uses the new `ApproveVouchers` enum case. Both permissions can be granted to the same user (typical: admin); separate users enforce maker-checker segregation.

**Config** (`Modules/Tally/config/config.php`):

```php
'workflow' => [
    'enabled' => env('TALLY_WORKFLOW_ENABLED', true),
    'approval_thresholds' => [
        ['type' => 'Payment', 'amount' => 100000],
        ['type' => 'Journal', 'amount' => 250000],
    ],
    'require_distinct_approver' => true,
],
```

---

## Recurring Voucher Scheduling *(Phase 9L)*

### `RecurringVoucherService`

Advances scheduled voucher templates stored in `tally_recurring_vouchers`.

| Method | Purpose |
|---|---|
| `fire(TallyRecurringVoucher $recurring): array` | Inject `DATE` into template → call `VoucherService::create()` → advance `next_run_at` → update `last_run_at` + `last_run_result`. Deactivates the row once past `end_date`. |
| `calculateNextRun($recurring, ?CarbonImmutable $from = null): CarbonImmutable` | Next tick based on frequency: daily/weekly/monthly/quarterly/yearly. Clamps `day_of_month` to ≤28 (avoids Feb-30 issues). |
| `bootstrapNextRun($recurring): CarbonImmutable` | Initial `next_run_at` when a row is first created (finds first valid firing on/after `start_date`). |

Frequency values: `daily`, `weekly`, `monthly`, `quarterly`, `yearly`.

---

## Banking (`Services/Banking/`) — *Phase 9D*

### `BankingService`

Handles reconciliation (ALTER voucher with BANKERDATE), cheque operations, and bank-statement CSV parsing + matching.

| Method | Purpose |
|---|---|
| `reconcile($voucherNumber, $voucherDate, VoucherType $type, $statementDate, $bankLedger)` | Mark voucher as reconciled with bank statement (sets `BANKERDATE` in `BANKALLOCATIONS.LIST`). |
| `unreconcile($voucherNumber, $voucherDate, VoucherType $type, $bankLedger)` | Clear `BANKERDATE` (empty string). |
| `parseStatement(string $csv): array` | Parse CSV with headers: `date, description, debit, credit, amount, reference, cheque_number` (case-insensitive). |
| `findMatches(array $rows, array $vouchers, int $dateToleranceDays = 3): array` | Match statement rows to vouchers by amount + date tolerance. Returns entries with `confidence: exact/high/low`. |
| `batchReconcile(array $entries): array` | Apply multiple reconciliations, returns `{reconciled, failed, errors}`. |

---

## Field Registry *(Phase 9N)*

### `TallyFieldRegistry` (all static, `Modules\Tally\Services\Fields\`)

Canonical XML tag + TallyPrime-UI alias map for every master and voucher. 316 mappings across 14 entity types. Case- and whitespace-insensitive lookup.

| Method | Purpose |
|---|---|
| `canonicalize(string $entity, array $data): array` | Rewrite every key from any alias to canonical XML tag. Unknown keys pass through unchanged (safe for UDFs / custom fields). |
| `canonicalFields(string $entity): array<string>` | All canonical field names known for an entity. |
| `aliasesFor(string $entity, string $canonical): array<string>` | Every documented alias for a canonical field. |

**Entity constants:** `GROUP`, `LEDGER`, `COST_CENTRE`, `COST_CATEGORY`, `STOCK_GROUP`, `STOCK_CATEGORY`, `UNIT`, `GODOWN`, `STOCK_ITEM`, `EMPLOYEE_GROUP`, `EMPLOYEE_CATEGORY`, `EMPLOYEE`, `ATTENDANCE_TYPE`, `VOUCHER`.

**Where it runs:**
- Every master service's `create()` / `update()` canonicalises first.
- `VoucherService::create / createBatch / alter` canonicalises first.
- `AcceptsFieldAliases` trait (in `Http/Requests/Concerns/`) canonicalises inside Form Request `prepareForValidation()`.

Full field reference: `Modules/Tally/docs/FIELD-REFERENCE.md`.

---

## Master mappings + naming series *(Phase 9M)*

### `TallyMasterMapping` model

Per-connection Tally-name ↔ ERP-name alias. Table `tally_master_mappings`. Pattern borrowed from `laxmantandon/tally_migration_tdl` (CustomerMappingTool / ItemMappingTool).

| Method | Purpose |
|---|---|
| `resolveTallyName(int $connectionId, string $entityType, string $erpName)` | Look up the Tally name given the ERP name. |
| `resolveErpName(int $connectionId, string $entityType, string $tallyName)` | Reverse lookup. |

Controller: `MasterMappingController` — GET list / POST upsert / DELETE. Routes gated by `manage_connections`.

### `TallyVoucherNamingSeries` model

One voucher type → multiple numbering streams (`SI/2026/`, `SINV/`). Table `tally_voucher_naming_series`. Column `naming_series` also added to `tally_vouchers`.

| Method | Purpose |
|---|---|
| `nextNumber(): string` | Atomic increment of `last_number`; returns `prefix + number + suffix`. |

Controller: `VoucherNamingSeriesController`. Routes gated by `manage_connections`.

### Sync exception report + reset

`SyncController::exceptions` — filtered list of failed/conflict syncs per connection.
`SyncController::resetStatus` — bulk reset failed/conflict rows to `pending`, clearing error messages. Mirrors `tally_migration_tdl`'s EXCEPTION_Reports.txt + "Reset Status" button.

---

## Concerns (traits)

### `CachesMasterData`

| Method | Purpose |
|---|---|
| `cachedList(string $key, Closure $fetcher)` | Remember a list for `tally.cache.ttl`. |
| `cachedGet(string $key, Closure $fetcher)` | Remember a single item. |
| `invalidateCache(string ...$keys)` | Forget keys (called on create/update/delete). |

### `PaginatesResults`

| Method | Purpose |
|---|---|
| `paginate(array $items, Request $request)` | In-memory paginator with `search`, `sort_by`, `sort_dir`, `page`, `per_page`. Returns `{data, meta}`. |

---

## Jobs (`app/Jobs/`, all `ShouldQueue`)

| Job | Constructor | Trigger | Behaviour |
|---|---|---|---|
| `HealthCheckJob` | — | Scheduler (every 5 min) | Ping every active connection; fire `TallyConnectionHealthChanged`. |
| `SyncAllConnectionsJob` | — | Scheduler (hourly) | Dispatch `SyncMastersJob` per active connection. |
| `SyncMastersJob` | `string $connectionCode, string $type = 'all'` | `SyncAllConnectionsJob` + `tally:sync` command | Sync masters of given type (`all`, `ledger`, `group`, `stock-item`). |
| `SyncFromTallyJob` | `string $connectionCode, bool $force = false` | Scheduler (every 10 min) + `SyncController::triggerInbound` | Tally → ERP: refreshes ledgers/groups/stock items using AlterID. Fires `TallySyncCompleted`. |
| `SyncToTallyJob` | `string $connectionCode, int $batchSize = 50` | Scheduler + `SyncController::triggerOutbound` | ERP → Tally: process pending `tally_syncs` rows. |
| `ProcessConflictsJob` | `string $connectionCode, int $batchSize = 20` | Scheduler (every 5 min) | Apply resolution strategies to resolved conflicts. |
| `BulkVoucherImportJob` | voucher payload | Ad-hoc | Push a batch of vouchers via `VoucherService::createBatch()`. |
| `ProcessRecurringVouchersJob` *(9L)* | `?string $connectionCode` | Scheduler (daily at 00:30) | Fires all `tally_recurring_vouchers` rows with `next_run_at <= today`; advances cursor + writes `last_run_result`. |
| `ProcessImportJob` *(9I)* | `int $importJobId` | `ImportController@start` | Consumes one `TallyImportJob`, parses CSV, creates masters row-by-row; writes progress to `processed_rows` / `failed_rows`. |
| `DeliverWebhookJob` *(9I)* | `int $deliveryId` | Event listener + self-reschedule | One POST attempt; on failure, schedules the next attempt with exponential backoff from config. |

---

## Events (`app/Events/`, all `Dispatchable`)

| Event | Constructor payload |
|---|---|
| `TallyMasterCreated` | `string $objectType, ?string $objectName, array $result, ?string $connectionCode` |
| `TallyMasterUpdated` | same |
| `TallyMasterDeleted` | same |
| `TallyVoucherCreated` | `VoucherType $type, array $result, ?string $connectionCode` |
| `TallyVoucherAltered` | same |
| `TallyVoucherCancelled` | same |
| `TallySyncCompleted` | `string $connectionCode, string $syncType, int $recordCount` |
| `TallyConnectionHealthChanged` | `string $connectionCode, bool $isHealthy, ?string $error` |

Auto-discovery: `EventServiceProvider::$shouldDiscoverEvents = true` (no manual `$listen` map).

---

## Exceptions (`app/Exceptions/`)

| Exception | Thrown when |
|---|---|
| `TallyConnectionException` | Network / circuit-breaker open |
| `TallyResponseException` | Non-XML or malformed Tally response |
| `TallyImportException` | Import response contains `LINEERROR` |
| `TallyXmlParseException` | XML parse failure |
| `TallyValidationException` | Input fails `SafeXmlString` or similar |

---

## Middleware (`app/Http/Middleware/`)

| Middleware | Behaviour |
|---|---|
| `CheckTallyPermission` | `handle($req, $next, string $permission)` — 403 if `user.tally_permissions` lacks the permission enum value. 401 if unauthenticated. |
| `ResolveTallyConnection` | Resolves `{connection}` code to `TallyConnection` (must be active). Builds a `TallyHttpClient` via `TallyConnectionManager` and rebinds it in container so downstream services resolve the right one. 404 if missing. |

---

## Providers (`app/Providers/`)

| Provider | Role |
|---|---|
| `TallyServiceProvider` | Registers `TallyConnectionManager` (singleton), factory-binds `TallyHttpClient` from config. Loads migrations. Publishes config. Registers commands `tally:health`, `tally:sync`, `tally:demo`. Wires scheduler. |
| `RouteServiceProvider` | Prefix `api/tally`, name prefix `tally.`. Loads `routes/api.php` and `routes/web.php`. |
| `EventServiceProvider` | Event auto-discovery (`$shouldDiscoverEvents = true`). |

---

## Demo Sandbox (`Services/Demo/`)

Single-command demo sandbox that seeds, resets, and full-cycle-tests every module capability against a dedicated **`SwatTech Demo`** Tally company. Every operation is prefix-scoped so production data cannot be touched. Entry point: `php artisan tally:demo`.

| Class | Role |
|---|---|
| `DemoConstants` | Single source of truth: company name, prefixes (`Demo `, `[DEMO]`, `DEMO/`), full seed manifest (2 units, 2 stock groups, 4 stock items, 2 cost centers, 5 groups, 14 ledgers, 18 vouchers covering all 8 `VoucherType` cases). |
| `DemoSafetyException` | Thrown on any guard violation. Aborts the command. |
| `DemoGuard` | XML pre-send assertions: requires `<SVCURRENTCOMPANY>SwatTech Demo</SVCURRENTCOMPANY>` + forbids `Delete`/`Cancel` on non-prefixed targets. |
| `DemoHttpClient` | `TallyHttpClient` subclass that runs `DemoGuard::assertSafe()` before every `sendXml`. |
| `DemoEnvironment` | `run(Closure $fn)` — overrides `config('tally.company')` + rebinds `TallyHttpClient` to `DemoHttpClient` for the closure's lifetime. Also verifies `tally_connections.company_name === 'SwatTech Demo'` before proceeding. |
| `DemoTokenVault` | Persists Sanctum plaintext to `storage/app/tally-demo/token.txt` (0600, gitignored). `resolve(bool $rotate = false)` reuses the stored token if still valid. |
| `DemoSeeder` | Idempotent upsert of demo user, DEMO connection, and all 46 masters + vouchers. `run(): array` returns a count-per-entity summary. |
| `DemoReset` | Prefix-scoped teardown. Dry-run by default (`dryRun(false)` to execute). Cancels only `DEMO/…`-numbered vouchers, deletes only `Demo …`-named masters, then cleans DB rows. |
| `DemoCycleRunner` | 10-phase end-to-end test (connectivity, company primitives, masters read, masters round-trip, vouchers round-trip, reports, sync, jobs, observability, permissions). Transient entities use `Demo Test …` / `[DEMO TEST] …` prefixes so a failure leaves the seeded set intact; `try/finally` cleanup. |

### Command `tally:demo`

| Action | Behaviour |
|---|---|
| *(no args, interactive)* | Menu: `[1] test · [2] fresh · [3] seed · [4] reset · [5] status · [6] rotate-token` |
| `seed` | Idempotent — skips entities that already exist. Prints a Sanctum token (reused from vault unless `--rotate-token`). |
| `reset` | **Dry-run by default.** Pass `--execute` to apply. Deletes only prefix-matched entities. |
| `fresh` | `reset` + `seed` + `test`. Dry-run first; `--execute` runs for real. |
| `test` | Runs `DemoCycleRunner`. `--json` emits machine-readable output. Exit 0 on pass, 1 on fail. |
| `status` | Shows connection / vault / log-dir state without touching anything. |
| `rotate-token` | Revokes all demo tokens, mints a fresh one, overwrites the vault file. |

Global flags: `--force` (required in production for destructive ops), `--execute` (gate for real reset/fresh), `--rotate-token`, `--json`.

### Smoke script

`Modules/Tally/scripts/tally-smoke-test.sh` — bash harness that curl-hits every API endpoint (all 165 route registrations + 18 report sub-types) using a bootstrapped Sanctum token (smoke-test-* name → internal rate tier). Modes: default (pretty), `--clean` (wipe `[DEMO]`-prefixed entities first), `--keep` (tolerate existing), `--dry-run` (print without executing), `--no-fail-fast`, `--phase=...`. Supports `CURL_INSECURE=1` for self-signed `.test` domains and works without `jq` via a PHP fallback. Default base URL: `https://tallyintegration.test`.

---

## Schedule (configured in `TallyServiceProvider`)

| Cadence | Work |
|---|---|
| every 5 min | `HealthCheckJob` |
| every 5 min | `ProcessConflictsJob` per active connection |
| every 10 min | `SyncFromTallyJob` + `SyncToTallyJob` per active connection |
| hourly | `SyncAllConnectionsJob` |
| daily at 00:30 *(9L)* | `ProcessRecurringVouchersJob` |

---

## Console commands (`app/Console/`)

| Signature | Purpose |
|---|---|
| `tally:health {connection?}` | Health probe of one connection or all active. Prints table. |
| `tally:sync {connection} {--type=all}` | Dispatches `SyncMastersJob`. `--type` ∈ `all, ledger, group, stock-item`. |

---

## Config (`Modules/Tally/config/config.php`)

| Key | Env var | Default |
|---|---|---|
| `tally.host` | `TALLY_HOST` | `localhost` |
| `tally.port` | `TALLY_PORT` | `9000` |
| `tally.company` | `TALLY_COMPANY` | *(empty)* |
| `tally.timeout` | `TALLY_TIMEOUT` | `30` |
| `tally.logging.enabled` | `TALLY_LOG_REQUESTS` | `true` |
| `tally.logging.channel` | — | `tally` |
| `tally.logging.max_body_size` | — | `10240` |
| `tally.cache.enabled` | `TALLY_CACHE_ENABLED` | `true` |
| `tally.cache.ttl` | `TALLY_CACHE_TTL` | `300` |
| `tally.cache.prefix` | — | `tally` |
| `tally.circuit_breaker.enabled` | `TALLY_CIRCUIT_BREAKER` | `true` |
| `tally.circuit_breaker.failure_threshold` | — | `5` |
| `tally.circuit_breaker.recovery_timeout` | — | `60` |
| `tally.object_export_format` | `TALLY_OBJECT_EXPORT_FORMAT` | `BinaryXML` (`SysName` for plain XML) |
| `tally.workflow.enabled` *(9J)* | `TALLY_WORKFLOW_ENABLED` | `true` |
| `tally.workflow.approval_thresholds` *(9J)* | — | `[]` (empty → all drafts require approval) |
| `tally.workflow.require_distinct_approver` *(9J)* | — | `true` |
| `tally.integration.pdf.driver` *(9I)* | `TALLY_PDF_DRIVER` | `mpdf` |
| `tally.integration.pdf.paper` *(9I)* | `TALLY_PDF_PAPER` | `A4` |
| `tally.integration.mail.from_address` *(9I)* | `TALLY_MAIL_FROM` | (falls back to `MAIL_FROM_ADDRESS`) |
| `tally.integration.mail.from_name` *(9I)* | `TALLY_MAIL_FROM_NAME` | (falls back to `MAIL_FROM_NAME`) |
| `tally.integration.attachments.disk` *(9I)* | `TALLY_ATTACHMENT_DISK` | `local` |
| `tally.integration.attachments.max_size_kb` *(9I)* | — | `10240` |
| `tally.integration.attachments.allowed_mimes` *(9I)* | — | `pdf,png,jpg,jpeg,xlsx,docx,txt,csv` |
| `tally.integration.webhooks.max_attempts` *(9I)* | — | `5` |
| `tally.integration.webhooks.backoff_seconds` *(9I)* | — | `[60, 300, 900, 3600, 14400]` |
| `tally.integration.webhooks.timeout_seconds` *(9I)* | — | `10` |
| `tally.integration.webhooks.queue` *(9I)* | `TALLY_WEBHOOK_QUEUE` | `default` |
| `tally.integration.imports.disk` *(9I)* | `TALLY_IMPORT_DISK` | `local` |
| `tally.integration.imports.queue` *(9I)* | `TALLY_IMPORT_QUEUE` | `default` |
| `tally.integration.imports.chunk_size` *(9I)* | — | `100` |

---

## Rules

### `SafeXmlString` (`app/Rules/SafeXmlString.php`)

Custom validation rule — rejects strings containing `<!DOCTYPE`, `<!ENTITY`, `<![CDATA[`, `<?xml`, `<ENVELOPE`, `<HEADER`, `<TALLYMESSAGE`, or `<TALLYREQUEST`. Used on every Tally master field that accepts user input.
