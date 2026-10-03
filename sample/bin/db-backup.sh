#!/usr/bin/env bash
# Dump every running mgr.backup-labelled database container on this host into
# <compose dir>/backups/<engine>/, prune old dumps, optionally upload new ones.
# One run covers every site on the host.
#
# Usage: bin/db-backup.sh
# Env:   DB_BACKUP_KEEP (default 7) — good dumps kept per database, 1 to 999999.
#        DB_BACKUP_S3_URI (optional) — e.g. s3://bucket/prefix; each new dump
#        is uploaded to <uri>/<site>/<engine>/<file>.
#        DB_BACKUP_ALLOWED_ROOT (optional, blank = any) — only back up sites
#        whose compose directory resolves under this path (the directory
#        holding every site); any other labelled container fails.
#
# A failed dump never leaves a final-named file, so retention can't count it.
# backups/ and backups/<engine>/ must be the running user's own 0700
# directories (created that way); anything else fails the site. Exits
# non-zero if any site failed; the others still run.

set -euo pipefail
umask 077

keep="${DB_BACKUP_KEEP:-7}"
s3_uri="${DB_BACKUP_S3_URI:-}"
allowed_root="${DB_BACKUP_ALLOWED_ROOT:-}"
failed=0

log() { echo "db-backup: $*"; }
fail_site() { log "site=${1} engine=${2} status=failed reason=${3}"; failed=1; }

# Bounded: a huge value would wrap in bash arithmetic and prune everything.
if [[ ! "$keep" =~ ^[1-9][0-9]{0,5}$ ]]; then
	log "status=failed reason=DB_BACKUP_KEEP '${keep}' must be an integer from 1 to 999999"
	exit 1
fi

if [[ -n "$allowed_root" ]]; then
	allowed_root="$(cd -- "$allowed_root" 2>/dev/null && pwd -P)" \
		|| { log "status=failed reason=DB_BACKUP_ALLOWED_ROOT '${DB_BACKUP_ALLOWED_ROOT}' is not a directory"; exit 1; }
fi

# Creates <name> in the current directory if missing and enters it, only if it
# resolves to <expected> and is ours with mode 0700. Writes then go through the
# open working directory, so a later rename or symlink swap can't redirect them.
enter_private_dir() {
	mkdir -p -- "$1" 2>/dev/null && cd -- "$1" 2>/dev/null || return 1
	[[ "$(pwd -P)" == "$2" ]] || return 1
	[[ "$(stat -c '%u %a' .)" == "$(id -u) 700" ]]
}

# ── Engine dump commands — a new engine adds one here plus its case branch ──
dump_postgres() {
	docker exec -u postgres "$1" pg_dump -U postgres -Fc -Z0 "$2"
}

containers="$(docker ps -q --filter 'label=mgr.backup')"
if [[ -z "$containers" ]]; then
	log "no mgr.backup-labelled containers found"
	exit 0
fi

# Every fallible step below is guarded explicitly: under set -e an unguarded
# failure would abort every remaining site, not just this one.
for container in $containers; do
	cd /
	labels="$(docker inspect --format '{{index .Config.Labels "mgr.backup"}}{{"\n"}}{{index .Config.Labels "com.docker.compose.project"}}{{"\n"}}{{index .Config.Labels "com.docker.compose.project.working_dir"}}' "$container" 2>/dev/null)" \
		|| { fail_site "unknown" "unknown" "container ${container} could not be inspected (removed mid-run?)"; continue; }
	{ read -r engine; read -r project; read -r working_dir; } <<<"$labels" || true
	project="${project:-unknown}"

	site_root="$(cd -- "${working_dir:-/nonexistent}" 2>/dev/null && pwd -P)" \
		|| { fail_site "$project" "$engine" "working_dir '${working_dir}' is missing or not a directory"; continue; }
	if [[ -n "$allowed_root" && "$site_root" != "${allowed_root}/"* ]]; then
		fail_site "$project" "$engine" "working_dir '${working_dir}' is outside DB_BACKUP_ALLOWED_ROOT"
		continue
	fi

	case "$engine" in
		postgres)
			env_list="$(docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' "$container" 2>/dev/null)" \
				|| { fail_site "$project" "$engine" "container env could not be read"; continue; }
			db_name="$(sed -n 's/^POSTGRES_DB=//p' <<<"$env_list")"
			pg_major="$(sed -n 's/^PG_MAJOR=//p' <<<"$env_list")"
			if [[ ! "$pg_major" =~ ^[0-9]+$ ]]; then
				fail_site "$project" "$engine" "no numeric PG_MAJOR in the container's env"
				continue
			fi
			tag="pg${pg_major}"
			ext="dump"
			dump_command=dump_postgres
			;;
		*)
			fail_site "$project" "$engine" "unsupported engine"
			continue
			;;
	esac
	# The name becomes a path and a find pattern: no separators, dots-first or globs.
	if [[ ! "$db_name" =~ ^[A-Za-z0-9_][A-Za-z0-9_.-]*$ ]]; then
		fail_site "$project" "$engine" "database name '${db_name}' is empty or not a plain identifier"
		continue
	fi

	backup_dir="${site_root}/backups/${engine}"
	if ! { cd -- "$site_root" 2>/dev/null && [[ "$(pwd -P)" == "$site_root" ]] \
		&& enter_private_dir backups "${site_root}/backups" \
		&& enter_private_dir "$engine" "$backup_dir"; }; then
		fail_site "$project" "$engine" "${backup_dir} is not a directory owned by uid $(id -u) with mode 0700 (or a symlink)"
		continue
	fi

	final_name="${db_name}-${tag}-$(date -u +%Y%m%dT%H%M%SZ).${ext}.zst"
	partial_name="${final_name}.partial"

	if ! "$dump_command" "$container" "$db_name" | zstd -q -o "$partial_name"; then
		rm -f -- "$partial_name"
		fail_site "$project" "$engine" "dump or compression failed"
		continue
	fi
	if ! mv -f -- "$partial_name" "$final_name"; then
		fail_site "$project" "$engine" "could not rename ${partial_name}"
		continue
	fi
	log "site=${project} engine=${engine} status=ok file=${backup_dir}/${final_name}"

	# Oldest first by the filename's UTC stamp, never mtime (a copy touches it).
	# The exact stamp shape, so a database named "<db>-<tag>-…" never matches.
	stamp_glob='[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]T[0-9][0-9][0-9][0-9][0-9][0-9]Z'
	mapfile -t existing < <(find . -maxdepth 1 -type f -name "${db_name}-${tag}-${stamp_glob}.${ext}.zst" | sort)
	stale_count=$(( ${#existing[@]} - keep ))
	for ((i = 0; i < stale_count; i++)); do
		rm -f -- "${existing[$i]}" || fail_site "$project" "$engine" "could not prune ${backup_dir}/${existing[$i]#./}"
	done

	if [[ -n "$s3_uri" ]]; then
		if aws s3 cp "$final_name" "${s3_uri}/${project}/${engine}/${final_name}"; then
			log "site=${project} engine=${engine} status=uploaded file=${final_name}"
		else
			fail_site "$project" "$engine" "s3 upload failed"
		fi
	fi
done

(( failed == 0 )) || exit 1
exit 0
