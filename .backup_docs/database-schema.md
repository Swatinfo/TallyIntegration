# Database Schema Reference

Generated from migrations. Update after schema changes.

## Module: Tally

### tally_connections

Stores configuration for each TallyPrime instance/company connection.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| name | string | No | | Display name ("Mumbai HQ") |
| code | string (unique) | No | | URL identifier ("MUM") — used in route prefix |
| host | string | No | localhost | TallyPrime server hostname/IP |
| port | smallint unsigned | No | 9000 | TallyPrime HTTP port |
| company_name | string | No | '' | Target company name in Tally |
| timeout | smallint unsigned | No | 30 | HTTP timeout seconds |
| is_active | boolean | No | true | Whether connection is usable |
| last_alter_master_id | bigint unsigned | No | 0 | Tally's AltMstId at last sync — for incremental sync |
| last_alter_voucher_id | bigint unsigned | No | 0 | Tally's AltVchId at last sync — for incremental sync |
| last_synced_at | timestamp | Yes | | When last sync completed |
| created_at | timestamp | Yes | | |
| updated_at | timestamp | Yes | | |

**Indexes**: `code` (unique)

**Model**: `Modules\Tally\Models\TallyConnection`
**Factory**: `Modules\Tally\Database\Factories\TallyConnectionFactory`
**Migration**: `Modules/Tally/database/migrations/2026_04_16_064355_create_tally_connections_table.php`

### tally_ledgers
Local mirror of Tally ledgers. Unique on (tally_connection_id, name).
Columns: id, tally_connection_id (FK), name, parent, gstin, gst_registration_type, state, email, phone, contact_person, opening_balance, closing_balance, credit_period, credit_limit, currency, address (JSON), tally_raw_data (JSON), data_hash, timestamps.

### tally_vouchers
Local mirror of Tally vouchers. Indexed on connection+type, connection+date, connection+party.
Columns: id, tally_connection_id (FK), voucher_number, tally_master_id, voucher_type, date, party_name, narration, amount, is_cancelled, ledger_entries (JSON), inventory_entries (JSON), bill_allocations (JSON), tally_raw_data (JSON), data_hash, timestamps.

### tally_stock_items
Local mirror of Tally stock items. Unique on (tally_connection_id, name).
Columns: id, tally_connection_id (FK), name, parent, base_unit, opening/closing_balance_qty/value, opening_rate, has_batches, hsn_code, tally_raw_data (JSON), data_hash, timestamps.

### tally_groups
Local mirror of Tally account groups. Unique on (tally_connection_id, name).
Columns: id, tally_connection_id (FK), name, parent, nature, is_primary, tally_raw_data (JSON), data_hash, timestamps.

### tally_syncs
Per-entity sync tracking for bidirectional sync. Indexed on connection+type+status, status+priority.
Columns: id, tally_connection_id (FK), entity_type, entity_id, tally_name, tally_master_id, sync_direction, sync_status, priority, local_data_hash, tally_data_hash, last_synced_at, last_sync_attempt, sync_attempts, error_message, conflict_data (JSON), resolution_strategy, resolved_at, resolved_by (FK users), timestamps.

### tally_audit_logs
Audit trail for all Tally CUD operations.
Columns: id, user_id (FK), tally_connection_id (FK), action, object_type, object_name, request_data (JSON), response_data (JSON), ip_address, user_agent, created_at.

### tally_response_metrics
Per-request response time tracking.
Columns: id, tally_connection_id (FK), endpoint, response_time_ms, status, created_at.

## Laravel Core Tables

### users
Standard Laravel users table (id, name, email, password, timestamps).

### personal_access_tokens
Laravel Sanctum tokens (tokenable_type, tokenable_id, name, token, abilities, timestamps).

### cache, cache_locks
Laravel cache driver tables.

### jobs, job_batches, failed_jobs
Laravel queue driver tables.
