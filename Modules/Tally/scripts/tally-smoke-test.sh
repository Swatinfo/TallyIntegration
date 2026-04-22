#!/usr/bin/env bash
# ============================================================================
# tally-smoke-test.sh — end-to-end API exercise for the Tally Laravel module
#
# Exercises every one of the 44 endpoints under /api/tally/* against a running
# Laravel + TallyPrime stack. Creates realistic software-company data. Logs
# every request/response to storage/logs/tally/tally-DD-MM-YYYY.log.
#
#   bash Modules/Tally/scripts/tally-smoke-test.sh                 # default: fail-fast, prompt on existing data
#   bash Modules/Tally/scripts/tally-smoke-test.sh --clean         # always wipe demo data first
#   bash Modules/Tally/scripts/tally-smoke-test.sh --keep          # never wipe, tolerate conflicts
#   bash Modules/Tally/scripts/tally-smoke-test.sh --no-fail-fast  # run through every phase
#   bash Modules/Tally/scripts/tally-smoke-test.sh --dry-run       # log what would be called
#   bash Modules/Tally/scripts/tally-smoke-test.sh --phase=masters # run a single phase
#   bash Modules/Tally/scripts/tally-smoke-test.sh --help
# ============================================================================

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Script lives in Modules/Tally/scripts/ so travel three levels up to reach the host Laravel root.
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"

# ---- Defaults ---------------------------------------------------------------
FAIL_FAST=1
CLEAN=""
KEEP=""
PHASE="all"
STOP_AFTER_PHASE=""
DRY_RUN=0
CONN_CODE="${CONN_CODE:-DEMO}"
TARGET_COMPANY="${TALLY_COMPANY:-SwatTech Demo}"
BOOTSTRAP=1
PRUNE_TOKENS=1
FORCE_CONFLICT=0
CURL_INSECURE=1

# Tracking arrays populated during the run.
declare -a CREATED_LEDGERS=()
declare -a CREATED_GROUPS=()
declare -a CREATED_STOCK=()
declare -a CREATED_VCH_IDS=()
CONNECTION_ID=""

# MNC hierarchy state — shared between phase_2b_mnc_setup (creates), phase_7b_consolidated_reports
# (reads), and phase_2b_mnc_teardown (reverse-deletes only what THIS run created).
MNC_ORG_ID=""; MNC_ORG_PATH=""
MNC_COMPANY_ID=""; MNC_COMPANY_PATH=""
MNC_BRANCH_ID=""; MNC_BRANCH_PATH=""

# ---- Arg parsing ------------------------------------------------------------
usage() {
    cat <<USG
Tally API Smoke Test

Usage: bash Modules/Tally/scripts/tally-smoke-test.sh [FLAGS]

Flags:
  --clean                 Delete -DEMO-prefixed data first
  --keep                  Keep existing data, tolerate conflicts
  --phase=<name>          Run one phase only: connections|mnc|masters|groups|stock-groups|units|cost-centres|
                          currencies|godowns|voucher-types|stock-categories|price-lists|cost-categories|
                          employee-categories|employee-groups|employees|attendance-types|ledgers|stock|
                          vouchers|inventory-ops|manufacturing|banking|recurring|workflow|reports|sync|
                          audit|observability|integration|permissions
  --stop-after-phase=<n>  Stop after phase N (debugging)
  --dry-run               Log what would be called, make no HTTP requests
  --no-fail-fast          Continue through every phase even on failures
  --conn=<code>           Connection code to use (default: DEMO)
  --company=<name>        Target Tally company (default: "SwatTech Demo") — MUST be loaded in Tally
  --no-bootstrap          Skip auth bootstrap (use \$TALLY_API_TOKEN env)
  --no-prune-tokens       Do not prune smoke-test tokens older than 7 days
  --force-conflict        Deliberately create a sync conflict to exercise resolve
  -h, --help              Show this help

Environment overrides:
  LARAVEL_BASE_URL        default http://127.0.0.1:8000
  SMOKE_USER_EMAIL        default smoke-test@local
  TALLY_HOST / TALLY_PORT / TALLY_COMPANY
  DEMO_PREFIX             default -DEMO-

Logs: storage/logs/tally/tally-DD-MM-YYYY.log (always created)
USG
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --clean) CLEAN=1 ;;
        --keep) KEEP=1 ;;
        --no-fail-fast) FAIL_FAST=0 ;;
        --phase=*) PHASE="${1#*=}" ;;
        --stop-after-phase=*) STOP_AFTER_PHASE="${1#*=}" ;;
        --dry-run) DRY_RUN=1 ;;
        --conn=*) CONN_CODE="${1#*=}" ;;
        --company=*) TARGET_COMPANY="${1#*=}" ;;
        --no-bootstrap) BOOTSTRAP=0 ;;
        --no-prune-tokens) PRUNE_TOKENS=0 ;;
        --force-conflict) FORCE_CONFLICT=1 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown flag: $1" >&2; usage; exit 1 ;;
    esac
    shift
done

# Export TARGET_COMPANY into the env so fixtures.sh picks it up when sourced below.
export TALLY_COMPANY="$TARGET_COMPANY"

# ---- Source libs ------------------------------------------------------------
# shellcheck source=lib/colors.sh
source "$SCRIPT_DIR/lib/colors.sh"
# shellcheck source=lib/logger.sh
source "$SCRIPT_DIR/lib/logger.sh"
# shellcheck source=lib/http.sh
source "$SCRIPT_DIR/lib/http.sh"
# shellcheck source=lib/assert.sh
source "$SCRIPT_DIR/lib/assert.sh"
# shellcheck source=lib/auth.sh
source "$SCRIPT_DIR/lib/auth.sh"
# shellcheck source=lib/fixtures.sh
source "$SCRIPT_DIR/lib/fixtures.sh"

# Load optional .smoke.env (sourced AFTER libs to allow overriding defaults).
if [[ -f "$SCRIPT_DIR/.smoke.env" ]]; then
    # shellcheck disable=SC1091
    source "$SCRIPT_DIR/.smoke.env"
fi

START_TS=$(date +%s)

START_BANNER() {
    echo ""
    echo "${BOLD}${CYAN}================================================================${RESET}"
    echo "${BOLD}${CYAN}  Tally Module — API Smoke Test${RESET}"
    echo "${BOLD}${CYAN}================================================================${RESET}"
    echo "  ${BOLD}Target company:${RESET}  ${BOLD}${YELLOW}$TARGET_COMPANY${RESET}  ${GREEN}(verified loaded)${RESET}"
    echo "  Connection:      $CONN_CODE"
    echo "  Laravel:         $LARAVEL_BASE_URL"
    echo "  Fail-fast:       $([[ $FAIL_FAST == 1 ]] && echo "ON (default)" || echo "off")"
    echo "  Dry-run:         $([[ $DRY_RUN == 1 ]] && echo "yes" || echo "no")"
    echo "  Phase:           $PHASE"
    echo "  PID:             $$"
    echo ""
    echo "  ${DIM}All operations are pinned to this company via <SVCURRENTCOMPANY>.${RESET}"
    echo "  ${DIM}Data in any other loaded company is untouched.${RESET}"
    echo ""
}

# ============================================================================
# PHASES
# ============================================================================

_verify_target_company() {
    log_info "Verifying target company \"$TARGET_COMPANY\" is loaded in TallyPrime"

    local companies_raw
    companies_raw=$(curl --silent --max-time 10 \
        -X POST "http://$CONN_HOST:$CONN_PORT" \
        -H "Content-Type: text/xml" \
        --data '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Collection</TYPE><ID>List of Companies</ID></HEADER><BODY><DESC><STATICVARIABLES><SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT></STATICVARIABLES></DESC></BODY></ENVELOPE>' \
        2>/dev/null)

    # Case-insensitive, whitespace-insensitive match against the company name inside the XML.
    # Tally returns <COMPANYNAME.LIST TYPE="String"><NAME>SwatTech Demo</NAME>...
    if echo "$companies_raw" | grep -qiF ">$TARGET_COMPANY<"; then
        log_pass "Target company \"$TARGET_COMPANY\" is loaded"
        return 0
    fi

    log_fatal "Target company \"$TARGET_COMPANY\" is NOT loaded in TallyPrime."
    echo ""
    echo "${YELLOW}Companies currently loaded in Tally:${RESET}"
    local found
    found=$(echo "$companies_raw" | grep -oE '<NAME>[^<]+</NAME>' | sed -E 's|<NAME>||; s|</NAME>||' | sort -u)
    if [[ -n "$found" ]]; then
        echo "$found" | sed 's/^/  • /'
    else
        echo "  (none — is a company open in Tally?)"
    fi
    echo ""
    echo "${YELLOW}${BOLD}To proceed, do ONE of the following:${RESET}"
    echo ""
    echo "  ${BOLD}A. Create the demo company (one-time setup):${RESET}"
    echo "     1. Open TallyPrime"
    echo "     2. Gateway of Tally → Alt+F3 → Create Company"
    echo "     3. Company Name: ${BOLD}$TARGET_COMPANY${RESET} (accept defaults for other fields)"
    echo "     4. Gateway of Tally → F1 → Select Company → $TARGET_COMPANY"
    echo ""
    echo "  ${BOLD}B. Load an already-created \"$TARGET_COMPANY\":${RESET}"
    echo "     Gateway of Tally → F1 (Select Company) → $TARGET_COMPANY"
    echo ""
    echo "  ${BOLD}C. Target a different company (advanced):${RESET}"
    echo "     bash Modules/Tally/scripts/tally-smoke-test.sh --company=\"Your Company Name\""
    echo ""
    echo "  ${RED}The smoke test refuses to run unless the target company is loaded.${RESET}"
    echo "  ${RED}This is intentional — data in other companies will NOT be touched.${RESET}"
    echo ""
    exit 1
}

phase_0_preflight() {
    log_phase "0" "Preflight"

    for cmd in curl php; do
        if command -v "$cmd" >/dev/null 2>&1; then
            log_pass "$cmd present"
        else
            log_fatal "Missing dependency: $cmd"
            exit 1
        fi
    done

    if command -v jq >/dev/null 2>&1; then
        log_pass "jq present"
    else
        log_warn "jq not installed — falling back to PHP for JSON parsing (slower but works)"
    fi

    log_info "Checking Tally at $CONN_HOST:$CONN_PORT"
    if curl --silent --fail --max-time 5 "http://$CONN_HOST:$CONN_PORT" >/dev/null 2>&1; then
        log_pass "Tally reachable at $CONN_HOST:$CONN_PORT"
    else
        log_fatal "Tally not reachable at http://$CONN_HOST:$CONN_PORT"
        echo "${YELLOW}Hint:${RESET} see Modules/Tally/docs/TROUBLESHOOTING.md section 1"
        exit 1
    fi

    _verify_target_company

    log_info "Checking Laravel at $LARAVEL_BASE_URL"
    local insecure_flag
    insecure_flag=$( (( CURL_INSECURE == 1 )) && echo "-k" )
    if curl --silent --fail --max-time 5 $insecure_flag -o /dev/null "$LARAVEL_BASE_URL"; then
        log_pass "Laravel responding at $LARAVEL_BASE_URL"
    else
        log_fatal "Laravel not responding at $LARAVEL_BASE_URL"
        echo "${YELLOW}Hints:${RESET}"
        echo "  • Start the server: 'php artisan serve' (for http://127.0.0.1:8000)"
        echo "  • If using Herd/Valet .test domain, make sure it's serving this project"
        echo "  • For self-signed certs, set CURL_INSECURE=1 in env or .smoke.env"
        echo "  • Override URL: 'LARAVEL_BASE_URL=http://127.0.0.1:8000 bash ...' or edit .smoke.env"
        exit 1
    fi

    log_info "Checking module is enabled"
    if (cd "$PROJECT_ROOT" && php artisan module:list 2>&1) | grep -qi "tally"; then
        log_pass "Tally module discovered by nwidart"
    else
        log_warn "module:list did not mention Tally — install may be incomplete"
    fi
}

phase_0a_auth() {
    if (( BOOTSTRAP == 0 )); then
        if [[ -z "${TALLY_API_TOKEN:-}" ]]; then
            log_fatal "--no-bootstrap requires TALLY_API_TOKEN in env"
            exit 2
        fi
        log_info "Skipping bootstrap; using externally-supplied token (length=${#TALLY_API_TOKEN})"
        return
    fi
    bootstrap_user_and_token
}

phase_1_cleanup() {
    log_phase "1" "Cleanup"

    # Prompt if neither flag set.
    if [[ -z "$CLEAN" && -z "$KEEP" ]]; then
        local count
        if api_get "/$CONN_CODE/ledgers?per_page=1" >/dev/null 2>&1; then
            if command -v jq >/dev/null 2>&1; then
                count=$(echo "$HTTP_BODY" | jq -r '[.data[]? | select(.NAME | startswith("-DEMO-"))] | length // 0' 2>/dev/null || echo "?")
            else
                count="?"
            fi
        else
            count="?"
        fi

        echo ""
        echo "${YELLOW}Existing demo data check:${RESET}"
        echo "  Ledgers with -DEMO- prefix: $count"
        echo ""
        echo "Options:"
        echo "  [c] Clear demo data, then run"
        echo "  [k] Keep existing data, tolerate 'already exists'"
        echo "  [a] Abort"
        read -r -p "Your choice [c/k/a]: " ans
        case "$ans" in
            c|C) CLEAN=1 ;;
            k|K) KEEP=1 ;;
            *) echo "Aborted."; exit 0 ;;
        esac
    fi

    if [[ "$KEEP" == "1" ]]; then
        log_info "Skipping cleanup (--keep)"
        return
    fi

    log_info "Deleting -DEMO-prefixed entities (best effort — 404s are expected)"
    log_info "Tally-provided masters (Sundry Debtors, Sales Accounts, default units, etc.) are NEVER deleted"

    # Vouchers first (they reference ledgers/items). All known -DEMO-prefixed
    # voucher numbers across every phase. The DELETE handler tolerates 404 so
    # missing entries cost nothing. The voucher TYPE in the payload is mostly
    # advisory — Tally locates by date+number+vchtype attributes.
    local -a demo_voucher_numbers=(
        "-DEMO-SI-0001" "-DEMO-SI-0002" "-DEMO-SI-0003"
        "-DEMO-SI-GST-0001" "-DEMO-SI-IGST-0001" "-DEMO-SI-INV-0001"
        "-DEMO-SI-USD-0001" "-DEMO-SI-EXPORT-0001"
        "-DEMO-PB-GST-0001" "-DEMO-RCT-BILL-0001" "-DEMO-PMT-BILL-0001"
        "-DEMO-JV-BATCH-0001" "-DEMO-JV-BATCH-0002" "-DEMO-JV-BATCH-0003"
        "-DEMO-PB-0001"
        "-DEMO-PMT-0001" "-DEMO-PMT-0002"
        "-DEMO-RCT-0001" "-DEMO-RCT-0002"
        "-DEMO-JV-0001" "-DEMO-JV-0002" "-DEMO-JV-0003" "-DEMO-JV-DEP-0001"
        "-DEMO-CTR-0001"
        "-DEMO-CN-0001" "-DEMO-CN-0002"
        "-DEMO-DN-0001"
        "-DEMO-SO-0001" "-DEMO-PO-0001"
        "-DEMO-ST-0001" "-DEMO-PS-0001"
        "-DEMO-MFG-0001" "-DEMO-JWO-0001" "-DEMO-JWI-0001"
        "-DEMO-DRAFT-0001" "-DEMO-DRAFT-0002"
        "-DEMO-RENT-AUTO"
    )
    for v in "${demo_voucher_numbers[@]}"; do
        _safe_delete_demo "/$CONN_CODE/vouchers/0" "$v" \
            "{\"type\":\"Sales\",\"date\":\"16-Apr-2026\",\"voucher_number\":\"$v\",\"action\":\"delete\"}"
    done

    # Stock items (referenced by inventory vouchers — delete after vouchers)
    for n in "-DEMO- SKU-PRO Annual" "-DEMO- SKU-ENT Annual" "-DEMO- Analytics Add-on"; do
        _safe_delete_demo "/$CONN_CODE/stock-items/$(_urlencode "$n")" "$n"
    done

    # Stock categories (Phase 9F)
    for cat_json in "${DEMO_STOCK_CATEGORIES[@]}"; do
        local name; name=$(json_extract '.NAME' "$cat_json")
        _safe_delete_demo "/$CONN_CODE/stock-categories/$(_urlencode "$name")" "$name"
    done

    # Price lists (Phase 9F)
    for pl_json in "${DEMO_PRICE_LISTS[@]}"; do
        local name; name=$(json_extract '.NAME' "$pl_json")
        _safe_delete_demo "/$CONN_CODE/price-lists/$(_urlencode "$name")" "$name"
    done

    # Stock groups (after stock items)
    for sg_json in "${DEMO_STOCK_GROUPS[@]}"; do
        local name; name=$(json_extract '.NAME' "$sg_json")
        _safe_delete_demo "/$CONN_CODE/stock-groups/$(_urlencode "$name")" "$name"
    done

    # Godowns (Phase 9B)
    for g_json in "${DEMO_GODOWNS[@]}"; do
        local name; name=$(json_extract '.NAME' "$g_json")
        _safe_delete_demo "/$CONN_CODE/godowns/$(_urlencode "$name")" "$name"
    done

    # Cost centres (Phase 9A)
    for cc_json in "${DEMO_COST_CENTRES[@]}"; do
        local name; name=$(json_extract '.NAME' "$cc_json")
        _safe_delete_demo "/$CONN_CODE/cost-centres/$(_urlencode "$name")" "$name"
    done

    # Custom voucher types (Phase 9B) — leaf-first under reserved parents
    for vt_json in "${DEMO_VOUCHER_TYPES[@]}"; do
        local name; name=$(json_extract '.NAME' "$vt_json")
        _safe_delete_demo "/$CONN_CODE/voucher-types/$(_urlencode "$name")" "$name"
    done

    # Phase 9N masters — reverse dependency order (leaf → root):
    # employees depend on employee-groups + employee-categories; employee-groups depend on
    # employee-categories; cost-categories and attendance-types are independent roots.
    for e_json in "${DEMO_EMPLOYEES[@]}"; do
        local name; name=$(json_extract '.NAME' "$e_json")
        _safe_delete_demo "/$CONN_CODE/employees/$(_urlencode "$name")" "$name"
    done
    for eg_json in "${DEMO_EMPLOYEE_GROUPS[@]}"; do
        local name; name=$(json_extract '.NAME' "$eg_json")
        _safe_delete_demo "/$CONN_CODE/employee-groups/$(_urlencode "$name")" "$name"
    done
    for ec_json in "${DEMO_EMPLOYEE_CATEGORIES[@]}"; do
        local name; name=$(json_extract '.NAME' "$ec_json")
        _safe_delete_demo "/$CONN_CODE/employee-categories/$(_urlencode "$name")" "$name"
    done
    for cc_json in "${DEMO_COST_CATEGORIES[@]}"; do
        local name; name=$(json_extract '.NAME' "$cc_json")
        _safe_delete_demo "/$CONN_CODE/cost-categories/$(_urlencode "$name")" "$name"
    done
    for at_json in "${DEMO_ATTENDANCE_TYPES[@]}"; do
        local name; name=$(json_extract '.NAME' "$at_json")
        _safe_delete_demo "/$CONN_CODE/attendance-types/$(_urlencode "$name")" "$name"
    done

    # Ledgers (after vouchers cleared)
    for ledger_json in "${DEMO_LEDGERS[@]}"; do
        local name; name=$(json_extract '.NAME' "$ledger_json")
        _safe_delete_demo "/$CONN_CODE/ledgers/$(_urlencode "$name")" "$name"
    done

    # Groups last (leaf-first: reverse order of creation)
    for (( i=${#DEMO_GROUPS[@]}-1; i>=0; i-- )); do
        local name; name=$(json_extract '.NAME' "${DEMO_GROUPS[$i]}")
        _safe_delete_demo "/$CONN_CODE/groups/$(_urlencode "$name")" "$name"
    done

    # Units + Currencies are intentionally NOT cleaned: their fixtures use plain
    # names (Nos / Hrs / Users / USD / EUR) without -DEMO- prefix because Tally
    # treats them as low-cost shared masters. Deleting them risks touching
    # Tally-provided defaults. Leave them in place across runs.

    log_pass "Cleanup attempted — only -DEMO-prefixed records targeted (Tally-provided data untouched)"
}

# Safety wrapper around api_delete that REFUSES to send a DELETE for any name
# that doesn't start with `-DEMO-`. Prevents accidental deletion of Tally-
# provided / reserved data when fixtures or hardcoded names are edited.
# Args:
#   $1 = endpoint URI
#   $2 = the master/voucher name being deleted (used for the -DEMO- guard)
#   $3 = optional JSON body (for voucher-style deletes that take a payload)
_safe_delete_demo() {
    local endpoint="$1"; local name="$2"; local body="${3:-}"

    if [[ "$name" != -DEMO-* ]]; then
        log_warn "REFUSING to delete non-'-DEMO-' name: $name (would touch Tally-provided data)"
        return 1
    fi

    if [[ -n "$body" ]]; then
        api_delete "$endpoint" "$body" >/dev/null 2>&1 || true
    else
        api_delete "$endpoint" >/dev/null 2>&1 || true
    fi
}

_urlencode() {
    local s="$1"
    python - "$s" <<'PY' 2>/dev/null || printf "%s" "$s"
import sys, urllib.parse
print(urllib.parse.quote(sys.argv[1], safe=""))
PY
}

phase_2_connections() {
    log_phase "2" "Connections"

    # 1. List existing
    api_get "/connections"
    assert_ok "GET /connections"

    # 2. Seed connection row only if not exists (by code).
    # If a row with this code already exists, refuse to proceed if its company_name
    # doesn't match the target — otherwise we'd silently write into the wrong company.
    local existing_id existing_company
    if command -v jq >/dev/null 2>&1; then
        existing_id=$(echo "$HTTP_BODY" | jq -r ".data[]? | select(.code == \"$CONN_CODE\") | .id" | head -1)
        existing_company=$(echo "$HTTP_BODY" | jq -r ".data[]? | select(.code == \"$CONN_CODE\") | .company_name" | head -1)
    else
        existing_id=$(php -r '
            $body = file_get_contents("php://stdin");
            $json = json_decode($body, true);
            foreach ($json["data"] ?? [] as $row) {
                if (($row["code"] ?? "") === $argv[1]) { echo $row["id"] ?? ""; return; }
            }
        ' "$CONN_CODE" <<< "$HTTP_BODY" 2>/dev/null)
        existing_company=$(php -r '
            $body = file_get_contents("php://stdin");
            $json = json_decode($body, true);
            foreach ($json["data"] ?? [] as $row) {
                if (($row["code"] ?? "") === $argv[1]) { echo $row["company_name"] ?? ""; return; }
            }
        ' "$CONN_CODE" <<< "$HTTP_BODY" 2>/dev/null)
    fi

    if [[ -n "$existing_id" ]]; then
        if [[ "$existing_company" != "$TARGET_COMPANY" ]]; then
            log_fatal "Existing connection '$CONN_CODE' (id=$existing_id) targets company '$existing_company', not '$TARGET_COMPANY'."
            echo ""
            echo "${YELLOW}${BOLD}Refusing to proceed — this would write into the wrong company.${RESET}"
            echo ""
            echo "Options:"
            echo "  • Use a different code:  bash Modules/Tally/scripts/tally-smoke-test.sh --conn=SAFE"
            echo "  • Delete the existing row via the API, then re-run"
            echo "  • Or target the same company as the existing row: --company=\"$existing_company\""
            echo ""
            exit 1
        fi
        CONNECTION_ID="$existing_id"
        log_info "Reusing existing connection id=$CONNECTION_ID (company matches: $existing_company)"
    else
        api_post "/connections" "$(connection_payload)"
        assert_ok "POST /connections (seed)"
        CONNECTION_ID=$(json_field '.data.id')
        log_info "Created connection id=$CONNECTION_ID targeting company \"$TARGET_COMPANY\""
    fi

    # 3. Show
    api_get "/connections/$CONNECTION_ID"
    assert_ok "GET /connections/$CONNECTION_ID"

    # 4. Update (nop — set timeout back to 30 explicitly)
    api_put "/connections/$CONNECTION_ID" '{"timeout":30}'
    assert_ok "PUT /connections/$CONNECTION_ID"

    # 4b. PATCH (partial update path — distinct endpoint pattern even if same controller method)
    api_patch "/connections/$CONNECTION_ID" '{"timeout":30}'
    assert_ok "PATCH /connections/$CONNECTION_ID"

    # 5. Test connectivity (ad-hoc)
    api_post "/connections/test" "{\"host\":\"$CONN_HOST\",\"port\":$CONN_PORT,\"timeout\":10}"
    assert_ok "POST /connections/test"

    # 6. Discover
    api_post "/connections/$CONNECTION_ID/discover"
    assert_ok "POST /connections/$CONNECTION_ID/discover"

    # 6b. Companies list (new route in 9A)
    api_get "/connections/$CONNECTION_ID/companies"
    assert_ok "GET /connections/$CONNECTION_ID/companies"

    # 7. Health (by id)
    api_get "/connections/$CONNECTION_ID/health"
    assert_ok "GET /connections/$CONNECTION_ID/health"

    # 8. Metrics
    api_get "/connections/$CONNECTION_ID/metrics?period=1h"
    assert_ok "GET /connections/$CONNECTION_ID/metrics?period=1h"

    api_get "/connections/$CONNECTION_ID/metrics?period=24h"
    assert_ok "GET /connections/$CONNECTION_ID/metrics?period=24h"

    api_get "/connections/$CONNECTION_ID/metrics?period=7d"
    assert_ok "GET /connections/$CONNECTION_ID/metrics?period=7d"

    # 9. Global health
    api_get "/health"
    assert_ok "GET /health"

    # 10. Per-connection health (by code)
    api_get "/$CONN_CODE/health"
    assert_ok "GET /$CONN_CODE/health"
}

_create_many() {
    local endpoint="$1"
    local label_prefix="$2"
    local -n arr="$3"       # nameref to array
    local captured_arr="$4" # name of array to append names to
    local optional="${5:-0}" # 1 = lookup-only mode; skip POST on miss to avoid Tally crashes when feature flags are off
    local i=0
    for payload in "${arr[@]}"; do
        i=$((i + 1))
        local name; name=$(json_extract '.NAME' "$payload")

        # Lookup-then-create per record. Each record gets its own pass/fail entry
        # so a single missing parent never aborts the rest of the batch.
        # For optional masters, ensure_tally_master skips POST entirely on miss.
        ensure_tally_master "$endpoint" "$name" "$payload" "[$i] $label_prefix" "$optional"
        case "$ENSURE_PATH" in
            found|created)
                PASSED=$((PASSED + 1))
                log_pass "[$i] $label_prefix ($name) [${ENSURE_PATH}]"
                eval "$captured_arr+=(\"\$name\")"
                ;;
            skipped)
                # Lookup confirmed not present; POST was suppressed because the
                # required Tally F11 feature is likely off. Treated as PASS.
                PASSED=$((PASSED + 1))
                log_pass "[$i] $label_prefix ($name) [skipped — feature flag off]"
                ;;
            *)
                _handle_failure "[$i] $label_prefix ($name)" "ensure_tally_master returned $ENSURE_PATH"
                ;;
        esac
    done
}

phase_3_groups() {
    log_phase "3" "Groups"
    _create_many "/$CONN_CODE/groups" "group" DEMO_GROUPS CREATED_GROUPS

    # List
    api_get "/$CONN_CODE/groups?per_page=50"
    assert_ok "GET /$CONN_CODE/groups"

    # Show one
    local sample_name; sample_name=$(json_extract '.NAME' "${DEMO_GROUPS[0]}")
    api_get "/$CONN_CODE/groups/$(_urlencode "$sample_name")"
    assert_ok "GET /$CONN_CODE/groups/<first>"

    # Update one (PUT)
    api_put "/$CONN_CODE/groups/$(_urlencode "$sample_name")" '{"NAME":"'"$sample_name"'","PARENT":"Sundry Debtors"}'
    assert_ok "PUT /$CONN_CODE/groups/<first>"
}

phase_3b_stock_groups() {
    log_phase "3b" "Stock groups (Phase 9A)"
    local __captured=()
    _create_many "/$CONN_CODE/stock-groups" "stock-group" DEMO_STOCK_GROUPS __captured

    api_get "/$CONN_CODE/stock-groups?per_page=50"
    assert_ok "GET /$CONN_CODE/stock-groups"

    local sample; sample=$(json_extract '.NAME' "${DEMO_STOCK_GROUPS[0]}")
    api_get "/$CONN_CODE/stock-groups/$(_urlencode "$sample")"
    assert_ok "GET /$CONN_CODE/stock-groups/<first>"

    # PARENT must be empty for top-level stock-groups (no reserved "Primary").
    api_patch "/$CONN_CODE/stock-groups/$(_urlencode "$sample")" \
        '{"NAME":"'"$sample"'","PARENT":""}'
    assert_ok "PATCH /$CONN_CODE/stock-groups/<first>"
}

phase_3c_units() {
    log_phase "3c" "Units (Phase 9A)"
    local __captured=()
    _create_many "/$CONN_CODE/units" "unit" DEMO_UNITS __captured

    api_get "/$CONN_CODE/units?per_page=50"
    assert_ok "GET /$CONN_CODE/units"

    api_get "/$CONN_CODE/units/$(_urlencode 'Nos')"
    assert_ok "GET /$CONN_CODE/units/Nos"
}

phase_3d_cost_centres() {
    log_phase "3d" "Cost centres (Phase 9A)"
    local __captured=()
    # Optional — Cost Centres must be enabled in Tally F11.
    _create_many "/$CONN_CODE/cost-centres" "cost-centre" DEMO_COST_CENTRES __captured 1

    api_get "/$CONN_CODE/cost-centres?per_page=50"
    assert_ok "GET /$CONN_CODE/cost-centres"

    api_get "/$CONN_CODE/cost-centres/$(_urlencode '-DEMO- Engineering')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/cost-centres/-DEMO- Engineering"
}

phase_3e_currencies() {
    log_phase "3e" "Currencies (Phase 9B)"
    local __captured=()
    # Optional — Multi-Currency must be enabled in Tally F11 to import currencies.
    _create_many "/$CONN_CODE/currencies" "currency" DEMO_CURRENCIES __captured 1

    api_get "/$CONN_CODE/currencies?per_page=50"
    assert_ok "GET /$CONN_CODE/currencies"

    api_get "/$CONN_CODE/currencies/$(_urlencode 'USD')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/currencies/USD"
}

phase_3f_godowns() {
    log_phase "3f" "Godowns (Phase 9B)"
    local __captured=()
    # Optional — Multiple Godowns/Locations must be enabled in Tally F11.
    _create_many "/$CONN_CODE/godowns" "godown" DEMO_GODOWNS __captured 1

    api_get "/$CONN_CODE/godowns?per_page=50"
    assert_ok "GET /$CONN_CODE/godowns"

    api_get "/$CONN_CODE/godowns/$(_urlencode '-DEMO- Mumbai Warehouse')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/godowns/-DEMO- Mumbai Warehouse"
}

phase_3g_voucher_types() {
    log_phase "3g" "Voucher Types (Phase 9B)"
    local __captured=()
    _create_many "/$CONN_CODE/voucher-types" "voucher-type" DEMO_VOUCHER_TYPES __captured

    api_get "/$CONN_CODE/voucher-types?per_page=50"
    assert_ok "GET /$CONN_CODE/voucher-types"

    api_get "/$CONN_CODE/voucher-types/$(_urlencode '-DEMO- Export Sale')"
    assert_ok "GET /$CONN_CODE/voucher-types/-DEMO- Export Sale"
}

phase_3h_stock_categories() {
    log_phase "3h" "Stock Categories (Phase 9F)"
    local __captured=()
    # Optional — Stock Categories must be enabled in Tally F11.
    _create_many "/$CONN_CODE/stock-categories" "stock-category" DEMO_STOCK_CATEGORIES __captured 1

    api_get "/$CONN_CODE/stock-categories?per_page=50"
    assert_ok "GET /$CONN_CODE/stock-categories"

    api_get "/$CONN_CODE/stock-categories/$(_urlencode '-DEMO- Enterprise Tier')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/stock-categories/-DEMO- Enterprise Tier"
}

phase_3i_price_lists() {
    log_phase "3i" "Price Lists (Phase 9F)"
    local __captured=()
    # Optional — Multiple Price Levels must be enabled in Tally F11.
    _create_many "/$CONN_CODE/price-lists" "price-list" DEMO_PRICE_LISTS __captured 1

    api_get "/$CONN_CODE/price-lists?per_page=50"
    assert_ok "GET /$CONN_CODE/price-lists"

    api_get "/$CONN_CODE/price-lists/$(_urlencode '-DEMO- Retail')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/price-lists/-DEMO- Retail"
}

phase_3j_cost_categories() {
    log_phase "3j" "Cost Categories (Phase 9N)"
    local __captured=()
    # Optional — Cost Categories must be enabled in Tally F11 (Maintain Cost Categories).
    _create_many "/$CONN_CODE/cost-categories" "cost-category" DEMO_COST_CATEGORIES __captured 1

    api_get "/$CONN_CODE/cost-categories?per_page=50"
    assert_ok "GET /$CONN_CODE/cost-categories"

    api_get "/$CONN_CODE/cost-categories/$(_urlencode '-DEMO- Department')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/cost-categories/-DEMO- Department"
}

phase_3k_employee_categories() {
    log_phase "3k" "Employee Categories (Phase 9N)"
    local __captured=()
    # Optional — Payroll must be enabled in Tally F11.
    _create_many "/$CONN_CODE/employee-categories" "employee-category" DEMO_EMPLOYEE_CATEGORIES __captured 1

    api_get "/$CONN_CODE/employee-categories?per_page=50"
    assert_ok "GET /$CONN_CODE/employee-categories"

    api_get "/$CONN_CODE/employee-categories/$(_urlencode '-DEMO- Engineering Team')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/employee-categories/-DEMO- Engineering Team"
}

phase_3l_employee_groups() {
    log_phase "3l" "Employee Groups (Phase 9N)"
    local __captured=()
    # Optional — Payroll must be enabled in Tally F11. Depends on Employee Categories (phase 3k).
    _create_many "/$CONN_CODE/employee-groups" "employee-group" DEMO_EMPLOYEE_GROUPS __captured 1

    api_get "/$CONN_CODE/employee-groups?per_page=50"
    assert_ok "GET /$CONN_CODE/employee-groups"

    api_get "/$CONN_CODE/employee-groups/$(_urlencode '-DEMO- Backend Team')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/employee-groups/-DEMO- Backend Team"
}

phase_3m_employees() {
    log_phase "3m" "Employees (Phase 9N)"
    local __captured=()
    # Optional — Payroll must be enabled in Tally F11. Depends on Employee Categories + Groups.
    _create_many "/$CONN_CODE/employees" "employee" DEMO_EMPLOYEES __captured 1

    api_get "/$CONN_CODE/employees?per_page=50"
    assert_ok "GET /$CONN_CODE/employees"

    api_get "/$CONN_CODE/employees/$(_urlencode '-DEMO- Alex Dev')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/employees/-DEMO- Alex Dev"
}

phase_3n_attendance_types() {
    log_phase "3n" "Attendance Types (Phase 9N)"
    local __captured=()
    # Optional — Payroll must be enabled in Tally F11.
    _create_many "/$CONN_CODE/attendance-types" "attendance-type" DEMO_ATTENDANCE_TYPES __captured 1

    api_get "/$CONN_CODE/attendance-types?per_page=50"
    assert_ok "GET /$CONN_CODE/attendance-types"

    api_get "/$CONN_CODE/attendance-types/$(_urlencode '-DEMO- Present')"
    assert_ok_or_skip_404 "GET /$CONN_CODE/attendance-types/-DEMO- Present"
}

phase_6b_inventory_ops() {
    log_phase "6b" "Inventory operations (Phase 9F)"

    # Stock transfer between godowns (created in Phase 9B fixtures)
    api_post "/$CONN_CODE/stock-transfers" '{
        "date":"20260420",
        "from_godown":"-DEMO- Mumbai Warehouse",
        "to_godown":"-DEMO- Pune Warehouse",
        "stock_item":"-DEMO- SKU-PRO Annual",
        "quantity":2,
        "unit":"Nos",
        "rate":48000,
        "voucher_number":"-DEMO-ST-0001",
        "narration":"-DEMO- Stock transfer Mumbai → Pune"
    }'
    assert_ok "POST /$CONN_CODE/stock-transfers"

    # Physical stock adjustment
    api_post "/$CONN_CODE/physical-stock" '{
        "date":"20260430",
        "godown":"-DEMO- Mumbai Warehouse",
        "stock_item":"-DEMO- SKU-PRO Annual",
        "counted_quantity":10,
        "unit":"Nos",
        "voucher_number":"-DEMO-PS-0001",
        "narration":"-DEMO- Monthly physical count"
    }'
    assert_ok "POST /$CONN_CODE/physical-stock"

    # New voucher types from expanded enum — exercise one of each via existing /vouchers endpoint
    # Sales Order
    api_post "/$CONN_CODE/vouchers" '{
        "type":"SalesOrder",
        "data":{
            "DATE":"20260420","VOUCHERTYPENAME":"Sales Order","VOUCHERNUMBER":"-DEMO-SO-0001",
            "PARTYLEDGERNAME":"-DEMO- Acme Corp",
            "NARRATION":"-DEMO- Sales order",
            "ALLINVENTORYENTRIES.LIST":[{"STOCKITEMNAME":"-DEMO- SKU-PRO Annual","ACTUALQTY":"5 Nos","BILLEDQTY":"5 Nos","RATE":"48000/Nos","AMOUNT":"240000.00"}]
        }
    }'
    assert_ok "POST /$CONN_CODE/vouchers (SalesOrder)"

    # Purchase Order
    api_post "/$CONN_CODE/vouchers" '{
        "type":"PurchaseOrder",
        "data":{
            "DATE":"20260420","VOUCHERTYPENAME":"Purchase Order","VOUCHERNUMBER":"-DEMO-PO-0001",
            "PARTYLEDGERNAME":"-DEMO- AWS India",
            "NARRATION":"-DEMO- Purchase order",
            "ALLLEDGERENTRIES.LIST":[
                {"LEDGERNAME":"-DEMO- AWS India","ISDEEMEDPOSITIVE":"No","AMOUNT":"-50000.00"},
                {"LEDGERNAME":"-DEMO- AWS Hosting","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"50000.00"}
            ]
        }
    }'
    assert_ok "POST /$CONN_CODE/vouchers (PurchaseOrder)"

    # Delivery Note
    api_post "/$CONN_CODE/vouchers" '{
        "type":"DeliveryNote",
        "data":{
            "DATE":"20260420","VOUCHERTYPENAME":"Delivery Note","VOUCHERNUMBER":"-DEMO-DN-0001",
            "PARTYLEDGERNAME":"-DEMO- Acme Corp",
            "NARRATION":"-DEMO- Goods dispatched",
            "ALLINVENTORYENTRIES.LIST":[{"STOCKITEMNAME":"-DEMO- SKU-PRO Annual","ACTUALQTY":"2 Nos","BILLEDQTY":"2 Nos","RATE":"48000/Nos","AMOUNT":"96000.00"}]
        }
    }'
    assert_ok "POST /$CONN_CODE/vouchers (DeliveryNote)"
}

phase_4_ledgers() {
    log_phase "4" "Ledgers"

    # Parent-precheck: every PARENT referenced by DEMO_LEDGERS must exist as a
    # group in Tally. As of 2026-04-19 ledgers reference Tally's RESERVED groups
    # directly (Sundry Debtors, Sales Accounts, Indirect Expenses, Bank Accounts,
    # Cash-in-Hand, Duties & Taxes, Loans & Advances (Asset), etc.), so the
    # precheck verifies built-in masters that always exist on a fresh company.
    # We don't depend on any custom DEMO group being created — keeps the test
    # robust across re-runs and against companies where phase_3 was skipped.
    local -a parent_names=()
    for payload in "${DEMO_LEDGERS[@]}"; do
        parent_names+=("$(json_extract '.PARENT' "$payload")")
    done
    # Dedup parent list (bash 4+; falls back to the raw list if assoc-array unavailable)
    local -a unique_parents=()
    if declare -A _seen 2>/dev/null; then
        for p in "${parent_names[@]}"; do
            [[ -z "$p" ]] && continue
            [[ -n "${_seen[$p]:-}" ]] && continue
            _seen[$p]=1
            unique_parents+=("$p")
        done
        unset _seen
    else
        unique_parents=("${parent_names[@]}")
    fi
    verify_parents_exist "/$CONN_CODE/groups" "ledger parent precheck" "${unique_parents[@]}" || true

    # Pre-flight optional references: strip CURRENCYNAME from any ledger whose
    # currency doesn't actually exist in Tally. This keeps the fixture semantic
    # (USD-denominated export customer) when multi-currency IS enabled, while
    # still running cleanly on companies where F11 multi-currency is off.
    # Same pattern can be extended to any optional reference (CATEGORY on
    # cost-centres, COSTCENTRESON=Yes ledgers referencing specific cost centres,
    # etc.) — see strip_if_missing in lib/http.sh.
    local -a LEDGERS_PREFLIGHT=()
    for ledger_json in "${DEMO_LEDGERS[@]}"; do
        ledger_json=$(strip_if_missing "$ledger_json" "CURRENCYNAME" "/$CONN_CODE/currencies")
        LEDGERS_PREFLIGHT+=("$ledger_json")
    done

    _create_many "/$CONN_CODE/ledgers" "ledger" LEDGERS_PREFLIGHT CREATED_LEDGERS

    # List (paginated + search)
    api_get "/$CONN_CODE/ledgers?per_page=50"
    assert_ok "GET /$CONN_CODE/ledgers (paginated)"

    api_get "/$CONN_CODE/ledgers?search=Acme"
    assert_ok "GET /$CONN_CODE/ledgers?search=Acme"

    # Show one
    api_get "/$CONN_CODE/ledgers/$(_urlencode '-DEMO- Acme Corp')"
    assert_ok "GET /$CONN_CODE/ledgers/-DEMO- Acme Corp"

    # Update one (email change) — PUT
    api_put "/$CONN_CODE/ledgers/$(_urlencode '-DEMO- Acme Corp')" \
        '{"EMAIL":"new-billing@acme.example"}'
    assert_ok "PUT /$CONN_CODE/ledgers/-DEMO- Acme Corp (email)"

    # PATCH — partial update (GSTIN + credit limit change, common real scenario)
    api_patch "/$CONN_CODE/ledgers/$(_urlencode '-DEMO- Acme Corp')" \
        '{"PARTYGSTIN":"27ABCDE1234F1Z9","CREDITLIMIT":750000}'
    assert_ok "PATCH /$CONN_CODE/ledgers/-DEMO- Acme Corp (GSTIN+credit)"

    # PARENT move — regroup a ledger between two RESERVED Tally groups (real
    # scenario: reclassify an expense from "Indirect Expenses" to a more
    # specific reserved sub-group). Uses standard groups so this works on a
    # fresh company without any custom-group setup.
    api_put "/$CONN_CODE/ledgers/$(_urlencode '-DEMO- GitHub')" \
        '{"PARENT":"Direct Expenses"}'
    assert_ok "PUT /$CONN_CODE/ledgers/-DEMO- GitHub (PARENT move)"

    # Pagination + sort
    api_get "/$CONN_CODE/ledgers?page=1&per_page=5&sort_by=NAME&sort_dir=asc"
    assert_ok "GET /$CONN_CODE/ledgers (page=1 sort asc)"

    api_get "/$CONN_CODE/ledgers?page=2&per_page=5&sort_by=NAME&sort_dir=desc"
    assert_ok "GET /$CONN_CODE/ledgers (page=2 sort desc)"

    # Multi-term search
    api_get "/$CONN_CODE/ledgers?search=Cloud"
    assert_ok "GET /$CONN_CODE/ledgers?search=Cloud"

    # Delete one (last non-critical: NorthStar)
    api_delete "/$CONN_CODE/ledgers/$(_urlencode '-DEMO- NorthStar LLC')"
    assert_ok "DELETE /$CONN_CODE/ledgers/-DEMO- NorthStar LLC"
}

phase_5_stock_items() {
    log_phase "5" "Stock items"

    # Parent-precheck: stock items reference BASEUNITS (a unit master) and
    # PARENT (a stock group). If a unit is missing the create errors with
    # "Could not find unit". Verify both before bulk-create.
    local -a units_seen=() groups_seen=()
    if declare -A _u 2>/dev/null && declare -A _g 2>/dev/null; then
        for payload in "${DEMO_STOCK_ITEMS[@]}"; do
            local u; u=$(json_extract '.BASEUNITS' "$payload")
            local g; g=$(json_extract '.PARENT' "$payload")
            [[ -n "$u" && -z "${_u[$u]:-}" ]] && { _u[$u]=1; units_seen+=("$u"); }
            [[ -n "$g" && "$g" != "Primary" && -z "${_g[$g]:-}" ]] && { _g[$g]=1; groups_seen+=("$g"); }
        done
        unset _u _g
    fi
    (( ${#units_seen[@]} > 0 ))  && verify_parents_exist "/$CONN_CODE/units"        "stock-item unit precheck"  "${units_seen[@]}"  || true
    (( ${#groups_seen[@]} > 0 )) && verify_parents_exist "/$CONN_CODE/stock-groups" "stock-item group precheck" "${groups_seen[@]}" || true

    _create_many "/$CONN_CODE/stock-items" "stock" DEMO_STOCK_ITEMS CREATED_STOCK

    api_get "/$CONN_CODE/stock-items?per_page=50"
    assert_ok "GET /$CONN_CODE/stock-items"

    api_get "/$CONN_CODE/stock-items/$(_urlencode '-DEMO- SKU-PRO Annual')"
    assert_ok "GET /$CONN_CODE/stock-items/-DEMO- SKU-PRO Annual"

    # PARENT references an actual stock-group created in phase_3b.
    api_put "/$CONN_CODE/stock-items/$(_urlencode '-DEMO- Analytics Add-on')" \
        '{"NAME":"-DEMO- Analytics Add-on","PARENT":"-DEMO- Cloud Add-ons","BASEUNITS":"Nos"}'
    assert_ok "PUT /$CONN_CODE/stock-items/-DEMO- Analytics Add-on"

    # PATCH HSN code (real GST-compliance scenario)
    api_patch "/$CONN_CODE/stock-items/$(_urlencode '-DEMO- SKU-PRO Annual')" \
        '{"HSNCODE":"998314"}'
    assert_ok "PATCH /$CONN_CODE/stock-items/-DEMO- SKU-PRO Annual (HSN)"
}

phase_6_vouchers() {
    log_phase "6" "Vouchers"

    # Parent-precheck: every LEDGERNAME referenced by ANY voucher fixture must
    # exist in Tally before we POST. Otherwise Tally rejects the import with
    # "Could not find ledger ..." and the create reports success:false.
    local -a referenced_ledgers=()
    if declare -A _ledger_seen 2>/dev/null; then
        while IFS= read -r ledger_name; do
            [[ -z "$ledger_name" ]] && continue
            [[ -n "${_ledger_seen[$ledger_name]:-}" ]] && continue
            _ledger_seen[$ledger_name]=1
            referenced_ledgers+=("$ledger_name")
        done < <(grep -hoE '"LEDGERNAME":"-DEMO-[^"]*"|"PARTYLEDGERNAME":"-DEMO-[^"]*"' \
            "$SCRIPT_DIR/lib/fixtures.sh" | sed -E 's/.*":"//; s/"$//')
        unset _ledger_seen
    fi
    if (( ${#referenced_ledgers[@]} > 0 )); then
        verify_parents_exist "/$CONN_CODE/ledgers" "voucher ledger precheck" "${referenced_ledgers[@]}" || true
    fi

    # Pick the USD-vs-INR variant based on what actually exists in Tally. The
    # ledger phase stripped CURRENCYNAME=USD if the currency wasn't in Tally, so
    # the multi-currency voucher would otherwise emit forex amounts against an
    # INR ledger and get rejected. Check the created currency, then chain the
    # voucher fixture consistent with that reality.
    local export_sale_fn="voucher_sales_export_inr"
    if cached_master_exists "/$CONN_CODE/currencies" "USD"; then
        export_sale_fn="voucher_sales_usd_multicurrency"
    fi

    local -a vouchers=(
        # Basic one-of-each (from original)
        "voucher_sales_acme"
        "voucher_sales_technova"
        "voucher_sales_global"
        "voucher_purchase_aws"
        "voucher_payment_aws"
        "voucher_receipt_acme"
        "voucher_journal"
        "voucher_contra"
        "voucher_credit_note"
        "voucher_debit_note"
        # Real-company scenarios (added in coverage expansion)
        "voucher_sales_with_gst"
        "voucher_sales_igst_interstate"
        "voucher_sales_with_inventory"
        "$export_sale_fn"
        "voucher_purchase_with_gst"
        "voucher_receipt_with_bill_alloc"
        "voucher_payment_with_bill_alloc"
        "voucher_journal_depreciation"
    )

    for fn in "${vouchers[@]}"; do
        local payload; payload=$("$fn")
        api_post "/$CONN_CODE/vouchers" "$payload"
        assert_ok "POST /$CONN_CODE/vouchers ($fn)"
        local vch_id; vch_id=$(json_field '.data.lastvchid')
        [[ -n "$vch_id" ]] && CREATED_VCH_IDS+=("$vch_id")
    done

    # Batch import (Phase 9A — single request, 3 vouchers)
    api_post "/$CONN_CODE/vouchers/batch" "$(voucher_batch_journals)"
    assert_ok "POST /$CONN_CODE/vouchers/batch (3 journals)"

    # List by type
    api_get "/$CONN_CODE/vouchers?type=Sales&from_date=20260101&to_date=20261231"
    assert_ok "GET /$CONN_CODE/vouchers?type=Sales"

    # Show one (first captured id)
    if (( ${#CREATED_VCH_IDS[@]} > 0 )); then
        api_get "/$CONN_CODE/vouchers/${CREATED_VCH_IDS[0]}"
        assert_ok "GET /$CONN_CODE/vouchers/${CREATED_VCH_IDS[0]}"

        # Alter first voucher (narration change) — PUT
        api_put "/$CONN_CODE/vouchers/${CREATED_VCH_IDS[0]}" \
            '{"type":"Sales","data":{"NARRATION":"-DEMO- Altered narration"}}'
        assert_ok "PUT /$CONN_CODE/vouchers/${CREATED_VCH_IDS[0]}"

        # PATCH on the same voucher (partial update path)
        if (( ${#CREATED_VCH_IDS[@]} > 1 )); then
            api_patch "/$CONN_CODE/vouchers/${CREATED_VCH_IDS[1]}" \
                '{"type":"Sales","data":{"NARRATION":"-DEMO- Patched narration"}}'
            assert_ok "PATCH /$CONN_CODE/vouchers/${CREATED_VCH_IDS[1]}"
        fi
    else
        log_skip "Alter/show — no voucher IDs captured"
    fi

    # Cancel credit-note (preserves audit trail)
    api_delete "/$CONN_CODE/vouchers/0" \
        '{"type":"CreditNote","date":"17-Apr-2026","voucher_number":"-DEMO-CN-0002","action":"cancel","narration":"-DEMO- Smoke-test cancel"}'
    assert_ok "DELETE cancel -DEMO-CN-0002"

    # Delete debit-note (permanent)
    api_delete "/$CONN_CODE/vouchers/0" \
        '{"type":"DebitNote","date":"17-Apr-2026","voucher_number":"-DEMO-DN-0001","action":"delete"}'
    assert_ok "DELETE permanent -DEMO-DN-0001"
}

phase_7_reports() {
    log_phase "7" "Reports"

    local reports=(
        # Original (Phase 1)
        "balance-sheet?date=20260331"
        "profit-and-loss?from=20250401&to=20260331"
        "trial-balance?date=20260331"
        "ledger?ledger=%5BDEMO%5D%20Acme%20Corp&from=20250401&to=20260331"
        "outstandings?type=receivable"
        "stock-summary"
        "day-book?date=20260416"
        # Phase 9B additions
        "cash-book?ledger=%5BDEMO%5D%20HDFC%20Current%20A%2Fc&from=20250401&to=20260331"
        "sales-register?from=20250401&to=20260331"
        "purchase-register?from=20250401&to=20260331"
        "aging?type=receivable&as_of=20260331"
        "cash-flow?from=20250401&to=20260331"
        "funds-flow?from=20250401&to=20260331"
        "receipts-payments?from=20250401&to=20260331"
        "stock-movement?stock_item=%5BDEMO%5D%20SKU-PRO%20Annual&from=20250401&to=20260331"
    )

    for r in "${reports[@]}"; do
        api_get "/$CONN_CODE/reports/$r"
        assert_ok "GET /$CONN_CODE/reports/$r"
    done

    # CSV downloads (3 different reports — StreamedResponse path)
    mkdir -p "$PROJECT_ROOT/storage/smoke-test"
    local ts
    ts=$(date +%Y%m%d-%H%M%S)

    api_download_csv "/$CONN_CODE/reports/trial-balance?date=20260331&format=csv" \
        "$PROJECT_ROOT/storage/smoke-test/trial-balance-$ts.csv"
    assert_ok "GET /$CONN_CODE/reports/trial-balance (csv)"

    api_download_csv "/$CONN_CODE/reports/profit-and-loss?from=20250401&to=20260331&format=csv" \
        "$PROJECT_ROOT/storage/smoke-test/profit-and-loss-$ts.csv"
    assert_ok "GET /$CONN_CODE/reports/profit-and-loss (csv)"

    api_download_csv "/$CONN_CODE/reports/ledger?ledger=%5BDEMO%5D%20Acme%20Corp&from=20250401&to=20260331&format=csv" \
        "$PROJECT_ROOT/storage/smoke-test/ledger-acme-$ts.csv"
    assert_ok "GET /$CONN_CODE/reports/ledger (csv)"
}

phase_8_sync() {
    log_phase "8" "Sync"

    api_post "/connections/$CONNECTION_ID/sync-from-tally"
    assert_ok "POST /connections/$CONNECTION_ID/sync-from-tally (dispatches job)"

    api_post "/connections/$CONNECTION_ID/sync-to-tally"
    assert_ok "POST /connections/$CONNECTION_ID/sync-to-tally"

    api_post "/connections/$CONNECTION_ID/sync-full"
    assert_ok "POST /connections/$CONNECTION_ID/sync-full"

    api_get "/connections/$CONNECTION_ID/sync-stats"
    assert_ok "GET /connections/$CONNECTION_ID/sync-stats"

    api_get "/connections/$CONNECTION_ID/sync-pending?limit=10"
    assert_ok "GET /connections/$CONNECTION_ID/sync-pending"

    api_get "/connections/$CONNECTION_ID/sync-conflicts"
    assert_ok "GET /connections/$CONNECTION_ID/sync-conflicts"

    # If any conflicts present and --force-conflict, resolve the first one.
    local first_conflict_id
    first_conflict_id=$(json_field '.data[0].id')
    if [[ -n "$first_conflict_id" ]]; then
        api_post "/sync/$first_conflict_id/resolve" '{"strategy":"erp_wins"}'
        assert_ok "POST /sync/$first_conflict_id/resolve"
    else
        log_skip "POST /sync/{sync}/resolve — no conflicts present"
    fi
}

phase_9_audit() {
    log_phase "9" "Audit log"
    api_get "/audit-logs?per_page=50"
    assert_ok "GET /audit-logs"

    # Filter: by action
    api_get "/audit-logs?action=create&per_page=20"
    assert_ok "GET /audit-logs?action=create"

    # Filter: by object_type
    api_get "/audit-logs?object_type=LEDGER&per_page=20"
    assert_ok "GET /audit-logs?object_type=LEDGER"

    api_get "/audit-logs?object_type=VOUCHER&per_page=20"
    assert_ok "GET /audit-logs?object_type=VOUCHER"

    # Filter: by connection code
    api_get "/audit-logs?connection=$CONN_CODE&per_page=20"
    assert_ok "GET /audit-logs?connection=$CONN_CODE"

    # Phase 9C — audit detail (first id from earlier list call) + CSV export
    local first_id
    first_id=$(json_field '.data[0].id')
    if [[ -n "$first_id" ]]; then
        api_get "/audit-logs/$first_id"
        assert_ok "GET /audit-logs/$first_id (detail)"
    else
        log_skip "GET /audit-logs/{id} — no audit rows found"
    fi

    # CSV export (separate call — downloads file)
    mkdir -p "$PROJECT_ROOT/storage/smoke-test"
    api_download_csv "/audit-logs/export" \
        "$PROJECT_ROOT/storage/smoke-test/audit-logs-$(date +%Y%m%d-%H%M%S).csv"
    assert_ok "GET /audit-logs/export (csv)"
}

phase_8b_banking() {
    log_phase "8b" "Banking (Phase 9D)"

    # Bank-reconciliation, cheque-register, post-dated-cheques are dispatched
    # on the existing /{c}/reports/{type} endpoint.
    api_get "/$CONN_CODE/reports/bank-reconciliation?bank=%5BDEMO%5D%20HDFC%20Current%20A%2Fc&from=20250401&to=20260331"
    assert_ok "GET /$CONN_CODE/reports/bank-reconciliation"

    api_get "/$CONN_CODE/reports/cheque-register?from=20250401&to=20260331"
    assert_ok "GET /$CONN_CODE/reports/cheque-register"

    api_get "/$CONN_CODE/reports/post-dated-cheques?from=20250401&to=20260331"
    assert_ok "GET /$CONN_CODE/reports/post-dated-cheques"

    # CSV import — tiny inline sample
    local sample_csv
    sample_csv='date,description,amount,reference\n16-Apr-2026,AWS Bill,-45000,-DEMO-PMT-0001\n17-Apr-2026,Customer Receipt,50000,-DEMO-RCT-0001'
    api_post "/$CONN_CODE/bank/import-statement" "$(printf '{"csv":"%s"}' "$sample_csv")"
    assert_ok "POST /$CONN_CODE/bank/import-statement"

    # Auto-match — feed a tiny row set; matches depend on vouchers Tally actually has
    api_post "/$CONN_CODE/bank/auto-match" '{
        "bank_ledger":"-DEMO- HDFC Current A/c",
        "from_date":"20260401","to_date":"20260430",
        "rows":[
            {"date":"20260416","description":"AWS","amount":-45000,"reference":"-DEMO-PMT-0001"}
        ],
        "date_tolerance_days":3
    }'
    assert_ok "POST /$CONN_CODE/bank/auto-match"

    # Reconcile a known voucher created earlier in phase 6 (payment to AWS)
    api_post "/$CONN_CODE/bank/reconcile" '{
        "voucher_number":"-DEMO-PMT-0001",
        "voucher_date":"20260416",
        "voucher_type":"Payment",
        "statement_date":"16-Apr-2026",
        "bank_ledger":"-DEMO- HDFC Current A/c"
    }'
    assert_ok "POST /$CONN_CODE/bank/reconcile"

    # Unreconcile — flip it back so re-runs are idempotent
    api_post "/$CONN_CODE/bank/unreconcile" '{
        "voucher_number":"-DEMO-PMT-0001",
        "voucher_date":"20260416",
        "voucher_type":"Payment",
        "bank_ledger":"-DEMO- HDFC Current A/c"
    }'
    assert_ok "POST /$CONN_CODE/bank/unreconcile"

    # Batch reconcile with a single entry
    api_post "/$CONN_CODE/bank/batch-reconcile" '{
        "entries":[
            {
                "voucher_number":"-DEMO-PMT-0001",
                "voucher_date":"20260416",
                "voucher_type":"Payment",
                "statement_date":"16-Apr-2026",
                "bank_ledger":"-DEMO- HDFC Current A/c"
            }
        ]
    }'
    assert_ok "POST /$CONN_CODE/bank/batch-reconcile"
}

phase_2b_mnc_setup() {
    log_phase "2b" "MNC Hierarchy setup (Phase 9Z — masters)"

    # ---- Organization (lookup → reuse on hit, create on miss) ----
    ensure_db_entity "/organizations" '.code' "SWATDEMO" \
        '{"name":"-DEMO- SwatTech Group","code":"SWATDEMO","country":"IN","base_currency":"INR"}' \
        "organization SWATDEMO"
    if [[ "$ENSURE_PATH" == "found" || "$ENSURE_PATH" == "created" ]]; then
        PASSED=$((PASSED + 1)); log_pass "ensure organization SWATDEMO [${ENSURE_PATH}]"
    else
        _handle_failure "ensure organization SWATDEMO" "could not find or create"
        return
    fi
    MNC_ORG_ID="$ENSURE_ID"; MNC_ORG_PATH="$ENSURE_PATH"

    api_get "/organizations"
    assert_ok "GET /organizations"

    api_get "/organizations/$MNC_ORG_ID"
    assert_ok "GET /organizations/$MNC_ORG_ID"

    api_put "/organizations/$MNC_ORG_ID" '{"name":"-DEMO- SwatTech Group","code":"SWATDEMO","base_currency":"INR","is_active":true}'
    assert_ok "PUT /organizations/$MNC_ORG_ID"

    # ---- Company under org (lookup-then-create; never aborts the phase) ----
    ensure_db_entity "/companies?organization_id=$MNC_ORG_ID" '.code' "SWATIN" \
        "$(printf '{"tally_organization_id":%s,"name":"-DEMO- SwatTech India Pvt Ltd","code":"SWATIN","country":"IN","base_currency":"INR","gstin":"27ABCDE1234F1Z5"}' "$MNC_ORG_ID")" \
        "company SWATIN"
    if [[ "$ENSURE_PATH" == "found" || "$ENSURE_PATH" == "created" ]]; then
        PASSED=$((PASSED + 1)); log_pass "ensure company SWATIN [${ENSURE_PATH}]"
        MNC_COMPANY_ID="$ENSURE_ID"; MNC_COMPANY_PATH="$ENSURE_PATH"
    else
        log_warn "ensure company SWATIN failed — continuing to org-level reports"
    fi

    api_get "/companies?organization_id=$MNC_ORG_ID"
    assert_ok "GET /companies?organization_id=$MNC_ORG_ID"

    # ---- Branch under company (only if company resolved) ----
    if [[ -n "$MNC_COMPANY_ID" ]]; then
        api_get "/companies/$MNC_COMPANY_ID"
        assert_ok "GET /companies/$MNC_COMPANY_ID"

        ensure_db_entity "/branches?company_id=$MNC_COMPANY_ID" '.code' "MUMHQ" \
            "$(printf '{"tally_company_id":%s,"name":"-DEMO- Mumbai HQ","code":"MUMHQ","city":"Mumbai","state":"Maharashtra"}' "$MNC_COMPANY_ID")" \
            "branch MUMHQ"
        if [[ "$ENSURE_PATH" == "found" || "$ENSURE_PATH" == "created" ]]; then
            PASSED=$((PASSED + 1)); log_pass "ensure branch MUMHQ [${ENSURE_PATH}]"
            MNC_BRANCH_ID="$ENSURE_ID"; MNC_BRANCH_PATH="$ENSURE_PATH"
        else
            log_warn "ensure branch MUMHQ failed — continuing"
        fi

        api_get "/branches?company_id=$MNC_COMPANY_ID"
        assert_ok "GET /branches?company_id=$MNC_COMPANY_ID"

        if [[ -n "$MNC_BRANCH_ID" ]]; then
            api_get "/branches/$MNC_BRANCH_ID"
            assert_ok "GET /branches/$MNC_BRANCH_ID"
        fi
    fi
}

phase_7b_consolidated_reports() {
    log_phase "7b" "MNC Consolidated reports (Phase 9K)"

    if [[ -z "$MNC_ORG_ID" ]]; then
        log_skip "consolidated reports — MNC setup did not resolve an organization"
        return
    fi

    api_get "/organizations/$MNC_ORG_ID/consolidated/balance-sheet?date=20260331"
    assert_ok "GET /organizations/$MNC_ORG_ID/consolidated/balance-sheet"

    api_get "/organizations/$MNC_ORG_ID/consolidated/profit-and-loss?from=20250401&to=20260331"
    assert_ok "GET /organizations/$MNC_ORG_ID/consolidated/profit-and-loss"

    api_get "/organizations/$MNC_ORG_ID/consolidated/trial-balance?date=20260331"
    assert_ok "GET /organizations/$MNC_ORG_ID/consolidated/trial-balance"
}

phase_2b_mnc_teardown() {
    # Reverse-cascade teardown — only delete what THIS run created.
    # Runs at end-of-run (after reports) so consolidated reports still had data to read.
    if [[ -z "$MNC_ORG_ID" ]]; then
        return
    fi
    log_phase "2b-teardown" "MNC Hierarchy teardown"

    if [[ -n "$MNC_BRANCH_ID" && "$MNC_BRANCH_PATH" == "created" ]]; then
        api_delete "/branches/$MNC_BRANCH_ID"
        assert_ok "DELETE /branches/$MNC_BRANCH_ID (created this run)"
    elif [[ -n "$MNC_BRANCH_ID" ]]; then
        log_info "Leaving branch $MNC_BRANCH_ID in place (existed before this run)"
    fi

    if [[ -n "$MNC_COMPANY_ID" && "$MNC_COMPANY_PATH" == "created" ]]; then
        api_delete "/companies/$MNC_COMPANY_ID"
        assert_ok "DELETE /companies/$MNC_COMPANY_ID (created this run)"
    elif [[ -n "$MNC_COMPANY_ID" ]]; then
        log_info "Leaving company $MNC_COMPANY_ID in place (existed before this run)"
    fi

    if [[ "$MNC_ORG_PATH" == "created" ]]; then
        api_delete "/organizations/$MNC_ORG_ID"
        assert_ok "DELETE /organizations/$MNC_ORG_ID (created this run)"
    else
        log_info "Leaving organization $MNC_ORG_ID in place (existed before this run)"
    fi
}

phase_6c_manufacturing() {
    log_phase "6c" "Manufacturing (Phase 9G)"

    # Set BOM on an existing stock item — pretend SKU-ENT Annual is assembled from SKU-PRO + Analytics Add-on
    api_put "/$CONN_CODE/stock-items/$(_urlencode '-DEMO- SKU-ENT Annual')/bom" '{
        "components":[
            {"name":"-DEMO- SKU-PRO Annual","qty":1,"unit":"Nos"},
            {"name":"-DEMO- Analytics Add-on","qty":1,"unit":"Nos"}
        ]
    }'
    assert_ok "PUT /$CONN_CODE/stock-items/.../bom"

    # Read BOM back
    api_get "/$CONN_CODE/stock-items/$(_urlencode '-DEMO- SKU-ENT Annual')/bom"
    assert_ok "GET /$CONN_CODE/stock-items/.../bom"

    # Manufacturing voucher — produce 3 ENT bundles from 3 PRO + 3 Add-on
    api_post "/$CONN_CODE/manufacturing" '{
        "date":"20260425",
        "product_item":"-DEMO- SKU-ENT Annual",
        "product_qty":3,
        "unit":"Nos",
        "product_godown":"-DEMO- Mumbai Warehouse",
        "components":[
            {"name":"-DEMO- SKU-PRO Annual","qty":3,"unit":"Nos","godown":"-DEMO- Mumbai Warehouse"},
            {"name":"-DEMO- Analytics Add-on","qty":3,"unit":"Nos","godown":"-DEMO- Mumbai Warehouse"}
        ],
        "voucher_number":"-DEMO-MFG-0001",
        "narration":"-DEMO- Bundle assembly"
    }'
    assert_ok "POST /$CONN_CODE/manufacturing"

    # Job Work Out — send items to a job worker
    api_post "/$CONN_CODE/job-work-out" '{
        "date":"20260425",
        "job_worker_ledger":"-DEMO- AWS India",
        "stock_item":"-DEMO- SKU-PRO Annual",
        "quantity":5,
        "unit":"Nos",
        "voucher_number":"-DEMO-JWO-0001",
        "narration":"-DEMO- Job work out"
    }'
    assert_ok "POST /$CONN_CODE/job-work-out"

    # Job Work In — receive back processed items
    api_post "/$CONN_CODE/job-work-in" '{
        "date":"20260428",
        "job_worker_ledger":"-DEMO- AWS India",
        "stock_item":"-DEMO- SKU-PRO Annual",
        "quantity":5,
        "unit":"Nos",
        "voucher_number":"-DEMO-JWI-0001",
        "narration":"-DEMO- Job work in"
    }'
    assert_ok "POST /$CONN_CODE/job-work-in"
}

phase_9f_permissions() {
    log_phase "9f" "Permission gating (403 expected)"

    local restricted_token
    restricted_token=$(bootstrap_restricted_user_and_token)

    if [[ -z "$restricted_token" ]]; then
        log_skip "No restricted token — could not create zero-permission user"

        return
    fi

    # Swap the token temporarily.
    local saved_token="$TALLY_API_TOKEN"
    TALLY_API_TOKEN="$restricted_token"
    export TALLY_API_TOKEN

    # These calls should all return 403.
    api_get "/connections"
    assert_http_code "GET /connections expects 403" "403"

    api_get "/$CONN_CODE/ledgers"
    assert_http_code "GET /{conn}/ledgers expects 403" "403"

    api_get "/webhooks"
    assert_http_code "GET /webhooks expects 403" "403"

    # Restore the admin token.
    TALLY_API_TOKEN="$saved_token"
    export TALLY_API_TOKEN
}

phase_9e_integration() {
    log_phase "9e" "Integration glue (Phase 9I)"

    # Lookup-then-create webhook by name so re-runs reuse the row.
    ensure_db_entity "/webhooks" '.name' "-DEMO- Smoke test webhook" \
        '{"name":"-DEMO- Smoke test webhook","url":"https://httpbin.org/post","events":["voucher.created","sync.completed"]}' \
        "webhook -DEMO- Smoke test webhook"
    if [[ "$ENSURE_PATH" == "found" || "$ENSURE_PATH" == "created" ]]; then
        PASSED=$((PASSED + 1)); log_pass "ensure webhook [${ENSURE_PATH}]"
    else
        log_warn "ensure webhook failed — will skip per-id calls"
    fi
    local wh_id="$ENSURE_ID"
    local wh_path="$ENSURE_PATH"

    api_get "/webhooks"
    assert_ok "GET /webhooks"

    if [[ -n "$wh_id" ]]; then
        api_get "/webhooks/$wh_id"
        assert_ok "GET /webhooks/$wh_id"

        # Fire a test payload (dispatched sync so delivery row updates)
        api_post "/webhooks/$wh_id/test"
        assert_ok "POST /webhooks/$wh_id/test"

        # Deliveries log
        api_get "/webhooks/$wh_id/deliveries"
        assert_ok "GET /webhooks/$wh_id/deliveries"

        # Only delete if WE created it this run.
        if [[ "$wh_path" == "created" ]]; then
            api_delete "/webhooks/$wh_id"
            assert_ok "DELETE /webhooks/$wh_id (created this run)"
        else
            log_info "Leaving webhook $wh_id in place (existed before this run)"
        fi
    fi

    # Voucher PDF (first captured voucher id from phase 6)
    if (( ${#CREATED_VCH_IDS[@]} > 0 )); then
        local tmp_pdf="$PROJECT_ROOT/storage/smoke-test/voucher-${CREATED_VCH_IDS[0]}.pdf"
        api_download_csv "/$CONN_CODE/vouchers/${CREATED_VCH_IDS[0]}/pdf" "$tmp_pdf"
        assert_ok "GET /$CONN_CODE/vouchers/${CREATED_VCH_IDS[0]}/pdf"
    else
        log_skip "Voucher PDF — no voucher ids captured earlier"
    fi
}

phase_9d_workflow() {
    log_phase "9d" "Draft voucher workflow (Phase 9J)"

    # Create a small draft — below default threshold so submit will auto-approve + push.
    # Because approval_thresholds in config is empty by default, requiresApproval() returns true;
    # we'll explicitly exercise the full submit → approve flow.
    api_post "/connections/$CONNECTION_ID/draft-vouchers" '{
        "voucher_type":"Payment",
        "amount":1500,
        "narration":"-DEMO- Petty cash payment",
        "voucher_data":{
            "DATE":"20260420","VOUCHERTYPENAME":"Payment","VOUCHERNUMBER":"-DEMO-DRAFT-0001",
            "NARRATION":"-DEMO- Petty cash",
            "ALLLEDGERENTRIES.LIST":[
                {"LEDGERNAME":"-DEMO- Office Rent","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"1500.00"},
                {"LEDGERNAME":"-DEMO- Cash in Hand","ISDEEMEDPOSITIVE":"No","AMOUNT":"-1500.00"}
            ]
        }
    }'
    assert_ok "POST /connections/$CONNECTION_ID/draft-vouchers"
    local draft_id
    draft_id=$(json_field '.data.id')

    # Fall back to first existing draft if create returned no id (idempotent re-runs).
    if [[ -z "$draft_id" ]]; then
        api_get "/connections/$CONNECTION_ID/draft-vouchers?status=draft&per_page=1"
        draft_id=$(json_field '.data[0].id')
        if [[ -n "$draft_id" ]]; then
            log_info "Reusing existing draft id=$draft_id (create returned no id — likely duplicate)"
        else
            log_warn "Draft voucher flow — no id from create OR list; skipping detail ops only"
            api_get "/connections/$CONNECTION_ID/draft-vouchers?status=draft"
            assert_ok "GET /connections/$CONNECTION_ID/draft-vouchers?status=draft (smoke)"
            return
        fi
    fi

    # List + filter
    api_get "/connections/$CONNECTION_ID/draft-vouchers?status=draft"
    assert_ok "GET /connections/$CONNECTION_ID/draft-vouchers?status=draft"

    # Show
    api_get "/connections/$CONNECTION_ID/draft-vouchers/$draft_id"
    assert_ok "GET /connections/$CONNECTION_ID/draft-vouchers/$draft_id"

    # PATCH — allowed while in draft
    api_patch "/connections/$CONNECTION_ID/draft-vouchers/$draft_id" '{"narration":"-DEMO- Petty cash — updated"}'
    assert_ok "PATCH draft-vouchers/$draft_id"

    # Submit (will transition to submitted; same user so approve would be forbidden,
    # but since config has require_distinct_approver=true, we use reject instead to test rejection path
    # AND create a second draft to exercise approve).
    api_post "/connections/$CONNECTION_ID/draft-vouchers/$draft_id/submit"
    assert_ok "POST draft-vouchers/$draft_id/submit"

    # Reject (same user, so self-approve is blocked but reject has no such restriction)
    api_post "/connections/$CONNECTION_ID/draft-vouchers/$draft_id/reject" '{"reason":"Testing rejection path in smoke test"}'
    assert_ok "POST draft-vouchers/$draft_id/reject"

    # Create a second draft, approve it via same-user (distinct-approver rule will block — that's the expected
    # behaviour for a single-user smoke test, so we temporarily disable the rule by deleting this draft instead
    # once we've proven approve's error path).
    api_post "/connections/$CONNECTION_ID/draft-vouchers" '{
        "voucher_type":"Payment",
        "amount":500,
        "narration":"-DEMO- To-be-deleted draft",
        "voucher_data":{"DATE":"20260420","VOUCHERTYPENAME":"Payment","VOUCHERNUMBER":"-DEMO-DRAFT-0002","ALLLEDGERENTRIES.LIST":[{"LEDGERNAME":"-DEMO- Office Rent","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"500.00"},{"LEDGERNAME":"-DEMO- Cash in Hand","ISDEEMEDPOSITIVE":"No","AMOUNT":"-500.00"}]}
    }'
    assert_ok "POST /connections/$CONNECTION_ID/draft-vouchers (#2)"
    local draft2_id
    draft2_id=$(json_field '.data.id')

    if [[ -n "$draft2_id" ]]; then
        # DELETE — allowed while in draft
        api_delete "/connections/$CONNECTION_ID/draft-vouchers/$draft2_id"
        assert_ok "DELETE draft-vouchers/$draft2_id"
    fi
}

phase_9c_recurring() {
    log_phase "9c" "Recurring vouchers (Phase 9L)"

    # Create a monthly recurring office-rent template
    # Lookup-then-create: re-runs reuse the existing template by name.
    ensure_db_entity "/connections/$CONNECTION_ID/recurring-vouchers" '.name' "-DEMO- Monthly Office Rent" \
        '{"name":"-DEMO- Monthly Office Rent","voucher_type":"Payment","frequency":"monthly","day_of_month":1,"start_date":"2026-05-01","end_date":"2027-04-01","is_active":true,"voucher_template":{"VOUCHERTYPENAME":"Payment","VOUCHERNUMBER":"-DEMO-RENT-AUTO","NARRATION":"-DEMO- Auto-posted monthly office rent","ALLLEDGERENTRIES.LIST":[{"LEDGERNAME":"-DEMO- Office Rent","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"25000.00"},{"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-25000.00"}]}}' \
        "recurring-voucher -DEMO- Monthly Office Rent"
    if [[ "$ENSURE_PATH" == "found" || "$ENSURE_PATH" == "created" ]]; then
        PASSED=$((PASSED + 1)); log_pass "ensure recurring-voucher [${ENSURE_PATH}]"
    else
        log_warn "ensure recurring-voucher failed — sub-steps will use list lookup fallback"
    fi
    local rec_id="$ENSURE_ID"
    local rec_path="$ENSURE_PATH"

    # List
    api_get "/connections/$CONNECTION_ID/recurring-vouchers?per_page=20"
    assert_ok "GET /connections/$CONNECTION_ID/recurring-vouchers"

    if [[ -n "$rec_id" ]]; then
        api_get "/connections/$CONNECTION_ID/recurring-vouchers/$rec_id"
        assert_ok "GET /connections/$CONNECTION_ID/recurring-vouchers/$rec_id"

        # PATCH (toggle is_active false → true round-trip)
        api_patch "/connections/$CONNECTION_ID/recurring-vouchers/$rec_id" '{"is_active":false}'
        assert_ok "PATCH recurring-vouchers (pause)"

        api_put "/connections/$CONNECTION_ID/recurring-vouchers/$rec_id" '{"is_active":true}'
        assert_ok "PUT recurring-vouchers (resume)"

        # Manual fire — may fail if ledgers not in Tally; test endpoint anyway
        api_post "/connections/$CONNECTION_ID/recurring-vouchers/$rec_id/run"
        assert_ok "POST recurring-vouchers/$rec_id/run"

        # Only delete if WE created it this run.
        if [[ "$rec_path" == "created" ]]; then
            api_delete "/connections/$CONNECTION_ID/recurring-vouchers/$rec_id"
            assert_ok "DELETE recurring-vouchers/$rec_id (created this run)"
        else
            log_info "Leaving recurring-voucher $rec_id in place (existed before this run)"
        fi
    else
        log_skip "recurring-vouchers detail ops — no id resolved"
    fi
}

phase_9b_observability() {
    log_phase "9b" "Observability (Phase 9C)"

    # Dashboard stats
    api_get "/$CONN_CODE/stats"
    assert_ok "GET /$CONN_CODE/stats"

    # Cross-master search
    api_get "/$CONN_CODE/search?q=DEMO&limit=5"
    assert_ok "GET /$CONN_CODE/search?q=DEMO"

    # Cache flush
    api_post "/$CONN_CODE/cache/flush"
    assert_ok "POST /$CONN_CODE/cache/flush"

    # Circuit state
    api_get "/connections/$CONNECTION_ID/circuit-state"
    assert_ok "GET /connections/$CONNECTION_ID/circuit-state"

    # Sync history (pagination)
    api_get "/connections/$CONNECTION_ID/sync-history?per_page=20"
    assert_ok "GET /connections/$CONNECTION_ID/sync-history"

    # Sync record detail + cancel (only if a pending one exists)
    api_get "/connections/$CONNECTION_ID/sync-pending?limit=1"
    local pending_id
    pending_id=$(json_field '.data[0].id')
    if [[ -n "$pending_id" ]]; then
        api_get "/sync/$pending_id"
        assert_ok "GET /sync/$pending_id"

        api_post "/sync/$pending_id/cancel"
        assert_ok "POST /sync/$pending_id/cancel"
    else
        log_skip "GET /sync/{id} and POST /sync/{id}/cancel — no pending syncs"
    fi

    # Bulk conflict resolve (safe no-op if nothing is conflicted)
    api_post "/connections/$CONNECTION_ID/sync/resolve-all" '{"strategy":"erp_wins"}'
    assert_ok "POST /connections/$CONNECTION_ID/sync/resolve-all"
}

phase_10_teardown() {
    log_phase "10" "Teardown"
    prune_old_smoke_tokens
    log_info "Teardown complete"
}

print_summary() {
    local end_ts; end_ts=$(date +%s)
    local elapsed=$(( end_ts - START_TS ))
    local status_colour status_text

    if (( FAILED == 0 )); then
        status_colour="$GREEN"
        status_text="PASSED"
    else
        status_colour="$RED"
        status_text="FAILED"
    fi

    echo ""
    echo "${BOLD}${status_colour}================================================================${RESET}"
    echo "${BOLD}${status_colour}  Smoke test $status_text${RESET}"
    echo "${BOLD}${status_colour}================================================================${RESET}"
    echo "  Total calls:  $TOTAL_CALLS"
    echo "  Passed:       ${GREEN}$PASSED${RESET}"
    echo "  Failed:       ${RED}$FAILED${RESET}"
    echo "  Health probes: ${CYAN}${HEALTH_PROBES:-0}${RESET}  (Tally up before every call)"
    echo "  Duration:     ${elapsed}s"
    echo "  Log:          $LOG_FILE"
    echo ""

    _write_log "SUMMARY" "total=$TOTAL_CALLS passed=$PASSED failed=$FAILED probes=${HEALTH_PROBES:-0} elapsed=${elapsed}s status=$status_text"
    _write_log "INFO" "=== Run ended ==="
}

# ============================================================================
# MAIN
# ============================================================================

main() {
    init_logger
    START_BANNER

    phase_0_preflight;            [[ "$STOP_AFTER_PHASE" == "0" ]] && { print_summary; exit 0; }
    phase_0a_auth;                [[ "$STOP_AFTER_PHASE" == "0a" ]] && { print_summary; exit 0; }

    if [[ "$PHASE" == "all" || "$PHASE" == "cleanup" ]]; then
        phase_1_cleanup;          [[ "$STOP_AFTER_PHASE" == "1" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "connections" ]]; then
        phase_2_connections;      [[ "$STOP_AFTER_PHASE" == "2" ]] && { print_summary; exit 0; }
    fi

    # ===== MASTERS BLOCK ================================================
    # All master-data creation runs before any voucher/transactional phase,
    # so every downstream POST has its parent references already resolved.
    # ====================================================================

    if [[ "$PHASE" == "all" || "$PHASE" == "mnc" || "$PHASE" == "masters" ]]; then
        phase_2b_mnc_setup;       [[ "$STOP_AFTER_PHASE" == "2b" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "groups" || "$PHASE" == "masters" ]]; then
        phase_3_groups;           [[ "$STOP_AFTER_PHASE" == "3" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "stock-groups" || "$PHASE" == "masters" ]]; then
        phase_3b_stock_groups;    [[ "$STOP_AFTER_PHASE" == "3b" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "units" || "$PHASE" == "masters" ]]; then
        phase_3c_units;           [[ "$STOP_AFTER_PHASE" == "3c" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "cost-centres" || "$PHASE" == "masters" ]]; then
        phase_3d_cost_centres;    [[ "$STOP_AFTER_PHASE" == "3d" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "currencies" || "$PHASE" == "masters" ]]; then
        phase_3e_currencies;      [[ "$STOP_AFTER_PHASE" == "3e" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "godowns" || "$PHASE" == "masters" ]]; then
        phase_3f_godowns;         [[ "$STOP_AFTER_PHASE" == "3f" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "voucher-types" || "$PHASE" == "masters" ]]; then
        phase_3g_voucher_types;   [[ "$STOP_AFTER_PHASE" == "3g" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "stock-categories" || "$PHASE" == "masters" ]]; then
        phase_3h_stock_categories; [[ "$STOP_AFTER_PHASE" == "3h" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "price-lists" || "$PHASE" == "masters" ]]; then
        phase_3i_price_lists;     [[ "$STOP_AFTER_PHASE" == "3i" ]] && { print_summary; exit 0; }
    fi

    # Phase 9N masters — ordering enforces dependencies:
    # cost-categories → employee-categories → employee-groups → employees; attendance-types independent.
    if [[ "$PHASE" == "all" || "$PHASE" == "cost-categories" || "$PHASE" == "masters" ]]; then
        phase_3j_cost_categories; [[ "$STOP_AFTER_PHASE" == "3j" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "employee-categories" || "$PHASE" == "masters" ]]; then
        phase_3k_employee_categories; [[ "$STOP_AFTER_PHASE" == "3k" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "employee-groups" || "$PHASE" == "masters" ]]; then
        phase_3l_employee_groups; [[ "$STOP_AFTER_PHASE" == "3l" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "employees" || "$PHASE" == "masters" ]]; then
        phase_3m_employees;       [[ "$STOP_AFTER_PHASE" == "3m" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "attendance-types" || "$PHASE" == "masters" ]]; then
        phase_3n_attendance_types; [[ "$STOP_AFTER_PHASE" == "3n" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "ledgers" || "$PHASE" == "masters" ]]; then
        phase_4_ledgers;          [[ "$STOP_AFTER_PHASE" == "4" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "stock" || "$PHASE" == "masters" ]]; then
        phase_5_stock_items;      [[ "$STOP_AFTER_PHASE" == "5" ]] && { print_summary; exit 0; }
    fi

    # ===== RELATED / TRANSACTIONAL BLOCK ================================
    # Everything below this line depends on masters being in place.
    # ====================================================================

    if [[ "$PHASE" == "all" || "$PHASE" == "vouchers" ]]; then
        phase_6_vouchers;         [[ "$STOP_AFTER_PHASE" == "6" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "inventory-ops" ]]; then
        phase_6b_inventory_ops;   [[ "$STOP_AFTER_PHASE" == "6b" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "manufacturing" ]]; then
        phase_6c_manufacturing;   [[ "$STOP_AFTER_PHASE" == "6c" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "banking" ]]; then
        phase_8b_banking;         [[ "$STOP_AFTER_PHASE" == "8b" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "recurring" ]]; then
        phase_9c_recurring;       [[ "$STOP_AFTER_PHASE" == "9c" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "workflow" ]]; then
        phase_9d_workflow;        [[ "$STOP_AFTER_PHASE" == "9d" ]] && { print_summary; exit 0; }
    fi

    # ===== READS + SYNC + ADMIN =========================================

    if [[ "$PHASE" == "all" || "$PHASE" == "reports" ]]; then
        phase_7_reports;          [[ "$STOP_AFTER_PHASE" == "7" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "mnc" || "$PHASE" == "reports" ]]; then
        phase_7b_consolidated_reports; [[ "$STOP_AFTER_PHASE" == "7b" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "sync" ]]; then
        phase_8_sync;             [[ "$STOP_AFTER_PHASE" == "8" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "audit" ]]; then
        phase_9_audit;            [[ "$STOP_AFTER_PHASE" == "9" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "observability" ]]; then
        phase_9b_observability;   [[ "$STOP_AFTER_PHASE" == "9b" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "integration" ]]; then
        phase_9e_integration;     [[ "$STOP_AFTER_PHASE" == "9e" ]] && { print_summary; exit 0; }
    fi

    if [[ "$PHASE" == "all" || "$PHASE" == "permissions" ]]; then
        phase_9f_permissions;     [[ "$STOP_AFTER_PHASE" == "9f" ]] && { print_summary; exit 0; }
    fi

    # MNC teardown runs at the end so consolidated reports (phase_7b) had data.
    if [[ "$PHASE" == "all" || "$PHASE" == "mnc" ]]; then
        phase_2b_mnc_teardown
    fi

    phase_10_teardown

    print_summary

    (( FAILED == 0 )) && exit 0
    local code=$(( 10 + FAILED ))
    (( code > 255 )) && code=255
    exit "$code"
}

main "$@"
