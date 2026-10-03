#!/bin/sh
# Runs as root in front of the image's own docker-entrypoint.sh. On a fresh
# cluster it passes db_password to initdb/10-app-role.sh as
# POSTGRES_APP_PASSWORD (the image unsets POSTGRES_* before the server starts)
# and marks the init incomplete until initdb/99-init-complete.sh clears it.
# A cluster whose init never finished is refused. Never bypass this script.
set -eu

MARKER_DIR=/var/lib/postgresql/.mgr-init
MARKER="$MARKER_DIR/init-incomplete"
SECRET=/run/secrets/db_password

# The image's own server-start test: anything else (`env`, `--version`) passes
# straight through, without reading the secret or leaving a marker.
case "${1:-}" in -*) set -- postgres "$@" ;; esac
[ "${1:-}" = postgres ] || exec docker-entrypoint.sh "$@"
for arg; do
    case "$arg" in '-?'|--help|--describe-config|-V|--version) exec docker-entrypoint.sh "$@" ;; esac
done

if [ -e "$MARKER" ] || [ -L "$MARKER" ]; then
    # Only a regular file is printed: the directory belongs to postgres, not root.
    started="an unknown time"
    [ -f "$MARKER" ] && [ ! -L "$MARKER" ] && started="$(head -c 40 "$MARKER")"
    echo "[postgres-entrypoint] FATAL: the init started $started never finished, so this cluster is incomplete (no client could connect while it ran); marker: $MARKER." >&2
    echo "[postgres-entrypoint] Fix the cause shown in that first run's log, then move aside the whole volume mounted at /var/lib/postgresql (a server's ./data/postgres; down -v for a local named volume), never deleting it unchecked, and start again." >&2
    exit 1
fi

if [ ! -s "$PGDATA/PG_VERSION" ]; then
    # Another major's data (or the pre-18 layout) on this volume: hand over
    # unmarked, so the image's upgrade guard refuses; the old pin still starts.
    for old in /var/lib/postgresql /var/lib/postgresql/data /var/lib/postgresql/*/docker; do
        [ -s "$old/PG_VERSION" ] && exec docker-entrypoint.sh "$@"
    done

    if ! grep -q '[^[:space:]]' "$SECRET"; then
        echo "[postgres-entrypoint] FATAL: $SECRET is missing or empty; not initializing" >&2
        exit 1
    fi
    POSTGRES_APP_PASSWORD="$(cat "$SECRET")"
    export POSTGRES_APP_PASSWORD

    if [ -L "$MARKER_DIR" ]; then
        echo "[postgres-entrypoint] FATAL: $MARKER_DIR is a symlink; not initializing" >&2
        exit 1
    fi
    mkdir -p -m 0700 "$MARKER_DIR"
    date -u +%Y-%m-%dT%H:%M:%SZ > "$MARKER"
    chown -R postgres:postgres "$MARKER_DIR"
fi

exec docker-entrypoint.sh "$@"
