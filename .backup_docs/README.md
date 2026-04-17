# Project Documentation Index

Reference documentation for Claude Code. Each file covers one area of the project.

## Reference Files (.claude/)

| File | Purpose |
|------|---------|
| [database-schema.md](../.claude/database-schema.md) | All tables, columns, indexes |
| [routes-reference.md](../.claude/routes-reference.md) | All 29 routes, controllers, middleware |
| [services-reference.md](../.claude/services-reference.md) | All services, methods, parameters |

## Feature Documentation (.docs/)

| File | Purpose |
|------|---------|
| **This file** | Documentation index |
| [tally-integration.md](tally-integration.md) | Module architecture, usage examples, multi-connection, API endpoints |
| [tally-api-reference.md](tally-api-reference.md) | TallyPrime XML API format, request/response examples, report names |
| [api-examples/](api-examples/README.md) | **Complete API examples** — curl + PHP for every operation (12 files) |
| `Demo Samples/` | Official TallyPrime XML samples (source of truth for XML format) |

## Demo Samples Reference

| Folder | Content |
|--------|---------|
| `1_XML Messaging Format/` | Basic report requests (Balance Sheet) |
| `2_Export Data/` | Report exports with/without TDL |
| `4_Collection Specification/` | Collection exports (list of ledgers) — response format |
| `5_Object Specification/` | Single object export by name — response format |
| `8_Import Vouchers/` | Voucher creation, cancellation, deletion — import format |
| `9_Tally as Server/` | VB frontend importing masters & creating vouchers |
| `10_Tally as Client/` | PHP web service responding to Tally requests |
| `11_Integration Using ODBC/` | ODBC data access examples |

## Module Structure

All Tally code lives in `Modules/Tally/` (nwidart/laravel-modules v13). Namespace: `Modules\Tally\*`.

## How to Regenerate .claude/ Reference Files

```
"Scan module migrations and update .claude/database-schema.md"
"Scan module routes and update .claude/routes-reference.md"
"Scan module services and update .claude/services-reference.md"
```

Regenerate after major refactors to keep them current.
