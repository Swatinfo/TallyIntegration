# Database Schema

Canonical reference for all Tally module tables. Regenerated from 22 migrations under `Modules/Tally/database/migrations/` (19 module tables + 3 column additions on host `users`, `tally_connections`, and `tally_vouchers`).

**Last verified:** 2026-04-20

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

## Table: `tally_recurring_vouchers` *(Phase 9L)*

Scheduled voucher templates that `ProcessRecurringVouchersJob` fires on their due date.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK → `tally_connections` | `cascadeOnDelete` |
| `name` | string | Human-readable name (e.g. "Monthly Office Rent") |
| `voucher_type` | string(30) | `VoucherType` enum value (Payment/Journal/Receipt/etc.) |
| `frequency` | string(20) | `daily`, `weekly`, `monthly`, `quarterly`, `yearly` |
| `day_of_month` | unsignedTinyInt | nullable; 1-28 for monthly/quarterly/yearly |
| `day_of_week` | unsignedTinyInt | nullable; 0-6 (Sun-Sat) for weekly |
| `start_date` | date | First eligible run date |
| `end_date` | date | nullable; inclusive last run |
| `next_run_at` | date | When the scheduler should fire next |
| `last_run_at` | timestamp | nullable |
| `last_run_result` | json | nullable; `{created, altered, errors, ...}` from `VoucherService::create` |
| `voucher_template` | json | The `data` payload minus `DATE` (injected at run time) |
| `is_active` | boolean | default `true` |
| `created_at`, `updated_at` | timestamps | |

Index: `recurring_vch_due_idx` on (`tally_connection_id`, `is_active`, `next_run_at`).

---

## Table: `tally_draft_vouchers` *(Phase 9J)*

Maker-checker draft vouchers. Live in our DB until approved, then pushed to Tally via `VoucherService::create`. Approval thresholds come from `config('tally.workflow.approval_thresholds')`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK → `tally_connections` | `cascadeOnDelete` |
| `voucher_type` | string(30) | `VoucherType` enum value |
| `voucher_data` | json | Full voucher payload (the `data` field of `POST /vouchers`) |
| `narration` | string(500) | nullable |
| `amount` | decimal(18,2) | Indexed — used for threshold queries |
| `status` | string(20) | `draft` (default), `submitted`, `approved`, `rejected`, `pushed` |
| `created_by` | FK → `users` | nullable, `nullOnDelete` |
| `submitted_at` | timestamp | nullable |
| `submitted_by` | FK → `users` | nullable |
| `approved_at` | timestamp | nullable |
| `approved_by` | FK → `users` | nullable |
| `rejected_at` | timestamp | nullable |
| `rejected_by` | FK → `users` | nullable |
| `rejection_reason` | text | nullable |
| `pushed_at` | timestamp | nullable — when sent to Tally |
| `push_result` | json | nullable — full `parseImportResult()` output |
| `tally_master_id` | string | nullable — assigned by Tally after push |
| `is_locked` | boolean | default `false`; set `true` once approved |
| `created_at`, `updated_at` | timestamps | |

Indexes: `draft_vch_status_idx` on (`tally_connection_id`, `status`); `draft_vch_amount_idx` on (`tally_connection_id`, `amount`).

---

## Table: `tally_organizations` *(Phase 9Z)*

Top-level MNC / group entity. A software company may own multiple legal companies under one org.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `code` | string(20) | **unique**, uppercase |
| `country` | string(3) | nullable, ISO country |
| `base_currency` | string(10) | nullable |
| `is_active` | boolean | default `true` |
| `created_at`, `updated_at` | timestamps | |

---

## Table: `tally_companies` *(Phase 9Z)*

Legal entity inside an organization. One org can have N companies (different GSTINs, countries).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_organization_id` | FK | `cascadeOnDelete` |
| `name`, `code` | strings | unique(`tally_organization_id`, `code`) |
| `country`, `base_currency`, `gstin` | nullable | |
| `is_active` | boolean | default `true` |
| `created_at`, `updated_at` | timestamps | |

---

## Table: `tally_branches` *(Phase 9Z)*

Branch / location under a company.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_company_id` | FK | `cascadeOnDelete` |
| `name`, `code` | strings | unique(`tally_company_id`, `code`) |
| `city`, `state`, `gstin` | nullable | |
| `is_active` | boolean | default `true` |
| `created_at`, `updated_at` | timestamps | |

---

## Modification: `tally_connections` *(Phase 9Z)*

Three nullable FKs added by `2026_04_17_140003_add_org_company_branch_to_tally_connections_table.php`:

| Column | Type | Notes |
|---|---|---|
| `tally_organization_id` | FK → `tally_organizations` | nullable, `nullOnDelete`. Indexed as `conn_org_idx`. |
| `tally_company_id` | FK → `tally_companies` | nullable, `nullOnDelete`. Indexed as `conn_company_idx`. |
| `tally_branch_id` | FK → `tally_branches` | nullable, `nullOnDelete` |

All three nullable — backwards-compatible with connections created before 9Z.

---

## Table: `tally_webhook_endpoints` *(Phase 9I)*

Outbound webhook targets for Tally events.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `tally_connection_id` | FK | nullable (global subscription) |
| `name` | string | |
| `url` | string(500) | target URL |
| `secret` | string(64) | **HMAC key** — hidden in responses after creation |
| `events` | json | array of event names (`['*']` = all) |
| `headers` | json | nullable — custom headers |
| `is_active` | boolean | default `true` |
| `failure_count`, `last_failure_at` | — | error counters |

---

## Table: `tally_webhook_deliveries` *(Phase 9I)*

Per-delivery log with attempt tracking.

| Column | Notes |
|---|---|
| `tally_webhook_endpoint_id` | FK, `cascadeOnDelete` |
| `event`, `payload`, `attempt_number` | one row per attempt |
| `status` | `pending` / `delivered` / `failed` |
| `response_code`, `response_body`, `delivered_at`, `next_retry_at` | — |

---

## Table: `tally_voucher_attachments` *(Phase 9I)*

Supporting files attached to a voucher.

| Column | Notes |
|---|---|
| `tally_connection_id` | FK, `cascadeOnDelete` |
| `voucher_master_id` | string — Tally master ID |
| `file_disk`, `file_path` | Laravel `Storage` disk + path |
| `original_name`, `mime_type`, `size_bytes` | — |
| `uploaded_by` | FK → `users`, `nullOnDelete` |

---

## Table: `tally_import_jobs` *(Phase 9I)*

CSV/Excel import tracking.

| Column | Notes |
|---|---|
| `tally_connection_id` | FK |
| `entity_type` | `ledger` / `group` / `stock_item` |
| `file_disk`, `file_path` | upload location |
| `total_rows`, `processed_rows`, `failed_rows` | progress counters |
| `status` | `queued` / `running` / `completed` / `failed` |
| `result_summary` | json — errors list + counts |
| `uploaded_by` | FK → `users` |

---

## Table: `tally_master_mappings` *(Phase 9M)*

Tally-name ↔ ERP-name aliases per connection. Pattern borrowed from laxmantandon/tally_migration_tdl (CustomerMappingTool / ItemMappingTool). Consulted during sync so renames on either side don't break the link.

| Column | Notes |
|---|---|
| `tally_connection_id` | FK → `tally_connections`, cascade delete |
| `entity_type` | string(32) — `ledger` / `group` / `stock_item` / etc. |
| `tally_name` | string(255) |
| `erp_name` | string(255) |
| `metadata` | json, nullable |

Unique: `(tally_connection_id, entity_type, tally_name)`. Index: `(tally_connection_id, entity_type, erp_name)`.

---

## Table: `tally_voucher_naming_series` *(Phase 9M)*

Numbering streams per voucher type — one Tally voucher type can drive multiple series (e.g. `SI/2026/`, `SINV/`). Pattern borrowed from laxmantandon/tally_migration_tdl's `NamingSeriesConfig.txt`.

| Column | Notes |
|---|---|
| `tally_connection_id` | FK, cascade delete |
| `voucher_type` | string(64) |
| `series_name` | string(64) |
| `prefix`, `suffix` | string(32), nullable |
| `last_number` | unsignedBigInt, default `0` |
| `is_active` | bool, default `true` |

Unique: `(tally_connection_id, voucher_type, series_name)`. Also adds `naming_series` column to `tally_vouchers`.

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
| `TallyRecurringVoucher` *(9L)* | `tally_recurring_vouchers` | belongsTo connection; `isDue()` |
| `TallyDraftVoucher` *(9J)* | `tally_draft_vouchers` | belongsTo connection; `isEditable()`, `isSubmittable()`, `isActionable()`; STATUS_* constants |
| `TallyOrganization` *(9Z)* | `tally_organizations` | hasMany companies, connections; hasManyThrough branches |
| `TallyCompany` *(9Z)* | `tally_companies` | belongsTo organization; hasMany branches, connections |
| `TallyBranch` *(9Z)* | `tally_branches` | belongsTo company; hasMany connections |
| `TallyWebhookEndpoint` *(9I)* | `tally_webhook_endpoints` | belongsTo connection; hasMany deliveries; `subscribesTo()` helper; `$hidden['secret']` |
| `TallyWebhookDelivery` *(9I)* | `tally_webhook_deliveries` | belongsTo endpoint |
| `TallyVoucherAttachment` *(9I)* | `tally_voucher_attachments` | belongsTo connection |
| `TallyImportJob` *(9I)* | `tally_import_jobs` | belongsTo connection; STATUS_* constants |
| `TallyMasterMapping` *(9M)* | `tally_master_mappings` | belongsTo connection; `resolveTallyName()`, `resolveErpName()` static helpers |
| `TallyVoucherNamingSeries` *(9M)* | `tally_voucher_naming_series` | belongsTo connection; `nextNumber()` atomic increment |

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
| `ApproveVouchers` *(9J)* | `approve_vouchers` |
| `ManageIntegrations` *(9I)* | `manage_integrations` |
| `SendInvoices` *(9I)* | `send_invoices` |
