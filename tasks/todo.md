# Task Tracker

Current and completed tasks. Updated as work progresses.

---

## In Progress

<!-- No tasks currently in progress -->

### XML Format Corrections from Demo Samples (2026-04-16)
- [x] Rewrote TallyXmlBuilder — correct VERSION+TYPE+ID header format, 3 export types (Data/Collection/Object)
- [x] Added buildObjectExportRequest() for single-entity fetch
- [x] Added buildBatchImportVoucherRequest() for batch voucher import
- [x] Added buildCancelVoucherRequest() with attribute-based format
- [x] Fixed buildDeleteVoucherRequest() to use attribute-based format (DATE+TAGNAME+TAGVALUE)
- [x] Fixed TallyXmlParser — extractObject(), extractCollection() with correct response paths
- [x] Fixed all 6 master service get() methods to use OBJECT export instead of report/list-filter
- [x] Fixed VoucherService amount signs (Payment: debit=positive, credit=negative)
- [x] Added VoucherService::cancel() and ::createBatch()
- [x] Used VOUCHERTYPENAME child element instead of VCHTYPE attribute for creation
- [x] Updated VoucherController destroy() to support cancel vs delete
- [x] Rewrote tally-api-reference.md based on official Demo Samples
- [x] Updated lessons.md with all Tally XML API patterns

### Multi-Company / Multi-Location Support (2026-04-16)
- [x] TallyConnection model + migration + factory
- [x] TallyConnectionManager (resolves code → TallyHttpClient)
- [x] Refactored TallyHttpClient (constructor accepts host/port/company instead of reading config)
- [x] ResolveTallyConnection middleware (binds correct client per request)
- [x] TallyConnectionController (CRUD + per-connection health check)
- [x] Routes updated: `/api/tally/{connection}/...` prefix with middleware
- [x] Updated CLAUDE.md, .docs/tally-integration.md

---

## Just Completed

### TallyPrime Integration — Full Service Layer (2026-04-16)
- [x] Phase 1: Config (`config/tally.php`), TallyHttpClient, TallyXmlBuilder, TallyXmlParser, TallyServiceProvider
- [x] Phase 2: Master services (Ledger, Group, StockItem, StockGroup, Unit, CostCenter)
- [x] Phase 3: Voucher services (VoucherService + VoucherType enum)
- [x] Phase 4: Report service (Balance Sheet, P&L, Trial Balance, Outstandings, Stock Summary, Day Book)
- [x] Phase 5: API controllers (Health, Ledger, Group, StockItem, Voucher, Report) + routes
- [x] Phase 6: Updated CLAUDE.md, created .docs/tally-integration.md and .docs/tally-api-reference.md

---

## Completed

<!-- Archive old completed tasks or move to a separate file -->
