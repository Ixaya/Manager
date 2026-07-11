#!/usr/bin/env bash
# Entrypoint for php, ws, cron and cli (share the php-app image): prepares
# log dirs, optionally waits for the DB, optionally migrates, then execs
# the requested command. Fails loud — no silent fallbacks.
set -euo pipefail

log() { echo "[entrypoint] $*"; }
die() { echo "[entrypoint] FATAL: $*" >&2; exit 1; }

# 1. Log dirs (MGR_LOG_PATH: app/cli/cron) — created here since neither the
#    framework nor the async CLI lib creates its own.
: "${MGR_LOG_PATH:?MGR_LOG_PATH must be set (unified log root, e.g. /var/log/manager/)}"
for stream in app cli cron; do
    dir="${MGR_LOG_PATH%/}/${stream}/"
    if ! mkdir -p "$dir" 2>/dev/null; then
        die "cannot create '$dir' (is the manager-logs volume mounted?)"
    fi
    chown www-data:www-data "$dir" 2>/dev/null || true
done

# 2. Wait for a TCP dependency (bounded)
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

# 3. Migrations — opt-in, and only ever from the php service
if [[ "${RUN_MIGRATIONS:-false}" == "true" ]]; then
    log "Running database migrations..."
    php /var/www/html/public/index.php manager/tools/migrate
    log "Migrations complete."
fi

log "exec: $*"
exec "$@"
