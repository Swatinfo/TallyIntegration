#!/usr/bin/env bash
# Modules/Tally/scripts/lib/assert.sh — assertion helpers respecting FAIL_FAST.
#
# Every assertion increments PASSED or FAILED and, on failure with FAIL_FAST=1,
# aborts the run with exit code 10.
#
# Uniqueness conflicts ("already exists", "has already been taken", duplicate
# key, etc.) are ALWAYS tolerated — the intent of the smoke test is to verify
# the endpoint works, not to force a blank slate. If you want a blank slate,
# use --clean.

# Patterns that indicate "this already exists" — treated as PASS, not failure.
# The record is left alone — the endpoint works, that's enough.
ALREADY_EXISTS_PATTERNS='already (been taken|exists)|duplicate|UNIQUE constraint|Integrity constraint violation|Duplicate entry'

# Patterns that indicate Tally rejected a payload because a referenced master
# (parent group, ledger, stock item, unit, godown, etc.) is missing. Surfaced
# loudly so it's obvious which dependency needs to exist first.
TALLY_MISSING_REF_PATTERNS='Could not find|reference (master|ledger|group) (missing|not found)|LINEERROR|Cannot find|does not exist'

assert_ok() {
    local label="$1"

    if [[ "$HTTP_CODE" =~ ^(200|201|202|204)$ ]]; then
        if [[ -z "$HTTP_BODY" ]] || [[ "$HTTP_BODY" == "(CSV saved"* ]]; then
            PASSED=$((PASSED + 1))
            log_pass "$label"
            return 0
        fi

        # For JSON responses, enforce success:true if the field exists.
        local success
        success=$(json_field '.success')
        if [[ -z "$success" || "$success" == "true" ]]; then
            PASSED=$((PASSED + 1))
            log_pass "$label"
            return 0
        fi

        # Tally returned success:false (LINEERROR etc.). Check for tolerable patterns.
        if _is_already_exists "$HTTP_BODY"; then
            PASSED=$((PASSED + 1))
            log_warn "$label — record already exists, skipped (not treated as failure)"
            return 0
        fi

        _handle_failure "$label" "success:false in body"
        return 1
    fi

    # Non-2xx — but still tolerate uniqueness errors (typically 422).
    if _is_already_exists "$HTTP_BODY"; then
        PASSED=$((PASSED + 1))
        log_warn "$label — HTTP $HTTP_CODE but record already exists (not treated as failure)"
        return 0
    fi

    _handle_failure "$label" "HTTP $HTTP_CODE"
    return 1
}

# Back-compat alias. Earlier callers passed a pattern — now ignored; always tolerates.
assert_ok_or_soft_fail() {
    local label="$1"
    assert_ok "$label"
}

_is_already_exists() {
    local body="$1"
    echo "$body" | grep -qiE "$ALREADY_EXISTS_PATTERNS"
}

# Tolerant assertion for optional masters: 200/201 = PASS, 404 = SKIP (counted
# as PASS), anything else = FAIL. Use for show/list-by-name calls in phases that
# depend on a TallyPrime feature flag (currencies, cost centres, godowns, stock
# categories, price lists). The 404 is the legitimate response when the feature
# is off and the create was intentionally skipped.
assert_ok_or_skip_404() {
    local label="$1"

    if [[ "$HTTP_CODE" == "404" ]]; then
        PASSED=$((PASSED + 1))
        log_pass "$label [skip — 404, optional master not present]"

        return 0
    fi

    assert_ok "$label"
}

# Assert an expected HTTP status code (e.g. for permission-denied probes).
# Use this when a failure response IS the pass condition.
assert_http_code() {
    local label="$1"; local expected="$2"

    if [[ "$HTTP_CODE" == "$expected" ]]; then
        PASSED=$((PASSED + 1))
        log_pass "$label (expected HTTP $expected)"

        return 0
    fi

    _handle_failure "$label" "expected HTTP $expected, got $HTTP_CODE"

    return 1
}

_handle_failure() {
    local label="$1"; local reason="$2"
    FAILED=$((FAILED + 1))
    log_fail "$label — $reason"

    # Always show a body excerpt (truncated) so the user doesn't have to tail logs.
    local excerpt
    excerpt=$(echo "${HTTP_BODY:-}" | tr -d '\n' | head -c 500)
    if [[ -n "$excerpt" ]]; then
        echo "    ${YELLOW}body:${RESET} $excerpt" >&2
    fi

    # Specifically call out Tally missing-master reference errors — they
    # mean a parent record was not actually created (or named differently).
    if echo "${HTTP_BODY:-}" | grep -qiE "$TALLY_MISSING_REF_PATTERNS"; then
        echo "    ${RED}HINT:${RESET} Tally rejected a referenced master." >&2
        echo "    Verify the parent group / ledger / unit name in the payload exists in Tally with EXACT case + whitespace." >&2
    fi

    # Silent EXCEPTIONS (no LINEERROR text) usually means the import depends on
    # a TallyPrime company feature that isn't enabled (Multi-Currency, Cost
    # Centres, Multiple Godowns, GST, etc.). Tally swallows these without a
    # specific message. Surface the likely cause loudly.
    if echo "${HTTP_BODY:-}" | grep -qE '"exceptions":[1-9]' \
       && echo "${HTTP_BODY:-}" | grep -qE '"line_error":null'; then
        echo "    ${RED}HINT:${RESET} Tally returned EXCEPTIONS but no LINEERROR text." >&2
        echo "    Most common cause: a required TallyPrime company feature is OFF." >&2
        echo "    Enable in TallyPrime: Gateway → F11 → Accounting / Inventory Features:" >&2
        echo "      • Currency import → enable 'Multi-Currency'" >&2
        echo "      • Cost Centre import → enable 'Cost Centres' / 'More than ONE Cost Category'" >&2
        echo "      • Godown import → enable 'Multiple Godowns/Locations'" >&2
        echo "      • Stock Category import → enable 'Stock Categories'" >&2
        echo "      • Price List import → enable 'Multiple Price Levels'" >&2
        echo "      • GST ledger import → enable 'GST' under Statutory & Taxation" >&2
    fi

    if (( FAIL_FAST == 1 )); then
        log_fatal "Stopping due to --fail-fast (default). Use --no-fail-fast to continue."
        _emergency_summary
        exit 10
    fi
}

_emergency_summary() {
    echo ""
    echo "${RED}${BOLD}========================================================${RESET}"
    echo "${RED}${BOLD}  Smoke test FAILED${RESET}"
    echo "${RED}${BOLD}========================================================${RESET}"
    echo "  Calls:   $TOTAL_CALLS"
    echo "  Passed:  ${GREEN}$PASSED${RESET}"
    echo "  Failed:  ${RED}$FAILED${RESET}"
    echo "  Log:     $LOG_FILE"
    echo ""
}
