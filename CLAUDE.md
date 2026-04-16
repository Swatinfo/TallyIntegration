# CLAUDE.md

## Project Overview

TallyIntegration — Laravel service layer for integrating with TallyPrime accounting software via its HTTP/XML API. Provides REST API endpoints for managing masters (ledgers, groups, stock items), vouchers (sales, purchase, payment, receipt, journal), and financial reports (balance sheet, P&L, trial balance).

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.4, MariaDB |
| API | REST JSON API (Laravel Sanctum available) |
| Tally Protocol | HTTP/XML to TallyPrime (port 9000) |
| Testing | Pest 4, Laravel Pint |

## Development Commands

```bash
# Start dev server
php artisan serve

# Run all tests
php artisan test --compact

# Run specific test
php artisan test --compact --filter=TallyHealth

# Format code
vendor/bin/pint --dirty --format agent

# List Tally routes
php artisan route:list --path=api/tally
```

## Key Conventions

- **Service layer pattern**: All Tally logic in `app/Services/Tally/` — controllers are thin
- **XML protocol**: Tally uses XML for import/export, not JSON. `TallyXmlBuilder` builds, `TallyXmlParser` parses
- **Multi-connection**: `tally_connections` DB table stores per-location configs. `{connection}` route prefix resolves via middleware
- **Default fallback**: `TALLY_HOST`, `TALLY_PORT`, `TALLY_COMPANY`, `TALLY_TIMEOUT` in `.env` for single-instance use
- **Consistent API response**: `{ success: bool, data: mixed, message: string }`

## Architecture

```
app/Services/Tally/
├── TallyHttpClient.php          # HTTP POST to Tally (per-connection instance)
├── TallyConnectionManager.php   # Resolves connection code → TallyHttpClient
├── TallyXmlBuilder.php          # Builds XML request envelopes
├── TallyXmlParser.php           # Parses XML responses
├── Masters/                     # Ledger, Group, StockItem, StockGroup, Unit, CostCenter
├── Vouchers/                    # VoucherService + VoucherType enum
└── Reports/                     # ReportService (Balance Sheet, P&L, etc.)

app/Models/TallyConnection.php   # Eloquent model for tally_connections table
app/Http/Middleware/ResolveTallyConnection.php  # Binds correct client per {connection} route
```

## API Routes

All routes under `/api/tally/`:

**Connection management:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/connections` | List / create connections |
| GET/PUT/DELETE | `/connections/{id}` | Get / update / delete connection |
| GET | `/connections/{id}/health` | Health check for specific connection |
| GET | `/health` | Default health check (uses .env config) |

**Per-connection operations** (`{connection}` = connection code like `MUM`, `DEL`):

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/{connection}/health` | Connection-specific health check |
| GET/POST | `/{connection}/ledgers` | List / create ledgers |
| GET/PUT/DELETE | `/{connection}/ledgers/{name}` | Ledger CRUD |
| GET/POST | `/{connection}/groups` | List / create groups |
| GET/POST | `/{connection}/stock-items` | List / create stock items |
| GET/POST | `/{connection}/vouchers` | List / create vouchers |
| GET/PUT/DELETE | `/{connection}/vouchers/{masterID}` | Voucher CRUD |
| GET | `/{connection}/reports/{type}` | Fetch report |

## Mandatory Pre-Read Gate

Before writing ANY code, read the relevant docs for your task:

| Task | Read FIRST |
|------|-----------|
| Tally services/XML | `.docs/tally-integration.md` + `.docs/tally-api-reference.md` |
| Routes/Controllers | `.claude/routes-reference.md` |
| Config/Settings | `config/tally.php` |

**Always read** (every task): `tasks/lessons.md` + `tasks/todo.md`

## Reference Documentation

| Area | File(s) |
|------|---------|
| Tally integration guide | `.docs/tally-integration.md` |
| Tally XML API reference | `.docs/tally-api-reference.md` |
| All docs index | `.docs/README.md` |

## Source of Truth Files

- `config/tally.php` — Tally connection config
- `app/Services/Tally/TallyXmlBuilder.php` — XML request format
- `app/Services/Tally/TallyXmlParser.php` — XML response parsing

Rules auto-loaded from `.claude/rules/`: `pre-read-gate.md`, `coding-feedback.md`, `project-context.md`, `workflow.md`
