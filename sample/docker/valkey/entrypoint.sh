#!/bin/sh
# Valkey entrypoint: renders requirepass into a tmpfs config (never in argv),
# then drops from root to the valkey user. Do not add a compose `user:`
# override on this service — it breaks both the ownership fix and the
# privilege drop.
set -eu

PW_FILE="${VALKEY_PASSWORD_FILE:-/run/secrets/valkey_password}"

if [ ! -s "$PW_FILE" ]; then
    echo "[valkey-entrypoint] FATAL: password file '$PW_FILE' is missing or empty" >&2
    exit 1
fi

PW="$(cat "$PW_FILE")"

CONF_FILE="$1"
shift

AUTH_CONF=/tmp/valkey-auth.conf
{
    printf 'include %s\n' "$CONF_FILE"
    printf 'requirepass "%s"\n' "$PW"
} > "$AUTH_CONF"
chmod 600 "$AUTH_CONF"
chown valkey "$AUTH_CONF"

find . \! -user valkey -exec chown valkey '{}' +
exec setpriv --reuid=valkey --regid=valkey --clear-groups -- \
    valkey-server "$AUTH_CONF" "$@"
