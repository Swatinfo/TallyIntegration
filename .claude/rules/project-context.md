# Project Context

## Domain
TallyIntegration — Laravel module for integrating with TallyPrime accounting software. Used by businesses that run TallyPrime for accounting and need programmatic access via REST API.

## Business Logic
- **Tally XML Protocol**: All communication uses HTTP POST with XML bodies. Three export types (Data/Collection/Object) and import format. Official format verified against `.docs/Demo Samples/`.
- **Amount Convention**: Debit = ISDEEMEDPOSITIVE=Yes, Credit = ISDEEMEDPOSITIVE=No. Sign conventions differ by voucher type.
- **Object Identity**: Tally uses **names** (case-insensitive), not numeric IDs, to identify all entities.
- **Date Format**: YYYYMMDD for most operations. DD-Mon-YYYY for voucher cancel/delete attributes.
- **Multi-Connection**: One Tally instance may have multiple companies. Multiple instances may exist at different locations. Each combination = one `tally_connections` row.

## Tally Editions
- **TallyPrime Standalone (Silver)**: Single-user desktop. API on localhost.
- **TallyPrime Server (Gold)**: Multi-user server. Always-on, best for integration.
- **TallyPrime Cloud Access**: Remote desktop (OCI VM). Same API, but needs network tunnel.
- All three use the **identical XML API protocol**.

## Key Workflows
1. **Register connection** → POST /api/tally/connections with host/port/company
2. **Health check** → GET /api/tally/{conn}/health
3. **Manage masters** → CRUD ledgers, groups, stock items via REST
4. **Create vouchers** → POST with type + data, or use convenience methods
5. **Fetch reports** → GET /api/tally/{conn}/reports/{type}

## Module Architecture
- All code in `Modules/Tally/` (nwidart/laravel-modules v13)
- Namespace: `Modules\Tally\*`
- Self-contained and portable — copy to any Laravel project
