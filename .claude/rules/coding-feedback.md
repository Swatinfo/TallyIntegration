# Coding Feedback & Preferences

Consolidated rules from past sessions. Follow these in all future work.

## Module Structure
- **nwidart/laravel-modules v13**: All Tally code in `Modules/Tally/`. Namespace `Modules\Tally\*`
- **PSR-4 mapping**: `Modules/Tally/app/` maps to `Modules\Tally\` namespace
- **No code in main app/**: All Tally logic must stay inside the module

## Tally XML Format
- **Always verify against Demo Samples**: `.docs/Demo Samples/` is the canonical XML format reference
- **Header format**: `<VERSION>1</VERSION>` + `<TYPE>` + `<ID>` — NOT `<TALLYREQUEST>Export Data</TALLYREQUEST>`
- **Three export types**: Data (reports), Collection (lists), Object (single entity by name)
- **Voucher creation**: Use `VOUCHERTYPENAME` child element, not `VCHTYPE` attribute
- **Voucher cancel/delete**: Use attribute format (DATE, TAGNAME, TAGVALUE, VCHTYPE, ACTION)
- **FETCHLIST/FETCH** for field selection, not DESC/FIELD

## API Patterns
- **Consistent response**: Always `{ success: bool, data: mixed, message: string }`
- **Service layer**: Controllers delegate to services. Controllers only handle validation and response formatting
- **Multi-connection**: Route prefix `{connection}` resolved by middleware

## Config / Settings
- **Module config**: `Modules/Tally/config/config.php`, published as `config('tally.*')`
- **Env vars**: `TALLY_HOST`, `TALLY_PORT`, `TALLY_COMPANY`, `TALLY_TIMEOUT`

## Testing
- **Pest 4**: All tests use Pest syntax
- **Pint**: Run `vendor/bin/pint --dirty --format agent` after PHP changes

## Workflow Preferences
- **Windows dev environment**: Use PHP scripts for batch file operations (sed fails with backslashes)
- **Module portability**: Keep module self-contained — no dependencies on main app code
