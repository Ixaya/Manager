#!/bin/sh
# Renders the real-client-IP trust that nginx.conf includes, at every start:
# NGINX_TRUSTED_PROXIES (space/comma list of addresses or CIDRs; empty = trust
# no proxy, the client IP is the TCP peer) and NGINX_REAL_IP_HEADER (default
# X-Forwarded-For). An invalid value exits 1, so nginx never starts with it.
set -eu
# An entry like '*' must reach the charset check, not expand to file names.
set -f

log() { echo "[real-ip] $*"; }
die() { echo "[real-ip] FATAL: $*" >&2; exit 1; }

CONF=/var/cache/nginx/real_ip.conf
header="${NGINX_REAL_IP_HEADER:-X-Forwarded-For}"
proxies="$(printf '%s' "${NGINX_TRUSTED_PROXIES:-}" | tr ',' ' ')"

case "$header" in
    *[!0-9A-Za-z_-]*) die "NGINX_REAL_IP_HEADER must be a header name (got '$header')" ;;
esac

count=0
for entry in $proxies; do
    case "$entry" in
        *[!0-9a-fA-F:./]*) die "NGINX_TRUSTED_PROXIES entry '$entry' is not an address or CIDR" ;;
    esac
    count=$((count + 1))
done

{
    for entry in $proxies; do
        printf 'set_real_ip_from %s;\n' "$entry"
    done
    printf 'real_ip_header %s;\n' "$header"
    printf 'real_ip_recursive on;\n'
} > "$CONF.tmp"
mv "$CONF.tmp" "$CONF"

if [ "$count" -eq 0 ]; then
    log "no trusted proxies: client IP is the TCP peer"
else
    log "trusted proxy entries: $count, header: $header"
fi
