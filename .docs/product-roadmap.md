# Product Roadmap — TallyPrime Integration Module

## Phase 1: Security, Validation, Exceptions, Tests - COMPLETE
- [x] Custom exceptions (5 classes in `Modules/Tally/app/Exceptions/`)
- [x] Global exception handler in `bootstrap/app.php` (503, 502, 422, 400)
- [x] Form Request classes (9 classes in `Modules/Tally/app/Http/Requests/`)
- [x] SafeXmlString validation rule (`Modules/Tally/app/Rules/`)
- [x] Sanctum auth on all routes (`auth:sanctum`)
- [x] Permission system (TallyPermission enum + CheckTallyPermission middleware)
- [x] Rate limiting (tally-api 60/min, tally-write 30/min, tally-reports 10/min)
- [x] Unit tests: TallyXmlBuilder (13), TallyXmlParser (15)
- [x] Feature tests: Health, Connections, Ledgers, Vouchers, Reports
- [x] Test fixtures (6 XML files) + MocksTallyClient trait
- [x] **63 tests passing, 135 assertions**

## Phase 2: Logging, Audit Trail, Caching - COMPLETE
- [x] TallyRequestLogger service (logs XML in/out with timing)
- [x] Tally log channel in `config/logging.php` (daily, 14 days)
- [x] tally_audit_logs table + TallyAuditLog model + AuditLogger service
- [x] Audit log API endpoint (`GET /api/tally/audit-logs` with filters)
- [x] CachesMasterData trait (cachedList, cachedGet, invalidateCache)
- [x] Cache config (enabled, TTL, prefix) — applied to all 6 master services

## Phase 3: Background Jobs, Events, Sync - COMPLETE
- [x] 8 event classes (TallyMasterCreated/Updated/Deleted, TallyVoucherCreated/Altered/Cancelled, TallySyncCompleted, TallyConnectionHealthChanged)
- [x] HealthCheckJob, SyncMastersJob, SyncAllConnectionsJob, BulkVoucherImportJob
- [x] Schedule: health checks every 5 min, sync hourly

## Phase 4: Monitoring & Resilience - COMPLETE
- [x] CircuitBreaker service (closed/open/half-open states, configurable threshold)
- [x] tally_response_metrics table + MetricsCollector service
- [x] Metrics API endpoint (`GET /api/tally/connections/{id}/metrics`)
- [x] Connection test endpoint (`POST /api/tally/connections/test`)
- [x] Company discover endpoint (`POST /api/tally/connections/{id}/discover`)
- [x] Circuit breaker config (enabled, failure_threshold, recovery_timeout)

## Phase 5: API Polish - COMPLETE
- [x] PaginatesResults trait (search, sort, page, per_page)
- [x] Applied to LedgerController, GroupController, StockItemController
- [x] CSV export for reports (`?format=csv` or `Accept: text/csv`)

## Phase 6: Enterprise - COMPLETE
- [x] `tally:health` Artisan command (check all or specific connection)
- [x] `tally:sync` Artisan command (dispatch sync job for a connection)
- [x] Commands registered in TallyServiceProvider

## Phase 7: Advanced Sync & Performance - COMPLETE
- [x] AlterID-based incremental sync (TallyCompanyService, migration for tracking columns)
- [x] SyncMastersJob checks AlterIDs before pulling data — skips if unchanged
- [x] Function export support (buildFunctionExportRequest, TYPE=Function)
- [x] TallyCompanyService: getAlterIds(), hasChangedSince(), callFunction(), getFinancialYearPeriod()
- [x] Batch voucher listing (monthly splits for 100K+ transactions)
- [x] AlterID TDL query (buildAlterIdQueryRequest) for $AltMstId/$AltVchId

## Phase 8: Bidirectional Sync Engine - COMPLETE
- [x] Phase A: Local mirror tables (tally_ledgers, tally_vouchers, tally_stock_items, tally_groups) + 4 Eloquent models with data_hash + computeDataHash()
- [x] Phase B: tally_syncs table (per-entity tracking) + TallySync model (retry logic, conflict detection, priority scopes, stats) + SyncTracker service + SyncController + 7 API routes
- [x] Phase C: SyncToTallyJob (outbound — pushes local changes to Tally, processes by priority)
- [x] Phase D: SyncFromTallyJob (inbound — pulls Tally data into local tables, detects conflicts via hash comparison)
- [x] Schedule: bidirectional sync every 10 min for all active connections
- [x] Manual trigger routes: sync-from-tally, sync-to-tally, sync-full (return 202 Accepted)
- [x] TallyConnection relationships: ledgers(), vouchers(), stockItems(), groups()

## Phase 9A: Expose existing services — COMPLETE (2026-04-17)

- [x] **Stock Groups CRUD** — `StockGroupController` + `StoreStockGroupRequest` + 5 routes (+1 unnamed PATCH)
- [x] **Units CRUD** — `UnitController` + `StoreUnitRequest` + 5 routes (+1 unnamed PATCH)
- [x] **Cost Centres CRUD** — `CostCenterController` + `StoreCostCenterRequest` + 5 routes (+1 unnamed PATCH)
- [x] **Batch voucher import** — `POST /{conn}/vouchers/batch` (delegates to `VoucherService::createBatch`)
- [x] **Companies list endpoint** — `GET /connections/{id}/companies`
- [x] Smoke test extended — 3 new phases (3b/3c/3d) + batch voucher call + companies call
- [x] Docs synced: `.claude/routes-reference.md`, `.claude/services-reference.md`, `Modules/Tally/docs/API-USAGE.md`, `CLAUDE.md`, `.docs/README.md`
- [x] Route count: 44 → 64 (57 named + 7 unnamed PATCH)
- [ ] **Permission negative test fixture** — optional: seed a second Sanctum user without `tally_permissions` in the smoke test so it can verify `CheckTallyPermission` returns 403.

## Phase 9B: New master domains + reports — COMPLETE (2026-04-17)

- [x] **Currencies CRUD** — `CurrencyService` + `CurrencyController` + `StoreCurrencyRequest` + 5 routes (+1 PATCH)
- [x] **Godowns CRUD** — `GodownService` + `GodownController` + `StoreGodownRequest` + 5 routes (+1 PATCH)
- [x] **Voucher Types CRUD** — `VoucherTypeService` + `VoucherTypeController` + `StoreVoucherTypeRequest` + 5 routes (+1 PATCH)
- [x] **Reports extension** — 8 new `ReportService` methods: `cashBankBook`, `salesRegister`, `purchaseRegister`, `agingAnalysis`, `cashFlow`, `fundsFlow`, `receiptsPayments`, `stockMovement`
- [x] **ReportController** — 8 new report types dispatched: `cash-book`, `sales-register`, `purchase-register`, `aging`, `cash-flow`, `funds-flow`, `receipts-payments`, `stock-movement`
- [x] Smoke test extended — 3 new sub-phases (3e/3f/3g) + 8 new report calls. Fixtures: 2 currencies, 2 godowns, 2 voucher types
- [x] Docs synced: `.claude/routes-reference.md`, `.claude/services-reference.md`, `Modules/Tally/docs/API-USAGE.md` (new §4e/4f/4g + reports table)
- [x] Route count: 64 → 82 (72 named + 10 unnamed PATCH)

## Phase 9C: Observability polish — COMPLETE (2026-04-17)

- [x] `POST /{c}/cache/flush` — invalidates every master list cache (`OperationsController@flushCache`)
- [x] `GET /{c}/stats` — dashboard counts across 9 master types
- [x] `GET /{c}/search?q=...` — cross-master filter (ledgers/groups/stock-items, `limit` 1-50)
- [x] `GET /connections/{id}/circuit-state` — breaker state + availability flag
- [x] `GET /audit-logs/{id}` — single log with full request/response JSON
- [x] `GET /audit-logs/export` — streamed CSV with same filters as index
- [x] `GET /connections/{id}/sync-history` — completed + failed + cancelled, paginated
- [x] `POST /connections/{id}/sync/resolve-all` — bulk-resolve open conflicts with one strategy
- [x] `GET /sync/{id}` — single sync record detail
- [x] `POST /sync/{id}/cancel` — cancel pending/in-progress sync (adds `cancelled` status)
- [x] Route count: 82 → 92. New `OperationsController`
- [x] Smoke test extended — `phase_9b_observability` covers all 10 new endpoints (conditional on pending syncs for cancel)

## Phase 9D: Banking — COMPLETE (2026-04-17)

- [x] **Bank Reconciliation (BRS)** — new report type `bank-reconciliation` + `POST /{c}/bank/reconcile` / `unreconcile` to set/clear `BANKERDATE` on existing vouchers
- [x] **Cheque register** — new report type `cheque-register`
- [x] **Post-dated cheques** — new report type `post-dated-cheques`
- [x] **Bank feed CSV import** — `POST /{c}/bank/import-statement` (file upload or inline CSV string); `POST /{c}/bank/auto-match` (amount + date tolerance matching); `POST /{c}/bank/batch-reconcile` (apply bulk)
- [x] New `BankingService` (Services/Banking/) with parse/match/reconcile logic; new `BankingController`; 2 new Form Requests
- [x] Route count: 92 → 97 (5 new named). No new unnamed PATCH.
- [x] Smoke test extended — new `phase_8b_banking` covers all 3 reports + import-statement + auto-match + reconcile/unreconcile + batch-reconcile (7 API calls)

## Phase 9E: Tax compliance — DEFERRED (full plan in `.docs/features.md`)

Blocks on GSP (GST Suvidha Provider) partnership + DSC decision. **Full build brief** with tables, services, routes, permissions, config, and week-by-week implementation sequence is in `.docs/features.md#phase-9e--tax-compliance`.

Scope: GSTR-1, GSTR-3B, GSTR-2A/2B, E-Invoicing (IRN+QR via IRP GSP), E-Way Bill, HSN summary, TDS + Form 26Q, TCS returns, RCM, ITC mismatch. ~15 new routes, 4 new tables, 3 new services (including pluggable GSP client adapters), 2 new permissions.

**To unblock:** send GSP vendor choice (ClearTax/Masters India/IRIS/Cygnet) + sandbox credentials.

## Phase 9F: Inventory advanced — COMPLETE (2026-04-17)

- [x] **Sales Orders / Purchase Orders / Quotations** — 3 new `VoucherType` enum cases, usable via existing `POST /{c}/vouchers`
- [x] **Delivery Notes / Receipt Notes / Rejection In/Out** — 4 new `VoucherType` enum cases
- [x] **Stock Journal / Physical Stock** — 2 new `VoucherType` enum cases
- [x] **Stock Transfer convenience endpoint** — `POST /{c}/stock-transfers` builds Stock Journal with `BATCHALLOCATIONS.LIST` (source godown − / destination +)
- [x] **Physical Stock convenience endpoint** — `POST /{c}/physical-stock` for count adjustments
- [x] **Price lists CRUD** — `PriceListService` + `PriceListController` + Form Request. Tally entity: `PRICELEVEL`
- [x] **Stock Categories CRUD** — `StockCategoryService` + `StockCategoryController` + Form Request. Tally entity: `STOCKCATEGORY`
- [x] **Batch + serial number tracking** — supported inline via `BATCHALLOCATIONS.LIST` on inventory entries (voucher payloads); no new endpoint needed
- [x] New `InventoryController` for convenience endpoints + 2 new `VoucherService` helpers (`createStockTransfer`, `createPhysicalStock`)
- [x] Route count: 97 → 111 (+14: 12 named + 2 unnamed PATCH)
- [x] Smoke test: 2 new master sub-phases (3h/3i) + new `phase_6b_inventory_ops` exercising stock-transfer, physical-stock, SalesOrder, PurchaseOrder, DeliveryNote

## Phase 9G: Manufacturing — COMPLETE (2026-04-17)

- [x] **Bill of Materials (BOM)** — read/write endpoints `GET/PUT /{c}/stock-items/{name}/bom` (stored on stock item's `COMPONENTLIST.LIST`, not a separate master)
- [x] **Manufacturing Journal voucher** — `POST /{c}/manufacturing` convenience endpoint assembles consumption + production inventory entries with `BATCHALLOCATIONS.LIST`
- [x] **Stock Journal** — already covered in Phase 9F via `/stock-transfers` (godown-to-godown) and available via bare `VoucherType::StockJournal`
- [x] **Job Work In/Out** — `POST /{c}/job-work-out` and `POST /{c}/job-work-in` convenience endpoints
- [x] New `ManufacturingService` (Services/Manufacturing/) + `ManufacturingController`
- [x] `VoucherType` enum +3 cases: `ManufacturingJournal`, `JobWorkInOrder`, `JobWorkOutOrder`
- [x] +5 routes (2 under `view_masters`/`manage_masters` for BOM, 3 under `manage_vouchers` for voucher ops). Route count 127 → 132
- [x] Smoke test: new `phase_6c_manufacturing` covering BOM set/get + manufacture + job-work-out + job-work-in (5 API calls)

## Phase 9H: Payroll — PENDING

- [ ] Employee masters
- [ ] Pay Heads masters
- [ ] Attendance / Leave types + daily entry
- [ ] Payroll voucher
- [ ] Payslip PDF generation
- [ ] PF / ESI / PT / IT outputs

## Phase 9I: Integration glue — COMPLETE (2026-04-17)

Shipped with mpdf for PDFs, default Laravel mailer, local disk for attachments, database queue for webhooks. All defaults configurable in `tally.integration.*`.

- [x] 4 new tables: `tally_webhook_endpoints`, `tally_webhook_deliveries`, `tally_voucher_attachments`, `tally_import_jobs` + 4 matching models
- [x] 5 new services in `Services/Integration/`: `PdfService`, `MailService`, `AttachmentService`, `ImportService`, `WebhookDispatcher`
- [x] 2 new jobs: `ProcessImportJob`, `DeliverWebhookJob` (with exponential backoff)
- [x] 1 new listener: `DispatchWebhooksOnTallyEvent` — wired to all 8 Tally events in `EventServiceProvider::$listen`
- [x] 4 new controllers: `WebhookController`, `AttachmentController`, `ImportController`, `IntegrationController` (PDF + email)
- [x] 4 new Form Requests
- [x] 2 new permissions: `manage_integrations`, `send_invoices`
- [x] 15 new routes (webhooks CRUD + deliveries + test, imports + status, attachments CRUD, voucher PDF, email invoice)
- [x] New `tally.integration.*` config block (pdf/mail/attachments/webhooks/imports)
- [x] `mpdf/mpdf ^8.3` added to `composer.json`
- [ ] **Excel import** — deferred; `phpoffice/phpspreadsheet` not installed. CSV covers most cases.
- [ ] **WhatsApp notification** — deferred
- [ ] **Digital signature on PDFs** — deferred

## Phase 9J: Workflow / approvals — COMPLETE (2026-04-17)

- [x] **Draft voucher state** — new `tally_draft_vouchers` table + `TallyDraftVoucher` model with `STATUS_*` constants + `isEditable()/isSubmittable()/isActionable()`
- [x] **Maker-checker workflow** — `WorkflowService` with `submit/approve/reject`; config-driven self-approval block (`require_distinct_approver`)
- [x] **Approval thresholds** — `config('tally.workflow.approval_thresholds')` — drafts below threshold auto-approve + push on submit (single-call fast path)
- [x] **Voucher lock after approval** — `is_locked` flag set on approve/reject; `update/delete` return 409 once locked
- [x] New `ApproveVouchers` permission enum case; `approve`/`reject` routes gated on it separately from `manage_vouchers`
- [x] 9 new routes (8 named + 1 unnamed PATCH). Route count 118 → 127
- [x] Smoke test: new `phase_9d_workflow` exercises full create → PATCH → submit → reject path + second draft deletion (7 API calls)

## Phase 9Z: Organizations / Companies / Branches architecture — COMPLETE (2026-04-17)

- [x] 3 new tables: `tally_organizations`, `tally_companies`, `tally_branches`
- [x] 3 nullable FK columns on `tally_connections` — backwards-compatible
- [x] 3 new Eloquent models with hasMany / belongsTo / hasManyThrough
- [x] 3 new controllers (apiResource) + 3 Form Requests, under `manage_connections`
- [x] 15 new CRUD route registrations

## Phase 9K: Multi-company / consolidation — COMPLETE (2026-04-17)

- [x] **`ConsolidationService`** fans out a report across every active connection belonging to an organization; failure-tolerant (single broken connection doesn't abort rollup)
- [x] **`ConsolidatedReportController`** — 3 endpoints on `/organizations/{id}/consolidated/{balance-sheet,profit-and-loss,trial-balance}`
- [ ] **Inter-company reconciliation** — deferred; needs bespoke matching rules per customer
- [ ] **Scoped permissions** (org/company/branch) — deferred; current permission model still works since `manage_connections` gates hierarchy access

## Phase 9L: Scheduled / recurring ops — COMPLETE (2026-04-17, partial)

- [x] **Recurring vouchers** — new `tally_recurring_vouchers` table + `TallyRecurringVoucher` model + `RecurringVoucherService` + `RecurringVoucherController` + `StoreRecurringVoucherRequest`. 7 routes including manual-run endpoint. New `ProcessRecurringVouchersJob` scheduled **daily at 00:30**. Frequency options: daily/weekly/monthly/quarterly/yearly with `day_of_month` clamped ≤28
- [ ] **Scheduled report email** — deferred, depends on **Phase 9I** (mail driver decision)
- [ ] **Auto cheque-clearance rollover** — Tally handles PDC activation natively; no API work needed

Route count: 111 → 118.

## Summary

| Phase | Files Created | Key Features |
|-------|--------------|-------------|
| 1 | ~25 | Auth, Permissions, Exceptions, Validation, Tests |
| 2 | ~8 | Logging, Audit Trail, Caching |
| 3 | ~12 | Events, Jobs, Scheduled Tasks |
| 4 | ~5 | Circuit Breaker, Metrics, Connection Testing |
| 5 | ~3 | Pagination, CSV Export |
| 6 | ~2 | Artisan Commands |
| 7 | ~4 | Incremental Sync, Function Export, Batch Listing |
| 8 | ~15 | Bidirectional Sync (mirror tables, sync tracking, inbound/outbound jobs) |
| **Total** | **~74** | **64 tests, all passing** |
