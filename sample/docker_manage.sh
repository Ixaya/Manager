#!/usr/bin/env bash
# docker_manage.sh — the single entrypoint for every Docker Compose operation.
#
#   ./docker_manage.sh -e <instance> [-b|--bind] <docker compose args...>
#
# Examples:
#   ./docker_manage.sh -e dev build
#   ./docker_manage.sh -e dev up -d
#   ./docker_manage.sh -e dev --profile ws --profile cron up -d
#   ./docker_manage.sh -e dev exec php bash
#   ./docker_manage.sh -e dev logs -f php
#   ./docker_manage.sh -e local -b up -d      # dev live-code bind mode, see below
#
# It wraps:
#   docker compose -f docker/docker-compose.yml -p <instance> \
#                  --env-file docker/env/<instance>.docker.env \
#                  --env-file docker/env/<instance>.env <args...>
#
# TWO per-instance env files are always passed, docker.env first:
#   <instance>.docker.env — docker-infrastructure-only vars (ports, image
#     tags, build args, resource limits, bind-mount source paths, dev-bind
#     mode). Used ONLY for compose interpolation — never loaded into any
#     container's process environment.
#   <instance>.env — vars the PHP app itself reads at runtime, or that a
#     container-side script (docker/php/entrypoint.sh) reads. This is the
#     ONLY file also injected into php/ws/cron/cli via env_file:.
#
# and exports the per-instance file paths the compose file interpolates:
#   APP_ENV_FILE / APP_SECRETS_MOUNT / VALKEY_SECRET_FILE / DB_*_FILE
#
# -b / --bind (optional, must come right after -e <instance>): also passes
# `-f docker/docker-compose.dev-bind.yml`, which bind-mounts application/ and
# public/ from CODE_BIND_PATH (a host checkout) over the baked image code.
# NEVER use for prod instances. Requires CODE_BIND_PATH=<path> set in the
# instance's DOCKER env-file; without it, this aborts rather than silently
# falling back to baked code. Without -b, the compose invocation is
# unchanged from before this flag existed.
#
# Fail loud: a missing instance name or required file aborts immediately.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_DIR="${SCRIPT_DIR}/docker"

die() { echo "docker_manage.sh: FATAL: $*" >&2; exit 1; }

# ── Parse -e <instance> (must come first) ─────────────────────────────────────
[[ "${1:-}" == "-e" ]] || die "usage: ./docker_manage.sh -e <instance> [-b|--bind] <docker compose args...>"
INSTANCE="${2:-}"
[[ -n "$INSTANCE" ]]   || die "missing <instance> after -e"
[[ "$INSTANCE" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || die "invalid instance name '$INSTANCE' (use [a-z0-9_-])"
shift 2

# ── Parse optional -b / --bind (must come right after -e <instance>) ─────────
BIND_MODE=false
if [[ "${1:-}" == "-b" || "${1:-}" == "--bind" ]]; then
    BIND_MODE=true
    shift
fi

DOCKER_ENV_FILE="${DOCKER_DIR}/env/${INSTANCE}.docker.env"
ENV_FILE="${DOCKER_DIR}/env/${INSTANCE}.env"
[[ -f "$DOCKER_ENV_FILE" ]] || die "docker env file not found: docker/env/${INSTANCE}.docker.env  (copy docker/env/sample.docker.env)"
[[ -f "$ENV_FILE" ]]        || die "env file not found: docker/env/${INSTANCE}.env  (copy docker/env/sample.env)"

COMPOSE_FILE_ARGS=(-f "${DOCKER_DIR}/docker-compose.yml")
if [[ "$BIND_MODE" == true ]]; then
    grep -qE '^CODE_BIND_PATH=.+$' "$DOCKER_ENV_FILE" \
        || die "usage: -b/--bind requires CODE_BIND_PATH=<host path containing application/ and public/> in docker/env/${INSTANCE}.docker.env"
    COMPOSE_FILE_ARGS+=(-f "${DOCKER_DIR}/docker-compose.dev-bind.yml")
fi

# Paths below are RELATIVE TO docker/ (the compose file's directory), because
# compose resolves env_file:, secrets:, and bind-mount sources from there.
export APP_ENV_FILE="env/${INSTANCE}.env"
# Leading ./ so compose treats this as a bind-mount source, not a named volume.
export APP_SECRETS_MOUNT="./env/${INSTANCE}.priv.env"
export VALKEY_SECRET_FILE="secrets/${INSTANCE}.valkey_password"
export DB_PASSWORD_FILE="secrets/${INSTANCE}.db_password"
export DB_ROOT_PASSWORD_FILE="secrets/${INSTANCE}.db_root_password"

# The app secrets file and the Valkey password are required for any real run.
# (Compose only reads DB_* secret files when a db profile is active.)
require_file() { [[ -f "${DOCKER_DIR}/$1" ]] || die "required file missing: docker/$1  (copy from docker/env/sample.secrets.env)"; }
require_file "$APP_SECRETS_MOUNT"
require_file "$VALKEY_SECRET_FILE"

exec docker compose \
    "${COMPOSE_FILE_ARGS[@]}" \
    -p "$INSTANCE" \
    --env-file "$DOCKER_ENV_FILE" \
    --env-file "$ENV_FILE" \
    "$@"
