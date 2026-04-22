# Lessons Learned

Patterns and corrections captured during development. Review at session start.

---

## Tally XML API

- **2026-04-16**: Official Tally XML uses `<VERSION>1</VERSION>` + `<TYPE>` + `<ID>` in `<HEADER>`, NOT `<TALLYREQUEST>Export Data</TALLYREQUEST>` with `<REPORTNAME>` in body. Always match the format from `.docs/Demo Samples/`.
- **2026-04-16**: Voucher import uses `<TALLYREQUEST>Import</TALLYREQUEST>` (not "Import Data"), `<TYPE>Data</TYPE>`, `<ID>Vouchers</ID>`. Data goes in `<BODY><DATA><TALLYMESSAGE>`, not `<IMPORTDATA><REQUESTDATA>`.
- **2026-04-16**: Use `VOUCHERTYPENAME` as child element for voucher creation, NOT `VCHTYPE` attribute. The `VCHTYPE` attribute is only used for Cancel/Delete actions.
- **2026-04-16**: Voucher cancel/delete uses **attribute format**: `DATE`, `TAGNAME="Voucher Number"`, `TAGVALUE`, `VCHTYPE`, `ACTION` all as attributes on `<VOUCHER>`.
- **2026-04-16**: Payment voucher amount signs: debit entry (expense) = `ISDEEMEDPOSITIVE=Yes, AMOUNT=positive`, credit entry (bank) = `ISDEEMEDPOSITIVE=No, AMOUNT=negative`.
- **2026-04-16**: Single-entity fetch should use `TYPE=Object` + `SUBTYPE=Ledger` + `ID TYPE="Name"`, NOT report export. Report export returns report data, not the master object itself.
- **2026-04-16**: `<FETCHLIST><FETCH>Name</FETCH></FETCHLIST>` for selective fields, NOT `<DESC><FIELD>`.
- **2026-04-16**: Batch voucher import is supported — multiple `<VOUCHER>` elements inside one `<TALLYMESSAGE>`.
- **2026-04-16**: `<IMPORTDUPS>@@DUPCOMBINE</IMPORTDUPS>` in static variables controls duplicate handling for voucher imports.
- **2026-04-16**: Cancel response returns `COMBINED=1` (not `DELETED=1`). Delete response returns `ALTERED=1`.
- **2026-04-16**: `<EXPLODEFLAG>Yes</EXPLODEFLAG>` MUST be in STATICVARIABLES for all Data and Collection exports. Without it, nested groups/sub-ledgers may not expand. All Demo Samples include it.
- **2026-04-16**: Voucher creation uses bare `<VOUCHER>` tag — NO `ACTION` attribute. ACTION is only for Alter/Cancel/Delete. Demo sample 8 confirms this.
- **2026-04-16**: Object export uses `<SVEXPORTFORMAT>BinaryXML</SVEXPORTFORMAT>`, NOT `$$SysName:XML`. Data/Collection exports use `$$SysName:XML`.
- **2026-04-16**: Voucher `<TALLYMESSAGE>` does NOT need `xmlns:UDF="TallyUDF"`. That namespace is only on master import TALLYMESSAGE.
- **2026-04-16**: Amount signs in Demo Sample 8 show BOTH conventions (positive+negative and negative+positive) in two separate Payment vouchers. Tally accepts either as long as entries balance to zero. Our code's sign convention is valid.

## Tally Deployment Types

- **2026-04-16**: TallyPrime Standalone, Server, and Cloud Access all use the **identical XML API protocol**. The only difference is network accessibility (host/port). Cloud Access is a remote desktop (OCI), NOT a SaaS API.

## Module Structure

- **2026-04-16**: Project uses `nwidart/laravel-modules` v13. All Tally code lives in `Modules/Tally/`. Namespace is `Modules\Tally\*` with code in `Modules/Tally/app/`. PSR-4 mapping in root `composer.json`: `"Modules\\Tally\\"` → `"Modules/Tally/app/"`.
- **2026-04-16**: Module routes are prefixed `api/tally` by `RouteServiceProvider`. Route names prefixed `tally.`. No web routes — API only.
- **2026-04-16**: On Windows, `sed` namespace replacement fails with backslash escaping. Use a temp PHP script file instead for batch find/replace.

## Incremental Sync

- **2026-04-16**: Tally tracks changes via `$AltMstId` (master AlterID) and `$AltVchId` (voucher AlterID). Query these using a TDL report before fetching data. If IDs match the stored values, skip the sync entirely — this makes hourly sync nearly free for stable companies.
- **2026-04-16**: `TYPE=Function` with `<ID>$$SystemPeriodFrom</ID>` returns the financial year start date. Useful for auto-detecting date ranges instead of hardcoding.
- **2026-04-16**: For companies with 100K+ vouchers, batch by monthly date ranges to avoid Tally timeouts or memory issues.

## Postman Collection Comparison

- **2026-04-16**: The third-party Postman collection (TallyConnector) uses `LEDGERENTRIES.LIST` while official Tally samples use `ALLLEDGERENTRIES.LIST`. Both work — Tally accepts either. Our code follows the official sample format.
- **2026-04-16**: The Postman collection uses `<ID>All Masters</ID>` for voucher imports; official samples use `<ID>Vouchers</ID>`. Both work.
- **2026-04-16**: The Postman collection adds `Action="Create"` attribute to `<VOUCHER>` tags; official samples use bare `<VOUCHER>` for creation. Both work.

## Parser — text alongside attributes

- **2026-04-18**: `TallyXmlParser::xmlToArray()` now stores text content under a `#text` key when an element carries attributes. Tally stamps `TYPE="String"` / `TYPE="Logical"` on most leaf fields (PARENT, ISREVENUE, BASEUNITS, PARENTSTRUCTURE…) — without this, the values silently disappeared from every master response. Integrations should read `$row['PARENT']['#text'] ?? $row['PARENT']` (the second branch catches attributeless leaves).

## SVCURRENTCOMPANY Pin Via Request-Scoped Client

- **2026-04-18**: `TallyXmlBuilder::resolveDefaultCompany()` now pulls the company from the request-scoped `TallyHttpClient` (rebound per-request by `ResolveTallyConnection` middleware) before falling back to `config('tally.company')`. Previously all master/voucher/report calls dropped `<SVCURRENTCOMPANY>` because services didn't thread `$company` through and the config default is empty in multi-connection setups. Observed effect: Object exports hung for the full 30s timeout because Tally didn't know which loaded company to search. To intentionally suppress the pin (global collections like `List of Companies`), pass `company: ''` — the resolver short-circuits on non-null values.

## Global Tally Collections Must Not Pin Company

- **2026-04-18**: `TallyHttpClient::getCompanies()` / `isConnected()` send the List-of-Companies export with `company: ''` so `SVCURRENTCOMPANY` is omitted. Pinning a specific company on a global collection returns empty results — applies to List of Companies and any other cross-company metadata report.

## Circuit Breaker Must Be Wired

- **2026-04-18**: The `CircuitBreaker` service existed but was never called from `TallyHttpClient::sendXml()` — writes and reads just paid the full TCP timeout on every request while Tally was down. Now `sendXml()` calls `assertAvailable` before, `recordSuccess` / `recordFailure` after. `TallyHttpClient` carries its `connectionCode` so the breaker keys per connection. When adding new network-bound services, wire the breaker the same way or they'll bypass the whole protection.

## Sync Tracker Semantics

- **2026-04-18**: `tally_connections.last_alter_master_id` / `last_alter_voucher_id` advance **only** from `SyncFromTallyJob` / `SyncMastersJob`, never from write endpoints. They represent "the last AlterID we've ingested FROM Tally", not "the last thing we wrote TO Tally" — so the counters stay at 0 after a fresh set of creates until the next pull runs. Intentional for pull-based sync; don't wire writes into the tracker or you'll skip records that the sync job would otherwise pull.

## TallyPrime Crash on Explode

- **2026-04-18**: `Collection` exports with `EXPLODEFLAG=Yes` can crash TallyPrime (Windows memory-access violation) when the collection rows reference other rows by name. `List of Units` is the first confirmed offender — compound units inline `BASEUNITS` / `ADDITIONALUNITS` recursively and overflow Tally's internal buffer. `buildCollectionExportRequest()` now accepts `$explode` (default `true` for back-compat). The following list services pin `explode=false` + a minimal `FETCHLIST`: `UnitService`, `StockItemService` (references units), `StockGroupService` (default units), `VoucherTypeService` (parent type). Apply the same pattern to any future master whose rows reference other rows by name.

## Controller Signatures for Multi-Connection Routes

- **2026-04-18**: Master-CRUD routes are shaped `/{connection}/{entity}/{name}` (or `{masterID}` for vouchers). Laravel passes **both** URI params positionally to the controller action, so `show(string $name)` silently binds `{connection}` into `$name` and drops the real name — Tally then errors "Could not find DEMO" and `extractObject` throws a parse exception surfaced as HTTP 500. Every `show`/`update`/`destroy` action under a `{connection}`-prefixed group must declare `string $connection` before the identifier param even if the value is unused (middleware rebinds `TallyHttpClient` from the connection). Affected controllers: CostCenter, Currency, Godown, Group, Ledger, PriceList, StockCategory, StockGroup, StockItem, Unit, VoucherType, Voucher. The feature mocks ignored `$xml`, so existing tests passed despite the bug — always assert on captured request XML when the param matters.

## XML Response Sanitisation

- **2026-04-18**: TallyPrime embeds raw ASCII control bytes (`0x03` ETX, `0x04` EOT) inside `<PARENTSTRUCTURE>` and emits numeric character references like `&#4;` inside `<PARENT>` for default reserved groups (Primary, Indirect Incomes, etc.). These are forbidden in XML 1.0 both as raw bytes and as char-refs, so `simplexml_load_string` rejects the whole document with "Unknown XML error". `TallyXmlParser::sanitizeXml()` must strip `[\x00-\x08\x0B\x0C\x0E-\x1F]` **and** numeric references to the same range. This is visible on any Collection export that contains default masters (Groups, Voucher Types, reserved Ledgers) but not on import-result responses.
- **2026-04-18**: `simplexml_load_string` returns no libxml errors unless `libxml_use_internal_errors(true)` is set before the call. Without it the parser exception falls back to "Unknown XML error", hiding the real cause. Wrap the parse call so the previous flag is restored via `finally`.

## Object Export Hangs / Crashes Across All Master Types

- **2026-04-19**: TallyPrime's per-master Object export (`<TYPE>Object</TYPE><SUBTYPE>X</SUBTYPE>`) is unreliable across master types. Confirmed failures: `Group` (30s hang → 503), `Stock Group` (same), `Unit` (lookup of `Nos` returned 503 then crashed Tally entirely — health probe died after the call). Likely affected by similar recursive expansion: `Stock Item` (references units), `Voucher Type` (references parent base type), and small-master Object exports in general where the implementation isn't well-exercised. **Decision: every master service's `get($name)` now filters from the cached `list()` result instead of issuing an Object export.** Migrated services: `GroupService`, `StockGroupService`, `UnitService`, `StockItemService`, `VoucherTypeService`, `CostCenterService`, `CurrencyService`, `GodownService`, `StockCategoryService`, `PriceListService`, `LedgerService`. The list export already returns full fields per row and is shared with the index endpoint, so per-name lookups are O(n) on the first call then O(1) until invalidation. For very large ledger sets (10K+) consider revisiting LedgerService.

## Smoke Test Idempotency

- **2026-04-19**: Smoke test must be re-runnable. Phases that captured a created entity's id (`org_id`, `wh_id`, `rec_id`, `draft_id`, `company_id`, `branch_id`) used to early-return when the create was tolerated as "already exists" and `.data.id` came back empty — silently skipping every downstream sub-step. Replaced with `ensure_db_entity` (lookup by code/name → reuse → else POST) and `ensure_tally_master` (GET-by-name → reuse → else POST). Tracks `ENSURE_PATH=found|created` so teardown only deletes what THIS run created. Pattern lives in `Modules/Tally/scripts/lib/http.sh`.

## Parent-Master Precheck Before Bulk Create

- **2026-04-19**: Tally rejects child master imports with "Could not find parent group / ledger / unit" — the import returns success:false but earlier code masked it. Smoke test now runs `verify_parents_exist` before phase_4_ledgers (PARENT groups), phase_5_stock_items (BASEUNITS + PARENT stock-groups), and phase_6_vouchers (every LEDGERNAME / PARTYLEDGERNAME). `ensure_tally_master` also re-fetches by name AFTER each create — if the post-create lookup misses, the create silently failed (likely a missing reference master in the payload). Both produce loud warnings with the body excerpt + hint.

## Stock-Side Masters Have No "Primary" Default

- **2026-04-19**: Account groups have a reserved root named `Primary` — `<PARENT>Primary</PARENT>` works on a fresh company. **Stock-side masters do NOT.** Sending `<PARENT>Primary</PARENT>` for `STOCKGROUP`, `STOCKCATEGORY`, or `GODOWN` returns `<LINEERROR>Stock Group 'Primary' does not exist!</LINEERROR>` with `CREATED=0 ERRORS=0 EXCEPTIONS=1` — the import is a silent no-op (zero ERRORS) unless you read EXCEPTIONS or re-fetch by name. For top-level stock-side entries, send empty PARENT (`""`). Stock items can reference an actual stock-group as PARENT once one exists, or use empty for the implicit root. `parseImportResult` should consider EXCEPTIONS as a failure indicator (it does not currently — read the response body's `LINEERROR` instead).

## TallyPrime Has No Built-in `List of Units` Collection (and others) — Use Inline TDL Injection

- **2026-04-19**: Even after correctly spelling collection IDs, TallyPrime returns `Error in TDL, 'Collection: List of Units' Could not find description` because there is **no built-in TDL collection named `List of Units`**. The TDL error pops a UI dialog that blocks Tally's HTTP responder, so from the script's perspective the request times out at 30s and Tally then becomes unresponsive entirely (108 health-probe failures). The same risk applies to other small-master collections that aren't on the well-known "big four" list (Groups, Ledgers, Stock Items, Stock Groups). Confirmed working built-in collections: `List of Groups`, `List of Ledgers`, `List of Stock Items`, `List of Stock Groups`. **For everything else use inline TDL injection** — define the collection in the request body itself via `<TDL><TDLMESSAGE><COLLECTION NAME="X" ISMODIFY="No"><TYPE>Unit</TYPE><FETCH>NAME, ...</FETCH></COLLECTION>...`. New helper: `TallyXmlBuilder::buildAdHocCollectionExportRequest(collectionName, tallyType, fetchFields)`. Migrated services: `UnitService`, `CostCenterService`, `CurrencyService`, `GodownService`, `VoucherTypeService`, `StockCategoryService`, `PriceListService`. The `<FETCH>` inside an inline collection takes a single comma-separated string, NOT one element per field (different from the outer `<FETCHLIST>` syntax).

## Inline-TDL Collection: Use `<NATIVEMETHOD>` Per Field, Not Comma-Separated `<FETCH>`

- **2026-04-19**: Inside an inline `<COLLECTION>` definition (within `<TDL><TDLMESSAGE>`), fields must be declared as **one `<NATIVEMETHOD>X</NATIVEMETHOD>` per field** — not as a single comma-separated `<FETCH>X, Y</FETCH>`. Per Tally docs Sample 16. Sending `<FETCH>NAME, USEFORGROUPS</FETCH>` for `<TYPE>PriceLevel</TYPE>` reset the connection after 6 seconds (reproduced 2026-04-19) — `USEFORGROUPS` is a valid IMPORT field but NOT a valid TDL accessor on the Price Level type, and `<FETCH>` errors hard on unknown methods. `<NATIVEMETHOD>` is silently tolerated for unknown names. `TallyXmlBuilder::buildAdHocCollectionExportRequest` now emits `<NATIVEMETHOD>` per field.
- **2026-04-19 (related)**: Defensively trimmed all inline-TDL service `fetchFields` to the minimum safe set (`NAME` for Unit/Currency/PriceList, `NAME + PARENT` for Godown/VoucherType/CostCentre/StockCategory). The filter-from-list pattern in each service's `get($name)` only reads the NAME attribute; richer fields aren't needed and risk crashing Tally on unknown TDL methods.

## TDL Inline-Collection TYPE Uses NO-SPACE Form for Multi-Word Masters

- **2026-04-19**: TallyPrime's TDL `<TYPE>` element inside an inline `<COLLECTION>` definition uses the **no-space** internal form for multi-word master types — this is the opposite convention from Object SUBTYPEs (which use spaces). Sending `<TYPE>Price Level</TYPE>` hangs Tally for 30 seconds then crashes the process entirely (reproduced 2026-04-19, 138 health-probe failures). Canonical TDL TYPE values: `Group`, `Ledger`, `Currency`, `Godown`, `Unit`, `Voucher` (single-word — same form), `StockGroup`, `StockItem`, `StockCategory`, `CostCentre`, `CostCategory`, `VoucherType`, `PriceLevel` (multi-word — concatenated, no space). Updated services: `PriceListService` (Price Level → PriceLevel), `StockCategoryService` (Stock Category → StockCategory), `VoucherTypeService` (Voucher Type → VoucherType), `CostCenterService` (Cost Centre → CostCentre).
- **2026-04-20 (RE-CONFIRMED)**: The original 2026-04-19 no-space rule is correct. Cross-checked against production TDL code in `laxmantandon/tally_migration_tdl/send/*.txt` — every inline `[Collection]` declaration uses the concatenated form (`Type : StockItem`, `Type : StockGroup`). A brief detour on 2026-04-20 flipped all 4 services to spaced form ("Cost Centre", "Voucher Type", "Stock Category", "Price Level") based on the Tally Help user-facing docs, but that was reverted the same day once the production TDL was inspected. **Final rule: TDL `<TYPE>` inside `<COLLECTION>` uses concatenated form; Object `<SUBTYPE>` uses spaced form.** Tally's XML parser tolerates both in most cases but concatenated is the idiomatic TDL form. The Price Level 30s timeout is orthogonal — not a spacing issue; Price Level is not a standalone TDL-queryable master (names live on Company via F11; rates live on Stock Item — handled via Company-master refactor, see below).

## Tally Built-in Collection Names + Object SUBTYPEs Use Spaces

- **2026-04-19**: TallyPrime's TDL collection and object-subtype identifiers use spaces between words. Wrong names yield `Error in TDL, 'Collection: List of X' Could not find description` (collections) or silent empty results / hangs (object exports).
  - **Canonical Collection IDs (`<TYPE>Collection</TYPE><ID>X</ID>`):** `List of Ledgers` (NOT `List of Accounts`), `List of Groups`, `List of Stock Items`, `List of Stock Groups`, `List of Stock Categories` (NOT `List of StockCategories`), `List of Cost Centres`, `List of Currencies`, `List of Godowns`, `List of Voucher Types` (NOT `List of VoucherTypes`), `List of Units`, `List of Price Levels` (NOT `List of PriceLevels`), `List of Companies`.
  - **Canonical Object SUBTYPEs (`<TYPE>Object</TYPE><SUBTYPE>X</SUBTYPE>`):** `Ledger`, `Group`, `Stock Item`, `Stock Group`, `Stock Category`, `Cost Centre`, `Currency`, `Godown`, `Voucher Type` (NOT `VoucherType`), `Unit`, `Price Level`, `Voucher`, `Company`.
  - Confirmed against `https://help.tallysolutions.com/developer-reference/tally-definition-language/appendix/` (object hierarchy section names objects as `Group`, `Ledger`, `Stock Group`, `Stock Item`, `Voucher` — all with spaces) and `.docs/Demo Samples/`.
  - Always cross-reference Demo Samples or run a quick `<TYPE>Collection</TYPE><ID>List of X</ID>` smoke probe before adding a new master service.

## Tally Silent EXCEPTIONS = Disabled Company Feature Flag (and Tally CRASHES on POST)

- **2026-04-19**: A master import that returns `<EXCEPTIONS>1</EXCEPTIONS>` with NO `<LINEERROR>` text (and `CREATED=0 ERRORS=0`) almost always means the master depends on a TallyPrime company feature that is OFF. **More dangerously, the POST itself can hard-crash TallyPrime** when the required feature is off — reproduced 2026-04-19 on `POST /currencies` (USD): the lookup via inline TDL returned an empty collection (HTTP 200, healthy), but the subsequent currency import crashed Tally entirely (119 health-probe failures after). So for **optional masters** (currencies, cost centres, godowns, stock categories, price lists), `ensure_tally_master(..., optional=1)` now **skips the POST entirely on lookup-miss** — never sends an import that could take Tally down. `_create_many` accepts `optional=1` as its 5th arg and counts the skip as a PASS. `assert.sh _handle_failure` still emits the F11 feature-flag hint when EXCEPTIONS surface elsewhere.

## Voucher Cancel uses `VoucherNumber`, Alter uses `Voucher Number`

- **2026-04-19**: Per Tally `sample-xml` docs (Sample 13 vs Sample 12), Cancel uses the compressed attribute `TAGNAME="VoucherNumber"` while Alter uses the spaced `TAGNAME="Voucher Number"`. Tally accepts either form for both, but `TallyXmlBuilder::buildCancelVoucherRequest` follows the cancel-specific canonical spelling. Delete keeps the spaced form (no canonical sample exists for Delete; Alter is the closest paired form).

## Invoice-Mode Vouchers Need OBJVIEW + PERSISTEDVIEW

- **2026-04-19**: Per Tally `sample-xml` Sample 11 (Sales Voucher Invoice Mode), an invoice-mode voucher requires both `<PERSISTEDVIEW>Invoice Voucher View</PERSISTEDVIEW>` AND `<OBJVIEW>Invoice Voucher View</OBJVIEW>` alongside `<ISINVOICE>Yes</ISINVOICE>`. Voucher-mode vouchers leave both off (Tally defaults to `Accounting Voucher View`). `VoucherService::create/createBatch/alter` now run `applyInvoiceMode($data)` which auto-fills both views when `ISINVOICE=Yes`, so callers can opt into invoice mode by setting that one field. Caller-supplied PERSISTEDVIEW/OBJVIEW values are never overwritten.

## Cleanup Touches ONLY [DEMO]-Prefixed Records — Tally Provided Data Is Never Deleted

- **2026-04-19**: The smoke test cleanup phase is restricted to `[DEMO]`-prefixed records via the `_safe_delete_demo` wrapper which REFUSES to send a DELETE for any name not starting with `[DEMO]`. This protects Tally-provided masters (Sundry Debtors, Sales Accounts, default ledgers like Cash, etc.) and any custom non-demo data the user may have in the company. Cleanup now covers every demo fixture: vouchers (26 known voucher numbers), stock items, stock categories, price lists, stock groups, godowns, cost centres, custom voucher types, ledgers, and groups — in dependency order (vouchers → items → categories → groups → ledgers → top-level groups). Units (`Nos`/`Hrs`/`Users`) and currencies (`USD`/`EUR`) are intentionally NOT cleaned because their fixtures use plain names without the `[DEMO]` prefix — deleting them risks touching Tally defaults. Per-phase teardown in MNC / webhooks / recurring / drafts already deletes only entities WHERE the script created them THIS run (`ENSURE_PATH=="created"`); rows that already existed are left in place.

## Use Tally's Reserved Groups Directly; Don't Layer Custom Intermediate Groups

- **2026-04-19**: Ledgers in the smoke test fixtures now reference Tally's RESERVED groups directly (Sundry Debtors, Sundry Creditors, Sales Accounts, Indirect Expenses, Direct Expenses, Bank Accounts, Cash-in-Hand, Duties & Taxes, Loans & Advances (Asset), etc.) instead of layering custom `[DEMO] X` intermediate groups underneath them. Reasons: (1) reserved groups always exist on a fresh company, so phase_4_ledgers no longer depends on phase_3_groups succeeding; (2) re-runs are cleaner — no orphan custom groups left after teardown; (3) parent-precheck passes trivially against built-in masters; (4) clients building real integrations should follow the same pattern unless they have a real categorization need. DEMO_GROUPS is reduced from 8 entries to 2 — just enough to exercise the create/list/show/update endpoints once. Rule: **only create a custom group if some downstream record actually requires it; otherwise reference Tally's reserved groups by name.**

## Workflow

- **2026-04-16**: Always cross-reference XML format against `.docs/Demo Samples/` before building or modifying TallyXmlBuilder methods. The official samples are the source of truth.
