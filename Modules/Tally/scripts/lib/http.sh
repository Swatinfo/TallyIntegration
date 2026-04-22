#!/usr/bin/env bash
# Modules/Tally/scripts/lib/http.sh — curl wrappers that log every call and track counters.
#
# Globals populated for callers:
#   HTTP_CODE  - last HTTP status code
#   HTTP_BODY  - last response body (raw)
#   TOTAL_CALLS, PASSED, FAILED — running counters.

LARAVEL_BASE_URL="${LARAVEL_BASE_URL:-https://tallyintegration.test}"
TALLY_API_BASE="${TALLY_API_BASE:-$LARAVEL_BASE_URL/api/tally}"
REQUEST_TIMEOUT="${REQUEST_TIMEOUT:-60}"
HEALTH_PROBE_TIMEOUT="${HEALTH_PROBE_TIMEOUT:-3}"
# Set CURL_INSECURE=1 if your local .test domain uses a self-signed cert that
# curl doesn't trust (e.g. some Herd / Valet installs on Windows). Adds -k.
CURL_INSECURE="${CURL_INSECURE:-0}"

_curl_insecure_flag() {
    (( CURL_INSECURE == 1 )) && echo "-k"
}

TOTAL_CALLS=0
PASSED=0
FAILED=0
HEALTH_PROBES=0
HTTP_CODE=""
HTTP_BODY=""

# Probe Tally directly (NOT through Laravel) to confirm it's still up before
# each API call. Silent on success, fatal abort on failure — we'd rather stop
# with a clean message than cascade errors through the rest of the suite.
# Skipped during --dry-run. CONN_HOST / CONN_PORT come from fixtures.sh.
_health_probe_tally() {
    (( DRY_RUN == 1 )) && return 0

    HEALTH_PROBES=$((HEALTH_PROBES + 1))

    if curl --silent --fail --max-time "$HEALTH_PROBE_TIMEOUT" \
        "http://${CONN_HOST:-localhost}:${CONN_PORT:-9000}" >/dev/null 2>&1; then
        return 0
    fi

    log_fatal "Tally at http://${CONN_HOST:-localhost}:${CONN_PORT:-9000} became unreachable (pre-call health probe failed after ${HEALTH_PROBES} checks)."
    echo "${YELLOW}The Tally server stopped responding during the smoke test.${RESET}"
    echo "  • Did TallyPrime close, or did the company get unloaded?"
    echo "  • Check Windows Firewall / network if Tally is on another machine."
    echo "  • See Modules/Tally/docs/TROUBLESHOOTING.md § 1 and § 2."
    _emergency_summary 2>/dev/null || true
    exit 11
}

_curl() {
    local method="$1"; local path="$2"; local body="${3:-}"
    local url

    if [[ "$path" == http://* || "$path" == https://* ]]; then
        url="$path"
    else
        url="${TALLY_API_BASE}${path}"
    fi

    # Defensive: verify Tally is still up BEFORE every call. Aborts the run on failure.
    _health_probe_tally

    TOTAL_CALLS=$((TOTAL_CALLS + 1))
    log_call "$method" "$url"
    [[ -n "$body" ]] && log_request "$body"

    if (( DRY_RUN == 1 )); then
        HTTP_CODE="000"
        HTTP_BODY='{"success":true,"data":null,"message":"(dry-run)"}'
        log_response "$HTTP_CODE" "(dry-run, no request sent)"
        return 0
    fi

    local auth_header=""
    [[ -n "${TALLY_API_TOKEN:-}" ]] && auth_header="Authorization: Bearer $TALLY_API_TOKEN"

    local tmp
    tmp=$(mktemp 2>/dev/null || echo "/tmp/tally-smoke-$$-${TOTAL_CALLS}")

    local -a curl_args=(
        --silent
        --show-error
        --max-time "$REQUEST_TIMEOUT"
        -X "$method"
        -w '%{http_code}'
        -o "$tmp"
        -H 'Accept: application/json'
    )
    (( CURL_INSECURE == 1 )) && curl_args+=(-k)
    [[ -n "$auth_header" ]] && curl_args+=(-H "$auth_header")

    if [[ -n "$body" ]]; then
        curl_args+=(-H 'Content-Type: application/json' --data "$body")
    fi

    HTTP_CODE=$(curl "${curl_args[@]}" "$url" 2>&1) || {
        HTTP_CODE="000"
        HTTP_BODY=$(cat "$tmp" 2>/dev/null || echo "")
        log_response "$HTTP_CODE" "curl error: $HTTP_BODY"
        rm -f "$tmp"
        return 1
    }

    HTTP_BODY=$(cat "$tmp")
    rm -f "$tmp"

    log_response "$HTTP_CODE" "$HTTP_BODY"
    return 0
}

api_get()    { _curl "GET"    "$1" ""; }
api_post()   { _curl "POST"   "$1" "${2:-}"; }
api_put()    { _curl "PUT"    "$1" "${2:-}"; }
api_patch()  { _curl "PATCH"  "$1" "${2:-}"; }
api_delete() { _curl "DELETE" "$1" "${2:-}"; }

# Download a report as CSV to a target path. Follows Accept: text/csv.
api_download_csv() {
    local path="$1"; local target="$2"
    local url="${TALLY_API_BASE}${path}"

    _health_probe_tally

    TOTAL_CALLS=$((TOTAL_CALLS + 1))
    log_call "GET(csv)" "$url -> $target"

    if (( DRY_RUN == 1 )); then
        log_response "000" "(dry-run)"
        return 0
    fi

    curl --silent --show-error --max-time "$REQUEST_TIMEOUT" \
        $(_curl_insecure_flag) \
        -H "Accept: text/csv" \
        -H "Authorization: Bearer $TALLY_API_TOKEN" \
        -o "$target" "$url" || return 1

    HTTP_CODE="200"
    HTTP_BODY="(CSV saved to $target, $(wc -c < "$target" 2>/dev/null || echo ?) bytes)"
    log_response "$HTTP_CODE" "$HTTP_BODY"
    return 0
}

# ----------------------------------------------------------------------------
# Idempotent "ensure exists" helpers.
#
# Smoke runs are re-runnable. Every entity must be checked for existence first
# so we never skip a downstream sub-step when a record was created by an
# earlier run. On hit, we reuse the existing id; on miss, we create.
#
# Globals on return:
#   ENSURE_ID     — id of the existing or newly-created row (empty if both failed)
#   ENSURE_PATH   — "found" | "created" | "failed"
# ----------------------------------------------------------------------------

# Quick pass/fail existence check for a Tally master. Returns 0 if name exists
# (HTTP 200), non-zero otherwise. Silent — discards body/code afterwards.
# Usage: if master_exists "/$CONN_CODE/currencies" "USD"; then ...; fi
master_exists() {
    local endpoint="$1"; local name="$2"
    api_get "${endpoint}/$(_url_encode_local "$name")" >/dev/null 2>&1
    [[ "$HTTP_CODE" == "200" ]]
}

# Cache of existence results so one smoke run doesn't re-ask Tally for the same
# name ten times. Key = "endpoint|name", value = 1 (exists) or 0 (missing).
declare -A TALLY_MASTER_EXISTS_CACHE=()

# Cached wrapper around master_exists — same signature, memoises per-run.
cached_master_exists() {
    local endpoint="$1"; local name="$2"
    local key="${endpoint}|${name}"
    if [[ -n "${TALLY_MASTER_EXISTS_CACHE[$key]:-}" ]]; then
        [[ "${TALLY_MASTER_EXISTS_CACHE[$key]}" == "1" ]]
        return $?
    fi
    if master_exists "$endpoint" "$name"; then
        TALLY_MASTER_EXISTS_CACHE[$key]=1
        return 0
    fi
    TALLY_MASTER_EXISTS_CACHE[$key]=0
    return 1
}

# Strip a JSON field from a payload if the value references a master that does
# NOT exist in Tally. Used to keep fixtures portable across Tally companies
# with different F11 feature flags (e.g. multi-currency off → strip CURRENCYNAME).
#
# Usage: strip_if_missing <json> <field> <endpoint-to-check>
#   strip_if_missing "$ledger_json" "CURRENCYNAME" "/$CONN_CODE/currencies"
#
# Echoes the possibly-modified JSON. Logs a warning when a field is stripped.
strip_if_missing() {
    local payload="$1"; local field="$2"; local endpoint="$3"
    local value
    value=$(echo "$payload" | jq -r --arg f "$field" '.[$f] // empty' 2>/dev/null)

    if [[ -z "$value" ]]; then
        echo "$payload"
        return 0
    fi

    if cached_master_exists "$endpoint" "$value"; then
        echo "$payload"
        return 0
    fi

    log_warn "Stripped ${field}=${value} — not present in Tally; Tally would reject the import otherwise"
    echo "$payload" | jq --arg f "$field" 'del(.[$f])'
}

# Look up a Tally master by name. Sets:
#   LOOKUP_FOUND=1  on HTTP 200
#   LOOKUP_FOUND=0  on HTTP 404
#   LOOKUP_FOUND=-1 on any other code (bubbled to caller)
# Usage: lookup_master_by_name "/$CONN_CODE/ledgers" "-DEMO- Acme Corp"
lookup_master_by_name() {
    local endpoint="$1"; local name="$2"
    api_get "${endpoint}/$(_url_encode_local "$name")"
    case "$HTTP_CODE" in
        200) LOOKUP_FOUND=1 ;;
        404) LOOKUP_FOUND=0 ;;
        *)   LOOKUP_FOUND=-1 ;;
    esac
}

# Look up a row id by filtering a list endpoint on a JSON field value.
# Usage: lookup_id_by_field "/organizations" ".code" "SWATDEMO"
# Sets ENSURE_ID to the matched id (empty if no match).
lookup_id_by_field() {
    local list_endpoint="$1"; local field="$2"; local value="$3"
    ENSURE_ID=""
    api_get "$list_endpoint" >/dev/null 2>&1
    if [[ "$HTTP_CODE" != "200" ]]; then
        return 1
    fi
    if command -v jq >/dev/null 2>&1; then
        ENSURE_ID=$(echo "$HTTP_BODY" | jq -r --arg v "$value" \
            "[.data[]? | select(${field} == \$v)] | first | .id // empty" 2>/dev/null)
    else
        ENSURE_ID=$(php -r '
            $body = file_get_contents("php://stdin");
            $json = json_decode($body, true);
            $field = trim($argv[1] ?? "", ". ");
            $value = $argv[2] ?? "";
            foreach ($json["data"] ?? [] as $row) {
                if (($row[$field] ?? null) === $value) {
                    echo $row["id"] ?? "";
                    return;
                }
            }
        ' "$field" "$value" <<< "$HTTP_BODY" 2>/dev/null)
    fi
    [[ -n "$ENSURE_ID" ]]
}

# Ensure a DB-backed (non-Tally) entity exists by code/name. Reuses on hit.
#   $1 list endpoint (e.g. /organizations)
#   $2 lookup field (e.g. .code)
#   $3 lookup value (e.g. SWATDEMO)
#   $4 create payload (json)
#   $5 label for logging
# Sets ENSURE_ID + ENSURE_PATH.
ensure_db_entity() {
    local list_endpoint="$1"; local field="$2"; local value="$3"; local payload="$4"; local label="$5"
    ENSURE_ID=""; ENSURE_PATH="failed"

    if lookup_id_by_field "$list_endpoint" "$field" "$value"; then
        ENSURE_PATH="found"
        log_info "$label — reusing existing id=$ENSURE_ID (${field}=${value})"
        return 0
    fi

    api_post "$list_endpoint" "$payload"
    if [[ "$HTTP_CODE" =~ ^(200|201)$ ]]; then
        ENSURE_ID=$(json_field '.data.id')
        ENSURE_PATH="created"
        log_info "$label — created id=$ENSURE_ID (${field}=${value})"
        return 0
    fi

    log_warn "$label — both lookup and create failed (HTTP $HTTP_CODE) — re-trying lookup"
    if lookup_id_by_field "$list_endpoint" "$field" "$value"; then
        ENSURE_PATH="found"
        log_info "$label — reusing id=$ENSURE_ID after retry"
        return 0
    fi
    return 1
}

# Ensure a Tally master exists by name. Reuses on hit (no create).
#   $1 endpoint prefix (e.g. /$CONN_CODE/ledgers)
#   $2 master name
#   $3 create payload (json)
#   $4 label
#   $5 optional flag (1 = skip POST when lookup misses, since the master needs
#                    a Tally feature flag enabled and POSTing has been observed
#                    to CRASH TallyPrime when the flag is off — not just fail)
# Sets ENSURE_PATH ("found" | "created" | "skipped" | "failed").
ensure_tally_master() {
    local endpoint="$1"; local name="$2"; local payload="$3"; local label="$4"
    local optional="${5:-0}"
    ENSURE_PATH="failed"

    lookup_master_by_name "$endpoint" "$name"
    if (( LOOKUP_FOUND == 1 )); then
        ENSURE_PATH="found"
        log_info "$label — already in Tally, skipping create ($name)"
        return 0
    fi

    if (( LOOKUP_FOUND == -1 )); then
        # Lookup itself failed for an unexpected reason — log and try create anyway.
        log_warn "$label — lookup returned HTTP $HTTP_CODE; attempting create"
    fi

    # Optional masters depend on TallyPrime feature flags (Multi-Currency, Cost
    # Centres, Multiple Godowns, etc.). If the master isn't present OR the
    # lookup itself failed/timed out, do NOT attempt to POST — TallyPrime can
    # hard-crash on the import when the required F11 feature is off, and a
    # 503/timeout often indicates Tally already crashed (or is about to) on
    # this master type entirely. Reproduced 2026-04-19 on Price Level (lookup
    # hung 30s, then create attempt 503'd, then health probe died).
    if (( optional == 1 )) && (( LOOKUP_FOUND != 1 )); then
        ENSURE_PATH="skipped"
        if (( LOOKUP_FOUND == 0 )); then
            log_warn "$label — optional master not in Tally; skipping POST to avoid crash. Enable the relevant F11 feature to import."
        else
            log_warn "$label — optional master lookup returned HTTP $HTTP_CODE; skipping POST to avoid further Tally instability."
        fi
        return 0
    fi

    api_post "$endpoint" "$payload"
    local create_body="$HTTP_BODY"
    local create_code="$HTTP_CODE"
    if [[ "$create_code" =~ ^(200|201)$ ]] && ! _is_already_exists "$create_body"; then
        local success
        success=$(echo "$create_body" | _json_extract_field '.success')
        if [[ -z "$success" || "$success" == "true" ]]; then
            # Verify the create actually landed in Tally — masters can report
            # success:true with errors:0 but still not be queryable when the
            # parent group doesn't exist or the cache returns the prior list.
            lookup_master_by_name "$endpoint" "$name"
            if (( LOOKUP_FOUND == 1 )); then
                ENSURE_PATH="created"
                log_info "$label — created and verified in Tally ($name)"
                return 0
            fi
            log_warn "$label — create returned success but post-create lookup MISSED ($name). Likely missing reference master in payload — check parent name."
            log_warn "$label — create response was: $(echo "$create_body" | head -c 400)"
            return 1
        fi
    fi

    if _is_already_exists "$create_body"; then
        ENSURE_PATH="found"
        log_info "$label — Tally reports already exists ($name)"
        return 0
    fi

    log_warn "$label — create failed for $name (HTTP $create_code) — body: $(echo "$create_body" | head -c 400)"
    return 1
}

# Lightweight wrapper around json_field so we can extract from a passed body.
_json_extract_field() {
    local expr="$1"
    if command -v jq >/dev/null 2>&1; then
        jq -r "$expr // empty" 2>/dev/null
    else
        php -r '
            $body = file_get_contents("php://stdin");
            $json = json_decode($body, true);
            $expr = trim($argv[1] ?? "", ". ");
            $tokens = preg_split("/\.|\[|\]/", $expr, -1, PREG_SPLIT_NO_EMPTY);
            $cur = $json;
            foreach ($tokens as $t) {
                $key = is_numeric($t) ? (int) $t : $t;
                if (! is_array($cur) || ! array_key_exists($key, $cur)) { exit; }
                $cur = $cur[$key];
            }
            if (is_bool($cur)) { echo $cur ? "true" : "false"; }
            elseif (is_scalar($cur)) { echo $cur; }
        ' "$expr" 2>/dev/null
    fi
}

# Assert that every parent name in the given list resolves on the given endpoint.
# Use this BEFORE bulk-creating children (ledgers reference groups, stock-items
# reference stock-groups + units, vouchers reference ledgers etc.).
#   $1 endpoint prefix (e.g. /$CONN_CODE/groups)
#   $2 label for logging
#   $@ remaining args = parent names to verify
# Returns 0 if all present, 1 if any missing (lists missing ones in log).
verify_parents_exist() {
    local endpoint="$1"; shift
    local label="$1"; shift
    local missing=()
    for name in "$@"; do
        lookup_master_by_name "$endpoint" "$name"
        if (( LOOKUP_FOUND != 1 )); then
            missing+=("$name")
        fi
    done
    if (( ${#missing[@]} > 0 )); then
        log_warn "$label — ${#missing[@]} parent master(s) missing on $endpoint: ${missing[*]}"
        log_warn "$label — child create will fail with 'reference master missing' until these exist."
        return 1
    fi
    return 0
}

# Inline URL-encoder (mirrors _urlencode in tally-smoke-test.sh so libs are
# self-sufficient without forcing the main script to source-order things).
_url_encode_local() {
    local s="$1"
    python - "$s" <<'PY' 2>/dev/null || printf "%s" "$s"
import sys, urllib.parse
print(urllib.parse.quote(sys.argv[1], safe=""))
PY
}

# Extract a field from an arbitrary JSON string via jq if available, else PHP.
# Usage: json_extract '.NAME' '$json_string'
json_extract() {
    local expr="$1"; local body="$2"
    if command -v jq >/dev/null 2>&1; then
        echo "$body" | jq -r "$expr // empty" 2>/dev/null

        return
    fi
    php -r '
        $body = file_get_contents("php://stdin");
        $json = json_decode($body, true);
        if (! is_array($json)) { exit; }
        $expr = trim($argv[1] ?? "", ". ");
        $tokens = preg_split("/\.|\[|\]/", $expr, -1, PREG_SPLIT_NO_EMPTY);
        $cur = $json;
        foreach ($tokens as $t) {
            $key = is_numeric($t) ? (int) $t : $t;
            if (! is_array($cur) || ! array_key_exists($key, $cur)) { exit; }
            $cur = $cur[$key];
        }
        if (is_bool($cur)) { echo $cur ? "true" : "false"; }
        elseif (is_scalar($cur)) { echo $cur; }
    ' "$expr" <<< "$body" 2>/dev/null
}

# Extract a field from HTTP_BODY using jq if available, else fall back to PHP.
# Supports simple dotted paths like .data.id or .data[0].id.
json_field() {
    local expr="$1"

    if command -v jq >/dev/null 2>&1; then
        echo "$HTTP_BODY" | jq -r "$expr // empty" 2>/dev/null

        return
    fi

    # PHP fallback — supports .a.b, .a[0].b, but NOT filter expressions.
    php -r '
        $body = file_get_contents("php://stdin");
        $json = json_decode($body, true);
        if (! is_array($json)) { exit; }
        $expr = trim($argv[1] ?? "", ". ");
        $tokens = preg_split("/\.|\[|\]/", $expr, -1, PREG_SPLIT_NO_EMPTY);
        $cur = $json;
        foreach ($tokens as $t) {
            $key = is_numeric($t) ? (int) $t : $t;
            if (! is_array($cur) || ! array_key_exists($key, $cur)) { exit; }
            $cur = $cur[$key];
        }
        if (is_bool($cur)) { echo $cur ? "true" : "false"; }
        elseif (is_scalar($cur)) { echo $cur; }
    ' "$expr" <<< "$HTTP_BODY" 2>/dev/null
}
