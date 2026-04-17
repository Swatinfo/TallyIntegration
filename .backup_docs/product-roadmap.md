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
