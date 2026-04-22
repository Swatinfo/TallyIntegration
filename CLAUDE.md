# CLAUDE.md

## Project Overview

TallyIntegration — Laravel module for integrating with TallyPrime accounting software via HTTP/XML API. Self-contained `nwidart/laravel-modules` module that can be dropped into any Laravel project. Provides REST API for masters, vouchers, and financial reports across multiple Tally connections.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.4, MariaDB |
| Module System | nwidart/laravel-modules v13 |
| API | REST JSON API (Sanctum available) |
| Tally Protocol | HTTP/XML to TallyPrime (port 9000) |
| Testing | Pest 4, Laravel Pint |

## Development Commands

```bash
php artisan serve                          # Start dev server
php artisan test --compact                 # Run all tests
vendor/bin/pint --dirty --format agent     # Format code
php artisan route:list --path=api/tally    # List Tally routes
php artisan module:list                    # Check module status
php artisan migrate                        # Run module migrations
bash Modules/Tally/scripts/tally-smoke-test.sh  # End-to-end API test (creates user + demo data)
```

## Key Conventions

- **Module-based**: All Tally code in `Modules/Tally/`. Namespace `Modules\Tally\*`
- **Service layer**: Services in `Modules/Tally/app/Services/` — controllers are thin
- **XML protocol**: Tally uses XML. `TallyXmlBuilder` builds envelopes, `TallyXmlParser` parses responses. Format verified against `.docs/Demo Samples/`
- **Multi-connection**: `tally_connections` DB table + `{connection}` route prefix + middleware
- **Consistent API response**: `{ success: bool, data: mixed, message: string }`

## Module Architecture

```
Modules/Tally/
├── app/
│   ├── Http/
│   │   ├── Controllers/           # ~31 controllers (masters, vouchers, reports, sync, audit, health, ops, banking, manufacturing, workflow, recurring, integration, MNC)
│   │   ├── Middleware/            # ResolveTallyConnection, CheckTallyPermission
│   │   └── Requests/             # 9 Form Request classes + SafeXmlString rule
│   ├── Models/                    # 15 models (Connection + mirror tables + sync + audit + metric + draft + recurring + MNC + integration + mapping + naming-series)
│   ├── Jobs/                      # 9 jobs (sync, conflicts, health, bulk, recurring, imports, webhook delivery)
│   ├── Events/                    # 8 event classes (+ 1 listener auto-dispatching webhooks)
│   ├── Exceptions/                # 5 custom exceptions
│   ├── Providers/                 # TallyServiceProvider, RouteServiceProvider
│   └── Services/
│       ├── TallyHttpClient.php    # HTTP POST with logging + circuit breaker
│       ├── TallyXmlBuilder.php    # XML envelopes (4 export types + import + Function + AlterID)
│       ├── TallyXmlParser.php     # XML response parsing
│       ├── TallyCompanyService.php # AlterID, Function exports, FY detection
│       ├── SyncTracker.php        # Per-entity sync state + conflict detection
│       ├── Masters/               # 11 CRUD services (with caching + audit)
│       ├── Vouchers/              # VoucherService (batch, cancel) + VoucherType enum
│       └── Reports/               # ReportService (18 report types + CSV export)
├── config/config.php              # Connection, logging, cache, circuit breaker, workflow, integration
├── database/migrations/           # 22 migrations (19 tables + column additions)
├── docs/                          # Module setup guides (9 files — incl. TDL-INSTALLATION)
├── scripts/tdl/                   # Optional TallyModuleIntegration.txt TDL companion
├── scripts/                       # tally-smoke-test.sh + lib/ (travels with module)
└── routes/api.php                 # 205 registrations (190 named) — through Phase 9N (5 new masters + field registry)
```

## API Routes (190 named + 15 unnamed PATCH, all `auth:sanctum` + permission-gated)

**Management & Sync:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/connections` | List / create connections |
| GET/PUT/DELETE | `/connections/{id}` | CRUD connection |
| GET | `/connections/{id}/health` | Health check |
| GET | `/connections/{id}/metrics` | Response time metrics |
| GET | `/connections/{id}/sync-stats` | Sync statistics |
| GET | `/connections/{id}/sync-pending` | Pending sync records |
| GET | `/connections/{id}/sync-conflicts` | Unresolved conflicts |
| POST | `/connections/{id}/sync-from-tally` | Trigger inbound sync |
| POST | `/connections/{id}/sync-to-tally` | Trigger outbound sync |
| POST | `/connections/{id}/sync-full` | Trigger full bidirectional |
| POST | `/connections/{id}/discover` | Discover companies |
| POST | `/connections/test` | Test connectivity |
| POST | `/sync/{id}/resolve` | Resolve a conflict |
| GET | `/audit-logs` | Audit trail |

**Per-connection** (`{conn}` = connection code like `MUM`):

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/{conn}/ledgers` | List (paginated) / create |
| GET/PUT/DELETE | `/{conn}/ledgers/{name}` | CRUD by name |
| GET/POST | `/{conn}/groups` | Account groups |
| GET/POST | `/{conn}/stock-items` | Stock items |
| GET/POST | `/{conn}/stock-groups` | Stock groups *(Phase 9A)* |
| GET/POST | `/{conn}/units` | Units of measurement *(Phase 9A)* |
| GET/POST | `/{conn}/cost-centres` | Cost centres *(Phase 9A)* |
| GET/POST | `/{conn}/currencies` | Currencies *(Phase 9B)* |
| GET/POST | `/{conn}/godowns` | Godowns / warehouses *(Phase 9B)* |
| GET/POST | `/{conn}/voucher-types` | Custom voucher types *(Phase 9B)* |
| GET/POST | `/{conn}/vouchers` | List / create vouchers |
| POST | `/{conn}/vouchers/batch` | Batch voucher import *(Phase 9A)* |
| GET/PUT/DELETE | `/{conn}/vouchers/{masterID}` | CRUD voucher |
| GET | `/{conn}/reports/{type}` | Reports (JSON or CSV) |
| GET | `/connections/{id}/companies` | List loaded companies *(Phase 9A)* |
| GET | `/{conn}/stats` | Dashboard counts *(Phase 9C)* |
| GET | `/{conn}/search?q=...` | Cross-master search *(Phase 9C)* |
| POST | `/{conn}/cache/flush` | Invalidate master cache *(Phase 9C)* |
| GET | `/connections/{id}/circuit-state` | Breaker state *(Phase 9C)* |
| GET | `/audit-logs/{id}` | Audit log detail *(Phase 9C)* |
| GET | `/audit-logs/export` | Audit log CSV *(Phase 9C)* |
| GET | `/connections/{id}/sync-history` | Completed/failed/cancelled syncs *(Phase 9C)* |
| POST | `/connections/{id}/sync/resolve-all` | Bulk resolve conflicts *(Phase 9C)* |
| GET | `/sync/{id}` | Single sync record *(Phase 9C)* |
| POST | `/sync/{id}/cancel` | Cancel pending sync *(Phase 9C)* |
| POST | `/{conn}/bank/reconcile` | Mark voucher reconciled *(Phase 9D)* |
| POST | `/{conn}/bank/unreconcile` | Clear reconciliation *(Phase 9D)* |
| POST | `/{conn}/bank/import-statement` | Upload bank CSV *(Phase 9D)* |
| POST | `/{conn}/bank/auto-match` | Match statement rows to vouchers *(Phase 9D)* |
| POST | `/{conn}/bank/batch-reconcile` | Batch reconcile *(Phase 9D)* |
| GET/POST | `/{conn}/stock-categories` | Stock categories *(Phase 9F)* |
| GET/POST | `/{conn}/price-lists` | Price lists (Price Levels) *(Phase 9F)* |
| POST | `/{conn}/stock-transfers` | Godown-to-godown transfer *(Phase 9F)* |
| POST | `/{conn}/physical-stock` | Inventory count adjustment *(Phase 9F)* |
| GET/POST | `/connections/{id}/recurring-vouchers` | Scheduled voucher templates *(Phase 9L)* |
| POST | `/connections/{id}/recurring-vouchers/{id}/run` | Manually fire a recurrence *(Phase 9L)* |
| GET/POST | `/connections/{id}/draft-vouchers` | Draft vouchers (maker-checker) *(Phase 9J)* |
| POST | `/connections/{id}/draft-vouchers/{id}/{submit,approve,reject}` | Workflow state transitions *(Phase 9J)* |
| GET/PUT | `/{conn}/stock-items/{name}/bom` | Bill of Materials read/write *(Phase 9G)* |
| POST | `/{conn}/manufacturing` | Manufacturing Journal voucher *(Phase 9G)* |
| POST | `/{conn}/job-work-{in,out}` | Job Work vouchers *(Phase 9G)* |
| GET/POST | `/organizations`, `/companies`, `/branches` | MNC hierarchy CRUD *(Phase 9Z)* |
| GET | `/organizations/{id}/consolidated/{balance-sheet,profit-and-loss,trial-balance}` | Consolidated reports *(Phase 9K)* |
| GET/POST | `/webhooks` | Outbound webhook endpoints *(Phase 9I)* |
| POST | `/webhooks/{id}/test` | Fire a test webhook *(Phase 9I)* |
| POST | `/connections/{id}/import/{entity}` | Queue a CSV master import *(Phase 9I)* |
| GET | `/import-jobs/{id}` | Import job status *(Phase 9I)* |
| GET/POST | `/connections/{id}/vouchers/{mid}/attachments` | Voucher attachments *(Phase 9I)* |
| GET | `/{conn}/vouchers/{mid}/pdf` | Voucher as PDF (mpdf) *(Phase 9I)* |
| POST | `/{conn}/vouchers/{mid}/email` | Email voucher PDF *(Phase 9I)* |
| GET/POST/DELETE | `/connections/{id}/master-mappings` | Tally↔ERP name alias CRUD *(Phase 9M)* |
| GET | `/connections/{id}/exceptions` | Failed + conflict sync rows *(Phase 9M)* |
| POST | `/connections/{id}/sync/reset-status` | Clear sync error flags *(Phase 9M)* |
| GET/POST/PUT/DELETE | `/connections/{id}/naming-series` | Voucher-type numbering streams *(Phase 9M)* |
| GET/POST | `/{conn}/cost-categories` | Cost Categories *(Phase 9N)* |
| GET/POST | `/{conn}/employee-groups` | Employee Groups *(Phase 9N)* |
| GET/POST | `/{conn}/employee-categories` | Employee Categories *(Phase 9N)* |
| GET/POST | `/{conn}/employees` | Employees *(Phase 9N)* |
| GET/POST | `/{conn}/attendance-types` | Attendance / Production Types *(Phase 9N)* |

## Dual-Location Doc Sync (REQUIRED)

When source in `Modules/Tally/` changes, update docs in **both** locations in the same change:

| Changed code | Update in `.docs/` + `.claude/` | Update in `Modules/Tally/docs/` |
|---|---|---|
| Migrations / models | `.claude/database-schema.md` | `CONFIGURATION.md` (if env/config impact) |
| Routes / controllers | `.claude/routes-reference.md` + `.docs/tally-integration.md` | `API-USAGE.md` |
| Services (XML, HTTP) | `.claude/services-reference.md` + `.docs/tally-api-reference.md` | `TROUBLESHOOTING.md` (if error-path changes) |
| Config | `.docs/tally-integration.md` | `CONFIGURATION.md` |
| New feature end-to-end | relevant `.docs/*.md` | `QUICK-START.md` + `API-USAGE.md` |
| Install steps | — | `INSTALLATION-FRESH.md` + `INSTALLATION-EXISTING.md` |

When the user says "update docs", update **both** locations without asking. A doc change is not complete until both are in sync. Full rule and rationale: `.claude/rules/workflow.md`.

## Mandatory Pre-Read Gate

| Task | Read FIRST |
|------|-----------|
| Tally services/XML | `.docs/tally-integration.md` + `.docs/tally-api-reference.md` |
| XML format changes | `.docs/Demo Samples/` (official Tally samples) |
| DB/Models | `.claude/database-schema.md` |
| Routes/Controllers | `.claude/routes-reference.md` |
| Services | `.claude/services-reference.md` |
| Config | `Modules/Tally/config/config.php` |

**Always read**: `tasks/lessons.md` + `tasks/todo.md`

## Reference Documentation

| Area | File(s) |
|------|---------|
| Docs index | `.docs/README.md` |
| Integration guide | `.docs/tally-integration.md` |
| XML API reference | `.docs/tally-api-reference.md` |
| API examples (12 files) | `.docs/api-examples/` |
| Demo XML samples | `.docs/Demo Samples/` |
| Product roadmap | `.docs/product-roadmap.md` |
| Database schema (17 tables) | `.claude/database-schema.md` |
| Routes reference (190 named, 205 total) | `.claude/routes-reference.md` |
| Field reference (316 field/alias mappings) | `Modules/Tally/docs/FIELD-REFERENCE.md` |
| Services reference (~34 services) | `.claude/services-reference.md` |
| Module setup guides (8 files) | `Modules/Tally/README.md` + `Modules/Tally/docs/` |

## Source of Truth Files

- `Modules/Tally/config/config.php` — Tally connection config
- `Modules/Tally/app/Services/TallyXmlBuilder.php` — XML request format
- `Modules/Tally/app/Services/TallyXmlParser.php` — XML response parsing
- `.docs/Demo Samples/` — Official TallyPrime XML samples (canonical format)

Rules auto-loaded from `.claude/rules/`: `pre-read-gate.md`, `coding-feedback.md`, `project-context.md`, `workflow.md`

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
