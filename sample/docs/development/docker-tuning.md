# Docker stack — resource sizing & tuning

> Scope: sizing memory and CPU for the stack on a real host, especially one
> shared by several instances, and tuning the bundled database engines to fit
> their caps. For running the stack, see `docker.md`; for running it on a
> server, `docker-server.md`; for editing the files under `docker/`, see
> `docker-internals.md` (all beside this file).

Every value below is a knob in `docker/env/<instance>.docker.env`, read at
compose time. The shipped defaults suit one instance on a developer machine;
a shared server needs its own numbers, worked out with the budget below and
confirmed with the measurement commands at the end. The numbers quoted here
were measured on this stack (arm64, cgroup v2) — treat them as a starting
point, not as a property of your box.

## How a cap behaves

`mem_limit` is a hard ceiling, and `memswap_limit` is set to the same value on
every service so swap on the host can't quietly extend it. When a container
reaches its cap, the kernel OOM-kills a process **inside that container** —
nothing degrades gracefully first:

| Service | What an OOM kill looks like |
|---|---|
| MySQL / MariaDB | The server process dies; the container restarts and runs crash recovery. Every connection drops. |
| PostgreSQL | One backend is killed, and the postmaster then resets **every** session ("terminating any other active server processes"). |
| PHP-FPM | One worker dies; that request gets a 502. |
| Valkey | The server dies. `valkey-state` replays its AOF on restart; `valkey-cache` comes back empty. |

No database engine reads its container's limit to size itself. A cap
without matching engine settings is only a kill threshold, so the engine
knobs (below) must be sized to fit **inside** the cap.

**Isolation only holds if the caps fit the host.** When the sum of every
cap on the box stays below the memory actually available, a runaway
container hits its own cap and only that instance suffers. Once the caps
add up to more than the host has, the host runs out first, and the kernel's
host-wide OOM killer picks a victim from any container — another site's
database included.

Memory is the only resource a cap isolates. All instances still share:

- **Disk I/O.** A heavy query often doesn't run out of memory: it spills
  temp tables and sorts to disk, which every other instance on the same
  volume feels as latency. A 40-session full-table `GROUP BY` against a
  512m MySQL stayed inside its cap but wrote 2.6 GB of temp data and ran for
  over eleven minutes.
- **CPU.** `*_CPUS` bounds each service, but on a burstable instance type
  every container spends the same CPU credits.

## Budget worksheet

1. **Available** = host RAM − what the OS, Docker itself, and every
   unrelated service on the box already use. Measure those, don't guess.
2. **Per instance** = available ÷ number of instances.
3. **Split per instance** across its services, starting from the database
   (the largest and least forgiving), then PHP, then the rest.
4. **Check the sum.** If the per-instance caps × instances exceed
   *available*, the isolation guarantee is gone — shrink the tier, drop
   services an instance doesn't use, or put the fleet under one parent cap
   (see "Fleet cap" below) so at least the unrelated services stay safe.

Caps are worst-case ceilings, not usage. Four lightly used dev sites
typically *use* far less than their caps; the caps exist for the moment
one of them doesn't.

### Worked example — four dev sites on a 4 GB host

A 4 GB instance reports about 3.7 GB usable. Say other services on the box
already hold ~710 MB, and the OS plus Docker ~300 MB: about 2.7 GB is left,
~670 MB per site.

A small tier per site, database knobs from the next section:

| Service | Cap | Knobs |
|---|---|---|
| php | 320m | `PHP_PM_MAX_CHILDREN=4`, `PHP_PM_MODE=ondemand` |
| nginx | 64m | — |
| valkey-state | 128m | `VALKEY_STATE_MAXMEMORY=32mb` |
| valkey-cache | 64m | `VALKEY_CACHE_MAXMEMORY=32mb` |
| postgres | 256m | small tier below |

That is 832m per site, ~3.3 GB for four — over the 2.7 GB available. On a
box this size the realistic choices are: drop Valkey on sites that don't use
it (−192m each), profile `valkey-state` below its rule on this host (see
"Valkey" below — 32mb passed at 96m on the reference host), pick the lighter
engine (MySQL's small tier needs 384m against Postgres' 256m), and put the
whole fleet under one parent cap so the unrelated services stay protected
even if several sites peak at once.

## Databases

The model that holds for all three engines:

```
cap ≥ fixed footprint + concurrent heavy sessions × per-session peak + spill headroom
```

- **Fixed footprint** — buffer pool / shared buffers once warm, plus the
  server's own base.
- **Per-session peak** — what one connection can allocate for a sort, hash,
  or in-memory temp table.
- **Spill headroom** — sorts and temp tables that spill to disk create dirty
  page cache, which is charged to the container and can't be reclaimed until
  it is written back. Bursts of ~90 MiB were measured under load; a cap with
  no headroom for them OOMs depending on timing.

`max_connections` is what bounds "concurrent sessions", so it is the lever
that makes the formula finite. Beyond it, new clients get a refused
connection (`1040 Too many connections` on MySQL/MariaDB) instead of the
server being killed. Size it from what can actually connect — a request
measured at one database connection on this stack — so

```
max_connections ≥ PHP_PM_MAX_CHILDREN + ws + cron + concurrent CLI/async jobs + a few admin sessions
```

The default `PHP_PM_MAX_CHILDREN=20` gives the default of 30.

### Knobs, defaults, and a small tier

| Engine | Knob | Stock | Shipped default | Small tier |
|---|---|---|---|---|
| MySQL | `MYSQL_MEM_LIMIT` | — | 512m | 384m |
| | `MYSQL_INNODB_BUFFER_POOL_SIZE` | 128M | 128M | 32M |
| | `MYSQL_MAX_CONNECTIONS` | 151 | 30 | 15 |
| | `MYSQL_TEMPTABLE_MAX_RAM` | 1G | 64M | 32M |
| | `MYSQL_PERFORMANCE_SCHEMA` | ON | OFF | OFF |
| MariaDB | `MARIADB_MEM_LIMIT` | — | 640m | 384m |
| | `MARIADB_INNODB_BUFFER_POOL_SIZE` | 128M | 128M | 32M |
| | `MARIADB_MAX_CONNECTIONS` | 151 | 30 | 15 |
| | `MARIADB_TMP_TABLE_SIZE` | 16M | 4M | 4M |
| PostgreSQL | `POSTGRES_MEM_LIMIT` | — | 640m | 256m |
| | `POSTGRES_SHARED_BUFFERS` | 128MB | 128MB | 32MB |
| | `POSTGRES_EFFECTIVE_CACHE_SIZE` | 4GB | 256MB | 128MB |
| | `POSTGRES_WORK_MEM` | 4MB | 4MB | 4MB |
| | `POSTGRES_MAINTENANCE_WORK_MEM` | 64MB | 64MB | 64MB |
| | `POSTGRES_MAX_CONNECTIONS` | 100 | 30 | 15 |
| | `POSTGRES_MAX_PARALLEL_WORKERS_PER_GATHER` | 2 | 0 | 0 |
| | `POSTGRES_SHM_SIZE` | 64m | 128m | 64m |

How they were validated, all with every session running a `GROUP BY` plus
an `ORDER BY` over a large table at once:

- **Shipped defaults** — 30 concurrent sessions over ~450 MB of data: MySQL
  peaked at 457 MiB inside 512m; MariaDB and PostgreSQL ran clean at 640m.
  At 384m and 512m both were OOM-killed.
- **Small tier** — 15 concurrent sessions over ~90 MB, run right after a
  bulk load: MySQL and MariaDB peaked around 255 MiB anonymous memory and ran
  clean at 384m; at 256m both were killed on the first burst. PostgreSQL
  stayed around 64 MiB plus its shared buffers, clean at 256m.

Size the buffer pool / shared buffers from the data: the working set (tables
plus indexes you actually read) is the ceiling worth paying for. Anything
above it is wasted; far below it, reads go to disk.

### MySQL

- **`performance_schema`** is the largest single cost: 224 MiB of a 453 MiB
  idle server with stock settings. Off by default here; turn it on
  (`MYSQL_PERFORMANCE_SCHEMA=ON`, plus ~250 MiB of cap) while diagnosing.
- **`temptable_max_ram` is one pool for the whole server**, so concurrency
  doesn't multiply it — past it, internal temp tables spill to disk. The
  stock 1G is as large as a whole small cap.
- **The fixed floor barely moves with the buffer pool:** ~155 MiB idle at a
  32M pool against ~162 MiB at 128M. That base is why MySQL is the most
  expensive engine to run one per site.
- **Binary logging is disabled** (`--disable-log-bin`). Stock MySQL keeps
  30 days of binlogs on disk, which buys nothing on a dev-only profile with
  no replicas.

### MariaDB

- **`tmp_table_size` is per session** — unlike MySQL's pool, 30 sessions ×
  the stock 16M is 480 MB. `MARIADB_TMP_TABLE_SIZE` sets both it and
  `max_heap_table_size` (the effective limit is the smaller of the two).
- `key_buffer_size` (8M) serves MyISAM only; `aria_pagecache_buffer_size`
  (16M) caches the Aria tables MariaDB uses for on-disk temp tables. Both
  are fixed in the compose file.
- `performance_schema` is already off in stock MariaDB.

### PostgreSQL

- **`work_mem` applies per sort or hash node, per process**, and hash
  nodes may use `work_mem × hash_mem_multiplier` (2 by default). Every
  parallel worker is one more process running the same plan, so parallel
  query is off by default (`POSTGRES_MAX_PARALLEL_WORKERS_PER_GATHER=0`); on a
  host with spare cores and a larger cap, raising it speeds up big scans.
- **`shared_buffers` shows up as shared memory** in the container's
  accounting once pages are touched — it counts against the cap like
  anything else.
- **`/dev/shm` is 64m in Docker unless `shm_size` says otherwise**, and it
  backs dynamic shared memory for parallel query. Its pages count against the
  cap too.
- **`effective_cache_size` allocates nothing** — it tells the planner how
  much caching to expect. Keep it near the container's cap; the stock 4GB
  describes a host this container doesn't have.

## PHP, nginx, Valkey

- **PHP-FPM.** Size `PHP_MEM_LIMIT` from measured worker RSS, not from
  PHP's `memory_limit`: roughly `PHP_PM_MAX_CHILDREN × avg worker RSS × 1.3 +
  128 MB` of OPcache shared memory (see `docker.md`, "Resource limits &
  tuning"). `PHP_PM_MODE=ondemand` forks workers per request and ends them
  after 10s idle, so an idle site holds none; `dynamic` (the default) keeps
  spares warm and answers bursts faster. Both are build args — rebuild to
  change them.
- **nginx.** ~15 MiB idle; memory follows open connections. Long-lived
  WebSockets are the main driver.
- **Valkey.** `valkey-cache` needs its dataset plus a little overhead: it
  never forks (`appendonly no`, `save ""`). `valkey-state` forks for every
  AOF rewrite and RDB save, and the fork can double its memory — see
  "Valkey" below.
- **`tools`** (2048m) is a build/analysis sandbox. Don't run PHPStan or
  composer on a small shared server while the sites are live.

### Valkey

`valkey-state` holds the sessions and persists them (AOF plus RDB
snapshots). Every AOF rewrite and RDB save forks a child that writes the
snapshot; while it runs, each page the server modifies is copied, and both
copies count against the cap. Under writes spread across the dataset the copy
grows to the whole dataset, so:

```
peak ≈ 2 × dataset + ~10–25 MiB
VALKEY_STATE_MEM_LIMIT ≥ 2 × VALKEY_STATE_MAXMEMORY + 64m
```

The rule assumes the full copy, so it holds whatever the host's speed; the
shipped 128mb / 320m follows it. Size from `maxmemory`, not current use:
`noeviction` lets the dataset reach it before writes start failing.

Measured with `bin/valkey-profile.sh` (see "Load-testing a tier") at
`cpus: 0.5` on the reference host — arm64 Linux VM, 16 KiB pages, no THP,
Valkey 8.1.10:

| `maxmemory` | Cap | Peak (MiB) | Verdict |
|---|---|---|---|
| 32mb | 64m | killed at 62 | FAIL |
| 32mb | 96m | 74 | PASS, 22% margin |
| 128mb | 320m | 249 | PASS, 22% margin |
| 256mb | 384m | killed | FAIL |
| 256mb | 512m | 484 | TIGHT, 5% margin |
| 256mb | 576m | 486 | PASS, 15% margin |

Every completed rewrite copied at least 95% of the dataset, 32mb included.

- **What fills it.** Live session keys ≈ new sessions per second × TTL.
  The default TTL is 24 minutes of inactivity: `CF_SESS_EXPIRATION=0` falls
  back to PHP's `session.gc_maxlifetime` (1440 s). Every
  `CF_SESS_TIME_TO_UPDATE` (300 s) a session gets a new ID and the old key
  stays until its own TTL (`CF_SESS_REGENERATE_DESTROY=false`), so an active
  user holds several keys. A page that loads the session creates a key for
  every visit without a cookie — crawlers included. A longer
  `CF_SESS_EXPIRATION` multiplies all of it.
- **Watch the dataset**, and raise `maxmemory` (and the cap with it) before
  it gets close:

  ```bash
  docker exec <instance>-valkey-state-1 sh -c 'REDISCLI_AUTH="$(cat /run/secrets/valkey_password)" valkey-cli INFO memory' | grep -E '^(used_memory|maxmemory):'
  ```

- **When it is full,** every request that starts a session fails with a
  500, cookie or not: the session driver's lock write is refused and the
  Redis client throws (`OOM command not allowed when used memory >
  'maxmemory'` in the app log). Nothing is evicted.
- **Below the rule** is fine where the profiler passes at that exact cap on
  that host. Its load overwrites the whole dataset at full speed during the
  rewrite, harder than session traffic ever does.
- **Versions.** 8.1.10 and 9.1.2 measured identically: 278 bytes per key
  holding a 200-byte value, same peak. 8.0.11 took 291 bytes, so the same
  `maxmemory` holds ~5% fewer sessions; its peak against the cap is the
  same, since `maxmemory` bounds the dataset either way. Profile a new
  Valkey tag with `--image` before moving the pin — the table belongs to
  8.1.10.

## Fleet cap

`CGROUP_PARENT` places every container of every instance under one parent
cgroup. A memory limit on that parent caps the whole fleet at once, so the
sites can't take memory from unrelated services on the box however their
own caps add up. Inside the parent, the per-container caps still apply;
when the parent itself fills, the kernel picks its victim among the fleet's
containers.

The value depends on Docker's cgroup driver (`docker info | grep -i
'cgroup driver'`): with `systemd` — the default on current Debian and Ubuntu
hosts — it must be a slice name; with `cgroupfs`, a path.

```ini
# /etc/systemd/system/manager-fleet.slice
[Slice]
MemoryAccounting=yes
MemoryMax=2700M
```

```bash
sudo systemctl daemon-reload && sudo systemctl start manager-fleet.slice
# every instance's docker.env:
CGROUP_PARENT=manager-fleet.slice
# after recreating the containers:
systemd-cgls --unit manager-fleet.slice          # lists the fleet's containers
cat /sys/fs/cgroup/manager-fleet.slice/memory.max
```

Leave `CGROUP_PARENT` empty for Docker's default placement.

## Measure, don't guess

Read-only, by container name (`<instance>-<service>-1`):

```bash
docker stats --no-stream                                   # usage vs cap, every container
docker exec <c> cat /sys/fs/cgroup/memory.stat             # anon = not reclaimable; file = page cache
docker exec <c> cat /sys/fs/cgroup/memory.events           # oom_kill counter for this container
docker events --since 1h --filter event=oom                # which containers were OOM-killed
docker inspect <c> --format '{{.RestartCount}} {{.State.OOMKilled}}'
```

`docker stats` includes reclaimable page cache, so a database sitting near
its cap is not necessarily in danger — `anon` in `memory.stat` is the part
that is. Confirm what an engine actually runs with, rather than what the env
file says:

```bash
docker exec <c> sh -c 'MYSQL_PWD="$(cat /run/secrets/db_root_password)" mysql -uroot -e "SHOW GLOBAL VARIABLES LIKE \"innodb_buffer_pool_size\""'
docker exec <c> psql -U <DB_USER> -d <DB_NAME> -c 'SHOW shared_buffers'
```

### Load-testing a tier

Load-test a tier before trusting it, and again whenever a pinned database or
Valkey image changes — every number in this file belongs to the image
versions it was measured on. Run it on a throwaway instance, never next to
live sites: it saturates the disk it shares with them. The shape that
validated the tables above, shown for PostgreSQL (MySQL/MariaDB: the same
table built with a recursive CTE — raise `cte_max_recursion_depth` on MySQL,
`max_recursive_iterations` on MariaDB — and the same two queries):

```bash
c=<instance>-postgres-1; N=<max_connections>
docker exec $c psql -U <DB_USER> -d <DB_NAME> -c "CREATE TABLE tune_probe AS
  SELECT n AS id, floor(random()*100000)::int AS k, repeat(md5(n::text),5) AS pad
  FROM generate_series(1,300000) n; CREATE INDEX ON tune_probe(k); ANALYZE tune_probe;"
Q="SELECT k, COUNT(*), MAX(pad) FROM tune_probe GROUP BY k ORDER BY 2 DESC LIMIT 5; SELECT * FROM tune_probe ORDER BY pad DESC LIMIT 5"
for i in $(seq 1 $N); do timeout 300 docker exec $c psql -U <DB_USER> -d <DB_NAME> -c "$Q" >/dev/null & done
while [ -n "$(jobs -r)" ]; do docker exec $c awk '/^anon /{print $2/1048576 " MiB anon"}' /sys/fs/cgroup/memory.stat; sleep 1; done
docker exec $c cat /sys/fs/cgroup/memory.events; docker inspect $c --format '{{.RestartCount}}'
docker exec $c psql -U <DB_USER> -d <DB_NAME> -c 'DROP TABLE tune_probe'
```

Size the table like your real data, and run the burst right after the bulk
load as well as on a fresh restart — the two behave differently. Reading the
result:

- **Pass** means `oom_kill 0` and an unchanged restart count, with peak
  `anon` leaving room for the spill headroom above. `docker stats` climbing
  to the cap is not a failure — that is reclaimable page cache.
- **The heap stays high after a bulk load.** Freed memory is not returned to
  the OS right away, so the first burst after a load starts from a higher
  floor than one after a restart. A tier that only passes after a restart
  fails in practice.
- **`memory.peak` can't be reset** from inside the container; recreate it to
  start a clean measurement.
- **A client-side `timeout` doesn't stop the query.** The server keeps
  running it and holding the connection slot, so the next run can hit
  `Too many connections` against its own leftovers. Restart the database
  between runs.

**Valkey** has its own profiler, run on the host — it needs `docker` and
`python3`, and reads the instance's resolved compose config through
`docker_manage.sh`:

```bash
bin/valkey-profile.sh -e <instance>                                  # the instance's own values
bin/valkey-profile.sh -e <instance> --maxmemory 256mb --cap 576m     # try values before editing docker.env
bin/valkey-profile.sh -e <instance> --image valkey/valkey:<new tag>  # try an image before moving the pin
```

It starts a throwaway copy of the instance's `valkey-state` — same image,
command, conf, cap, and `cpus`, with an empty volume and a random password;
never the live container or its data. Each run fills it to ~95% of
`maxmemory` and forces an AOF rewrite while an uncapped client overwrites
the whole dataset. Three runs by default (`--runs`); the worst one counts.
It refuses to start on a cgroup v1 host, or when the host has less than the
cap plus 256 MiB available. Output is `key=value` lines; the last one is the
verdict:

| `verdict` | Exit | Meaning |
|---|---|---|
| `PASS` | 0 | Nothing was killed and the peak left at least 10% of the cap. |
| `TIGHT` | 1 | Nothing was killed, but the margin is under 10% — a busier moment away from a kill. Raise the cap. |
| `FAIL` | 1 | The kernel killed the server (`server_oom_killed=true`) or the rewrite child (`oom_kills` above 0, `rewrite_status` not `ok`). Raise the cap or lower `maxmemory`. |
| `INVALID` | 2 | The write load stopped before the rewrite finished (`writer_ran_through=false`), so the peak is understated. Re-run it. |

Reading the rest:

- **A killed rewrite child is the easy one to miss:** the server keeps
  running, so its restart count doesn't move. The cgroup's `oom_kill`
  counter records every kill inside the container, and the profiler reads
  it.
- **`run.N.cow_mib`** is how much the rewrite copied. Close to the dataset
  (`fill_percent` of `maxmemory`) is the full copy the rule assumes.
- **`run.N.sampled_peak_mib`** is the peak memory of the rewrite phase,
  sampled every 50 ms; **`estimate_mib`** (memory before the fork plus
  `cow_mib`) should land a few MiB under it. It stands in when the sampler
  can't find the container's cgroup.
- **`host.overcommit_memory`** other than 1 prints a warning: on a host
  short of free memory the fork can fail outright ("Can't rewrite append
  only file in background: fork: Cannot allocate memory" in
  `docker logs`). **`host.thp`** other than `never` or `absent` is also
  worth fixing. Both are host prerequisites in `docker.md`, "Resource limits
  & tuning".
- **`host.page_size`** is 4096 on most x86 and Graviton hosts; the table in
  "Valkey" was measured at 16384.

Run it while the host's sites are idle: each run keeps a CPU busy and writes
the dataset to disk several times.
