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

## Tally Deployment Types

- **2026-04-16**: TallyPrime Standalone, Server, and Cloud Access all use the **identical XML API protocol**. The only difference is network accessibility (host/port). Cloud Access is a remote desktop (OCI), NOT a SaaS API.

## Workflow

- **2026-04-16**: Always cross-reference XML format against `.docs/Demo Samples/` before building or modifying TallyXmlBuilder methods. The official samples are the source of truth.
