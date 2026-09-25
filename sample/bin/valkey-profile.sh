#!/usr/bin/env bash
# Measure valkey-state's memory peak on THIS host against an instance's cap.
#
# Usage: bin/valkey-profile.sh -e <instance> [--maxmemory <size>] [--cap <size>] [--image <ref>] [--runs <n>]
#   bin/valkey-profile.sh -e dev
#   bin/valkey-profile.sh -e dev --maxmemory 256mb --cap 576m      # try values before editing docker.env
#   bin/valkey-profile.sh -e dev --image valkey/valkey:<new tag>   # try an image before moving the pin
#
# Starts a throwaway copy of the instance's valkey-state — its resolved image,
# command, conf/entrypoint mounts, mem_limit and cpus, with an empty volume and
# a random password; never the live container or its data. Each run fills it
# to ~95% of maxmemory and forces an AOF rewrite under concurrent writes, the
# fork whose copy-on-write sets the peak. The worst run is reported.
#
# --maxmemory takes Valkey units (mb = MiB, m = 10^6 bytes); --cap takes Docker
# units (m = MiB).
#
# Output: key=value lines on stdout, progress on stderr.
# Exit: 0 PASS, 1 TIGHT or FAIL, 2 usage error, unmet precondition, or INVALID
# (the write load stopped before the rewrite finished, so the peak is understated).
#
# It loads the host's CPU and disk: run it while the sites are idle.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Below 100 so a hashtable rehash near the ceiling doesn't hit noeviction.
FILL_PERCENT=95
TIGHT_PERCENT=10
CALIBRATION_KEYS=20000
VALUE_BYTES=200
HOST_RESERVE_MIB=256
# RSS-before-fork + COW reads 2-10 MiB under the sampled peak.
ESTIMATE_ALLOWANCE_MIB=16
REWRITE_TIMEOUT_SECONDS=900

die() { echo "valkey-profile.sh: FATAL: $*" >&2; exit 2; }
progress() { echo "valkey-profile.sh: $*" >&2; }
mib() { if [[ "$1" =~ ^[0-9]+$ ]]; then echo $(( $1 / 1048576 )); else echo "n/a"; fi; }

# ── Arguments ────────────────────────────────────────────────────────────────
usage="usage: bin/valkey-profile.sh -e <instance> [--maxmemory <size>] [--cap <size>] [--image <ref>] [--runs <n>]"
[[ "${1:-}" == "-e" ]] || die "$usage"
instance="${2:-}"
[[ "$instance" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || die "invalid or missing instance name '${instance}'"
shift 2

maxmemory_override=""
cap_override=""
image_override=""
runs=3
while [[ $# -gt 0 ]]; do
	case "$1" in
		--maxmemory) maxmemory_override="${2:?--maxmemory needs a value}"; shift 2 ;;
		--cap)       cap_override="${2:?--cap needs a value}"; shift 2 ;;
		--image)     image_override="${2:?--image needs a value}"; shift 2 ;;
		--runs)      runs="${2:?--runs needs a value}"; shift 2 ;;
		*) die "unknown argument '$1' — $usage" ;;
	esac
done
[[ "$runs" =~ ^[1-9][0-9]*$ ]] || die "--runs must be a positive integer"

command -v docker >/dev/null || die "docker not found"
command -v python3 >/dev/null || die "python3 not found (parses the resolved compose config)"

valkey_size_bytes() {
	local size
	size="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
	[[ "$size" =~ ^([0-9]+)(k|kb|m|mb|g|gb)?$ ]] || die "invalid Valkey size '$1' (e.g. 128mb)"
	case "${BASH_REMATCH[2]}" in
		'')  echo "${BASH_REMATCH[1]}" ;;
		k)   echo $(( BASH_REMATCH[1] * 1000 )) ;;
		kb)  echo $(( BASH_REMATCH[1] * 1024 )) ;;
		m)   echo $(( BASH_REMATCH[1] * 1000000 )) ;;
		mb)  echo $(( BASH_REMATCH[1] * 1048576 )) ;;
		g)   echo $(( BASH_REMATCH[1] * 1000000000 )) ;;
		gb)  echo $(( BASH_REMATCH[1] * 1073741824 )) ;;
	esac
}

docker_size_bytes() {
	local size
	size="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
	[[ "$size" =~ ^([0-9]+)([kmg]?)b?$ ]] || die "invalid Docker size '$1' (e.g. 320m)"
	case "${BASH_REMATCH[2]}" in
		'') echo "${BASH_REMATCH[1]}" ;;
		k)  echo $(( BASH_REMATCH[1] * 1024 )) ;;
		m)  echo $(( BASH_REMATCH[1] * 1048576 )) ;;
		g)  echo $(( BASH_REMATCH[1] * 1073741824 )) ;;
	esac
}

# ── Resolved compose config for valkey-state ─────────────────────────────────
progress "reading the resolved compose config of instance '${instance}'"
config_json="$("${ROOT_DIR}/docker_manage.sh" -e "$instance" config --format json valkey-state)" \
	|| die "docker_manage.sh could not resolve the compose config for '${instance}'"

# One tab-separated record per line: kind, then values.
config_records="$(printf '%s' "$config_json" | python3 -c '
import json, sys
service = json.load(sys.stdin)["services"]["valkey-state"]
def emit(*fields):
	print("\t".join(str(field) for field in fields))
emit("image", service["image"])
emit("mem_limit", service.get("mem_limit") or "")
emit("cpus", service.get("cpus") or "")
emit("read_only", 1 if service.get("read_only") else 0)
for path in service.get("tmpfs") or []:
	emit("tmpfs", path)
for option in service.get("security_opt") or []:
	emit("security_opt", option)
for arg in service.get("entrypoint") or []:
	emit("entrypoint", arg)
for arg in service.get("command") or []:
	emit("command", arg)
for volume in service.get("volumes") or []:
	if volume["type"] == "bind":
		emit("bind", volume["source"], volume["target"], 1 if volume.get("read_only") else 0)
	elif volume["type"] == "volume":
		emit("volume", volume["target"])
for secret in service.get("secrets") or []:
	emit("secret", secret["source"], secret.get("target") or "/run/secrets/" + secret["source"])
')"

image=""; mem_limit=""; cpus=""; read_only=0
entrypoint=(); command_args=(); run_mounts=()
secret_target=""
while IFS=$'\t' read -r kind first second third; do
	case "$kind" in
		image)        image="$first" ;;
		mem_limit)    mem_limit="$first" ;;
		cpus)         cpus="$first" ;;
		read_only)    read_only="$first" ;;
		tmpfs)        run_mounts+=(--tmpfs "$first") ;;
		security_opt) run_mounts+=(--security-opt "$first") ;;
		entrypoint)   entrypoint+=("$first") ;;
		command)      command_args+=("$first") ;;
		bind)
			bind_spec="${first}:${second}"
			[[ "$third" == 1 ]] && bind_spec+=":ro"
			run_mounts+=(-v "$bind_spec")
			;;
		volume)       data_target="$first" ;;
		secret)
			[[ "$first" == valkey_password ]] || die "unexpected secret '${first}' on valkey-state — this script only supplies valkey_password"
			secret_target="$second"
			;;
	esac
done <<< "$config_records"

[[ -n "$image_override" ]] && image="$image_override"
[[ -n "$image" ]] || die "valkey-state has no image in the resolved config"
[[ -n "$mem_limit" ]] || die "valkey-state has no mem_limit — nothing to verify"
[[ -n "$secret_target" ]] || die "valkey-state has no valkey_password secret"
[[ -n "${data_target:-}" ]] || die "valkey-state has no data volume"
[[ ${#entrypoint[@]} -gt 0 ]] || die "valkey-state has no entrypoint"

# --maxmemory in the compose command is replaced, never appended, so Valkey
# doesn't see two values.
maxmemory=""
for index in "${!command_args[@]}"; do
	if [[ "${command_args[$index]}" == --maxmemory ]]; then
		maxmemory="${command_args[$((index + 1))]}"
		[[ -n "$maxmemory_override" ]] && command_args[index + 1]="$maxmemory_override"
	fi
done
[[ -n "$maxmemory_override" ]] && maxmemory="$maxmemory_override"
[[ -n "$maxmemory" ]] || die "no --maxmemory in valkey-state's command"
maxmemory_bytes="$(valkey_size_bytes "$maxmemory")"

cap_bytes="$mem_limit"
[[ -n "$cap_override" ]] && cap_bytes="$(docker_size_bytes "$cap_override")"
cap_mib="$(mib "$cap_bytes")"
maxmemory_mib="$(mib "$maxmemory_bytes")"
rule_cap_mib=$(( 2 * maxmemory_mib + 64 ))

# ── Host facts, read from inside a container (a Docker Desktop VM, not macOS) ─
host_facts="$(docker run --rm --entrypoint sh "$image" -c '
	thp=/sys/kernel/mm/transparent_hugepage/enabled
	echo "page_size=$(getconf PAGESIZE)"
	if [ -r "$thp" ]; then echo "thp=$(sed "s/.*\[\(.*\)\].*/\1/" "$thp")"; else echo "thp=absent"; fi
	echo "overcommit_memory=$(cat /proc/sys/vm/overcommit_memory)"
	if [ -f /sys/fs/cgroup/cgroup.controllers ]; then echo "cgroup=v2"; else echo "cgroup=v1"; fi
	echo "mem_available_mib=$(awk "/^MemAvailable:/ { print int(\$2 / 1024) }" /proc/meminfo)"
	echo "cpu_model=$(awk -F": " "/^model name/ { print \$2; exit }" /proc/cpuinfo)"
')" || die "could not start ${image} to read host facts"
host_value() { printf '%s\n' "$host_facts" | awk -F= -v key="$1" '$1 == key { sub(/^[^=]*=/, ""); print; exit }'; }

cgroup_version="$(host_value cgroup)"
mem_available_mib="$(host_value mem_available_mib)"
[[ "$cgroup_version" == v2 ]] || die "cgroup v1 host — memory accounting differs; this script needs cgroup v2"
(( mem_available_mib >= cap_mib + HOST_RESERVE_MIB )) \
	|| die "host has ${mem_available_mib} MiB available; a ${cap_mib} MiB probe needs ${cap_mib} + ${HOST_RESERVE_MIB} MiB so it can't push live containers into the host's OOM killer"

echo "host.docker=$(docker info --format '{{.OperatingSystem}} / {{.Architecture}} / kernel {{.KernelVersion}} / {{.NCPU}} CPUs')"
cpu_model="$(host_value cpu_model)"
echo "host.cpu_model=${cpu_model:-unknown}"
echo "host.page_size=$(host_value page_size)"
echo "host.thp=$(host_value thp)"
echo "host.overcommit_memory=$(host_value overcommit_memory)"
echo "host.cgroup=${cgroup_version}"
echo "host.mem_available_mib=${mem_available_mib}"
echo "config.image=${image}"
echo "config.maxmemory=${maxmemory} (${maxmemory_mib} MiB)"
echo "config.cap_mib=${cap_mib}"
echo "config.cpus=${cpus:-unlimited}"
echo "config.rule_cap_mib=${rule_cap_mib}"

# ── Throwaway resources, removed on any exit ─────────────────────────────────
work_dir="$(mktemp -d)"
name_prefix="valkey-profile-$$"
cleanup() {
	docker ps -aq --filter "name=^${name_prefix}-" | xargs -r docker rm -f >/dev/null 2>&1 || true
	docker volume ls -q --filter "name=^${name_prefix}-" | xargs -r docker volume rm >/dev/null 2>&1 || true
	rm -rf "$work_dir"
}
trap cleanup EXIT

password="$(od -An -N24 -tx1 /dev/urandom | tr -d ' \n')"
printf '%s' "$password" > "${work_dir}/valkey_password"
chmod 644 "${work_dir}/valkey_password"

# ── One run ──────────────────────────────────────────────────────────────────
# Never fails: a server the kernel killed must reach the verdict, not abort it.
cli() { docker exec -e REDISCLI_AUTH="$password" "$server" valkey-cli "$@" 2>/dev/null | tr -d '\r' || true; }
info_field() { cli INFO "$1" | awk -F: -v key="$2" '$1 == key { print $2; exit }'; }
# Sets key:<12-digit n> for n in [first, last) — valkey-benchmark's own key
# format, so the writer below only ever overwrites and the dataset can't grow.
fill_keys() {
	docker run --rm --network "container:${server}" --entrypoint sh "$image" -c "
		awk -v first=$1 -v last=$2 -v size=$VALUE_BYTES 'BEGIN {
			while (length(value) < size) value = value \"x\"
			for (n = first; n < last; n++) printf \"SET key:%012d %s\\r\\n\", n, value
		}' | REDISCLI_AUTH='$password' valkey-cli --pipe" >/dev/null 2>&1 || true
}
server_running() { [[ "$(docker inspect --format '{{.State.Running}}' "$server" 2>/dev/null)" == true ]]; }
wait_for_no_fork() {
	local waited=0
	while server_running && [[ "$(info_field persistence aof_rewrite_in_progress)" != 0 || "$(info_field persistence rdb_bgsave_in_progress)" != 0 ]]; do
		(( waited++ < REWRITE_TIMEOUT_SECONDS )) || die "a background fork did not finish within ${REWRITE_TIMEOUT_SECONDS}s"
		sleep 1
	done
}

run_once() {
	local run="$1"
	local volume="${name_prefix}-${run}" run_args
	server="${name_prefix}-${run}"
	sampler="${name_prefix}-${run}-sampler"
	writer="${name_prefix}-${run}-writer"

	run_args=(--memory "$cap_bytes" --memory-swap "$cap_bytes")
	[[ -n "$cpus" ]] && run_args+=(--cpus "$cpus")
	[[ "$read_only" == 1 ]] && run_args+=(--read-only)
	docker volume create "$volume" >/dev/null
	docker run -d --name "$server" "${run_args[@]}" "${run_mounts[@]}" \
		-v "${work_dir}/valkey_password:${secret_target}:ro" -v "${volume}:${data_target}" \
		--entrypoint "${entrypoint[0]}" "$image" "${entrypoint[@]:1}" "${command_args[@]}" >/dev/null

	local waited=0
	until [[ "$(cli PING)" == PONG ]]; do
		(( waited++ < 60 )) || die "throwaway valkey-state did not answer PING; docker logs ${server}"
		sleep 0.5
	done

	# Uncapped sibling on the host cgroup namespace: samples every 50 ms, which a
	# ~0.2 s fork spike needs, without taking CPU from the capped server.
	local container_id
	container_id="$(docker inspect --format '{{.Id}}' "$server")"
	docker run -d --name "$sampler" --cgroupns host --entrypoint sh "$image" -c '
		dir="$(find /sys/fs/cgroup -maxdepth 4 -type d -name "*'"$container_id"'*" | head -n 1)"
		[ -n "$dir" ] || { echo "unavailable" > /tmp/state; exec sleep 3600; }
		max=0
		while [ -f "$dir/memory.stat" ]; do
			[ -f /tmp/reset ] && { max=0; rm -f /tmp/reset; }
			anon="$(awk "/^anon / { print \$2; exit }" "$dir/memory.stat")"
			kills="$(awk "/^oom_kill / { print \$2; exit }" "$dir/memory.events")"
			[ "${anon:-0}" -gt "$max" ] && max="$anon"
			echo "$max ${kills:-0}" > /tmp/state
			sleep 0.05
		done
		exec sleep 3600
	' >/dev/null

	local idle_used keyspace calibration_used calibration_keys bytes_per_key
	idle_used="$(info_field memory used_memory)"
	fill_keys 0 "$CALIBRATION_KEYS"
	calibration_keys="$(cli DBSIZE)"
	calibration_used="$(info_field memory used_memory)"
	[[ "${calibration_keys:-0}" -gt 0 ]] || die "calibration wrote no keys; docker logs ${server}"
	bytes_per_key=$(( (calibration_used - idle_used) / calibration_keys ))
	keyspace=$(( (maxmemory_bytes * FILL_PERCENT / 100 - idle_used) / bytes_per_key ))
	(( keyspace > CALIBRATION_KEYS )) || die "maxmemory ${maxmemory} is too small to profile"

	progress "run ${run}/${runs}: filling ${keyspace} keys (~${FILL_PERCENT}% of ${maxmemory_mib} MiB)"
	fill_keys "$CALIBRATION_KEYS" "$keyspace"
	wait_for_no_fork
	if ! server_running; then
		report_dead_server "$run"
		return
	fi
	local used_after_fill
	used_after_fill="$(info_field memory used_memory)"

	# The fill's --pipe client buffers far ahead of the server; only the rewrite
	# phase's peak counts. OOM kills keep counting across the whole run.
	docker exec "$sampler" touch /tmp/reset
	sleep 0.2
	# Writes must already be running when the fork starts: a rewrite that
	# finishes first copies almost nothing and understates the peak.
	docker run -d --name "$writer" --network "container:${server}" --entrypoint valkey-benchmark "$image" \
		-a "$password" -t set -d "$VALUE_BYTES" -P 8 -q -n $(( keyspace * 100 )) -r "$keyspace" >/dev/null
	sleep 1
	local rss_before rewrites_before
	rss_before="$(info_field memory used_memory_rss)"
	rewrites_before="$(info_field persistence aof_rewrites)"
	progress "run ${run}/${runs}: forcing an AOF rewrite under writes"
	cli BGREWRITEAOF >/dev/null
	waited=0
	while server_running && [[ "$(info_field persistence aof_rewrites)" == "$rewrites_before" || "$(info_field persistence aof_rewrite_in_progress)" != 0 ]]; do
		(( waited++ < REWRITE_TIMEOUT_SECONDS * 2 )) || die "the forced AOF rewrite did not finish within ${REWRITE_TIMEOUT_SECONDS}s"
		sleep 0.5
	done
	local writer_ran_through
	writer_ran_through="$(docker inspect --format '{{.State.Running}}' "$writer" 2>/dev/null || echo false)"
	docker rm -f "$writer" >/dev/null
	sleep 1

	local sampler_state sampled_peak_mib oom_kills cow_bytes rewrite_status estimate_mib peak_mib container_oom
	sampler_state="$(docker exec "$sampler" cat /tmp/state 2>/dev/null || echo unavailable)"
	container_oom="$(docker inspect --format '{{.State.OOMKilled}}' "$server")"
	if server_running; then
		cow_bytes="$(info_field persistence aof_last_cow_size)"
		rewrite_status="$(info_field persistence aof_last_bgrewrite_status)"
		estimate_mib=$(( $(mib "$rss_before") + $(mib "$cow_bytes") ))
	else
		cow_bytes=""; rewrite_status="server_died"; estimate_mib=""
	fi
	if [[ "$sampler_state" == unavailable ]]; then
		sampled_peak_mib=""; oom_kills="unknown"
	else
		sampled_peak_mib="$(mib "${sampler_state% *}")"; oom_kills="${sampler_state#* }"
	fi
	if [[ -n "$sampled_peak_mib" ]]; then
		peak_mib="$sampled_peak_mib"
	else
		peak_mib=$(( ${estimate_mib:-$cap_mib} + ESTIMATE_ALLOWANCE_MIB ))
	fi

	echo "run.${run}.fill_percent=$(( used_after_fill * 100 / maxmemory_bytes ))"
	echo "run.${run}.rss_before_fork_mib=$(mib "$rss_before")"
	echo "run.${run}.cow_mib=$(mib "$cow_bytes")"
	echo "run.${run}.estimate_mib=${estimate_mib:-n/a}"
	echo "run.${run}.sampled_peak_mib=${sampled_peak_mib:-unavailable}"
	echo "run.${run}.oom_kills=${oom_kills}"
	echo "run.${run}.server_oom_killed=${container_oom}"
	echo "run.${run}.rewrite_status=${rewrite_status}"
	echo "run.${run}.writer_ran_through=${writer_ran_through}"
	echo "run.${run}.rejected_writes=$(cli INFO errorstats | awk -F'[=,]' '/^errorstat_OOM:/ { print $2; exit }' | grep . || echo 0)"

	run_failed=0
	run_invalid=0
	[[ "$writer_ran_through" != true ]] && run_invalid=1
	[[ "$container_oom" == true || "$rewrite_status" != ok || ( "$oom_kills" != 0 && "$oom_kills" != unknown ) ]] && run_failed=1
	run_peak_mib="$peak_mib"

	docker rm -f "$writer" "$sampler" "$server" >/dev/null 2>&1 || true
	docker volume rm "$volume" >/dev/null 2>&1 || true
}

# The kernel killed the server before the forced rewrite: FAIL with what the
# sampler saw.
report_dead_server() {
	local run="$1" sampler_state
	sampler_state="$(docker exec "$sampler" cat /tmp/state 2>/dev/null || echo unavailable)"
	echo "run.${run}.server_oom_killed=$(docker inspect --format '{{.State.OOMKilled}}' "$server")"
	if [[ "$sampler_state" == unavailable ]]; then
		echo "run.${run}.sampled_peak_mib=unavailable"
	else
		echo "run.${run}.sampled_peak_mib=$(mib "${sampler_state% *}")"
	fi
	echo "run.${run}.rewrite_status=server_died_during_fill"
	run_failed=1
	run_invalid=0
	run_peak_mib="$cap_mib"
	docker rm -f "$sampler" "$server" >/dev/null 2>&1 || true
	docker volume rm "${name_prefix}-${run}" >/dev/null 2>&1 || true
}

# ── Runs and verdict ─────────────────────────────────────────────────────────
worst_peak_mib=0
any_failed=0
any_invalid=0
for run in $(seq 1 "$runs"); do
	run_once "$run"
	(( run_peak_mib > worst_peak_mib )) && worst_peak_mib="$run_peak_mib"
	(( run_failed == 0 )) || any_failed=1
	(( run_invalid == 0 )) || any_invalid=1
done

margin_mib=$(( cap_mib - worst_peak_mib ))
margin_percent=$(( margin_mib * 100 / cap_mib ))
echo "result.peak_mib=${worst_peak_mib}"
echo "result.margin_mib=${margin_mib}"
echo "result.margin_percent=${margin_percent}"
[[ "$(host_value overcommit_memory)" == 1 ]] || echo "warning=overcommit_memory is not 1 — on a host short of free memory the rewrite's fork can fail"
(( cap_mib >= rule_cap_mib )) || echo "warning=cap is below the 2 × maxmemory + 64m rule (${rule_cap_mib} MiB)"

if (( any_failed == 1 )); then
	echo "verdict=FAIL"
	exit 1
fi
if (( any_invalid == 1 )); then
	echo "verdict=INVALID"
	exit 2
fi
if (( margin_percent < TIGHT_PERCENT )); then
	echo "verdict=TIGHT"
	exit 1
fi
echo "verdict=PASS"
