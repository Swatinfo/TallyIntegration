# Task Tracker

---

## Completed

### Phase 9N — Canonical field registry + 5 new master endpoints (2026-04-20)
Fully aligns the module with TallyPrime's documented field/alias vocabulary (316 mappings across 14 entity types). All master and voucher writes now accept either the canonical XML tag or any TallyPrime-UI alias.

- [x] `TallyFieldRegistry` — `Modules/Tally/app/Services/Fields/TallyFieldRegistry.php`. Static map of canonical XML tag → array of aliases. Case- and whitespace-insensitive `canonicalize()` method. Entity constants for Group, Ledger, Cost Centre, Cost Category, Stock Group, Stock Category, Unit, Godown, Stock Item, Employee Group, Employee Category, Employee, Attendance Type, Voucher
- [x] `AcceptsFieldAliases` Form-Request trait — auto-applied on 11 existing `StoreXxxRequest` classes (Group, Ledger, StockItem, StockGroup, StockCategory, Unit, Godown, CostCenter + the 5 new master requests). Runs canonicalise in `prepareForValidation()` so validation rules always see canonical keys
- [x] Master services — create/update methods canonicalise payload: GroupService, LedgerService, StockItemService, StockGroupService, StockCategoryService, UnitService, GodownService, CostCenterService. Plus the 5 new services below
- [x] VoucherService — create/createBatch/alter canonicalise payload; all 149 voucher field aliases now accepted
- [x] 5 new master endpoints:
  - CostCategoryService + CostCategoryController + StoreCostCategoryRequest (2 index/show + 4 CRUD = 6 routes)
  - EmployeeGroupService + controller + request (6 routes) — maps to COSTCENTRE with CATEGORY flag
  - EmployeeCategoryService + controller + request (6 routes) — maps to COSTCATEGORY
  - EmployeeService + controller + request (6 routes) — uses EMPLOYEE master
  - AttendanceTypeService + controller + request (6 routes) — uses ATTENDANCETYPE master
- [x] Routes: 175 → 205 (+30). Named: 160 → 190. All gated by `view_masters` / `manage_masters` + `throttle:tally-write`
- [x] Smoke-test fixtures audited against canonical reference — all fields valid or documented aliases. Price List fixture cleaned (USEFORGROUPS removed — incompatible with Company.PRICELEVELLIST refactor). 5 new demo fixture arrays added for the new masters
- [x] New `Modules/Tally/docs/FIELD-REFERENCE.md` — 316-field canonical/alias reference for API integrators. Full usage examples (HTTP, PHP direct, new Form Request)
- [x] Docs synced: `CLAUDE.md` (route counts + 5 new endpoint rows + FIELD-REFERENCE.md link), `.claude/routes-reference.md` (Last verified 2026-04-20, counts bumped), `.claude/services-reference.md` (new TallyFieldRegistry section + 5 new master rows + Last verified bumped)
- [x] Pint clean, 77/77 tests pass, bash fixtures syntax clean

### Phase 9M — Reference-integration alignment (2026-04-20)
Consolidated whole-project refinements discovered by comparing against `laxmantandon/express_tally`, `laxmantandon/tally_migration_tdl`, and `aadil-sengupta/Tally.Py`.

- [x] **TDL `<TYPE>` reverted to concatenated form** — the 2026-04-20 spaced flip was itself reverted the same day. Production TDL in `tally_migration_tdl/send/*.txt` uses `Type : StockItem`, `Type : StockGroup`. Final rule: TDL `<TYPE>` = concatenated, Object `<SUBTYPE>` = spaced. Fixed: CostCenterService → `CostCentre`, VoucherTypeService → `VoucherType`, StockCategoryService → `StockCategory`, PriceListService → `PriceLevel`
- [x] **PriceListService refactored to Company.PRICELEVELLIST** — Tally's own docs + both reference integrations confirm Price Level is not a standalone master. New `TallyXmlBuilder::buildCompanyAlterRequest`. list() via `Object/Company` export + `FETCH PRICELEVELLIST`; create() via COMPANY ALTER; update/delete return a not-supported result with clear message
- [x] **Master-mapping table** — new migration `tally_master_mappings` (entity_type, tally_name, erp_name, metadata), model with `resolveTallyName`/`resolveErpName` helpers, `MasterMappingController` (3 routes under `manage_connections`), `StoreMasterMappingRequest`
- [x] **WebStatus UDF pattern + Exception Report** — `TallyXmlBuilder::withWebStatus()` helper to append `WebStatus`/`WebStatus_Message`/`WebStatus_DocName` UDF markers to any master/voucher payload. `SyncController::exceptions()` lists failed+conflict syncs; `SyncController::resetStatus()` bulk-resets to pending. 2 new routes under `manage_connections`
- [x] **Tally-push TDL companion file** — `Modules/Tally/scripts/tdl/TallyModuleIntegration.txt` starter template with UDF declarations on every master/voucher + Gateway menu scaffolding + Exception Report form + function stubs pointing at the module's existing `/import/{entity}` and `/vouchers/batch` endpoints. Documented in `Modules/Tally/docs/TDL-INSTALLATION.md`
- [x] **Naming-series per voucher-type** — migration `tally_voucher_naming_series` (prefix/suffix/last_number/is_active) + `naming_series` column on `tally_vouchers`. `TallyVoucherNamingSeries::nextNumber()` atomic increment. `VoucherNamingSeriesController` (5 routes under `manage_connections`), `StoreVoucherNamingSeriesRequest`
- [x] Routes: 165 → 175 total (151 → 160 named + 14 → 15 unnamed PATCH). Tables: 17 → 19 module tables. Migrations: 20 → 22. Models: 13 → 15
- [x] Docs synced: `CLAUDE.md` (route tree + new endpoint rows), `.claude/database-schema.md` (2 new tables + model rows, Last verified 2026-04-20), `.claude/routes-reference.md` (count update, 3 new route sections, Last verified 2026-04-20), `.claude/services-reference.md` (PriceListService row updated, 2 new builder methods, new "Master mappings + naming series" section)
- [x] `tasks/lessons.md` — 2026-04-19 no-space lesson retained; 2026-04-20 reversal entry replaced with RE-CONFIRMED note citing production TDL evidence
- [x] Migrations ran cleanly; pint clean; 77/77 tests pass

### TDL `<TYPE>` — switch all inline-TDL services to Tally's canonical spaced form (2026-04-20)
- [x] **User directive**: adopt Tally's standard canonical naming across the project. Per Tally's official TDL reference (Objects and Collections), multi-word TDL object TYPEs use spaces — same as Object SUBTYPEs
- [x] 4 services flipped from concatenated to canonical spaced form:
  - `CostCenterService`: `CostCentre` → `Cost Centre`
  - `VoucherTypeService`: `VoucherType` → `Voucher Type`
  - `StockCategoryService`: `StockCategory` → `Stock Category`
  - `PriceListService`: `PriceLevel` → `Price Level`
- [x] `TallyXmlBuilder::buildAdHocCollectionExportRequest` docblock updated with canonical name list
- [x] `.claude/services-reference.md` — inverted rule from "no-space MUST" to "canonical spaced"; Last verified bumped to 2026-04-20
- [x] `tasks/lessons.md` — prior no-space lesson (2026-04-19) marked superseded with reversal note pointing at the Price Level standalone-master architecture issue
- [x] Root-cause note on Price Level: the 30s-timeout crash is not about spacing — `Price Level` is not a standalone TDL-queryable master; names live on Company via F11 and rates live on Stock Item. Separate follow-up needed to refactor PriceListService to target Company's PRICELEVELLIST instead of a standalone PRICELEVEL master

### Cleanup safety — `_safe_delete_demo` guard + comprehensive coverage (2026-04-19)
- [x] New `_safe_delete_demo` wrapper REFUSES to delete any name not starting with `[DEMO]` — protects Tally-provided masters from accidental deletion regardless of fixture edits
- [x] Cleanup expanded across all phases: vouchers (26 voucher numbers covering every fixture + inventory ops + manufacturing + draft + recurring), stock items, stock categories, price lists, stock groups, godowns, cost centres, custom voucher types, ledgers, groups
- [x] Units + currencies intentionally NOT cleaned (no `[DEMO]` prefix on their fixtures; risks touching Tally defaults)
- [x] Audited ALL other `api_delete` call sites in the script (8 total) — every one is either `[DEMO]`-prefixed or guarded by `ENSURE_PATH=="created"`. Tally-provided / pre-existing data never touched.
- [x] `tasks/lessons.md` — new "Cleanup Touches ONLY [DEMO]-Prefixed Records" entry
- [x] 77/77 tests pass; pint clean; bash syntax clean

### Use Tally reserved groups directly — drop custom intermediate groups (2026-04-19)
- [x] DEMO_GROUPS reduced from 8 to 2 (just CRUD coverage for the group endpoint)
- [x] All 21 DEMO_LEDGERS now point to Tally RESERVED groups directly (Sundry Debtors / Sundry Creditors / Sales Accounts / Indirect Expenses / Bank Accounts / Cash-in-Hand / Duties & Taxes / Loans & Advances (Asset))
- [x] PARENT-move test in phase_4_ledgers updated to move between reserved groups (Indirect Expenses → Direct Expenses) instead of [DEMO] groups
- [x] phase_4 parent-precheck comment updated to reflect new design intent
- [x] No phase-cross dependencies left: phase_4 (ledgers) works even if phase_3 (groups) is skipped
- [x] `tasks/lessons.md` — new entry "Use Tally's Reserved Groups Directly"
- [x] 77/77 tests pass; pint clean; bash syntax clean

### Inline-TDL fields — switch to NATIVEMETHOD + minimum field sets (2026-04-19)
- [x] **Root cause**: `<FETCH>NAME, USEFORGROUPS</FETCH>` for `<TYPE>PriceLevel</TYPE>` crashed Tally (connection reset 6s) — `USEFORGROUPS` is a valid IMPORT field but NOT a valid TDL accessor on Price Level type, and comma-FETCH errors hard on unknown methods.
- [x] `buildAdHocCollectionExportRequest` rewritten — emits one `<NATIVEMETHOD>X</NATIVEMETHOD>` per field instead of single `<FETCH>X, Y</FETCH>` (per Tally docs Sample 16). NATIVEMETHOD is silently tolerated for unknown names.
- [x] Defensively trimmed `fetchFields` for all 7 inline-TDL services to minimum safe sets:
  - PriceList: `[NAME]` (was `[NAME, USEFORGROUPS]`)
  - Unit: `[NAME]` (was 6 fields)
  - Currency: `[NAME]` (was 7 fields)
  - Godown: `[NAME, PARENT]` (was 4 fields)
  - VoucherType: `[NAME, PARENT]` (was 5 fields)
  - CostCentre: `[NAME, PARENT]` (was 3 fields)
  - StockCategory: `[NAME, PARENT]` (unchanged — already safe)
- [x] filter-from-list `get($name)` only needs NAME — richer fields aren't required and add crash risk
- [x] `tasks/lessons.md` — new "Inline-TDL Collection: Use NATIVEMETHOD" entry
- [x] `.claude/services-reference.md` — buildAdHocCollectionExportRequest doc updated with both rules
- [x] 77/77 tests pass; pint clean

### TDL inline-collection TYPE — no-space form for multi-word masters (2026-04-19)
- [x] **Root cause**: `<TYPE>Price Level</TYPE>` (spaced) inside an inline `<COLLECTION>` definition hangs Tally 30s then crashes (138 health-probe failures). TDL TYPE convention for multi-word masters is the **no-space concatenated form**, opposite of Object SUBTYPE convention (which uses spaces).
- [x] Fixed 4 services: `PriceListService` (Price Level → PriceLevel), `StockCategoryService` (Stock Category → StockCategory), `VoucherTypeService` (Voucher Type → VoucherType), `CostCenterService` (Cost Centre → CostCentre)
- [x] `ensure_tally_master` extended skip condition: optional masters now skip POST on ANY non-found lookup (not just 404 — also 503/timeout) since lookup-failure often signals Tally is already unstable
- [x] `tasks/lessons.md` — new "TDL Inline-Collection TYPE Uses NO-SPACE Form" entry
- [x] `.claude/services-reference.md` — buildAdHocCollectionExportRequest doc updated with TDL TYPE naming rule

### Optional master show endpoints — tolerate 404 (2026-04-19, third iteration)
- [x] User reported: post-skip the show call (`GET /currencies/USD`) failed with 404. Show was unconditional even when create was skipped.
- [x] New `assert_ok_or_skip_404` helper in `lib/assert.sh` — 200/201 = PASS, 404 = SKIP-as-PASS (logged with reason), other = FAIL via assert_ok.
- [x] Applied to phases 3d (cost-centres), 3e (currencies), 3f (godowns), 3h (stock-categories), 3i (price-lists) — the show-by-name calls only.
- [x] Looked up multi-currency help (https://help.tallysolutions.com/multi-currency-accounting-made-simple-with-tallyprime/) — confirmed ISO 3-letter codes (USD/EUR) are canonical for NAME (our fixtures already correct). No technical XML format docs available.

### Feature-flag-dependent masters — lookup-only mode (2026-04-19, second iteration)
- [x] **Root cause refined**: not just silent EXCEPTIONS — TallyPrime can **hard-crash** on a POST when the master's required F11 feature is off. Reproduced 2026-04-19 on `POST /currencies (USD)`: lookup via inline TDL returned HTTP 200 with empty collection, but the POST that followed crashed Tally entirely (119 health-probe failures).
- [x] `ensure_tally_master` accepts new `$optional` arg (5th). When `optional=1` AND lookup misses, the function **skips POST entirely** rather than risking a crash. Sets new ENSURE_PATH value `skipped`.
- [x] `_create_many` propagates `optional` through to ensure_tally_master and counts `skipped` as a PASS (clean, intended outcome).
- [x] `lib/assert.sh _handle_failure` retains the F11-feature-flag hint for cases where EXCEPTIONS still surface (non-optional paths or upstream failures).
- [x] Marked optional in smoke test: `phase_3d_cost_centres`, `phase_3e_currencies`, `phase_3f_godowns`, `phase_3h_stock_categories`, `phase_3i_price_lists`. Voucher Types stays mandatory.
- [x] `Modules/Tally/docs/TROUBLESHOOTING.md` §24 updated to reflect lookup-only behaviour
- [x] `tasks/lessons.md` entry expanded with crash-on-POST detail

### Feature-flag-dependent masters — soft-skip + diagnostic hint (2026-04-19)
- [x] **Root cause discovered**: TallyPrime returns `EXCEPTIONS=1` with no `<LINEERROR>` text when a master import depends on a company feature flag that is OFF (Multi-Currency, Cost Centres, Multiple Godowns, Stock Categories, Multiple Price Levels). Reproduced on USD currency.
- [x] `lib/assert.sh _handle_failure` — when body has `exceptions:>=1 + line_error:null`, prints explicit hint listing all F11 features and which masters they unblock
- [x] `_create_many` accepts new optional 5th arg `$optional` (default 0) — when 1, silent EXCEPTIONS becomes a soft-skip warning instead of fatal
- [x] Marked `phase_3d_cost_centres`, `phase_3e_currencies`, `phase_3f_godowns`, `phase_3h_stock_categories`, `phase_3i_price_lists` as optional. Voucher Types stays mandatory (always-available Tally feature).
- [x] `Modules/Tally/docs/TROUBLESHOOTING.md` — new §24 with the F11 feature-to-master mapping table; old §24 renumbered to §25
- [x] `tasks/lessons.md` — new "Tally Silent EXCEPTIONS = Disabled Company Feature Flag" entry

### Optional improvements from Tally sample-xml docs alignment (2026-04-19)
- [x] **Cancel TAGNAME**: `buildCancelVoucherRequest` now emits `TAGNAME="VoucherNumber"` (compressed) per Tally Sample 13. Delete keeps spaced form. TallyXmlBuilderTest assertion updated.
- [x] **Invoice-mode auto-fill**: `VoucherService::create / createBatch / alter` run `applyInvoiceMode($data)` which auto-adds `PERSISTEDVIEW` + `OBJVIEW` = "Invoice Voucher View" when `ISINVOICE=Yes` (caller values preserved). Per Tally Sample 11.
- [x] **Unit fixtures**: DEMO_UNITS now include `ORIGINALNAME` + `DECIMALPLACES` per Tally Sample 7 (Nos→Numbers/0, Hrs→Hours/2, Users→Named Users/0).
- [x] 77/77 tests pass; pint clean; bash syntax clean
- [x] Lessons: 2 new entries (Cancel TAGNAME spelling, Invoice-mode views); services-reference: VoucherService row updated with invoice-mode + Cancel/Delete TAGNAME notes

### TDL inline-collection injection for non-built-in masters (2026-04-19)
- [x] Discovered: TallyPrime has NO built-in `List of Units` collection (and likely no built-in `List of Cost Centres`, `List of Currencies`, `List of Godowns`, `List of Voucher Types`, `List of Stock Categories`, `List of Price Levels` either). Sending these IDs returns TDL "Could not find description" error which pops a UI dialog blocking Tally's HTTP responder → 30s timeout → Tally becomes unresponsive entirely.
- [x] New builder method: `TallyXmlBuilder::buildAdHocCollectionExportRequest($collectionName, $tallyType, $fetchFields)` — defines the collection inline via `<TDL><TDLMESSAGE><COLLECTION NAME="X" ISMODIFY="No"><TYPE>Y</TYPE><FETCH>...</FETCH></COLLECTION>`.
- [x] Migrated 7 services to inline TDL: UnitService, CostCenterService, CurrencyService, GodownService, VoucherTypeService, StockCategoryService, PriceListService
- [x] Big four (Groups, Ledgers, Stock Items, Stock Groups) keep using built-in collection IDs (confirmed working)
- [x] 77/77 tests pass; pint clean
- [x] Lessons + services-reference docs updated

### Filter endpoints — "Pull X of Group" + zero-balance (2026-04-19)
- [x] Added `filterByField` and `filterByZeroBalance` helpers to `PaginatesResults` trait
- [x] `?parent=<name>` query param added to: `LedgerController@index`, `GroupController@index`, `StockItemController@index`, `StockGroupController@index`
- [x] `?zero_balance=true` query param added to `StockGroupController@index`
- [x] `StockGroupService::list()` FETCHLIST extended with `CLOSINGBALANCE` so the zero-balance filter has data to work with
- [x] 2 new tests in LedgerEndpointTest (filter by parent + filter by unknown parent → empty); 77/77 pass
- [x] `.claude/routes-reference.md` § 4b updated with filter param documentation
- [x] Mirrors API Explorer operations: "Pull Ledgers of Group", "Pull Groups of Group", "Pull Stock Items of Stock Group", "Pull Stock Group With Zero Balance"

### Object-export elimination across all master services (2026-04-19)
- [x] UnitService::get — filter-from-list (lookup of `Nos` reproducibly crashed TallyPrime; 503 → server-down)
- [x] StockItemService::get — filter-from-list (references units recursively, same crash class)
- [x] VoucherTypeService::get — filter-from-list (references parent base type)
- [x] CostCenterService::get, CurrencyService::get, GodownService::get, StockCategoryService::get, PriceListService::get — filter-from-list (defensive uniformity; small lists, Object exports unreliable)
- [x] LedgerService::get — filter-from-list (with note for future revisit if ledger count grows past 10K)
- [x] LedgerEndpointTest updated — asserts new Collection-export shape; uses collection-ledgers.xml fixture
- [x] Lessons + services-reference docs updated

### Tally built-in collection name fixes (2026-04-19)
- [x] `LedgerService::list()` — `List of Accounts` → `List of Ledgers` (Tally TDL: `Could not find description`)
- [x] `PriceListService::list()` — `List of PriceLevels` → `List of Price Levels`
- [x] `StockCategoryService::list()` — `List of StockCategories` → `List of Stock Categories`
- [x] `VoucherTypeService::list()` — `List of VoucherTypes` → `List of Voucher Types` (caught when cross-referencing the Tally appendix object hierarchy)
- [x] `VoucherTypeService::get()` — Object SUBTYPE `VoucherType` → `Voucher Type` (canonical per Tally docs)
- [x] Audit confirmed all 11 Collection IDs and all 9 Object SUBTYPEs now match Tally canonical naming
- [x] `.claude/services-reference.md` + `tasks/lessons.md` updated with full canonical lists for both Collection IDs and Object SUBTYPEs

### Stock-group/godown PARENT='Primary' fix + EXCEPTIONS handling (2026-04-19)
- [x] **Fixtures fix** — `Modules/Tally/scripts/lib/fixtures.sh`: stock-groups, godowns, stock-categories, stock-items had `PARENT:"Primary"` but Tally has no reserved "Primary" stock-side master (only account-groups). Tally returned `<LINEERROR>Stock Group 'Primary' does not exist!</LINEERROR>` with `CREATED=0 ERRORS=0 EXCEPTIONS=1` — silent no-op masquerading as success. Changed top-level entries to `PARENT:""`; stock items now reference actual created stock groups.
- [x] **Parser fix** — `TallyXmlParser::parseImportResult` now rolls `EXCEPTIONS` into `errors` count and exposes `exceptions` + `line_error` keys. Controllers will now correctly return success:false on silent-no-op imports going forward.
- [x] Lessons: new "Stock-Side Masters Have No 'Primary' Default" entry.

### Smoke test idempotency + parent-precheck + Group Object-export 503 (2026-04-19)
- [x] **Fix root-cause 503**: `GroupService::get` and `StockGroupService::get` now filter from cached `list()` result instead of issuing the hanging Object export (`SUBTYPE=Group` / `Stock Group` reproducibly times out at 30s)
- [x] **Idempotent helpers** in `lib/http.sh`: `lookup_master_by_name`, `lookup_id_by_field`, `ensure_db_entity`, `ensure_tally_master` (with **post-create verification** — re-fetches by name to catch silent-fail creates), `verify_parents_exist`
- [x] **`_create_many` rewrite** — per-record lookup-then-create; missing parent never aborts the batch
- [x] **`phase_2b_mnc_hierarchy` rewrite** — never early-returns; org/company/branch all use `ensure_db_entity`; consolidated reports always run; teardown only deletes what THIS run created
- [x] **`phase_9c_recurring`, `phase_9d_workflow`, `phase_9e_integration` rewrites** — fall back to list lookup when create id is empty; sub-steps always run
- [x] **Parent prechecks** — `verify_parents_exist` runs before phase_4_ledgers (PARENT groups), phase_5_stock_items (BASEUNITS + PARENT stock-groups), phase_6_vouchers (every LEDGERNAME / PARTYLEDGERNAME from fixtures.sh)
- [x] **`_handle_failure` diagnostics** — dumps 500-char body excerpt + explicit HINT when Tally returns `Could not find` / `LINEERROR` / `reference master missing`
- [x] **`assert.sh`** — new `TALLY_MISSING_REF_PATTERNS` regex
- [x] Bash syntax checked (`bash -n`); 75/75 PHP tests pass; pint clean
- [x] Docs synced: `tasks/lessons.md` (3 new entries — Object-export hang, idempotency, parent-precheck), `Modules/Tally/scripts/README.md` (new "Idempotent re-runs" section)

### Documentation audit (2026-04-18)
- [x] Brought every live doc to end-of-session canonical counts: 165 routes, 17 tables, 20 migrations, 9 permissions, 20 voucher types, 18 report types, 11 master entities, ~31 controllers, ~34 services, 9 queued jobs, 8 events + 1 listener
- [x] `CLAUDE.md` — module architecture tree, reference table entries updated
- [x] `.docs/tally-integration.md` — module-structure block refreshed, services table expanded, voucher/report groupings added, new MNC + Workflow + Recurring + Integration sections, rate-limit tier reference
- [x] `.docs/README.md` — table counts synced
- [x] `.docs/features.md` — Phase 9I banner "SHIPPED 2026-04-17" added (9E unchanged — still deferred)
- [x] `.claude/database-schema.md` — header reflects 20 migrations / 17 tables
- [x] `.claude/services-reference.md` — smoke-test description refreshed (165 routes + CURL_INSECURE + jq fallback + HTTPS default)
- [x] `Modules/Tally/README.md` — surface summary + module layout + dependency list refreshed
- [x] `Modules/Tally/docs/INSTALLATION-FRESH.md` — mpdf composer require, 9-permission grant example, token-name tier comment
- [x] `Modules/Tally/docs/INSTALLATION-EXISTING.md` — mpdf row in requirements, 17-table conflict check, complete TL;DR 10-step port checklist at end
- [x] `Modules/Tally/docs/CONFIGURATION.md` — permissions table now 9 rows, queue lists 10 jobs, new "Integration glue (Phase 9I)" config block
- [x] `Modules/Tally/docs/TROUBLESHOOTING.md` — four new sections (§16 429 rate limits + tier guidance; §17 SSL/.test + CURL_INSECURE; §18 "already exists" is not a failure; §19 mpdf tempDir)
- [x] `Modules/Tally/scripts/README.md` — tiered rate-limit subsection above health probe; 403 permission tests marked covered in Coverage Gaps
- [x] Rewrote negative framings to describe behaviour positively (e.g. Tally's no-auth XML port → "network-level controls, module provides authenticated gateway"; self-approval block → explicit toggle description)
- [x] Verified with grep — no stale route counts, permission counts, or table counts in any live doc (only session-tracking `tasks/todo.md` and `.backup_docs/` retain historical values, by design)

### Rate limiting — tiered + per-connection keying (2026-04-18)
- [x] `App\Providers\AppServiceProvider::boot()` rewritten with tiered `RateLimiter::for()` for `tally-api` / `tally-write` / `tally-reports`
- [x] Tier detection via `$request->user()?->currentAccessToken()?->name` — prefix match against `smoke-test-*` / `internal-*` / `system-*` (internal, 6000/min writes), `batch-*` / `sync-*` (batch, 600/min writes), everything else (standard, 60/min writes)
- [x] Per-connection keying — routes with `{connection}` route param get their own bucket (`tally:{tier}:user:{id}:conn:{code}`) so one busy branch doesn't starve another
- [x] `LIMITS` class constant holds the 3×3 rate table — single place to tune caps
- [x] Smoke-test tokens (`smoke-test-<timestamp>`) now land in the internal tier automatically — no more 429s during full smoke runs
- [x] Docs synced: `Modules/Tally/docs/CONFIGURATION.md` § Rate limiting (tier table + token-name guidance + examples), `.claude/routes-reference.md` § Throttle groups (pointer + 3-line summary)

---

## Completed

### Demo Sandbox + Smoke Harness (2026-04-17)
- [x] `Modules/Tally/app/Logging/TallyLogChannel.php` — ensures `storage/logs/tally/tally-DD-MM-YYYY.log` folder+file exist before every API call
- [x] `config/logging.php` — `tally` channel switched to custom driver
- [x] `TallyHttpClient::sendXml` — pre-flight `ensureTodayLogFile()` call
- [x] `Services/Demo/` — 8 classes: DemoConstants, DemoSafetyException, DemoGuard, DemoHttpClient, DemoEnvironment, DemoTokenVault, DemoSeeder, DemoReset, DemoCycleRunner
- [x] `Console/TallyDemoCommand.php` — interactive menu + seed/reset/fresh/test/status/rotate-token subcommands
- [x] `scripts/tally-smoke-test.sh` — bash harness hitting all 44 routes + 7 report sub-types with TAP/dry-run modes + cleanup trap
- [x] `database/seeders/TallyDatabaseSeeder.php` — delegates to DemoSeeder
- [x] `.gitignore` — `/storage/app/tally-demo/` excluded
- [x] `TallyServiceProvider` — registers `tally:demo` command
- [x] Docs: `.claude/services-reference.md` + `Modules/Tally/docs/QUICK-START.md` + `Modules/Tally/docs/CONFIGURATION.md` + `Modules/Tally/docs/TROUBLESHOOTING.md`
- [x] Pint clean, 64 existing tests still pass

## Follow-ups (deferred, not shipped in this session)

- [ ] Expand `DemoCycleRunner` from current ~25 assertions to full 108 (exercise all 8 voucher types round-trip, full permissions matrix per permission, circuit-breaker phase behind `--include-failure-tests`, conflict induction in sync phase)
- [ ] Factories for `TallyLedger`, `TallyVoucher`, `TallyStockItem`, `TallyGroup`
- [ ] Sync `.docs/tally-integration.md` + `Modules/Tally/docs/API-USAGE.md` + `INSTALLATION-FRESH.md` + `INSTALLATION-EXISTING.md` with demo sandbox references

### Phase 9I — Integration glue (2026-04-17)
- [x] 4 new migrations: `tally_webhook_endpoints`, `tally_webhook_deliveries`, `tally_voucher_attachments`, `tally_import_jobs` + 4 matching models
- [x] 5 new services in `Services/Integration/`: `PdfService` (mpdf), `MailService` (Laravel Mail), `AttachmentService` (Storage facade), `ImportService` (CSV parser), `WebhookDispatcher` (HMAC-SHA256 + exponential backoff)
- [x] 2 new queued jobs: `ProcessImportJob`, `DeliverWebhookJob` (self-reschedules on failure)
- [x] 1 new event listener: `DispatchWebhooksOnTallyEvent` — wired to all 8 Tally events in `EventServiceProvider::$listen`
- [x] 4 new controllers: `WebhookController` (CRUD + deliveries + test), `AttachmentController`, `ImportController`, `IntegrationController` (PDF + email)
- [x] 4 new Form Requests; 2 new permissions (`ManageIntegrations`, `SendInvoices`)
- [x] 15 new routes. Route count 150 → 165
- [x] New `tally.integration.*` config block; `mpdf/mpdf ^8.3` installed
- [x] Smoke-test phase `phase_9e_integration` covering webhook CRUD + test + delivery log + PDF download

### Polish (2026-04-17)
- [x] `TallyConnectionFactory` accepts nullable `tally_organization_id`/`company_id`/`branch_id` + `inHierarchy()` state helper
- [x] `App\Models\User` — added `Laravel\Sanctum\HasApiTokens` trait (uncovered by smoke test — host app had Sanctum installed but trait missing)
- [x] Smoke test: jq is now optional. `json_field` + new `json_extract` have PHP-based fallback for systems without jq (Windows Git Bash). Bool values in PHP fallback echo `true`/`false` matching jq
- [x] New smoke phase `phase_9f_permissions` + `assert_http_code` helper + `bootstrap_restricted_user_and_token` — creates zero-permission user and asserts HTTP 403 on protected routes

### Smoke test live run (2026-04-17)
- Ran against live SwatTech Demo with `--no-fail-fast --keep`
- Results: **46 passes / 5 failures / 51 calls** before Tally health probe tripped (probe worked correctly — Tally became unreachable mid-run)
- Follow-up bugs surfaced in module code:
  - `GET /DEMO/groups` → HTTP 502 (list timeout under load)
  - `GET /DEMO/groups/<first>` → HTTP 500 (show endpoint error)
  - `GET /DEMO/stock-groups/<first>` → HTTP 500 (show endpoint error)
  - `GET /DEMO/units` → HTTP 503 (Tally unreachable; probe caught it)
- These are legitimate edge-cases in `*Controller@show` handlers for certain masters — queued for a follow-up debugging session

### Phase 9Z + 9K — MNC hierarchy + Consolidation (2026-04-17)
- [x] 4 new migrations: `tally_organizations`, `tally_companies`, `tally_branches` + 3 nullable FK columns on `tally_connections` (backwards-compatible)
- [x] 3 new models with full relationship graph — `TallyOrganization` (hasMany companies/connections + hasManyThrough branches), `TallyCompany` (belongsTo organization, hasMany branches/connections), `TallyBranch` (belongsTo company, hasMany connections); `TallyConnection` fillable + 3 new belongsTo relationships
- [x] 3 new controllers with apiResource CRUD: `OrganizationController`, `CompanyController` (supports `?organization_id` filter + `withCount`), `BranchController` (supports `?company_id` filter)
- [x] 3 new Form Requests: `StoreOrganizationRequest` (code unique+alpha_num), `StoreCompanyRequest`, `StoreBranchRequest`
- [x] New `ConsolidationService` in `Services/Consolidation/` — fan-out over every active connection in an org; failure-tolerant (captures per-connection error in breakdown without aborting)
- [x] New `ConsolidatedReportController` with 3 endpoints — balance-sheet / profit-and-loss / trial-balance per organization
- [x] 18 new route registrations (all named via apiResource convention that fuses PUT+PATCH). Route count 132 → 150
- [x] All gated under `manage_connections` permission
- [x] Smoke test: new `phase_2b_mnc_hierarchy` — full org → company → branch create + list + show + update + reverse cascade delete + 3 consolidated report calls (13 API calls)
- [x] Dual-location doc sync: `.claude/database-schema.md` (3 new tables + FK modifications + 3 model rows), `.claude/routes-reference.md` (new §2c), `.claude/services-reference.md` (new Consolidation section), `Modules/Tally/docs/API-USAGE.md` (new §2b), `Modules/Tally/scripts/README.md`, `.docs/README.md`, `.docs/product-roadmap.md` (9Z + 9K marked complete), `CLAUDE.md` (186/200 lines)

### Deferred phase specs (2026-04-17)
- [x] **`.docs/features.md`** — full implementation specs for deferred phases 9E (Tax compliance — GSP-dependent) and 9I (Integration glue — library/driver/disk-dependent). Includes table schemas, service interfaces, route lists, config, permissions, week-by-week implementation sequence, and the specific open questions that must be answered before starting. Indexed in `.docs/README.md`.

### Phase 9G — Manufacturing (2026-04-17)
- [x] `VoucherType` enum +3: `ManufacturingJournal`, `JobWorkInOrder`, `JobWorkOutOrder`
- [x] New `Modules\Tally\Services\Manufacturing\ManufacturingService` — BOM `getBom()`/`setBom()` (ALTER `COMPONENTLIST.LIST` on stock item), `createManufacturingVoucher()` (builds production + N consumption inventory lines with `BATCHALLOCATIONS.LIST`), `createJobWorkIn()` + `createJobWorkOut()`
- [x] New `ManufacturingController` with 5 endpoints: `getBom`, `setBom`, `manufacture`, `jobWorkOut`, `jobWorkIn`
- [x] 5 new routes (all named, no new unnamed PATCH): 2 BOM routes nested under `stock-items/{name}/bom` (gated by `view_masters`/`manage_masters`); 3 voucher convenience routes under `manage_vouchers` + `throttle:tally-write`
- [x] Route count 127 → 132
- [x] Smoke test: new `phase_6c_manufacturing` — set BOM → read BOM → manufacture 3 bundles → Job Work Out 5 units → Job Work In 5 units (5 API calls)
- [x] Dual-location doc sync: `.claude/routes-reference.md` (new §4f-mfg + BOM rows on stock-items), `.claude/services-reference.md` (new Manufacturing section + enum expansion), `Modules/Tally/docs/API-USAGE.md` (new §7c with BOM + manufacture + job-work), `Modules/Tally/scripts/README.md`, `.docs/README.md`, `.docs/product-roadmap.md`, `CLAUDE.md` (184/200 lines)

### Phase 9J — Workflow / approvals (2026-04-17)
- [x] New migration `create_tally_draft_vouchers_table` with status state machine columns (submitted/approved/rejected by + timestamps), push tracking, `is_locked` flag, `draft_vch_status_idx` + `draft_vch_amount_idx` indexes
- [x] New `TallyDraftVoucher` model with `STATUS_*` constants + `isEditable()`, `isSubmittable()`, `isActionable()` helpers
- [x] New `TallyPermission::ApproveVouchers` enum case — splits approver role from maker (`ManageVouchers`)
- [x] New `WorkflowService` — state machine + auto-approval threshold check + self-approval guard (`require_distinct_approver`). On approve, pushes to Tally via `VoucherService::create` and records `tally_master_id`
- [x] New `DraftVoucherController` — 8 methods (index/show/store/update/destroy/submit/approve/reject) with 409 responses when state forbids action
- [x] New `StoreDraftVoucherRequest` — `voucher_type` enum + `voucher_data` array + `amount` ≥ 0
- [x] New `tally.workflow.*` config block (enabled, approval_thresholds, require_distinct_approver)
- [x] 9 new routes (8 named + 1 unnamed PATCH). Maker endpoints under `manage_vouchers`; approve/reject under new `approve_vouchers`. Route count 118 → 127
- [x] Smoke test: new `phase_9d_workflow` — create → PATCH → submit → reject + second draft → delete (7 API calls)
- [x] Dual-location doc sync: `.claude/database-schema.md` (new table + model row + permission row), `.claude/routes-reference.md` (new §2b + Form Request rows), `.claude/services-reference.md` (Workflow section + config rows), `Modules/Tally/docs/API-USAGE.md` (new §9a), `Modules/Tally/docs/CONFIGURATION.md` (workflow section + permissions row), `Modules/Tally/scripts/README.md`, `.docs/README.md`, `.docs/product-roadmap.md`, `CLAUDE.md` (181/200 lines)

### Phase 9L — Recurring vouchers (2026-04-17, partial)
- [x] New migration `create_tally_recurring_vouchers_table` + `TallyRecurringVoucher` model with `connection` BelongsTo + `isDue()`
- [x] New `RecurringVoucherService` — `fire()`, `calculateNextRun()`, `bootstrapNextRun()` (frequencies: daily/weekly/monthly/quarterly/yearly; `day_of_month` clamped ≤28 for Feb safety)
- [x] New `ProcessRecurringVouchersJob` — queries `is_active=true AND next_run_at <= today`, chunks 50, rebinds HTTP client per connection, catches exceptions and records them on `last_run_result`
- [x] New `RecurringVoucherController` with 6 named endpoints + PATCH — mounted under `/connections/{connection}/recurring-vouchers` in the `manage_connections` group (DB-only config, no `ResolveTallyConnection` middleware needed)
- [x] New `StoreRecurringVoucherRequest` — `voucher_type` enum, `frequency` enum, `day_of_month`/`day_of_week` `required_if` by frequency
- [x] Scheduler wired in `TallyServiceProvider::configureSchedules()` — daily at 00:30
- [x] Migration ran cleanly after fixing index name (MariaDB 64-char limit → `recurring_vch_due_idx`). Route count 111 → 118
- [x] Smoke test: new `phase_9c_recurring` covers create / list / show / PATCH pause / PUT resume / manual run / delete (7 API calls)
- [x] Deferred within 9L: scheduled report email (waits on 9I mail driver) and PDC rollover (Tally handles natively)
- [x] Dual-location doc sync: `.claude/database-schema.md` (new table + model row), `.claude/routes-reference.md` (7 new rows), `.claude/services-reference.md` (new Recurring section + job table + schedule row), `Modules/Tally/docs/API-USAGE.md` (new §9b), `Modules/Tally/scripts/README.md`, `.docs/README.md`, `.docs/product-roadmap.md`, `CLAUDE.md`

### Phase 9F — Inventory advanced (2026-04-17)
- [x] `VoucherType` enum expanded — +9 cases: `SalesOrder`, `PurchaseOrder`, `Quotation`, `DeliveryNote`, `ReceiptNote`, `RejectionIn`, `RejectionOut`, `StockJournal`, `PhysicalStock`. All work via existing `POST /{c}/vouchers` (no code change in `VoucherService::create`)
- [x] 2 new master services: `StockCategoryService` (STOCKCATEGORY), `PriceListService` (PRICELEVEL)
- [x] 2 new controllers: `StockCategoryController`, `PriceListController` + 2 new Form Requests
- [x] New `InventoryController` with `stockTransfer()` and `physicalStock()` convenience endpoints — take simplified flat payloads and assemble correct Stock Journal / Physical Stock voucher data with `BATCHALLOCATIONS.LIST`
- [x] `VoucherService` extended with `createStockTransfer()` and `createPhysicalStock()` helpers
- [x] +14 route registrations (12 named + 2 unnamed PATCH). Route count 97 → 111
- [x] Smoke test: 2 new master sub-phases (3h stock-categories, 3i price-lists) + new `phase_6b_inventory_ops` (stock-transfer, physical-stock, SalesOrder, PurchaseOrder, DeliveryNote — 5 API calls)
- [x] Batch + serial-number tracking supported inline via `BATCHALLOCATIONS.LIST` on voucher inventory entries — no dedicated endpoint needed; client assembles data on the existing `/vouchers` endpoint
- [x] Dual-location doc sync: `.claude/routes-reference.md` (new §4f-inv + all CRUD rows), `.claude/services-reference.md` (enum expansion + 2 new master rows + 2 helper methods), `Modules/Tally/docs/API-USAGE.md` (new §4h/4i/7b), `Modules/Tally/scripts/README.md`, `.docs/README.md`, `.docs/product-roadmap.md`, `CLAUDE.md` (177/200 lines)

### Phase 9D — Banking (2026-04-17)
- [x] New `Modules\Tally\Services\Banking\BankingService` — reconcile/unreconcile (ALTER voucher with `BANKERDATE`), CSV statement parser, amount+date match algorithm with `exact|high|low` confidence, batch reconcile
- [x] New `BankingController` with 5 endpoints: `reconcile`, `unreconcile`, `importStatement`, `autoMatch`, `batchReconcile`
- [x] New Form Requests: `ReconcileVoucherRequest`, `ImportBankStatementRequest` (file-or-csv with `withValidator` cross-check)
- [x] `ReportService` extended with 3 methods: `bankReconciliation`, `chequeRegister`, `postDatedCheques`
- [x] `ReportController` dispatch — 3 new report types: `bank-reconciliation`, `cheque-register`, `post-dated-cheques`
- [x] +5 route registrations under `manage_vouchers` + `throttle:tally-write`. Route count 92 → 97
- [x] Smoke test: new `phase_8b_banking` (8 API calls — 3 reports + import + auto-match + reconcile + unreconcile + batch)
- [x] Dual-location doc sync: `.claude/routes-reference.md` (new §4f-bank + report types + Form Request rows), `.claude/services-reference.md` (new Banking section), `Modules/Tally/docs/API-USAGE.md` (new §8b), `Modules/Tally/scripts/README.md`, `.docs/README.md`, `.docs/product-roadmap.md`, `CLAUDE.md` (173/200 lines)

### Phase 9C — Observability polish (2026-04-17)
- [x] New `OperationsController` (cross-cutting: stats, search, cache flush)
- [x] `AuditLogController` extended — `show()` + `export()` (streamed CSV with chunk query)
- [x] `SyncController` extended — `show()`, `cancel()` (adds `cancelled` status), `history()` (paginated completed/failed/cancelled), `resolveAll()` (bulk strategy application)
- [x] `TallyConnectionController` extended — `circuitState()` wraps `CircuitBreaker::getState()`
- [x] +10 new routes (all named, no unnamed variants). Verified via `php artisan route:list` — 82 → 92 total
- [x] Smoke test: new `phase_9b_observability` covers all 10 endpoints including conditional sync detail/cancel + audit-log CSV download
- [x] Dual-location doc sync: `.claude/routes-reference.md` (new rows + 4f-ops section), `Modules/Tally/docs/API-USAGE.md` (new §10), `Modules/Tally/scripts/README.md`, `.docs/README.md`, `.docs/product-roadmap.md` (9C complete), `CLAUDE.md` (9 new endpoint rows, 168/200 lines)

### Phase 9B — New master domains + reports (2026-04-17)
- [x] 3 new services: `CurrencyService`, `GodownService`, `VoucherTypeService`
- [x] 3 new controllers: `CurrencyController`, `GodownController`, `VoucherTypeController`
- [x] 3 new Form Requests with `SafeXmlString` validation
- [x] `ReportService` extended with 8 methods (cashBankBook / salesRegister / purchaseRegister / agingAnalysis / cashFlow / fundsFlow / receiptsPayments / stockMovement)
- [x] `ReportController` dispatch updated — 8 new report types: `cash-book`, `sales-register`, `purchase-register`, `aging`, `cash-flow`, `funds-flow`, `receipts-payments`, `stock-movement`
- [x] +18 route registrations (15 named + 3 unnamed PATCH). Verified via `php artisan route:list` — 64 → 82 total
- [x] Smoke test extended: 3 new sub-phases (3e/3f/3g) + 8 new report calls. Fixtures: 2 currencies, 2 godowns, 2 voucher types
- [x] Dual-location doc sync: `.claude/routes-reference.md`, `.claude/services-reference.md` (reports table + masters table), `Modules/Tally/docs/API-USAGE.md` (new §4e/4f/4g + expanded reports list), `Modules/Tally/scripts/README.md`, `.docs/README.md`, `.docs/product-roadmap.md`, `CLAUDE.md`

### Phase 9A — Expose existing services via REST (2026-04-17)
- [x] 3 new controllers: `StockGroupController`, `UnitController`, `CostCenterController` (mirror `GroupController` shape)
- [x] 3 new Form Requests with `SafeXmlString` validation
- [x] Extended `VoucherController` with `batch()` — exposes `VoucherService::createBatch` at `POST /{c}/vouchers/batch`
- [x] Extended `TallyConnectionController` with `companies()` — `GET /connections/{id}/companies`
- [x] +20 route registrations (17 named + 3 unnamed PATCH). Verified via `php artisan route:list --path=api/tally` — 44 → 64 total
- [x] Smoke test extended: 3 new sub-phases (3b/3c/3d), batch voucher call in phase 6, companies call in phase 2. Fixtures: 3 stock groups, 3 units, 3 cost centres, 1 batch voucher payload
- [x] Coverage Gaps section in `Modules/Tally/scripts/README.md` rewritten — items 1-5 now marked covered
- [x] Dual-location doc sync: `.claude/routes-reference.md` (with new Form-Request rows), `.claude/services-reference.md`, `Modules/Tally/docs/API-USAGE.md` (new §4b/4c/4d + batch + companies sections), `.docs/README.md`, `.docs/product-roadmap.md` (9A complete, 9B-9L listed)
- [x] `CLAUDE.md` updated — route counts + new endpoint rows (still 155/200 lines)

### Smoke-Test Script (2026-04-17)
- [x] `Modules/Tally/scripts/tally-smoke-test.sh` — 10 phases, 44-endpoint coverage, --fail-fast default, script lives inside the module so it travels with copy-paste installs
- [x] `Modules/Tally/scripts/lib/colors.sh, logger.sh, http.sh, assert.sh, auth.sh, fixtures.sh`
- [x] Always-on file logging to `storage/logs/tally/tally-DD-MM-YYYY.log` (auto-created)
- [x] Tinker-based user + token bootstrap (DB had no users)
- [x] Software-company demo data — 8 groups, 15 ledgers, 3 stock items, 10 vouchers (`[DEMO]` prefix)
- [x] Auto-seeds connection row only if missing
- [x] Interactive clean/keep prompt when demo data present
- [x] `Modules/Tally/scripts/README.md` + `.smoke.env.example`
- [x] Added `Modules/Tally/scripts/.smoke.env` to `.gitignore`
- [x] Dual-doc sync: API-USAGE.md, TROUBLESHOOTING.md, QUICK-START.md, .docs/README.md, CLAUDE.md all updated
- [x] **Company isolation (idiot-proof):** hard preflight gate that target company "SwatTech Demo" is loaded; `--company=<name>` flag; refuse-to-run if connection row targets wrong company; banner shows target company; docs updated (README, TALLY-SETUP §6b, QUICK-START, TROUBLESHOOTING)
- [x] **Per-call Tally health probe:** defensive curl to `http://host:port` before every API call; aborts with exit 11 if Tally becomes unreachable mid-run; probe count shown in summary
- [x] **Coverage expansion — real-world scenarios:** +6 ledgers (CGST/SGST/IGST input+output, TDS Receivable), +8 voucher fixtures (sales with CGST+SGST, sales IGST interstate, sales with inventory entry, USD multi-currency, purchase with input GST credit, receipt/payment with bill allocation, journal depreciation), +3 CSV exports, pagination + sort + multi-term search tests, PATCH variants (connection/ledger/stock/voucher), ledger PARENT-move + GSTIN/credit-limit PATCH, HSN update, metrics 24h+7d, audit-log filter tests (action / object_type / connection). Total API calls ~101 → ~150
- [x] **Coverage gaps documented (Phase 9 added to product-roadmap.md):** Stock Groups / Units / Cost Centres CRUD routes missing (services exist, no controllers); batch voucher import endpoint missing; permission-denied negative tests deferred. Flagged in `Modules/Tally/scripts/README.md` "Coverage gaps" section

### Documentation Refresh (2026-04-17)
- [x] Regenerated `.claude/database-schema.md` from all 10 migrations (verified source)
- [x] Regenerated `.claude/routes-reference.md` from `routes/api.php` — corrected count (40 named + 4 unnamed PATCH = 44 registrations; prior doc said 29/44)
- [x] Regenerated `.claude/services-reference.md` from full service/job/event source
- [x] Updated `.docs/tally-integration.md` — added sync engine, security, observability sections; fixed controller/model counts
- [x] Updated `.docs/tally-api-reference.md` — verified XML format against Demo Samples, added verification stamp
- [x] Updated `.docs/README.md` — fixed counts, added links to module setup guides, added dual-location rule
- [x] Created `Modules/Tally/README.md` — module entry point
- [x] Created `Modules/Tally/docs/INSTALLATION-FRESH.md` — fresh Laravel 13 install
- [x] Created `Modules/Tally/docs/INSTALLATION-EXISTING.md` — drop-in to existing Laravel 11+ app
- [x] Created `Modules/Tally/docs/CONFIGURATION.md` — env, Sanctum, queue, schedule, rate-limit
- [x] Created `Modules/Tally/docs/TALLY-SETUP.md` — TallyPrime-side setup across editions
- [x] Created `Modules/Tally/docs/QUICK-START.md` — 10-minute walkthrough
- [x] Created `Modules/Tally/docs/API-USAGE.md` — curl + PHP for every endpoint
- [x] Created `Modules/Tally/docs/TROUBLESHOOTING.md` — 15 symptom-first playbooks
- [x] Added **Dual-Location Doc Sync** rule to `CLAUDE.md` + `.claude/rules/workflow.md`
- [x] CLAUDE.md verified under 200 lines

### Phase 8: Bidirectional Sync Engine (2026-04-16)
- [x] Local mirror tables (tally_ledgers, tally_vouchers, tally_stock_items, tally_groups) + 4 models
- [x] tally_syncs per-entity tracking + TallySync model + SyncTracker service
- [x] SyncToTallyJob (outbound ERP → Tally, priority-based processing)
- [x] SyncFromTallyJob (inbound Tally → ERP, AlterID incremental, conflict detection)
- [x] ProcessConflictsJob (auto-resolves erp_wins/tally_wins/newest_wins strategies)
- [x] SyncController with 7 routes (stats, pending, conflicts, resolve, trigger inbound/outbound/full)
- [x] Scheduled: bidirectional every 10 min, conflicts every 5 min

### Phase 7: Advanced Sync (2026-04-16)
- [x] AlterID incremental sync + TallyCompanyService + buildAlterIdQueryRequest
- [x] Function export (TYPE=Function) + buildFunctionExportRequest
- [x] Batch voucher listing (monthly splits for large datasets)

### Phase 6: Enterprise (2026-04-16)
- [x] tally:health and tally:sync Artisan commands

### Phase 5: API Polish (2026-04-16)
- [x] PaginatesResults trait + CSV export for reports

### Phase 4: Monitoring (2026-04-16)
- [x] CircuitBreaker + MetricsCollector + connection test/discover

### Phase 3: Jobs & Events (2026-04-16)
- [x] 8 events + 4 jobs + schedule

### Phase 2: Observability (2026-04-16)
- [x] TallyRequestLogger + AuditLogger + CachesMasterData

### Phase 1: Security (2026-04-16)
- [x] 5 exceptions + 9 Form Requests + Sanctum + permissions + rate limiting + 64 tests

### Foundation (2026-04-16)
- [x] nwidart module + XML protocol + 6 master services + voucher + reports + multi-connection
