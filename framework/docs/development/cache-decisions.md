# Cache adapter — design decisions

> Scope: why `apc`/`apcu` are absent from this stack's Dockerfile and from
> the framework's own cache default. Not an adapter reference or catalog —
> this repo only ever uses `redis` (Docker) and `file` (bare install).

One-time rationale with evidence, kept compact.

**`apcu` removed from the Dockerfile; `apcu_bc` never added.**
Decision: the `php-base` stage installs only `redis` and `msgpack`. `apcu`
is not installed, and `apcu_bc` (the apc-compat shim) was not added either.
Why: every environment using this compose file runs the php/ws/cron/cli
containers with `read_only: true`, and every project env file sets
`CACHE_ADAPTER=redis` explicitly — `apcu` was never actually selectable, so
installing it was dead weight. Separately, `apc` (the extension `apcu`
would have stood in for) is gone from core on PHP 8.x entirely, and this
framework's vendored CI3 fork (`nielbuys/framework`) has no `apcu` driver at
all — `CI_Cache`'s `$valid_drivers` list only has `apc`, not `apcu`, so
requesting `adapter => apcu` hard-crashes via
`CI_Driver_Library::load_driver()` regardless of whether the apcu extension
is installed. `apcu_bc` was evaluated as a way to make `apc` itself work
against an apcu-only PHP build, and rejected: it does not build on PHP >=
8.0 at all.
Evidence: live build attempt against this repo's own `php-base` image,
2026-09-10 — `pecl install apcu apcu_bc` failed compiling
`apcu_bc-1.0.5/php_apc.c`, with apcu's own header emitting `#error Not
supported on PHP >= 8.0` (`/usr/local/include/php/ext/apcu/apc_arginfo.h`).
Separately, a probe (`sample/application/modules/probes/controllers/api/Cache_adapter.php`)
confirmed `extension_loaded('apc') === false`, no `apc_*` compat functions
exist, and `$this->load->driver('cache', ['adapter' => 'apcu'])` logs
`Invalid driver requested: Cache_apcu` and hard-fatals.
Cost: none — nothing in this repo ever selected `apc`/`apcu`.
Revisit when: `nielbuys/framework` ships a real `apcu` driver, or a
maintained apc-compat extension exists for PHP 8.x.

**Framework's package-level `CACHE_ADAPTER` default: `file`/`dummy`, not
`apc`/`file`.**
Decision: `system/package/config/cache.php`'s fallback (mirrored in
`sample/.env.sample`) changed from `adapter => 'apc', backup => 'file'` to
`adapter => 'file', backup => 'dummy'`.
Why: `apc` as a default value is dead weight on PHP 8.x for the same reason
above — it can never resolve, only ever degrade. `apcu` was tried as the
replacement default and rejected for the same reason it was pulled from the
Dockerfile: it crashes instead of degrading, which is worse than what it
replaced. `file` needs no extension and no external daemon, matching this
default's actual audience — a bare, low-volume, non-Docker install with no
cache infrastructure provisioned; Docker environments override this default
explicitly to `redis`/`valkey` in every project env file and are
unaffected. The backup is `dummy`, not `file`: a `file`-primary that also
fails degrades to its own backup value, and repeating `file` as its own
backup only produces a redundant "adapter file and backup file both
unavailable" log line instead of the correct single-step fallback to the
no-op `dummy` driver.
Evidence: same probe run as above; `CI_Cache::__construct()` confirmed to
cascade `apc` -> `file` -> `dummy` (not crash) when both the adapter and
backup are unsupported — `Cache_dummy::is_supported()` unconditionally
returns `true`.
Cost: an install that was silently relying on `apc` degrading to `file`
gets the same outcome under the new default (`file` is now primary
instead of backup) — no behavior change for that case.
Revisit when: a bare/non-Docker deploy pattern gains a standard bundled
local daemon worth defaulting to instead of `file`.
