#!/bin/sh
# nginx's runtime config, rendered from the environment into a volume nginx
# mounts read-only, so nginx itself can't rewrite what it loads.
#   render — run by the nginx-init service; writes real_ip.conf and the
#            inputs it was made from.
#   check  — run by nginx's entrypoint before nginx; exits 1 unless the
#            render exists and was made from nginx's own environment.
# NGINX_TRUSTED_PROXIES: space/comma list of addresses or CIDRs; empty trusts
# no proxy (the client IP is the TCP peer). NGINX_REAL_IP_HEADER: default
# X-Forwarded-For. The same list decides whose X-Forwarded-Proto is honored.
# An invalid value exits 1, so nothing starts with it.
set -eu
# An entry like '*' must reach the charset check, not expand to file names.
set -f

RUNTIME_DIR=/etc/nginx/runtime

log() { echo "[nginx-runtime] $*"; }
die() { echo "[nginx-runtime] FATAL: $*" >&2; exit 1; }

header="${NGINX_REAL_IP_HEADER:-X-Forwarded-For}"
proxies="$(printf '%s' "${NGINX_TRUSTED_PROXIES:-}" | tr ',' ' ')"
# Raw values, so both sides compare exactly what compose passed them.
inputs="$(printf 'NGINX_TRUSTED_PROXIES=%s\nNGINX_REAL_IP_HEADER=%s' \
    "${NGINX_TRUSTED_PROXIES:-}" "${NGINX_REAL_IP_HEADER:-}")"

validate() {
    case "$header" in
        *[!0-9A-Za-z_-]*) die "NGINX_REAL_IP_HEADER must be a header name (got '$header')" ;;
    esac
    for entry in $proxies; do
        case "$entry" in
            *[!0-9a-fA-F:./]*) die "NGINX_TRUSTED_PROXIES entry '$entry' is not an address or CIDR" ;;
        esac
    done
}

render() {
    validate
    conf="$RUNTIME_DIR/real_ip.conf"
    {
        for entry in $proxies; do
            printf 'set_real_ip_from %s;\n' "$entry"
        done
        printf 'real_ip_header %s;\n' "$header"
        printf 'real_ip_recursive on;\n'
        # Gates X-Forwarded-Proto in nginx.conf. Keyed on the TCP peer, not the
        # substituted client address, so a forged X-Forwarded-For can't pass it.
        printf 'geo $realip_remote_addr $from_trusted_proxy {\n'
        printf '    default 0;\n'
        for entry in $proxies; do
            printf '    %s 1;\n' "$entry"
        done
        printf '}\n'
    } > "$conf.tmp"
    mv "$conf.tmp" "$conf"
    # Written last: a render that stops halfway keeps the old inputs, which
    # then fail nginx's check instead of passing it.
    printf '%s\n' "$inputs" > "$RUNTIME_DIR/inputs.tmp"
    mv "$RUNTIME_DIR/inputs.tmp" "$RUNTIME_DIR/inputs"

    set -- $proxies
    if [ "$#" -eq 0 ]; then
        log "no trusted proxies: client IP is the TCP peer"
    else
        log "trusted proxy entries: $#, header: $header"
    fi
}

check() {
    [ -f "$RUNTIME_DIR/inputs" ] && [ -f "$RUNTIME_DIR/real_ip.conf" ] \
        || die "no render in $RUNTIME_DIR; nginx-init must run before nginx"
    [ "$(cat "$RUNTIME_DIR/inputs")" = "$inputs" ] \
        || die "the render in $RUNTIME_DIR was made from other NGINX_* values; recreate with up -d (not --no-deps) so nginx-init re-renders"
    log "render matches this container's environment"
}

case "${1:-}" in
    render) render ;;
    check) check ;;
    *) die "usage: $0 render|check" ;;
esac
