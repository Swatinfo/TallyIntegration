# Routes Reference

Canonical reference for every API route. Regenerated from `Modules/Tally/routes/api.php`.

**Last verified:** 2026-04-17

- **Base prefix:** `/api/tally/` (set by `RouteServiceProvider`)
- **Name prefix:** `tally.`
- **Global middleware:** `auth:sanctum`, `throttle:tally-api`
- **Total route registrations:** 44 (40 named + 4 unnamed `PATCH` variants for masters/vouchers)
- **Response shape (always):** `{ success: bool, data: mixed, message: string }`

---

## Throttle groups

| Name | Applies to |
|---|---|
| `tally-api` | All routes |
| `tally-write` | Master + voucher writes |
| `tally-reports` | `/reports/{type}` |

---

## Group 1 — Audit (`manage_connections` permission)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/audit-logs` | `AuditLogController@index` | `tally.audit-logs.index` |

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
| `POST` | `/connections/{connection}/sync-from-tally` | `SyncController@triggerInbound` | `tally.sync.inbound` |
| `POST` | `/connections/{connection}/sync-to-tally` | `SyncController@triggerOutbound` | `tally.sync.outbound` |
| `POST` | `/connections/{connection}/sync-full` | `SyncController@triggerFull` | `tally.sync.full` |
| `GET` | `/connections/{connection}/health` | `TallyConnectionController@health` | `tally.connections.health` |
| `GET` | `/connections/{connection}/metrics` | `TallyConnectionController@metrics` | `tally.connections.metrics` |
| `POST` | `/connections/{connection}/discover` | `TallyConnectionController@discover` | `tally.connections.discover` |
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

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/{connection}/ledgers` | `LedgerController@index` | `tally.ledgers.index` |
| `GET` | `/{connection}/ledgers/{name}` | `LedgerController@show` | `tally.ledgers.show` |
| `GET` | `/{connection}/groups` | `GroupController@index` | `tally.groups.index` |
| `GET` | `/{connection}/groups/{name}` | `GroupController@show` | `tally.groups.show` |
| `GET` | `/{connection}/stock-items` | `StockItemController@index` | `tally.stock-items.index` |
| `GET` | `/{connection}/stock-items/{name}` | `StockItemController@show` | `tally.stock-items.show` |

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

### 4d. Read vouchers (`view_vouchers` permission)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/{connection}/vouchers` | `VoucherController@index` | `tally.vouchers.index` |
| `GET` | `/{connection}/vouchers/{masterID}` | `VoucherController@show` | `tally.vouchers.show` |

### 4e. Write vouchers (`manage_vouchers` permission + `throttle:tally-write`)

| Method | URI | Action | Name |
|---|---|---|---|
| `POST` | `/{connection}/vouchers` | `VoucherController@store` | `tally.vouchers.store` |
| `PUT` | `/{connection}/vouchers/{masterID}` | `VoucherController@update` | `tally.vouchers.update` |
| `PATCH` | `/{connection}/vouchers/{masterID}` | `VoucherController@update` | *(unnamed)* |
| `DELETE` | `/{connection}/vouchers/{masterID}` | `VoucherController@destroy` | `tally.vouchers.destroy` |

### 4f. Reports (`view_reports` permission + `throttle:tally-reports`)

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/{connection}/reports/{type}` | `ReportController@show` | `tally.connection.reports.show` |

Valid `{type}` values: `balance-sheet`, `profit-and-loss`, `trial-balance`, `ledger`, `outstandings`, `stock-summary`, `day-book`.
Response format selection: `?format=csv` **or** `Accept: text/csv` header → `StreamedResponse`.

---

## Route-model binding

| Parameter | Resolves to |
|---|---|
| `{connection}` in Group 2 | `TallyConnection` model (by `id`) |
| `{connection}` in Group 4 | **connection code string** — resolved to `TallyHttpClient` by `ResolveTallyConnection` middleware |
| `{sync}` | `TallySync` model |
| `{name}`, `{masterID}`, `{type}` | plain strings (URL-decoded in controller) |

---

## Middleware details

| Middleware | Class | Behavior |
|---|---|---|
| `auth:sanctum` | Sanctum | 401 if unauthenticated |
| `CheckTallyPermission:{perm}` | `Modules\Tally\Http\Middleware\CheckTallyPermission` | 403 if `user.tally_permissions` does not contain the permission enum value |
| `ResolveTallyConnection` | `Modules\Tally\Http\Middleware\ResolveTallyConnection` | 404 if connection code is unknown / inactive. Rebinds `TallyHttpClient` in container. |
| `throttle:tally-*` | Laravel rate limiter | Rate limits per named group |

---

## Form Requests (validation)

| Controller method | Form Request | Key rules |
|---|---|---|
| `TallyConnectionController@store` | `StoreConnectionRequest` | `name` required, `code` unique+alpha_num, `port` 1-65535 |
| `TallyConnectionController@update` | `UpdateConnectionRequest` | Same, all nullable; `code` unique-except-self |
| `LedgerController@store` | `StoreLedgerRequest` | `NAME`, `PARENT` required + `SafeXmlString` |
| `LedgerController@update` | `UpdateLedgerRequest` | Same, all nullable |
| `GroupController@store` | `StoreGroupRequest` | `NAME`, `PARENT` required + `SafeXmlString` |
| `StockItemController@store` | `StoreStockItemRequest` | `NAME` required, `HASBATCHES` in `Yes,No` |
| `VoucherController@store` | `StoreVoucherRequest` | `type` is `VoucherType` enum, `data` array |
| `VoucherController@update` | `UpdateVoucherRequest` | Same |
| `VoucherController@destroy` | `DestroyVoucherRequest` | `type` enum, `date`, `voucher_number`, `action` in `delete,cancel` |
