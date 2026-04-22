# Routes Reference

Canonical reference for every API route. Regenerated from `Modules/Tally/routes/api.php`.

**Last verified:** 2026-04-20 (Phase 9N: +30 registrations — 5 new masters (Cost Category, Employee, Employee Group, Employee Category, Attendance Type) × ~6 routes each + Phase 9M items retained. New `TallyFieldRegistry` normalises 316 field/alias mappings across 14 entity types on every master + voucher create/update.)

- **Base prefix:** `/api/tally/` (set by `RouteServiceProvider`)
- **Name prefix:** `tally.`
- **Global middleware:** `auth:sanctum`, `throttle:tally-api`
- **Total route registrations:** 205 (190 named + 15 unnamed `PATCH` variants)
- **Response shape (always):** `{ success: bool, data: mixed, message: string }`

---

## Throttle groups

| Name | Applies to |
|---|---|
| `tally-api` | All routes |
| `tally-write` | Master + voucher writes |
| `tally-reports` | `/reports/{type}` |

Limits are **tiered** by Sanctum token name prefix, **keyed per connection** for routes with a `{connection}` segment. Full table in `Modules/Tally/docs/CONFIGURATION.md` § Rate limiting. Implementation: `app/Providers/AppServiceProvider::LIMITS`.

- `smoke-test-*` / `internal-*` / `system-*` → internal tier (uncapped in practice)
- `batch-*` / `sync-*` → batch tier (fat pipe for month-end)
- anything else → standard tier (60 writes/min guardrail)

---

## Group 1 — Audit (`manage_connections` permission)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/audit-logs` | `AuditLogController@index` | `tally.audit-logs.index` |
| `GET` | `/audit-logs/export` | `AuditLogController@export` | `tally.audit-logs.export` *(9C — streams CSV)* |
| `GET` | `/audit-logs/{auditLog}` | `AuditLogController@show` | `tally.audit-logs.show` *(9C)* |

---

## Group 2 — Connection management (`manage_connections` permission)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/connections` | `TallyConnectionController@index` | `tally.connections.index` |
| `POST` | `/connections` | `TallyConnectionController@store` | `tally.connections.store` |
| `GET` | `/connections/{connection}` | `TallyConnectionController@show` | `tally.connections.show` |
| `PUT/PATCH` | `/connections/{connection}` | `TallyConnectionController@update` | `tally.connections.update` |
| `DELETE` | `/connections/{connection}` | `TallyConnectionController@destroy` | `tally.connections.destroy` |
| `GET` | `/connections/{connection}/sync-stats` | `SyncController@stats` | `tally.sync.stats` |
| `GET` | `/connections/{connection}/sync-pending` | `SyncController@pending` | `tally.sync.pending` |
| `GET` | `/connections/{connection}/sync-conflicts` | `SyncController@conflicts` | `tally.sync.conflicts` |
| `POST` | `/sync/{sync}/resolve` | `SyncController@resolveConflict` | `tally.sync.resolve` |
| `GET` | `/sync/{sync}` | `SyncController@show` | `tally.sync.show` *(9C)* |
| `POST` | `/sync/{sync}/cancel` | `SyncController@cancel` | `tally.sync.cancel` *(9C)* |
| `GET` | `/connections/{connection}/sync-history` | `SyncController@history` | `tally.sync.history` *(9C)* |
| `POST` | `/connections/{connection}/sync/resolve-all` | `SyncController@resolveAll` | `tally.sync.resolve-all` *(9C)* |
| `GET` | `/connections/{connection}/circuit-state` | `TallyConnectionController@circuitState` | `tally.connections.circuit-state` *(9C)* |
| `GET` | `/connections/{connection}/recurring-vouchers` | `RecurringVoucherController@index` | `tally.recurring-vouchers.index` *(9L)* |
| `POST` | `/connections/{connection}/recurring-vouchers` | `RecurringVoucherController@store` | `tally.recurring-vouchers.store` *(9L)* |
| `GET` | `/connections/{connection}/recurring-vouchers/{recurringVoucher}` | `RecurringVoucherController@show` | `tally.recurring-vouchers.show` *(9L)* |
| `PUT` | `/connections/{connection}/recurring-vouchers/{recurringVoucher}` | `RecurringVoucherController@update` | `tally.recurring-vouchers.update` *(9L)* |
| `PATCH` | `/connections/{connection}/recurring-vouchers/{recurringVoucher}` | `RecurringVoucherController@update` | *(unnamed)* |
| `DELETE` | `/connections/{connection}/recurring-vouchers/{recurringVoucher}` | `RecurringVoucherController@destroy` | `tally.recurring-vouchers.destroy` *(9L)* |
| `POST` | `/connections/{connection}/recurring-vouchers/{recurringVoucher}/run` | `RecurringVoucherController@run` | `tally.recurring-vouchers.run` *(9L)* |

### 2d. Integration (Phase 9I)

Webhooks + imports + attachments — permission: `manage_integrations` unless noted:

| Method | URI | Action | Name |
|---|---|---|---|
| `GET/POST` | `/webhooks` | `WebhookController` | `tally.webhooks.index/store` |
| `GET/PUT/PATCH/DELETE` | `/webhooks/{webhook}` | `WebhookController` | `tally.webhooks.show/update/destroy` |
| `GET` | `/webhooks/{webhook}/deliveries` | `WebhookController@deliveries` | `tally.webhooks.deliveries` |
| `POST` | `/webhooks/{webhook}/test` | `WebhookController@test` | `tally.webhooks.test` |
| `POST` | `/connections/{connection}/import/{entity}` | `ImportController@start` | `tally.import.start` |
| `GET` | `/import-jobs/{importJob}` | `ImportController@status` | `tally.import-jobs.show` |
| `GET/POST` | `/connections/{connection}/vouchers/{masterID}/attachments` | `AttachmentController` | `tally.vouchers.attachments.index/store` |
| `GET` | `/attachments/{attachment}/download` | `AttachmentController@download` | `tally.attachments.download` |
| `DELETE` | `/attachments/{attachment}` | `AttachmentController@destroy` | `tally.attachments.destroy` |

Voucher convenience — permission gates differ:

| Method | URI | Action | Name | Permission |
|---|---|---|---|---|
| `GET` | `/{connection}/vouchers/{masterID}/pdf` | `IntegrationController@voucherPdf` | `tally.vouchers.pdf` | `view_vouchers` |
| `POST` | `/{connection}/vouchers/{masterID}/email` | `IntegrationController@emailVoucher` | `tally.vouchers.email` | **`send_invoices`** (new) |

### 2c. MNC hierarchy + consolidation (Phase 9Z + 9K — `manage_connections` permission)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET/POST` | `/organizations` | `OrganizationController` | `tally.organizations.index/store` |
| `GET/PUT/PATCH/DELETE` | `/organizations/{organization}` | `OrganizationController` | `tally.organizations.show/update/destroy` |
| `GET/POST` | `/companies` *(filter by `?organization_id`)* | `CompanyController` | `tally.companies.index/store` |
| `GET/PUT/PATCH/DELETE` | `/companies/{company}` | `CompanyController` | `tally.companies.show/update/destroy` |
| `GET/POST` | `/branches` *(filter by `?company_id`)* | `BranchController` | `tally.branches.index/store` |
| `GET/PUT/PATCH/DELETE` | `/branches/{branch}` | `BranchController` | `tally.branches.show/update/destroy` |
| `GET` | `/organizations/{organization}/consolidated/balance-sheet` | `ConsolidatedReportController@balanceSheet` | `tally.organizations.consolidated.balance-sheet` |
| `GET` | `/organizations/{organization}/consolidated/profit-and-loss` | `ConsolidatedReportController@profitAndLoss` | `tally.organizations.consolidated.profit-and-loss` |
| `GET` | `/organizations/{organization}/consolidated/trial-balance` | `ConsolidatedReportController@trialBalance` | `tally.organizations.consolidated.trial-balance` |

### 2b. Workflow / draft vouchers (Phase 9J)

Maker endpoints — `manage_vouchers` permission:

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/connections/{connection}/draft-vouchers` | `DraftVoucherController@index` | `tally.draft-vouchers.index` |
| `POST` | `/connections/{connection}/draft-vouchers` | `DraftVoucherController@store` | `tally.draft-vouchers.store` |
| `GET` | `/connections/{connection}/draft-vouchers/{draftVoucher}` | `DraftVoucherController@show` | `tally.draft-vouchers.show` |
| `PUT` | `/connections/{connection}/draft-vouchers/{draftVoucher}` | `DraftVoucherController@update` | `tally.draft-vouchers.update` |
| `PATCH` | `/connections/{connection}/draft-vouchers/{draftVoucher}` | `DraftVoucherController@update` | *(unnamed)* |
| `DELETE` | `/connections/{connection}/draft-vouchers/{draftVoucher}` | `DraftVoucherController@destroy` | `tally.draft-vouchers.destroy` |
| `POST` | `/connections/{connection}/draft-vouchers/{draftVoucher}/submit` | `DraftVoucherController@submit` | `tally.draft-vouchers.submit` |

Checker endpoints — **`approve_vouchers` permission** (new):

| Method | URI | Action | Name |
|---|---|---|---|
| `POST` | `/connections/{connection}/draft-vouchers/{draftVoucher}/approve` | `DraftVoucherController@approve` | `tally.draft-vouchers.approve` |
| `POST` | `/connections/{connection}/draft-vouchers/{draftVoucher}/reject` | `DraftVoucherController@reject` | `tally.draft-vouchers.reject` |
| `POST` | `/connections/{connection}/sync-from-tally` | `SyncController@triggerInbound` | `tally.sync.inbound` |
| `POST` | `/connections/{connection}/sync-to-tally` | `SyncController@triggerOutbound` | `tally.sync.outbound` |
| `POST` | `/connections/{connection}/sync-full` | `SyncController@triggerFull` | `tally.sync.full` |
| `GET` | `/connections/{connection}/health` | `TallyConnectionController@health` | `tally.connections.health` |
| `GET` | `/connections/{connection}/metrics` | `TallyConnectionController@metrics` | `tally.connections.metrics` |
| `POST` | `/connections/{connection}/discover` | `TallyConnectionController@discover` | `tally.connections.discover` |
| `GET` | `/connections/{connection}/companies` | `TallyConnectionController@companies` | `tally.connections.companies` |
| `POST` | `/connections/test` | `TallyConnectionController@test` | `tally.connections.test` |

`{connection}` here is the `TallyConnection` model (route-model binding by `id`).

---

## Group 3 — Global health check (no extra permission)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/health` | `HealthController` (invokable) | `tally.health` |

---

## Group 4 — Per-connection routes (prefix `{connection}`, middleware `ResolveTallyConnection`)

`{connection}` in this group is a **connection code** (e.g. `MUM`). The middleware resolves it to a `TallyHttpClient` and rebinds it in the container.

### 4a. Per-connection health

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/{connection}/health` | `HealthController` | `tally.connection.health` |

### 4b. Read masters (`view_masters` permission)

**Filter params on master list endpoints (added 2026-04-19, mirror Tally's "Pull X of Group" operations):**
- `?parent=<exact name>` — filter to rows whose `PARENT` matches exactly (case-insensitive). Available on `ledgers`, `groups`, `stock-items`, `stock-groups`.
- `?zero_balance=true` — filter to rows whose `CLOSINGBALANCE` is zero / empty. Available on `stock-groups` only (StockGroupService fetches CLOSINGBALANCE in its FETCHLIST).
- All filter params compose with existing pagination/search/sort params.

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/{connection}/ledgers` | `LedgerController@index` | `tally.ledgers.index` |
| `GET` | `/{connection}/ledgers/{name}` | `LedgerController@show` | `tally.ledgers.show` |
| `GET` | `/{connection}/groups` | `GroupController@index` | `tally.groups.index` |
| `GET` | `/{connection}/groups/{name}` | `GroupController@show` | `tally.groups.show` |
| `GET` | `/{connection}/stock-items` | `StockItemController@index` | `tally.stock-items.index` |
| `GET` | `/{connection}/stock-items/{name}` | `StockItemController@show` | `tally.stock-items.show` |
| `GET` | `/{connection}/stock-items/{name}/bom` | `ManufacturingController@getBom` | `tally.stock-items.bom.show` *(9G)* |
| `GET` | `/{connection}/stock-groups` | `StockGroupController@index` | `tally.stock-groups.index` |
| `GET` | `/{connection}/stock-groups/{name}` | `StockGroupController@show` | `tally.stock-groups.show` |
| `GET` | `/{connection}/units` | `UnitController@index` | `tally.units.index` |
| `GET` | `/{connection}/units/{name}` | `UnitController@show` | `tally.units.show` |
| `GET` | `/{connection}/cost-centres` | `CostCenterController@index` | `tally.cost-centres.index` |
| `GET` | `/{connection}/cost-centres/{name}` | `CostCenterController@show` | `tally.cost-centres.show` |
| `GET` | `/{connection}/currencies` | `CurrencyController@index` | `tally.currencies.index` |
| `GET` | `/{connection}/currencies/{name}` | `CurrencyController@show` | `tally.currencies.show` |
| `GET` | `/{connection}/godowns` | `GodownController@index` | `tally.godowns.index` |
| `GET` | `/{connection}/godowns/{name}` | `GodownController@show` | `tally.godowns.show` |
| `GET` | `/{connection}/voucher-types` | `VoucherTypeController@index` | `tally.voucher-types.index` |
| `GET` | `/{connection}/voucher-types/{name}` | `VoucherTypeController@show` | `tally.voucher-types.show` |
| `GET` | `/{connection}/stock-categories` | `StockCategoryController@index` | `tally.stock-categories.index` |
| `GET` | `/{connection}/stock-categories/{name}` | `StockCategoryController@show` | `tally.stock-categories.show` |
| `GET` | `/{connection}/price-lists` | `PriceListController@index` | `tally.price-lists.index` |
| `GET` | `/{connection}/price-lists/{name}` | `PriceListController@show` | `tally.price-lists.show` |

### 4c. Write masters (`manage_masters` permission + `throttle:tally-write`)

| Method | URI | Action | Name |
|---|---|---|---|
| `POST` | `/{connection}/ledgers` | `LedgerController@store` | `tally.ledgers.store` |
| `PUT` | `/{connection}/ledgers/{name}` | `LedgerController@update` | `tally.ledgers.update` |
| `PATCH` | `/{connection}/ledgers/{name}` | `LedgerController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/ledgers/{name}` | `LedgerController@destroy` | `tally.ledgers.destroy` |
| `POST` | `/{connection}/groups` | `GroupController@store` | `tally.groups.store` |
| `PUT` | `/{connection}/groups/{name}` | `GroupController@update` | `tally.groups.update` |
| `PATCH` | `/{connection}/groups/{name}` | `GroupController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/groups/{name}` | `GroupController@destroy` | `tally.groups.destroy` |
| `POST` | `/{connection}/stock-items` | `StockItemController@store` | `tally.stock-items.store` |
| `PUT` | `/{connection}/stock-items/{name}` | `StockItemController@update` | `tally.stock-items.update` |
| `PATCH` | `/{connection}/stock-items/{name}` | `StockItemController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/stock-items/{name}` | `StockItemController@destroy` | `tally.stock-items.destroy` |
| `PUT` | `/{connection}/stock-items/{name}/bom` | `ManufacturingController@setBom` | `tally.stock-items.bom.update` *(9G)* |
| `POST` | `/{connection}/stock-groups` | `StockGroupController@store` | `tally.stock-groups.store` |
| `PUT` | `/{connection}/stock-groups/{name}` | `StockGroupController@update` | `tally.stock-groups.update` |
| `PATCH` | `/{connection}/stock-groups/{name}` | `StockGroupController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/stock-groups/{name}` | `StockGroupController@destroy` | `tally.stock-groups.destroy` |
| `POST` | `/{connection}/units` | `UnitController@store` | `tally.units.store` |
| `PUT` | `/{connection}/units/{name}` | `UnitController@update` | `tally.units.update` |
| `PATCH` | `/{connection}/units/{name}` | `UnitController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/units/{name}` | `UnitController@destroy` | `tally.units.destroy` |
| `POST` | `/{connection}/cost-centres` | `CostCenterController@store` | `tally.cost-centres.store` |
| `PUT` | `/{connection}/cost-centres/{name}` | `CostCenterController@update` | `tally.cost-centres.update` |
| `PATCH` | `/{connection}/cost-centres/{name}` | `CostCenterController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/cost-centres/{name}` | `CostCenterController@destroy` | `tally.cost-centres.destroy` |
| `POST` | `/{connection}/currencies` | `CurrencyController@store` | `tally.currencies.store` |
| `PUT` | `/{connection}/currencies/{name}` | `CurrencyController@update` | `tally.currencies.update` |
| `PATCH` | `/{connection}/currencies/{name}` | `CurrencyController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/currencies/{name}` | `CurrencyController@destroy` | `tally.currencies.destroy` |
| `POST` | `/{connection}/godowns` | `GodownController@store` | `tally.godowns.store` |
| `PUT` | `/{connection}/godowns/{name}` | `GodownController@update` | `tally.godowns.update` |
| `PATCH` | `/{connection}/godowns/{name}` | `GodownController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/godowns/{name}` | `GodownController@destroy` | `tally.godowns.destroy` |
| `POST` | `/{connection}/voucher-types` | `VoucherTypeController@store` | `tally.voucher-types.store` |
| `PUT` | `/{connection}/voucher-types/{name}` | `VoucherTypeController@update` | `tally.voucher-types.update` |
| `PATCH` | `/{connection}/voucher-types/{name}` | `VoucherTypeController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/voucher-types/{name}` | `VoucherTypeController@destroy` | `tally.voucher-types.destroy` |
| `POST` | `/{connection}/stock-categories` | `StockCategoryController@store` | `tally.stock-categories.store` |
| `PUT` | `/{connection}/stock-categories/{name}` | `StockCategoryController@update` | `tally.stock-categories.update` |
| `PATCH` | `/{connection}/stock-categories/{name}` | `StockCategoryController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/stock-categories/{name}` | `StockCategoryController@destroy` | `tally.stock-categories.destroy` |
| `POST` | `/{connection}/price-lists` | `PriceListController@store` | `tally.price-lists.store` |
| `PUT` | `/{connection}/price-lists/{name}` | `PriceListController@update` | `tally.price-lists.update` |
| `PATCH` | `/{connection}/price-lists/{name}` | `PriceListController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/price-lists/{name}` | `PriceListController@destroy` | `tally.price-lists.destroy` |

### 4d. Read vouchers (`view_vouchers` permission)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/{connection}/vouchers` | `VoucherController@index` | `tally.vouchers.index` |
| `GET` | `/{connection}/vouchers/{masterID}` | `VoucherController@show` | `tally.vouchers.show` |

### 4e. Write vouchers (`manage_vouchers` permission + `throttle:tally-write`)

| Method | URI | Action | Name |
|---|---|---|---|
| `POST` | `/{connection}/vouchers` | `VoucherController@store` | `tally.vouchers.store` |
| `POST` | `/{connection}/vouchers/batch` | `VoucherController@batch` | `tally.vouchers.batch` |
| `PUT` | `/{connection}/vouchers/{masterID}` | `VoucherController@update` | `tally.vouchers.update` |
| `PATCH` | `/{connection}/vouchers/{masterID}` | `VoucherController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/vouchers/{masterID}` | `VoucherController@destroy` | `tally.vouchers.destroy` |

### 4f-inv. Inventory convenience (Phase 9F — `manage_vouchers` permission + `throttle:tally-write`)

| Method | URI | Action | Name |
|---|---|---|---|
| `POST` | `/{connection}/stock-transfers` | `InventoryController@stockTransfer` | `tally.inventory.stock-transfer` |
| `POST` | `/{connection}/physical-stock` | `InventoryController@physicalStock` | `tally.inventory.physical-stock` |

### 4f-mfg. Manufacturing (Phase 9G — `manage_vouchers` permission + `throttle:tally-write`)

| Method | URI | Action | Name |
|---|---|---|---|
| `POST` | `/{connection}/manufacturing` | `ManufacturingController@manufacture` | `tally.manufacturing.create` |
| `POST` | `/{connection}/job-work-out` | `ManufacturingController@jobWorkOut` | `tally.manufacturing.job-work-out` |
| `POST` | `/{connection}/job-work-in` | `ManufacturingController@jobWorkIn` | `tally.manufacturing.job-work-in` |

### 4f-bank. Banking (Phase 9D — `manage_vouchers` permission + `throttle:tally-write`)

| Method | URI | Action | Name |
|---|---|---|---|
| `POST` | `/{connection}/bank/reconcile` | `BankingController@reconcile` | `tally.bank.reconcile` |
| `POST` | `/{connection}/bank/unreconcile` | `BankingController@unreconcile` | `tally.bank.unreconcile` |
| `POST` | `/{connection}/bank/import-statement` | `BankingController@importStatement` | `tally.bank.import-statement` |
| `POST` | `/{connection}/bank/auto-match` | `BankingController@autoMatch` | `tally.bank.auto-match` |
| `POST` | `/{connection}/bank/batch-reconcile` | `BankingController@batchReconcile` | `tally.bank.batch-reconcile` |

### 4f-ops. Operations (Phase 9C)

| Method | URI | Action | Name | Permission |
|---|---|---|---|---|
| `GET` | `/{connection}/stats` | `OperationsController@stats` | `tally.operations.stats` | `view_masters` |
| `GET` | `/{connection}/search?q=…` | `OperationsController@search` | `tally.operations.search` | `view_masters` |
| `POST` | `/{connection}/cache/flush` | `OperationsController@flushCache` | `tally.operations.cache.flush` | `manage_masters` |

### 4f. Reports (`view_reports` permission + `throttle:tally-reports`)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/{connection}/reports/{type}` | `ReportController@show` | `tally.connection.reports.show` |

Valid `{type}` values: `balance-sheet`, `profit-and-loss`, `trial-balance`, `ledger`, `outstandings`, `stock-summary`, `day-book`, `cash-book`, `sales-register`, `purchase-register`, `aging`, `cash-flow`, `funds-flow`, `receipts-payments`, `stock-movement`, `bank-reconciliation`, `cheque-register`, `post-dated-cheques`.
Response format selection: `?format=csv` **or** `Accept: text/csv` header → `StreamedResponse`.

---

## Route-model binding

| Parameter | Resolves to |
|---|---|
| `{connection}` in Group 2 | `TallyConnection` model (by `id`) |
| `{connection}` in Group 4 | **connection code string** — resolved to `TallyHttpClient` by `ResolveTallyConnection` middleware |
| `{sync}` | `TallySync` model |
| `{name}`, `{masterID}`, `{type}` | plain strings (URL-decoded in controller) |

### Action signature convention (Group 4)

Laravel passes **every** URI param positionally to the action. For Group-4 endpoints shaped `/{connection}/{entity}/{name}` or `/{connection}/vouchers/{masterID}`, actions that touch the identifier MUST declare the connection slot first, even if unused:

```php
public function show(string $connection, string $name): JsonResponse
public function update(Request $request, string $connection, string $name): JsonResponse
public function destroy(string $connection, string $name): JsonResponse
```

Omitting `$connection` binds the connection code into `$name` and silently drops the real identifier — Tally then errors "Could not find {code}" and the parser surfaces HTTP 500. Middleware rebinds `TallyHttpClient` in the container, so the `$connection` value itself is not used inside the action.

---

## Middleware details

| Middleware | Class | Behavior |
|---|---|---|
| `auth:sanctum` | Sanctum | 401 if unauthenticated |
| `CheckTallyPermission:{perm}` | `Modules\Tally\Http\Middleware\CheckTallyPermission` | 403 if `user.tally_permissions` does not contain the permission enum value |
| `ResolveTallyConnection` | `Modules\Tally\Http\Middleware\ResolveTallyConnection` | 404 if connection code is unknown / inactive. Rebinds `TallyHttpClient` in container. |
| `GuardTallyPathParams` | `Modules\Tally\Http\Middleware\GuardTallyPathParams` | 422 if `{name}`, `{masterID}` or `{type}` contain XML-envelope tokens (`<!DOCTYPE`, `<ENVELOPE`, etc.). Parity with `SafeXmlString` on POST bodies. |
| `throttle:tally-*` | Laravel rate limiter | Rate limits per named group |

---

## § 4f-mapping (Phase 9M) — Master-name mappings

Tally-name ↔ ERP-name aliases. Under `manage_connections`.

| Method | Path | Name | Form Request |
|---|---|---|---|
| GET | `/connections/{connection}/master-mappings` | `master-mappings.index` | — (supports `?entity_type=`) |
| POST | `/connections/{connection}/master-mappings` | `master-mappings.store` | `StoreMasterMappingRequest` (updateOrCreate) |
| DELETE | `/connections/{connection}/master-mappings/{mapping}` | `master-mappings.destroy` | — |

## § 4f-exceptions (Phase 9M) — Sync exceptions + reset

Mirrors `tally_migration_tdl/EXCEPTION_Reports.txt`. Under `manage_connections`.

| Method | Path | Name |
|---|---|---|
| GET | `/connections/{connection}/exceptions` | `sync.exceptions` (supports `?entity_type=`) |
| POST | `/connections/{connection}/sync/reset-status` | `sync.reset-status` |

## § 4f-naming (Phase 9M) — Voucher numbering series

Per-voucher-type numbering streams. Under `manage_connections`.

| Method | Path | Name | Form Request |
|---|---|---|---|
| GET | `/connections/{connection}/naming-series` | `naming-series.index` | — (supports `?voucher_type=`) |
| POST | `/connections/{connection}/naming-series` | `naming-series.store` | `StoreVoucherNamingSeriesRequest` (updateOrCreate) |
| PUT/PATCH | `/connections/{connection}/naming-series/{series}` | `naming-series.update` | `StoreVoucherNamingSeriesRequest` |
| DELETE | `/connections/{connection}/naming-series/{series}` | `naming-series.destroy` | — |

---

## Form Requests (validation)

| Controller method | Form Request | Key rules |
|---|---|---|
| `TallyConnectionController@store` | `StoreConnectionRequest` | `name` required, `code` unique+alpha_num, `port` 1-65535 |
| `TallyConnectionController@update` | `UpdateConnectionRequest` | Same, all nullable; `code` unique-except-self |
| `LedgerController@store` | `StoreLedgerRequest` | `NAME`, `PARENT` required + `SafeXmlString` |
| `LedgerController@update` | `UpdateLedgerRequest` | Same, all nullable |
| `GroupController@store` | `StoreGroupRequest` | `NAME`, `PARENT` required + `SafeXmlString` |
| `StockGroupController@store` | `StoreStockGroupRequest` | `NAME` required + `SafeXmlString`; `PARENT` nullable |
| `UnitController@store` | `StoreUnitRequest` | `NAME` required; `ISSIMPLEUNIT` in `Yes,No`; `CONVERSION` numeric |
| `CostCenterController@store` | `StoreCostCenterRequest` | `NAME` required + `SafeXmlString` |
| `CurrencyController@store` | `StoreCurrencyRequest` | `NAME` required (symbol, max 10); `DECIMALPLACES` 0-4 |
| `GodownController@store` | `StoreGodownRequest` | `NAME` required; `STORAGETYPE` in `Not Applicable,External Godown,Our Godown` |
| `VoucherTypeController@store` | `StoreVoucherTypeRequest` | `NAME` + `PARENT` (base type) required; `NUMBERINGMETHOD` in `Automatic,Automatic (Manual Override),Manual,Multi-user Auto` |
| `StockItemController@store` | `StoreStockItemRequest` | `NAME` required, `HASBATCHES` in `Yes,No` |
| `VoucherController@store` | `StoreVoucherRequest` | `type` is `VoucherType` enum, `data` array |
| `VoucherController@batch` | inline validate | `type` enum, `vouchers` array of arrays (≥1) |
| `BankingController@reconcile` | `ReconcileVoucherRequest` | `voucher_number`, `voucher_date` (YYYYMMDD), `voucher_type` enum, `statement_date`, `bank_ledger` |
| `BankingController@importStatement` | `ImportBankStatementRequest` | `statement_file` (file, csv/txt, max 10MB) OR `csv` (string) |
| `DraftVoucherController@store` | `StoreDraftVoucherRequest` | `voucher_type` enum; `voucher_data` array; `amount` ≥ 0 |
| `DraftVoucherController@reject` | inline validate | `reason` required, 3-1000 chars |
| `VoucherController@update` | `UpdateVoucherRequest` | Same |
| `VoucherController@destroy` | `DestroyVoucherRequest` | `type` enum, `date`, `voucher_number`, `action` in `delete,cancel` |
