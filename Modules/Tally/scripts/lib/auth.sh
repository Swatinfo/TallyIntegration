#!/usr/bin/env bash
# Modules/Tally/scripts/lib/auth.sh — Sanctum token bootstrap via php artisan tinker
#
# Idempotently creates a dedicated smoke-test user, grants every TallyPermission,
# and mints a fresh token per run. The token's plaintext is echoed as the
# last line of tinker output and extracted via a regex match on Sanctum's
# "<id>|<hash>" format.

SMOKE_USER_EMAIL="${SMOKE_USER_EMAIL:-smoke-test@local}"
SMOKE_USER_NAME="${SMOKE_USER_NAME:-Smoke Test}"

bootstrap_user_and_token() {
    log_phase "0a" "Auth bootstrap"

    if ! command -v php >/dev/null 2>&1; then
        log_fatal "php command not found on PATH"
        exit 2
    fi

    local run_tag
    run_tag="smoke-test-$(date +'%Y%m%d%H%M%S')"

    local php_code
    php_code=$(cat <<PHP
use App\Models\User;
use Modules\Tally\Enums\TallyPermission;

\$user = User::firstOrCreate(
    ['email' => '${SMOKE_USER_EMAIL}'],
    [
        'name'     => '${SMOKE_USER_NAME}',
        'password' => bcrypt(bin2hex(random_bytes(16))),
    ]
);

\$user->tally_permissions = array_column(TallyPermission::cases(), 'value'); // includes approve_vouchers from Phase 9J
\$user->save();

echo \$user->createToken('${run_tag}')->plainTextToken;
PHP
)

    log_info "Bootstrapping user ${SMOKE_USER_EMAIL} and token ${run_tag}"

    local tinker_out
    tinker_out=$(cd "$PROJECT_ROOT" && php artisan tinker --execute="$php_code" 2>&1) || {
        log_fatal "tinker failed: $tinker_out"
        exit 2
    }

    local token
    token=$(echo "$tinker_out" | grep -oE '[0-9]+\|[A-Za-z0-9]+' | tail -1)

    if [[ -z "$token" ]]; then
        log_fatal "Could not parse Sanctum token from tinker output"
        log_error "Output was: $tinker_out"
        exit 2
    fi

    TALLY_API_TOKEN="$token"
    export TALLY_API_TOKEN

    log_pass "Created/verified user ${SMOKE_USER_EMAIL}"
    log_pass "Granted 6 tally_permissions"
    log_pass "Minted token ${run_tag} (length=${#token})"
}

bootstrap_restricted_user_and_token() {
    # Creates (or re-uses) a second user with ZERO tally_permissions.
    # Used to verify that CheckTallyPermission correctly returns 403.
    local php_code
    php_code=$(cat <<PHP
use App\Models\User;

\$user = User::firstOrCreate(
    ['email' => 'smoke-restricted@local'],
    ['name' => 'Smoke Restricted', 'password' => bcrypt(bin2hex(random_bytes(16)))]
);

\$user->tally_permissions = [];  // Empty on purpose.
\$user->save();

echo \$user->createToken('smoke-restricted-' . now()->format('YmdHis'))->plainTextToken;
PHP
)

    local tinker_out
    tinker_out=$(cd "$PROJECT_ROOT" && php artisan tinker --execute="$php_code" 2>&1) || {
        log_warn "Could not bootstrap restricted user"

        return 1
    }

    local token
    token=$(echo "$tinker_out" | grep -oE '[0-9]+\|[A-Za-z0-9]+' | tail -1)
    [[ -n "$token" ]] && echo "$token"
}

prune_old_smoke_tokens() {
    (( PRUNE_TOKENS == 1 )) || return 0

    local php_code
    php_code=$(cat <<PHP
use Laravel\Sanctum\PersonalAccessToken;

\$deleted = PersonalAccessToken::where('name', 'like', 'smoke-test-%')
    ->where('created_at', '<', now()->subDays(7))
    ->delete();

echo 'PRUNED:' . \$deleted;
PHP
)

    local out
    out=$(cd "$PROJECT_ROOT" && php artisan tinker --execute="$php_code" 2>&1) || return 0
    local n
    n=$(echo "$out" | grep -oE 'PRUNED:[0-9]+' | tail -1 | cut -d: -f2)
    [[ -n "$n" ]] && log_info "Pruned $n old smoke-test token(s) (>7 days old)"
}
