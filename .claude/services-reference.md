# Services Reference

Canonical reference for every service class under `Modules/Tally/app/Services/`. Namespace: `Modules\Tally\Services\*`.

**Last verified:** 2026-04-17

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
| `buildCollectionExportRequest($collectionType, $fetchFields, $filters, $company)` | Collection export (`List of Accounts`, `List of Groups`, etc.). |
| `buildObjectExportRequest($objectType, $objectName, $fetchFields, $company)` | Single object export (`BinaryXML` format). |
| `buildImportMasterRequest($objectType, $data, $action, $company)` | Master import — `LEDGER`, `GROUP`, `STOCKITEM`, `UNIT`, `STOCKGROUP`, `COSTCENTRE`. Action = `Create` / `Alter`. |
| `buildImportVoucherRequest($data, $action, $company)` | Single voucher import. |
| `buildBatchImportVoucherRequest($vouchers, $action, $company)` | Multiple vouchers in one `TALLYMESSAGE`. |
| `buildCancelVoucherRequest($date, $number, $type, $narration, $company)` | Cancel (attribute format: `ACTION=Cancel`). |
| `buildDeleteVoucherRequest($date, $number, $type, $company)` | Delete (attribute format: `ACTION=Delete`). |
| `buildFunctionExportRequest($functionName, $params)` | `TYPE=Function` — e.g. `$$SystemPeriodFrom`. |
| `buildAlterIdQueryRequest($company)` | TDL-based AlterID report for incremental sync. |
| `buildDeleteMasterRequest($objectType, $name, $company)` | Master delete (action `Delete`). |
| `arrayToXml(array $data)` | Recursively converts PHP array → XML. |
| `escapeXml(string $value)` | `htmlspecialchars` with `ENT_XML1`. |

**Key invariants** (from `tasks/lessons.md`):
- Header uses `<VERSION>1</VERSION>` + `<TYPE>` + `<ID>` (NOT `<TALLYREQUEST>Export Data</TALLYREQUEST>`).
- All Data/Collection exports include `<EXPLODEFLAG>Yes</EXPLODEFLAG>` and `<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>`.
- Object exports use `<SVEXPORTFORMAT>BinaryXML</SVEXPORTFORMAT>`.
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

Fail-fast cache-backed breaker (5 failures → open, 60s recovery).

| Method | Purpose |
|---|---|
| `isAvailable(string $code)` | `true` when closed or half-open (probe). |
| `recordSuccess(string $code)` | Reset failure count, set `closed`. |
| `recordFailure(string $code)` | Increment; open at threshold. |
| `getState(string $code)` | Returns `closed` / `open` / `half-open`. |
| `assertAvailable(string $code)` | Throws `TallyConnectionException` if not available. |

---

## Masters (`Services/Masters/`)

All six master services share the same shape (`list`, `get`, `create`, `update`, `delete`), use the `CachesMasterData` trait, and write an audit log on successful write.

| Service | Tally object | Collection name | Cache key prefix |
|---|---|---|---|
| `LedgerService` | `LEDGER` | `List of Accounts` | `ledger:*` |
| `GroupService` | `GROUP` | `List of Groups` | `group:*` |
| `StockItemService` | `STOCKITEM` | `List of StockItems` | `stock-item:*` |
| `StockGroupService` | `STOCKGROUP` | `List of StockGroups` | `stock-group:*` |
| `UnitService` | `UNIT` | `List of Units` | `unit:*` |
| `CostCenterService` | `COSTCENTRE` | `List of CostCentres` | `cost-centre:*` |

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

`Sales`, `Purchase`, `Payment`, `Receipt`, `Journal`, `Contra`, `CreditNote`, `DebitNote`.

### `VoucherService`

| Method | Purpose |
|---|---|
| `list(VoucherType $type, ?string $fromDate, ?string $toDate, ?int $batchSize)` | List vouchers; splits into monthly batches when `batchSize` set. |
| `get(string $masterID)` | Fetch one by master ID. |
| `create(VoucherType $type, array $data)` | Single create. |
| `createBatch(VoucherType $type, array $vouchers)` | Multiple in one request. |
| `alter(string $masterID, VoucherType $type, array $data)` | Alter. |
| `cancel(string $date, string $number, VoucherType $type, ?string $narration)` | Cancel (reversible). |
| `delete(string $date, string $number, VoucherType $type)` | Permanent delete. |
| `createSales / createPurchase / createPayment / createReceipt / createJournal` | Convenience helpers with simplified args. |

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

Date format: `YYYYMMDD`.

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
| `TallyServiceProvider` | Registers `TallyConnectionManager` (singleton), factory-binds `TallyHttpClient` from config. Loads migrations. Publishes config. Registers commands `tally:health`, `tally:sync`. Wires scheduler. |
| `RouteServiceProvider` | Prefix `api/tally`, name prefix `tally.`. Loads `routes/api.php` and `routes/web.php`. |
| `EventServiceProvider` | Event auto-discovery (`$shouldDiscoverEvents = true`). |

---

## Schedule (configured in `TallyServiceProvider`)

| Cadence | Work |
|---|---|
| every 5 min | `HealthCheckJob` |
| every 5 min | `ProcessConflictsJob` per active connection |
| every 10 min | `SyncFromTallyJob` + `SyncToTallyJob` per active connection |
| hourly | `SyncAllConnectionsJob` |

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

---

## Rules

### `SafeXmlString` (`app/Rules/SafeXmlString.php`)

Custom validation rule — rejects strings containing `<!DOCTYPE`, `<!ENTITY`, `<![CDATA[`, `<?xml`, `<ENVELOPE`, `<HEADER`, `<TALLYMESSAGE`, or `<TALLYREQUEST`. Used on every Tally master field that accepts user input.
