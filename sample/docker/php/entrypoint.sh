#!/usr/bin/env bash
# Entrypoint for php and ws (cron and cli override it; all share the php-app
# image): validates the PHP limits, prepares log dirs, optionally waits for
# the DB, optionally migrates, then execs the requested command. Fails loud
# — no silent fallbacks.
set -euo pipefail

log() { echo "[entrypoint] $*"; }
die() { echo "[entrypoint] FATAL: $*" >&2; exit 1; }

# 1. PHP limits — read by ${VAR:-default} placeholders in 10-app.ini and the
#    FPM pool. An invalid memory_limit only warns and runs at PHP's 128M, so
#    it is checked here; unset leaves the file default. cron/cli skip this
#    script, so php warns about their value instead of failing on it.
is_memory_limit() { [[ "$1" =~ ^([1-9][0-9]*[KkMmGg]?|-1)$ ]]; }

if [[ -n "${PHP_MEMORY_LIMIT+set}" ]] && ! is_memory_limit "$PHP_MEMORY_LIMIT"; then
    die "PHP_MEMORY_LIMIT must be a size like 256M, or -1 (got '${PHP_MEMORY_LIMIT}')"
fi

if [[ "${1:-}" == "php-fpm" ]]; then
    if [[ -n "${PHP_CLI_MEMORY_LIMIT+set}" ]] && ! is_memory_limit "$PHP_CLI_MEMORY_LIMIT"; then
        log "WARNING: PHP_CLI_MEMORY_LIMIT='${PHP_CLI_MEMORY_LIMIT}' is not a size like 256M, or -1; ws refuses to start with it, cron/cli run at PHP's 128M"
    fi

    # Empty counts as invalid (FPM would refuse it too); defaults match www.conf.
    mode="${PHP_PM_MODE-dynamic}"
    [[ "$mode" == "dynamic" || "$mode" == "ondemand" ]] \
        || die "PHP_PM_MODE must be dynamic or ondemand (got '${mode}')"
    max_children="${PHP_PM_MAX_CHILDREN-20}"
    [[ "$max_children" =~ ^[1-9][0-9]*$ ]] \
        || die "PHP_PM_MAX_CHILDREN must be a positive integer (got '${max_children}')"

    min_spare=$(( max_children / 4 )); (( min_spare >= 1 )) || min_spare=1
    max_spare=$(( max_children / 2 )); (( max_spare >= min_spare )) || max_spare=$min_spare
    start_servers=$(( min_spare + (max_spare - min_spare) / 2 ))
    export PHP_PM_MIN_SPARE=$min_spare PHP_PM_MAX_SPARE=$max_spare PHP_PM_START_SERVERS=$start_servers
    log "FPM pool: pm=${mode} pm.max_children=${max_children} start=${start_servers} spare=${min_spare}-${max_spare}"
fi

# 2. Log dirs (MGR_LOG_PATH: app/cli/cron) — neither the framework nor the
#    async CLI lib creates its own. Runs unprivileged: the compose `init`
#    service chowns the volume to this identity first.
: "${MGR_LOG_PATH:?MGR_LOG_PATH must be set (unified log root, e.g. /var/log/manager/)}"
for stream in app cli cron; do
    dir="${MGR_LOG_PATH%/}/${stream}/"
    if ! mkdir -p "$dir" 2>/dev/null || [[ ! -w "$dir" ]]; then
        die "'$dir' is not writable as uid $(id -u) (did the init service run?)"
    fi
done

# 3. Wait for a TCP dependency (bounded)
wait_for_tcp() {
    local host="$1" port="$2" elapsed=0 max="${WAIT_TIMEOUT:-60}"
    log "Waiting for ${host}:${port} (max ${max}s)..."
    until (exec 3<>"/dev/tcp/${host}/${port}") 2>/dev/null; do
        sleep 2; elapsed=$(( elapsed + 2 ))
        (( elapsed >= max )) && die "timed out waiting for ${host}:${port}"
    done
    exec 3>&- 2>/dev/null || true
    log "${host}:${port} is ready."
}

if [[ "${WAIT_FOR_DB:-false}" == "true" ]]; then
    [[ -n "${DB_HOST:-}" ]] || die "WAIT_FOR_DB=true but DB_HOST is empty"
    wait_for_tcp "${DB_HOST}" "${DB_PORT:-3306}"
fi

# 4. Migrations — opt-in, and only ever from the php service.
if [[ "${RUN_MIGRATIONS:-false}" == "true" ]]; then
    log "Running database migrations..."
    php /var/www/html/public/index.php manager/tools/migrate
    log "Migrations complete."
fi

log "exec: $*"
exec "$@"
