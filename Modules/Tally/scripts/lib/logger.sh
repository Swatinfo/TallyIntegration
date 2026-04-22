#!/usr/bin/env bash
# Modules/Tally/scripts/lib/logger.sh — always-on file + terminal logger
#
# Writes every event to storage/logs/tally/tally-DD-MM-YYYY.log (one file per day, appended).
# Auto-creates the directory and file on first use. Bodies are truncated at MAX_BODY_BYTES.

LOG_DIR="${LOG_DIR:-$PROJECT_ROOT/storage/logs/tally}"
LOG_FILE=""
MAX_BODY_BYTES=8192

init_logger() {
    mkdir -p "$LOG_DIR" || {
        echo "FATAL: cannot create log directory: $LOG_DIR" >&2
        exit 1
    }
    local today
    today="$(date +'%d-%m-%Y')"
    LOG_FILE="$LOG_DIR/tally-$today.log"
    [[ -f "$LOG_FILE" ]] || touch "$LOG_FILE"
    log_info "=== Tally Smoke Test Run Started (PID=$$) ==="
    log_info "Script: $0"
    log_info "Log file: $LOG_FILE"
}

_ts() { date +'%Y-%m-%d %H:%M:%S.%3N' 2>/dev/null || date +'%Y-%m-%d %H:%M:%S'; }

_truncate_body() {
    local body="$1"
    local len=${#body}
    if (( len > MAX_BODY_BYTES )); then
        printf "%s… [truncated, total=%d bytes]" "${body:0:$MAX_BODY_BYTES}" "$len"
    else
        printf "%s" "$body"
    fi
}

_write_log() {
    local level="$1"; shift
    local msg="$*"
    printf "[%s] %-5s %s\n" "$(_ts)" "$level" "$msg" >> "$LOG_FILE"
}

log_info()  { _write_log "INFO"  "$*"; }
log_warn()  { _write_log "WARN"  "$*"; echo "  $(c_warn) ${YELLOW}$*${RESET}" >&2; }
log_error() { _write_log "ERROR" "$*"; echo "  $(c_fail) ${RED}$*${RESET}" >&2; }
log_fatal() { _write_log "FATAL" "$*"; echo "${RED}${BOLD}FATAL:${RESET} $*" >&2; }

log_phase() {
    local phase="$1"; local title="$2"
    _write_log "PHASE" "[$phase] $title"
    echo ""
    echo "${BOLD}${BLUE}[PHASE $phase]${RESET} ${BOLD}$title${RESET}"
}

log_call() {
    local method="$1"; local url="$2"
    _write_log "CALL"  "$method $url"
}

log_request() {
    local body="$1"
    [[ -z "$body" ]] && return
    _write_log ">>>"   "$(_truncate_body "$body")"
}

log_response() {
    local code="$1"; local body="$2"
    _write_log "<<<"   "HTTP $code  $(_truncate_body "$body")"
}

log_pass() {
    local msg="$1"
    _write_log "PASS"  "$msg"
    echo "  $(c_pass) ${GREY}$msg${RESET}"
}

log_fail() {
    local msg="$1"
    _write_log "FAIL"  "$msg"
    echo "  $(c_fail) ${RED}$msg${RESET}"
}

log_skip() {
    local msg="$1"
    _write_log "SKIP"  "$msg"
    echo "  $(c_warn) ${YELLOW}SKIP $msg${RESET}"
}
