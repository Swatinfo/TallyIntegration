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

## Workflow

- **2026-04-16**: Always cross-reference XML format against `.docs/Demo Samples/` before building or modifying TallyXmlBuilder methods. The official samples are the source of truth.
