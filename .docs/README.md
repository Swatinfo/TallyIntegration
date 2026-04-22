# Project Documentation Index

Reference documentation for Claude Code. Each file covers one area of the project.

**Last verified:** 2026-04-17

## Reference Files (.claude/)

Authoritative, source-derived references. Regenerate these after major refactors.

| File | Purpose |
|------|---------|
| [database-schema.md](../.claude/database-schema.md) | All 17 tables, columns, indexes, FKs |
| [routes-reference.md](../.claude/routes-reference.md) | All 165 route registrations (151 named), controllers, middleware, permissions |
| [services-reference.md](../.claude/services-reference.md) | All services, methods, concerns, jobs, events, exceptions |

## Feature Documentation (.docs/)

| File | Purpose |
|------|---------|
| **This file** | Documentation index |
| [tally-integration.md](tally-integration.md) | Module architecture, sync engine, multi-connection, security, observability |
| [tally-api-reference.md](tally-api-reference.md) | TallyPrime XML API format — request/response examples, report IDs, amount signs |
| [api-examples/](api-examples/README.md) | **Complete REST API examples** — curl + PHP for every operation (12 files) |
| [product-roadmap.md](product-roadmap.md) | Planned work |
| [features.md](features.md) | Deferred-phase implementation specs (9E Tax, 9I Integration) — full build briefs waiting on external decisions |
| `Demo Samples/` | Official TallyPrime XML samples (canonical source-of-truth for XML format) |

## Operational Scripts (scripts/)

| File | Purpose |
|------|---------|
| [Modules/Tally/scripts/README.md](../Modules/Tally/scripts/README.md) | Overview of all operational scripts — **scripts live inside the module so they travel with it** |
| `Modules/Tally/scripts/tally-smoke-test.sh` | End-to-end exercise of all 44 API endpoints with software-company demo data. Probes Tally health before every call. Logs to `storage/logs/tally/tally-DD-MM-YYYY.log` |

## Module Setup Guides (Modules/Tally/docs/)

Setup + operations documentation lives next to the module so it travels with the code.

| File | Purpose |
|------|---------|
| [Modules/Tally/README.md](../Modules/Tally/README.md) | Module entry point, quick links |
| [INSTALLATION-FRESH.md](../Modules/Tally/docs/INSTALLATION-FRESH.md) | Install into a **fresh** Laravel 13 project |
| [INSTALLATION-EXISTING.md](../Modules/Tally/docs/INSTALLATION-EXISTING.md) | Drop-in install into an **existing** Laravel 11+ app |
| [CONFIGURATION.md](../Modules/Tally/docs/CONFIGURATION.md) | `.env` vars, `config/tally.php`, Sanctum, queue, schedule |
| [TALLY-SETUP.md](../Modules/Tally/docs/TALLY-SETUP.md) | TallyPrime-side setup (port 9000) across Standalone / Server / Cloud Access |
| [QUICK-START.md](../Modules/Tally/docs/QUICK-START.md) | 10-minute walkthrough |
| [API-USAGE.md](../Modules/Tally/docs/API-USAGE.md) | REST API usage with curl + PHP |
| [TROUBLESHOOTING.md](../Modules/Tally/docs/TROUBLESHOOTING.md) | Common errors, logs, debugging |

## Demo Samples Reference

| Folder | Content |
|--------|---------|
| `1_XML Messaging Format/` | Basic report requests (Balance Sheet) |
| `2_Export Data/` | Report exports with/without TDL |
| `4_Collection Specification/` | Collection exports — response format |
| `5_Object Specification/` | Single-object export by name — response format |
| `8_Import Vouchers/` | Voucher creation, cancellation, deletion |
| `9_Tally as Server/` | VB frontend importing masters & creating vouchers |
| `10_Tally as Client/` | PHP web service responding to Tally requests |
| `11_Integration Using ODBC/` | ODBC data access examples |

## Module Structure

All Tally code lives in `Modules/Tally/` (nwidart/laravel-modules v13). Namespace: `Modules\Tally\*`.

## Doc Sync Rule

**Any change to `Modules/Tally/` source must update docs in *both* locations:**

- `.docs/` + `.claude/` — project-level references (used by Claude Code and engineers reading the repo)
- `Modules/Tally/docs/` — module-level setup/operations guides (travel with the module)

See `CLAUDE.md` → "Dual-Location Doc Sync" for the exact mapping table.

## How to Regenerate Reference Files

```
"Scan module migrations and update .claude/database-schema.md"
"Scan module routes and update .claude/routes-reference.md"
"Scan module services and update .claude/services-reference.md"
```

Regenerate after major refactors to keep them current.
