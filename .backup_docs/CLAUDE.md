# CLAUDE.md

## Project Overview

TallyIntegration — Laravel module for integrating with TallyPrime accounting software via HTTP/XML API. Self-contained `nwidart/laravel-modules` module that can be dropped into any Laravel project. Provides REST API for masters, vouchers, and financial reports across multiple Tally connections.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.4, MariaDB |
| Module System | nwidart/laravel-modules v13 |
| API | REST JSON API (Sanctum available) |
| Tally Protocol | HTTP/XML to TallyPrime (port 9000) |
| Testing | Pest 4, Laravel Pint |

## Development Commands

```bash
php artisan serve                          # Start dev server
php artisan test --compact                 # Run all tests
vendor/bin/pint --dirty --format agent     # Format code
php artisan route:list --path=api/tally    # List Tally routes
php artisan module:list                    # Check module status
php artisan migrate                        # Run module migrations
```

## Key Conventions

- **Module-based**: All Tally code in `Modules/Tally/`. Namespace `Modules\Tally\*`
- **Service layer**: Services in `Modules/Tally/app/Services/` — controllers are thin
- **XML protocol**: Tally uses XML. `TallyXmlBuilder` builds envelopes, `TallyXmlParser` parses responses. Format verified against `.docs/Demo Samples/`
- **Multi-connection**: `tally_connections` DB table + `{connection}` route prefix + middleware
- **Consistent API response**: `{ success: bool, data: mixed, message: string }`

## Module Architecture

```
Modules/Tally/
├── app/
│   ├── Http/
│   │   ├── Controllers/           # 9 controllers (CRUD, sync, audit, health, reports)
│   │   ├── Middleware/            # ResolveTallyConnection, CheckTallyPermission
│   │   └── Requests/             # 9 Form Request classes + SafeXmlString rule
│   ├── Models/                    # 8 models (Connection, Ledger, Voucher, StockItem, Group, Sync, AuditLog, Metric)
│   ├── Jobs/                      # 7 jobs (sync inbound/outbound, conflicts, health, bulk)
│   ├── Events/                    # 8 event classes
│   ├── Exceptions/                # 5 custom exceptions
│   ├── Providers/                 # TallyServiceProvider, RouteServiceProvider
│   └── Services/
│       ├── TallyHttpClient.php    # HTTP POST with logging + circuit breaker
│       ├── TallyXmlBuilder.php    # XML envelopes (4 export types + import + Function + AlterID)
│       ├── TallyXmlParser.php     # XML response parsing
│       ├── TallyCompanyService.php # AlterID, Function exports, FY detection
│       ├── SyncTracker.php        # Per-entity sync state + conflict detection
│       ├── Masters/               # 6 CRUD services (with caching + audit)
│       ├── Vouchers/              # VoucherService (batch, cancel) + VoucherType enum
│       └── Reports/               # ReportService (7 report types + CSV export)
├── config/config.php              # Connection, logging, cache, circuit breaker
├── database/migrations/           # 10 migrations (10 tables)
└── routes/api.php                 # 44 named API routes
```

## API Routes (44 named, auth required)

**Management & Sync:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/connections` | List / create connections |
| GET/PUT/DELETE | `/connections/{id}` | CRUD connection |
| GET | `/connections/{id}/health` | Health check |
| GET | `/connections/{id}/metrics` | Response time metrics |
| GET | `/connections/{id}/sync-stats` | Sync statistics |
| GET | `/connections/{id}/sync-pending` | Pending sync records |
| GET | `/connections/{id}/sync-conflicts` | Unresolved conflicts |
| POST | `/connections/{id}/sync-from-tally` | Trigger inbound sync |
| POST | `/connections/{id}/sync-to-tally` | Trigger outbound sync |
| POST | `/connections/{id}/sync-full` | Trigger full bidirectional |
| POST | `/connections/{id}/discover` | Discover companies |
| POST | `/connections/test` | Test connectivity |
| POST | `/sync/{id}/resolve` | Resolve a conflict |
| GET | `/audit-logs` | Audit trail |

**Per-connection** (`{conn}` = connection code like `MUM`):

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/{conn}/ledgers` | List (paginated) / create |
| GET/PUT/DELETE | `/{conn}/ledgers/{name}` | CRUD by name |
| GET/POST | `/{conn}/groups` | Account groups |
| GET/POST | `/{conn}/stock-items` | Stock items |
| GET/POST | `/{conn}/vouchers` | List / create vouchers |
| GET/PUT/DELETE | `/{conn}/vouchers/{masterID}` | CRUD voucher |
| GET | `/{conn}/reports/{type}` | Reports (JSON or CSV) |

## Mandatory Pre-Read Gate

| Task | Read FIRST |
|------|-----------|
| Tally services/XML | `.docs/tally-integration.md` + `.docs/tally-api-reference.md` |
| XML format changes | `.docs/Demo Samples/` (official Tally samples) |
| DB/Models | `.claude/database-schema.md` |
| Routes/Controllers | `.claude/routes-reference.md` |
| Services | `.claude/services-reference.md` |
| Config | `Modules/Tally/config/config.php` |

**Always read**: `tasks/lessons.md` + `tasks/todo.md`

## Reference Documentation

| Area | File(s) |
|------|---------|
| Docs index | `.docs/README.md` |
| Integration guide | `.docs/tally-integration.md` |
| XML API reference | `.docs/tally-api-reference.md` |
| Demo XML samples | `.docs/Demo Samples/` |
| Database schema | `.claude/database-schema.md` |
| Routes reference | `.claude/routes-reference.md` |
| Services reference | `.claude/services-reference.md` |

## Source of Truth Files

- `Modules/Tally/config/config.php` — Tally connection config
- `Modules/Tally/app/Services/TallyXmlBuilder.php` — XML request format
- `Modules/Tally/app/Services/TallyXmlParser.php` — XML response parsing
- `.docs/Demo Samples/` — Official TallyPrime XML samples (canonical format)

Rules auto-loaded from `.claude/rules/`: `pre-read-gate.md`, `coding-feedback.md`, `project-context.md`, `workflow.md`
