# Database Schema

Canonical reference for all Tally module tables. Regenerated from 10 migrations under `Modules/Tally/database/migrations/`.

**Last verified:** 2026-04-17

---

## Table: `tally_connections`

Registered TallyPrime instances (one row per host+port+company).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(255) | Human-readable name |
| `code` | string(255) | **unique**, uppercase by controller |
| `host` | string | default `localhost` |
| `port` | unsignedSmallInt | default `9000` |
| `company_name` | string | default `''` |
| `timeout` | unsignedSmallInt | default `30` (seconds) |
| `is_active` | boolean | default `true` |
| `last_alter_master_id` | unsignedBigInt | default `0`, for incremental sync (AlterID) |
| `last_alter_voucher_id` | unsignedBigInt | default `0`, for incremental sync (AlterID) |
| `last_synced_at` | timestamp | nullable |
| `created_at`, `updated_at` | timestamps | |

Relations: `hasMany` ledgers, vouchers, stockItems, groups.

---

## Table: `tally_ledgers`

Local mirror of Tally ledgers (party accounts, banks, expense heads, etc.).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK → `tally_connections` | `cascadeOnDelete` |
| `name` | string | Ledger name (Tally identity) |
| `parent` | string | nullable, parent group name |
| `gstin` | string(20) | nullable |
| `gst_registration_type` | string(50) | nullable |
| `state` | string | nullable |
| `email` | string | nullable |
| `phone` | string(50) | nullable |
| `contact_person` | string | nullable |
| `opening_balance` | decimal(18,2) | default `0` |
| `closing_balance` | decimal(18,2) | default `0` |
| `credit_period` | string(50) | nullable |
| `credit_limit` | decimal(18,2) | nullable |
| `currency` | string(10) | nullable |
| `address` | json | nullable |
| `tally_raw_data` | json | nullable, full Tally response for audit |
| `data_hash` | string(32) | nullable, MD5 for change detection |
| `created_at`, `updated_at` | timestamps | |

Indexes: unique(`tally_connection_id`, `name`), index(`tally_connection_id`, `parent`).

---

## Table: `tally_vouchers`

Local mirror of Tally vouchers (sales, purchase, payment, receipt, journal, etc.).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK | `cascadeOnDelete` |
| `voucher_number` | string | nullable |
| `tally_master_id` | string | nullable, Tally internal ID |
| `voucher_type` | string(30) | `Sales`, `Purchase`, `Payment`, `Receipt`, `Journal`, `Contra`, `CreditNote`, `DebitNote` |
| `date` | date | |
| `party_name` | string | nullable |
| `narration` | string(500) | nullable |
| `amount` | decimal(18,2) | default `0` |
| `is_cancelled` | boolean | default `false` |
| `ledger_entries` | json | nullable |
| `inventory_entries` | json | nullable |
| `bill_allocations` | json | nullable |
| `tally_raw_data` | json | nullable |
| `data_hash` | string(32) | nullable |
| `created_at`, `updated_at` | timestamps | |

Indexes: (`tally_connection_id`, `voucher_type`), (`tally_connection_id`, `date`), (`tally_connection_id`, `party_name`), (`tally_connection_id`, `voucher_number`).

---

## Table: `tally_groups`

Local mirror of Tally account groups (chart of accounts).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK | `cascadeOnDelete` |
| `name` | string | Group name |
| `parent` | string | nullable |
| `nature` | string(50) | nullable (Assets, Liabilities, Income, Expenses) |
| `is_primary` | boolean | default `false` |
| `tally_raw_data` | json | nullable |
| `data_hash` | string(32) | nullable |
| `created_at`, `updated_at` | timestamps | |

Indexes: unique(`tally_connection_id`, `name`).

---

## Table: `tally_stock_items`

Local mirror of Tally inventory stock items.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK | `cascadeOnDelete` |
| `name` | string | Stock item name |
| `parent` | string | nullable, stock group |
| `base_unit` | string(50) | nullable |
| `opening_balance_qty` | decimal(18,4) | default `0` |
| `opening_balance_value` | decimal(18,2) | default `0` |
| `opening_rate` | decimal(18,2) | default `0` |
| `closing_balance_qty` | decimal(18,4) | default `0` |
| `closing_balance_value` | decimal(18,2) | default `0` |
| `has_batches` | boolean | default `false` |
| `hsn_code` | string(20) | nullable |
| `tally_raw_data` | json | nullable |
| `data_hash` | string(32) | nullable |
| `created_at`, `updated_at` | timestamps | |

Indexes: unique(`tally_connection_id`, `name`), index(`tally_connection_id`, `parent`).

---

## Table: `tally_syncs`

Per-entity bidirectional sync state (one row per local entity).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK | `cascadeOnDelete` |
| `entity_type` | string(30) | `ledger`, `voucher`, `stock_item`, `group` |
| `entity_id` | unsignedBigInt | FK to the mirror table (polymorphic) |
| `tally_name` | string | nullable |
| `tally_master_id` | string | nullable |
| `sync_direction` | string(20) | `to_tally`, `from_tally`, `bidirectional` (default) |
| `sync_status` | string(20) | `pending` (default), `in_progress`, `completed`, `failed`, `conflict` |
| `priority` | string(10) | `low`, `normal` (default), `high`, `critical` |
| `local_data_hash` | string(32) | nullable |
| `tally_data_hash` | string(32) | nullable |
| `last_synced_at` | timestamp | nullable |
| `last_sync_attempt` | timestamp | nullable |
| `sync_attempts` | unsignedSmallInt | default `0` |
| `error_message` | text | nullable |
| `conflict_data` | json | nullable, `{local, tally, conflict_fields}` |
| `resolution_strategy` | string(20) | nullable: `manual`, `erp_wins`, `tally_wins`, `merge`, `newest_wins` |
| `resolved_at` | timestamp | nullable |
| `resolved_by` | FK → `users` | nullable, `nullOnDelete` |
| `created_at`, `updated_at` | timestamps | |

Indexes: (`tally_connection_id`, `entity_type`, `entity_id`), (`tally_connection_id`, `sync_status`), (`sync_status`, `priority`), (`tally_connection_id`, `entity_type`, `sync_status`).

---

## Table: `tally_audit_logs`

Immutable audit trail of every create/alter/delete/cancel action.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → `users` | nullable, `nullOnDelete` |
| `tally_connection_id` | FK → `tally_connections` | nullable, `nullOnDelete` |
| `action` | string(20) | `create`, `alter`, `delete`, `cancel` |
| `object_type` | string(50) | `LEDGER`, `GROUP`, `STOCKITEM`, `VOUCHER`, `UNIT`, `STOCKGROUP`, `COSTCENTRE` |
| `object_name` | string | nullable |
| `request_data` | json | nullable |
| `response_data` | json | nullable |
| `ip_address` | string(45) | nullable |
| `user_agent` | string | nullable |
| `created_at` | timestamp | `useCurrent`; **no `updated_at`** |

---

## Table: `tally_response_metrics`

Per-request response-time telemetry.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK | nullable, `cascadeOnDelete` |
| `endpoint` | string(100) | Logical endpoint label |
| `response_time_ms` | unsignedInt | |
| `status` | string(20) | `success`, `error`, `timeout` |
| `created_at` | timestamp | `useCurrent`; **no `updated_at`** |

---

## Modification: `users` table

Added by `2026_04_16_085707_add_tally_permissions_to_users_table.php`:

| Column | Type | Notes |
|---|---|---|
| `tally_permissions` | json | nullable; stores array of `TallyPermission` enum values (e.g. `['view_masters', 'manage_vouchers']`). Placed after `remember_token`. |

---

## Eloquent Models

| Model | Table | Key relationships |
|---|---|---|
| `TallyConnection` | `tally_connections` | hasMany ledgers/vouchers/stockItems/groups |
| `TallyLedger` | `tally_ledgers` | belongsTo connection; `computeDataHash()` |
| `TallyVoucher` | `tally_vouchers` | belongsTo connection; `computeDataHash()` |
| `TallyGroup` | `tally_groups` | belongsTo connection; `computeDataHash()` |
| `TallyStockItem` | `tally_stock_items` | belongsTo connection; `computeDataHash()` |
| `TallySync` | `tally_syncs` | belongsTo connection; polymorphic `entity()`; scopes `pendingForConnection`, `conflictsForConnection`; `statsForConnection()`; `isDueForRetry()` with exponential backoff |
| `TallyAuditLog` | `tally_audit_logs` | belongsTo connection; `$timestamps = false` |
| `TallyResponseMetric` | `tally_response_metrics` | `$timestamps = false` |

---

## Permissions (enum values stored in `users.tally_permissions`)

From `Modules\Tally\Enums\TallyPermission`:

| Case | Value |
|---|---|
| `ViewMasters` | `view_masters` |
| `ManageMasters` | `manage_masters` |
| `ViewVouchers` | `view_vouchers` |
| `ManageVouchers` | `manage_vouchers` |
| `ViewReports` | `view_reports` |
| `ManageConnections` | `manage_connections` |
