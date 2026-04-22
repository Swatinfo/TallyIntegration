# TDL Companion Installation

The `Modules/Tally/scripts/tdl/TallyModuleIntegration.txt` file is an **optional** TDL (Tally Definition Language) extension that unlocks two capabilities the pure HTTP/XML API cannot deliver reliably:

1. **WebStatus UDFs** — custom fields on masters/vouchers that record sync state on the Tally side. The accountant sees "Synced to ERP on 2026-04-20" inside the ledger screen. Required for the Exception Report view to surface rows with `WebStatus != 'Synced'`.
2. **Tally-push bulk sync** — a Gateway-of-Tally menu entry that walks pending rows and POSTs them to the Laravel module as JSON. Sidesteps the 30-second Collection export timeout on large data sets.

The module works fine **without** the TDL; it's an opt-in upgrade for shops with either large data volume or a preference for Tally-driven workflows.

## Install steps

1. Stop TallyPrime if running.
2. Copy `TallyModuleIntegration.txt` to `C:\Program Files\TallyPrime\TDL\` on the Tally host. (Path differs on Server / Cloud editions — see your Tally admin console.)
3. Restart TallyPrime.
4. `F1 → Settings → TDL Management → Manage TDL` → Add local TDL → select the file.
5. Go to `Gateway of Tally → Send to Module` (new menu). The first entry will prompt for:
   - **Module endpoint URL** — `https://yourhost.example/api/tally/connections/1`
   - **Auth token** — a Sanctum bearer token with `manage_integrations` + `manage_masters` + `manage_vouchers`.
   - **Connection code** — the `{conn}` identifier registered in the module (e.g. `MUM`).

## Verification

- In any Ledger master, add a ledger and press Ctrl+Enter → the detail pane now shows `WebStatus`, `WebStatus_Message`, `WebStatus_DocName`.
- Open `Gateway of Tally → Send to Module → Exception Report` — should list any row that isn't Synced.
- Run `Gateway of Tally → Send to Module → Send Pending Masters` → check the module's `/api/tally/audit-logs` for matching entries.

## What the TDL does NOT do

- It does not replace the existing pull-based HTTP/XML API. The module keeps full parity for CRUD and on-demand calls.
- It does not modify existing Tally masters, voucher types, or business logic. It only **adds** three UDFs and a menu entry.
- It does not send data without explicit user action — the accountant triggers the bulk send from the menu.

## Status of the shipped file

`TallyModuleIntegration.txt` is a **starter template**. The WebStatus UDF declarations and menu scaffolding are complete; per-entity `SendAll*` function bodies mirror the layout of `laxmantandon/tally_migration_tdl/send/*.txt` and should be fleshed out for each master/voucher your deployment cares about. Each function should:

1. Walk a collection filtered by `WebStatus != 'Synced'`.
2. For each row, build JSON and `HTTP Post` to the matching Laravel endpoint.
3. On success (HTTP 200/201), `Alter Object` to set `WebStatus = 'Synced'` and capture the returned document ID in `WebStatus_DocName`.
4. On failure, set `WebStatus = 'Error'` and store the response text in `WebStatus_Message`.

The corresponding Laravel endpoints:

| Tally function | HTTP target |
|---|---|
| SendAllPendingMasters (Ledger) | `POST /api/tally/connections/{id}/import/ledger` |
| SendAllPendingMasters (Stock Item) | `POST /api/tally/connections/{id}/import/stock_item` |
| SendAllPendingVouchers | `POST /api/tally/{conn}/vouchers/batch` |
| ResetWebStatusMarkers | (local-only; no HTTP call) |

## Related reading

- `Modules/Tally/docs/API-USAGE.md` — pull-based endpoints used by HTTP clients.
- `Modules/Tally/docs/TALLY-SETUP.md` — configuring TallyPrime host/port/company.
- `tasks/lessons.md` — XML convention quirks discovered during development.
- [laxmantandon/tally_migration_tdl](https://github.com/laxmantandon/tally_migration_tdl) — reference TDL used as the pattern source.
