#!/usr/bin/env bash
# Tally module smoke test — exercises every API endpoint.
#
# Pre-reqs:
#   1. `php artisan tally:demo seed` has been run successfully
#   2. Laravel server is running (`php artisan serve`) or set TALLY_SMOKE_BASE
#   3. storage/app/tally-demo/token.txt exists (written by the demo seeder)
#
# Usage:
#   ./scripts/tally-smoke-test.sh              # pretty output
#   ./scripts/tally-smoke-test.sh --tap        # TAP format for CI
#   ./scripts/tally-smoke-test.sh --dry-run    # print calls without executing
#
# Idiot-proofing:
#   - Pre-check asserts the DEMO connection points at SwatTech Demo before any mutation
#   - Every transient entity uses "Demo Test …" / "[DEMO TEST]" prefix (distinct from seeded)
#   - Cleanup trap removes transient entities even on early exit

set -euo pipefail

BASE="${TALLY_SMOKE_BASE:-http://localhost:8000/api/tally}"
CONN="${TALLY_SMOKE_CONN:-DEMO}"
EXPECTED_COMPANY="SwatTech Demo"
TOKEN_FILE="storage/app/tally-demo/token.txt"
MODE="pretty"
DRY_RUN=0

for arg in "$@"; do
  case "$arg" in
    --tap) MODE="tap" ;;
    --dry-run) DRY_RUN=1 ;;
    --help|-h)
      grep -E '^# ' "$0" | sed 's/^# //'; exit 0 ;;
  esac
done

# ---------- token & preflight ----------

if [ ! -f "$TOKEN_FILE" ]; then
  echo "ERROR: token file not found at $TOKEN_FILE — run 'php artisan tally:demo seed' first." >&2
  exit 1
fi

TOKEN="$(tr -d '[:space:]' < "$TOKEN_FILE")"
if [ -z "$TOKEN" ]; then
  echo "ERROR: token file is empty." >&2
  exit 1
fi

AUTH_HEADER="Authorization: Bearer ${TOKEN}"
CONTENT_HEADER="Content-Type: application/json"
ACCEPT_HEADER="Accept: application/json"

# ---------- helpers ----------

PASS=0
FAIL=0
N=0
TRANSIENT_LEDGER="Demo Test Ledger $$"
TRANSIENT_GROUP="Demo Test Group $$"
TRANSIENT_STOCK="Demo Test Stock $$"
TRANSIENT_CONN_CODE="DEMOTEST${$}"
TRANSIENT_CONN_ID=""
TRANSIENT_VOUCHER_ID=""

cleanup() {
  set +e
  [ -n "$TRANSIENT_CONN_ID" ] && curl -s -o /dev/null -X DELETE -H "$AUTH_HEADER" "$BASE/connections/$TRANSIENT_CONN_ID"
  curl -s -o /dev/null -X DELETE -H "$AUTH_HEADER" "$BASE/$CONN/ledgers/$(printf '%s' "$TRANSIENT_LEDGER" | jq -sRr @uri)"
  curl -s -o /dev/null -X DELETE -H "$AUTH_HEADER" "$BASE/$CONN/groups/$(printf '%s' "$TRANSIENT_GROUP" | jq -sRr @uri)"
  curl -s -o /dev/null -X DELETE -H "$AUTH_HEADER" "$BASE/$CONN/stock-items/$(printf '%s' "$TRANSIENT_STOCK" | jq -sRr @uri)"
  [ -n "$TRANSIENT_VOUCHER_ID" ] && curl -s -o /dev/null -X DELETE -H "$AUTH_HEADER" "$BASE/$CONN/vouchers/$TRANSIENT_VOUCHER_ID"
}
trap cleanup EXIT

urlenc() { printf '%s' "$1" | jq -sRr @uri; }

emit() {
  # emit "STATUS" "LABEL" "METHOD" "PATH"
  local ok="$1" label="$2" method="$3" path="$4"
  N=$((N+1))
  if [ "$ok" = "ok" ]; then
    PASS=$((PASS+1))
    if [ "$MODE" = "tap" ]; then
      echo "ok $N - $method $path — $label"
    else
      printf "  [%2d] \033[32m✓\033[0m  %s %s — %s\n" "$N" "$method" "$path" "$label"
    fi
  else
    FAIL=$((FAIL+1))
    if [ "$MODE" = "tap" ]; then
      echo "not ok $N - $method $path — $label"
    else
      printf "  [%2d] \033[31m✗\033[0m  %s %s — %s (got: %s)\n" "$N" "$method" "$path" "$label" "$ok"
    fi
  fi
}

call() {
  # call METHOD PATH EXPECTED-STATUS LABEL [JSON-BODY]
  local method="$1" path="$2" expect="$3" label="$4" body="${5:-}"
  local url="${BASE}${path}"

  if [ "$DRY_RUN" = "1" ]; then
    echo "DRY: $method $url  (expect $expect)"
    emit "ok" "$label" "$method" "$path"
    return
  fi

  local http_code
  if [ -n "$body" ]; then
    http_code=$(curl -s -o /tmp/tally-smoke.out -w "%{http_code}" -X "$method" \
      -H "$AUTH_HEADER" -H "$CONTENT_HEADER" -H "$ACCEPT_HEADER" \
      --data "$body" "$url")
  else
    http_code=$(curl -s -o /tmp/tally-smoke.out -w "%{http_code}" -X "$method" \
      -H "$AUTH_HEADER" -H "$ACCEPT_HEADER" "$url")
  fi

  if [ "$http_code" = "$expect" ]; then
    emit "ok" "$label" "$method" "$path"
  else
    emit "$http_code" "$label" "$method" "$path"
  fi
}

jget() {
  jq -r "$1" /tmp/tally-smoke.out 2>/dev/null || true
}

# ---------- preflight ----------

echo "Tally Smoke Test — base=$BASE conn=$CONN"
[ "$MODE" != "tap" ] && echo ""

# Resolve the DEMO connection id and assert it points at the demo company.
DEMO_STATUS=$(curl -s -o /tmp/tally-smoke.out -w "%{http_code}" -H "$AUTH_HEADER" "$BASE/connections")
if [ "$DEMO_STATUS" != "200" ]; then
  echo "ERROR: could not list connections (HTTP $DEMO_STATUS). Is the server running?" >&2
  exit 1
fi

DEMO_ID=$(jq -r ".data[] | select(.code==\"$CONN\") | .id" /tmp/tally-smoke.out 2>/dev/null || true)
DEMO_COMPANY=$(jq -r ".data[] | select(.code==\"$CONN\") | .company_name" /tmp/tally-smoke.out 2>/dev/null || true)

if [ -z "$DEMO_ID" ]; then
  echo "ERROR: no connection with code '$CONN' found. Run 'php artisan tally:demo seed'." >&2
  exit 1
fi

if [ "$DEMO_COMPANY" != "$EXPECTED_COMPANY" ]; then
  echo "ERROR: connection $CONN points at company '$DEMO_COMPANY', expected '$EXPECTED_COMPANY'." >&2
  exit 1
fi

# ---------- 01–10 connection / health / metrics ----------

call GET    "/$CONN/health"                               200 "per-connection health"
call GET    "/connections"                                 200 "list connections"
call GET    "/connections/$DEMO_ID"                        200 "show DEMO connection"
call POST   "/connections/test"                            200 "test connectivity (unstored)" \
  "{\"host\":\"localhost\",\"port\":9000,\"company_name\":\"$EXPECTED_COMPANY\",\"timeout\":30}"

# Create + update + delete a throwaway connection — exercises all 4 write verbs
call POST   "/connections"                                 201 "create throwaway connection" \
  "{\"code\":\"$TRANSIENT_CONN_CODE\",\"name\":\"Smoke Test Conn\",\"host\":\"localhost\",\"port\":9000,\"company_name\":\"$EXPECTED_COMPANY\",\"timeout\":30,\"is_active\":false}"
TRANSIENT_CONN_ID=$(jget '.data.id')

call PUT    "/connections/$TRANSIENT_CONN_ID"              200 "update throwaway (PUT)" \
  "{\"code\":\"$TRANSIENT_CONN_CODE\",\"name\":\"Smoke Test Updated\",\"host\":\"localhost\",\"port\":9000,\"company_name\":\"$EXPECTED_COMPANY\",\"timeout\":30,\"is_active\":false}"

call PATCH  "/connections/$TRANSIENT_CONN_ID"              200 "update throwaway (PATCH)" \
  "{\"name\":\"Smoke Test Patched\"}"

call DELETE "/connections/$TRANSIENT_CONN_ID"              204 "delete throwaway connection"
TRANSIENT_CONN_ID=""

call GET    "/connections/$DEMO_ID/health"                 200 "DEMO connection health"
call GET    "/connections/$DEMO_ID/metrics"                200 "DEMO connection metrics"
call POST   "/connections/$DEMO_ID/discover"               200 "discover companies"

# ---------- 11–20 ledgers ----------

call GET    "/$CONN/ledgers"                               200 "list ledgers (paginated)"
call GET    "/$CONN/ledgers/$(urlenc 'Demo Cash')"         200 "show ledger: Demo Cash"
call POST   "/$CONN/ledgers"                               201 "create transient ledger" \
  "{\"NAME\":\"$TRANSIENT_LEDGER\",\"PARENT\":\"Indirect Expenses\"}"
call PUT    "/$CONN/ledgers/$(urlenc "$TRANSIENT_LEDGER")" 200 "update transient ledger (PUT)" \
  "{\"PARENT\":\"Indirect Expenses\"}"
call PATCH  "/$CONN/ledgers/$(urlenc "$TRANSIENT_LEDGER")" 200 "update transient ledger (PATCH)" \
  "{\"PARENT\":\"Indirect Expenses\"}"
call DELETE "/$CONN/ledgers/$(urlenc "$TRANSIENT_LEDGER")" 204 "delete transient ledger"

# ---------- 21–26 groups ----------

call GET    "/$CONN/groups"                                200 "list groups"
call GET    "/$CONN/groups/$(urlenc 'Demo Customers')"     200 "show group: Demo Customers"
call POST   "/$CONN/groups"                                201 "create transient group" \
  "{\"NAME\":\"$TRANSIENT_GROUP\",\"PARENT\":\"Primary\"}"
call PUT    "/$CONN/groups/$(urlenc "$TRANSIENT_GROUP")"   200 "update transient group (PUT)" \
  "{\"PARENT\":\"Primary\"}"
call PATCH  "/$CONN/groups/$(urlenc "$TRANSIENT_GROUP")"   200 "update transient group (PATCH)" \
  "{\"PARENT\":\"Primary\"}"
call DELETE "/$CONN/groups/$(urlenc "$TRANSIENT_GROUP")"   204 "delete transient group"

# ---------- 27–32 stock items ----------

call GET    "/$CONN/stock-items"                           200 "list stock items"
call GET    "/$CONN/stock-items/$(urlenc 'Demo Widget A')" 200 "show stock item: Demo Widget A"
call POST   "/$CONN/stock-items"                           201 "create transient stock item" \
  "{\"NAME\":\"$TRANSIENT_STOCK\",\"PARENT\":\"Demo Widgets\",\"BASEUNITS\":\"Demo Nos\"}"
call PUT    "/$CONN/stock-items/$(urlenc "$TRANSIENT_STOCK")" 200 "update transient stock (PUT)" \
  "{\"PARENT\":\"Demo Widgets\"}"
call PATCH  "/$CONN/stock-items/$(urlenc "$TRANSIENT_STOCK")" 200 "update transient stock (PATCH)" \
  "{\"PARENT\":\"Demo Widgets\"}"
call DELETE "/$CONN/stock-items/$(urlenc "$TRANSIENT_STOCK")" 204 "delete transient stock item"

# ---------- 33–38 vouchers ----------

call GET    "/$CONN/vouchers?type=Payment"                 200 "list vouchers (Payment)"

# Pull a seeded voucher's masterID to test show+update+delete. Fallback: skip.
FIRST_VCH_ID=$(jq -r '.data[0].MASTERID // empty' /tmp/tally-smoke.out 2>/dev/null || true)

if [ -n "$FIRST_VCH_ID" ]; then
  call GET  "/$CONN/vouchers/$FIRST_VCH_ID"                200 "show voucher by masterID"
else
  emit "skip" "show voucher (no masterID in list)" "GET" "/$CONN/vouchers/{id}"
fi

# Create + delete a transient voucher
TRANSIENT_VCH_BODY='{"VOUCHERTYPENAME":"Payment","DATE":"'"$(date +%Y%m%d)"'","VOUCHERNUMBER":"DEMO/TEST/'$$'","NARRATION":"[DEMO TEST] transient","ALLLEDGERENTRIES.LIST":[{"LEDGERNAME":"Demo Rent A/c","ISDEEMEDPOSITIVE":"Yes","AMOUNT":100.00},{"LEDGERNAME":"Demo Bank SBI","ISDEEMEDPOSITIVE":"No","AMOUNT":-100.00}]}'
call POST   "/$CONN/vouchers"                              201 "create transient voucher" "$TRANSIENT_VCH_BODY"
TRANSIENT_VOUCHER_ID=$(jget '.data.MASTERID // .data.masterID // ""')

if [ -n "$TRANSIENT_VOUCHER_ID" ]; then
  call PUT    "/$CONN/vouchers/$TRANSIENT_VOUCHER_ID"       200 "update transient voucher (PUT)" "$TRANSIENT_VCH_BODY"
  call PATCH  "/$CONN/vouchers/$TRANSIENT_VOUCHER_ID"       200 "update transient voucher (PATCH)" "$TRANSIENT_VCH_BODY"
  call DELETE "/$CONN/vouchers/$TRANSIENT_VOUCHER_ID"       204 "delete transient voucher"
  TRANSIENT_VOUCHER_ID=""
fi

# ---------- 39–47 reports ----------

TODAY=$(date +%Y%m%d)
FY_START="20260401"

call GET "/$CONN/reports/balance-sheet?date=$TODAY"                      200 "balance sheet"
call GET "/$CONN/reports/balance-sheet?date=$TODAY&format=csv"           200 "balance sheet (CSV)"
call GET "/$CONN/reports/profit-and-loss?from=$FY_START&to=$TODAY"       200 "profit & loss"
call GET "/$CONN/reports/trial-balance?date=$TODAY"                      200 "trial balance"
call GET "/$CONN/reports/ledger?ledger=$(urlenc 'Demo Cash')&from=$FY_START&to=$TODAY" 200 "ledger report"
call GET "/$CONN/reports/outstandings?type=receivable"                   200 "outstandings receivable"
call GET "/$CONN/reports/outstandings?type=payable"                      200 "outstandings payable"
call GET "/$CONN/reports/stock-summary"                                  200 "stock summary"
call GET "/$CONN/reports/day-book?date=$TODAY"                           200 "day book"

# ---------- 48–53 sync + audit ----------

call GET  "/connections/$DEMO_ID/sync-stats"                             200 "sync stats"
call GET  "/connections/$DEMO_ID/sync-pending"                           200 "sync pending"
call GET  "/connections/$DEMO_ID/sync-conflicts"                         200 "sync conflicts"
call POST "/connections/$DEMO_ID/sync-from-tally"                        200 "trigger inbound sync"
call POST "/connections/$DEMO_ID/sync-to-tally"                          200 "trigger outbound sync"
call POST "/connections/$DEMO_ID/sync-full"                              200 "trigger full sync"
call GET  "/audit-logs?connection=$DEMO_ID&limit=20"                     200 "audit logs tail"
call GET  "/health"                                                       200 "default /health endpoint"

# ---------- summary ----------

echo ""
if [ "$FAIL" -eq 0 ]; then
  echo "✓ All $N checks passed."
  exit 0
else
  echo "✗ $FAIL of $N checks failed."
  exit 1
fi
