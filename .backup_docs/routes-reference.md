# Routes Reference

Generated from `Modules/Tally/routes/api.php`. All routes prefixed `/api/tally/` by module RouteServiceProvider. Route names prefixed `tally.`.

## Connection Management (no middleware)

| Method | URI | Name | Controller | Action |
|--------|-----|------|------------|--------|
| GET | `/api/tally/connections` | tally.connections.index | TallyConnectionController | index |
| POST | `/api/tally/connections` | tally.connections.store | TallyConnectionController | store |
| GET | `/api/tally/connections/{connection}` | tally.connections.show | TallyConnectionController | show |
| PUT/PATCH | `/api/tally/connections/{connection}` | tally.connections.update | TallyConnectionController | update |
| DELETE | `/api/tally/connections/{connection}` | tally.connections.destroy | TallyConnectionController | destroy |
| GET | `/api/tally/connections/{connection}/health` | tally.connections.health | TallyConnectionController | health |

## Health Check

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| GET | `/api/tally/health` | tally.health | HealthController (invokable) |

## Per-Connection Routes

All routes below use `ResolveTallyConnection` middleware. `{connection}` = connection code (e.g., `MUM`).

### Ledgers

| Method | URI | Name | Controller | Action |
|--------|-----|------|------------|--------|
| GET | `/api/tally/{connection}/ledgers` | tally.ledgers.index | LedgerController | index |
| POST | `/api/tally/{connection}/ledgers` | tally.ledgers.store | LedgerController | store |
| GET | `/api/tally/{connection}/ledgers/{name}` | tally.ledgers.show | LedgerController | show |
| PUT/PATCH | `/api/tally/{connection}/ledgers/{name}` | tally.ledgers.update | LedgerController | update |
| DELETE | `/api/tally/{connection}/ledgers/{name}` | tally.ledgers.destroy | LedgerController | destroy |

### Groups

Same pattern as Ledgers: `/{connection}/groups/{name}`

### Stock Items

Same pattern: `/{connection}/stock-items/{name}`

### Vouchers

| Method | URI | Name | Controller | Action |
|--------|-----|------|------------|--------|
| GET | `/api/tally/{connection}/vouchers` | tally.vouchers.index | VoucherController | index |
| POST | `/api/tally/{connection}/vouchers` | tally.vouchers.store | VoucherController | store |
| GET | `/api/tally/{connection}/vouchers/{masterID}` | tally.vouchers.show | VoucherController | show |
| PUT/PATCH | `/api/tally/{connection}/vouchers/{masterID}` | tally.vouchers.update | VoucherController | update |
| DELETE | `/api/tally/{connection}/vouchers/{masterID}` | tally.vouchers.destroy | VoucherController | destroy |

**Query params for index**: `type` (required, VoucherType enum), `from_date` (YYYYMMDD), `to_date` (YYYYMMDD)
**Body for store**: `{ type: string, data: object }`
**Body for destroy**: `{ type: string, date: string, voucher_number: string, action: "delete"|"cancel", narration?: string }`

### Reports

| Method | URI | Name | Controller | Action |
|--------|-----|------|------------|--------|
| GET | `/api/tally/{connection}/reports/{type}` | tally.connection.reports.show | ReportController | show |

**Report types**: `balance-sheet`, `profit-and-loss`, `trial-balance`, `ledger`, `outstandings`, `stock-summary`, `day-book`

### Health (per-connection)

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| GET | `/api/tally/{connection}/health` | tally.connection.health | HealthController |

## Middleware

| Middleware | Applied To | Purpose |
|-----------|-----------|---------|
| `ResolveTallyConnection` | All `{connection}` prefixed routes | Resolves connection code from DB, rebinds TallyHttpClient |
| `api` | All routes (via RouteServiceProvider) | Standard API middleware stack |

## Total: 29 routes

All controllers in namespace `Modules\Tally\Http\Controllers\`.
