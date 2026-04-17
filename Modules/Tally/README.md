# Tally Module

Self-contained Laravel module for integrating with **TallyPrime** accounting software via the HTTP/XML API on port 9000.

- **Namespace:** `Modules\Tally\*`
- **Requires:** Laravel 11+ (Laravel 13 recommended), PHP 8.4, `nwidart/laravel-modules` v13, Sanctum, a queue driver, MariaDB / MySQL / PostgreSQL
- **Surface:** 44 REST routes, 9 controllers, 20+ services, 7 queued jobs, bidirectional sync engine
- **Works with:** TallyPrime Standalone (Silver), TallyPrime Server (Gold), TallyPrime Cloud Access

---

## Quick Links

| I want to… | Read |
|---|---|
| Install into a **fresh** Laravel 13 project | [docs/INSTALLATION-FRESH.md](docs/INSTALLATION-FRESH.md) |
| Drop into an **existing** Laravel app | [docs/INSTALLATION-EXISTING.md](docs/INSTALLATION-EXISTING.md) |
| Configure `.env` / `config/tally.php` | [docs/CONFIGURATION.md](docs/CONFIGURATION.md) |
| Configure TallyPrime itself (port 9000) | [docs/TALLY-SETUP.md](docs/TALLY-SETUP.md) |
| Walk through in 10 minutes | [docs/QUICK-START.md](docs/QUICK-START.md) |
| Call the REST API | [docs/API-USAGE.md](docs/API-USAGE.md) |
| Debug a problem | [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) |

Deep references (project-level): `../../.docs/tally-integration.md`, `../../.docs/tally-api-reference.md`, `../../.claude/services-reference.md`.

---

## At a glance

```
GET  /api/tally/connections               → list registered Tally instances
POST /api/tally/connections                → register a new one
GET  /api/tally/{code}/health              → ping that instance
GET  /api/tally/{code}/ledgers             → list ledgers
POST /api/tally/{code}/ledgers             → create a ledger
POST /api/tally/{code}/vouchers            → create a sales / purchase / payment voucher
GET  /api/tally/{code}/reports/balance-sheet?date=20260331
```

All responses: `{ success, data, message }`. All routes: `auth:sanctum` + permission middleware.

---

## Module layout

```
Modules/Tally/
├── app/               # 9 controllers, 20+ services, 8 models, 7 jobs, 8 events, 2 commands
├── config/config.php  # published as config('tally.*')
├── database/
│   ├── factories/     # TallyConnectionFactory
│   └── migrations/    # 10 migrations (10 tables + users column)
├── docs/              # the setup guides linked above
├── routes/api.php     # 44 route registrations
├── module.json        # nwidart manifest
└── composer.json
```

See [docs/QUICK-START.md](docs/QUICK-START.md) for the shortest path from zero to creating a voucher.
