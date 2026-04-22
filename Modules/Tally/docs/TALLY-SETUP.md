# TallyPrime Setup

The module talks to TallyPrime over HTTP/XML on a **TCP port** (default `9000`). TallyPrime must be configured to listen on that port and must have at least one company open.

This guide covers all three editions. The XML protocol is identical across them — only the network path differs.

---

## 1. Enable ODBC / HTTP server in TallyPrime

Every edition uses the same toggle.

1. Open TallyPrime
2. Press **F1 → Help → TDL & Add-On** *(or on older builds: F12 → Data Configuration)*
3. Or from the Gateway of Tally: **F12 → Configure → Connectivity**
4. Set:
   - **Client/Server configuration** → `Both` (or at minimum `Server`)
   - **Port** → `9000` (change only if 9000 is in use)
   - **Tally.NET Server** → `Yes` *(if the option is shown)*
5. Accept / Save. TallyPrime will start listening on that port.

Verify from another terminal on the same machine:

```bash
curl http://localhost:9000
# Expect a reply like "Tally is Active" or a short XML envelope
```

If the connection is refused, ODBC/HTTP server isn't running — re-check the F12 setting and restart TallyPrime.

---

## 2. Load at least one company

Tally's API only responds when a company is open.

- **Gateway of Tally → Select Company** → open the company you want to expose.
- You can load multiple companies; use `SVCURRENTCOMPANY` (via `TALLY_COMPANY` env var or the `company_name` field on a connection row) to target one.

List the currently loaded companies:

```bash
curl http://localhost:9000/api/tally/health -H "Authorization: Bearer $TOKEN"
# data.companies: ["ABC Enterprises", "XYZ Ltd"]
```

---

## 3. Edition-specific notes

### 3a. TallyPrime Standalone (Silver)

- Single-user desktop install.
- Runs on Windows only (10 / 11 / Server).
- **Gotcha:** if you're using the GUI, the HTTP server can occasionally conflict with heavy interactive operations (reports, exports). Prefer Gold for integration work.
- **Connection fields:**
  ```
  host = localhost           (if Laravel is on the same machine)
  host = 192.168.x.x         (if Laravel is on another LAN machine)
  port = 9000
  ```

### 3b. TallyPrime Server (Gold)

- Multi-user, always-on.
- Recommended for production integration.
- **Gotcha:** Windows Firewall often blocks port 9000 by default. Add an inbound rule:
  ```powershell
  # Run as Administrator on the Tally server
  New-NetFirewallRule -DisplayName "TallyPrime HTTP" -Direction Inbound `
    -Protocol TCP -LocalPort 9000 -Action Allow
  ```
- **Connection fields:**
  ```
  host = tally-server.local  (DNS or IP of the Tally server)
  port = 9000
  company_name = "ABC Enterprises"
  ```

### 3c. TallyPrime Cloud Access (OCI)

TallyPrime Cloud Access is a **remote desktop** on an Oracle Cloud (OCI) VM — it is **not** a SaaS API. The HTTP port lives inside the VM.

You have two options:

**Option A — SSH tunnel (recommended):**
```bash
# On the Laravel server
ssh -L 9000:localhost:9000 opc@your-oci-vm
# Then use host=localhost port=9000 in the connection row
```

**Option B — Open OCI security list (production):**
- Add an ingress rule to the OCI VCN's security list for TCP 9000 restricted to your Laravel server's IP.
- Open Windows Firewall on the VM (as in Gold, above).
- Use `host = <vm-public-ip>` in the connection row.

> **Never** expose port 9000 to `0.0.0.0/0` — Tally has no built-in authentication.

---

## 4. Network diagnostics

Before registering a connection in the Laravel app, prove connectivity from the **Laravel server** (not your workstation):

```bash
# TCP reachability
telnet tally-server 9000

# HTTP reachability
curl -v http://tally-server:9000

# Check Tally responds to a minimal request
curl -X POST http://tally-server:9000 \
  -H "Content-Type: text/xml" \
  --data '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Collection</TYPE><ID>List of Companies</ID></HEADER></ENVELOPE>'
```

A non-empty XML response with `<STATUS>1</STATUS>` means you're good.

---

## 5. Security

TallyPrime's HTTP interface has **no authentication or TLS**. The module mitigates with:

- Sanctum auth on every Laravel route (so only your app talks to Tally)
- `SafeXmlString` validation on every input field (prevents XML injection)
- `CircuitBreaker` to fail fast if Tally becomes unreachable
- Per-request logging (`tally` log channel)

You must also:

- Keep port 9000 **inside the LAN** or behind a firewall / tunnel — never expose publicly.
- Restrict firewall ingress to the Laravel server's IP only.
- For Cloud Access: use an SSH tunnel instead of opening the OCI security list, if possible.

---

## 6. Company-specific quirks

- **Financial year:** use `TallyCompanyService::getFinancialYearPeriod()` to auto-detect instead of hardcoding FY dates.
- **Multiple companies:** don't rely on Tally's "active" company — set `company_name` explicitly on each `tally_connections` row.
- **Large datasets:** for 100K+ vouchers, use `VoucherService::list(..., batchSize: 5000)` to split into monthly batches.
- **Mid-day changes:** the AlterID incremental sync catches everything. A manual `POST /connections/{id}/sync-from-tally` forces a refresh regardless.

---

## 6b. Creating a dedicated demo company (strongly recommended)

If you plan to run the smoke test (`Modules/Tally/scripts/tally-smoke-test.sh`) — or any exploratory integration work — **create a separate company** so no production data can ever be touched by accident.

1. Gateway of Tally → **Alt+F3** → **Create Company**
2. Company Name: **`SwatTech Demo`** (the smoke test defaults to this exact name; case-sensitive)
3. Accept defaults for the rest (financial year, base currency, etc.)
4. Gateway of Tally → **F1 (Select Company)** → `SwatTech Demo`

You can have multiple companies loaded simultaneously; the integration pins every request to one via `<SVCURRENTCOMPANY>` in the XML envelope, so only the targeted company's data is ever read or written.

**Why this matters:**
- The Tally XML API has no per-company access control — whatever is loaded is reachable.
- Defence in depth: the smoke test's `-DEMO-` naming prefix + the company pin = two independent safety barriers.
- The smoke test's preflight aborts loudly if `SwatTech Demo` (or your chosen target) isn't loaded — it never silently falls back.

To target a different name: pass `--company="Your Company"` to the smoke test, or set `TALLY_COMPANY` in `.env` / `Modules/Tally/scripts/.smoke.env`.

## 7. Smoke test from the Laravel side

After TallyPrime is configured:

```bash
TOKEN="your-token"

# Unauthenticated-to-Tally health check (uses .env TALLY_HOST/PORT)
curl http://127.0.0.1:8000/api/tally/health -H "Authorization: Bearer $TOKEN"

# Per-connection health (after registering a connection with code=MUM)
curl http://127.0.0.1:8000/api/tally/connections/1/health -H "Authorization: Bearer $TOKEN"

# Test a connection without saving it
curl -X POST http://127.0.0.1:8000/api/tally/connections/test \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"host":"tally-server","port":9000,"timeout":10}'
```

If any of these hang for > `TALLY_TIMEOUT` seconds, go back to step 4.

---

## Next

- Register your first connection + create a voucher → [QUICK-START.md](QUICK-START.md)
- Errors? → [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
