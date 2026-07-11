---
name: ixaya-cache-websockets
description: Use when caching data (Redis-backed cache driver, lists/sets/hashes, TTLs), publishing real-time messages, or wiring websocket notifications for async tasks in this codebase. Teaches the extended MGR cache API and the publish/generateLink websocket flow of the ixaya/manager framework.
---

# Ixaya Cache & WebSockets

The framework extends CI3's cache driver with configurable serialization, a
default TTL, Redis collection types (list/set/zset/hash), and Redis pub/sub —
which is also how the backend pushes real-time messages to websocket clients.

Source of truth (read for full signatures):
- `vendor/ixaya/manager/system/libraries/MGR/Cache/Cache.php` — extended cache API
- `vendor/ixaya/manager/system/libraries/MGR/Cache/drivers/Cache_redis.php` — Redis driver (collections, publish, channel prefix)
- `vendor/ixaya/manager/system/libraries/MGR_Websocket_lib.php` — websocket service + link generation

Configs (all env-driven, one-time infra setup — reference them, don't change them):
`vendor/ixaya/manager/system/package/config/cache.php` (`CACHE_ADAPTER`, `CACHE_BACKUP`,
`CACHE_KEY_PREFIX`, `CACHE_SERIALIZATION`, `CACHE_DEFAULT_TTL`, `CACHE_ENABLE_LOGGING`),
`redis.php` (`LIB_REDIS_HOST/PORT/PASSWORD/DATABASE/CHANNEL_PREFIX`, …),
`lib_websocket.php` and `lib_jwt.php` (websocket endpoint + token signing).

## Cache usage

Always load bare — adapter, backup, serialization, and TTL come from config:

```php
$this->load->driver('cache');                       // never pass adapter/backup here

$key = mgr_cache_key('sysusersidx', $params);       // stable key from a prefix + params
$data = $this->cache->get($key);
if (!empty($data)) { /* hit */ }

$this->cache->save($key, $data);                    // TTL = config default (600s)
$this->cache->save($key, $data, 60);                // explicit TTL; < 1 = no expiry (Redis)
$this->cache->delete($key);
```

Behavior to know:
- `save()`/`get()` serialize transparently per config (`php`/`json`/`json_gzip`/`msgpack`);
  arrays and objects round-trip — don't `json_encode` yourself.
- **Cache bypass**: requests from `CACHE_BYPASS_IPS` (pentest/dev) no-op the whole
  cache — `get()` returns `false`, `save()` returns `true`. Code must always
  produce a correct response on a cache miss; never treat the cache as storage.
- The per-call `$ttl` and `$encoding` parameters exist, but prefer the config
  defaults — only override when the user explicitly asks for it, and push back
  if a config-level change would serve better (that's infra's call).

### Redis collection types

Redis-only helpers for accumulating data (each logs an error and returns `false`
on non-Redis adapters). TTL is **reset on every save call**:

```php
$this->cache->save_list($id, $items, $ttl, $encoding, $prepend);  // lPush/rPush
$this->cache->save_set($id, $items);                              // unique members
$this->cache->save_zset($id, ['value' => $v, 'score' => 1.5]);    // sorted set
$this->cache->save_hash($id, ['field' => 'value']);               // field upsert
$this->cache->delete_from($id, $values);          // remove from list/set/zset (auto-detects type)
$this->cache->delete_hash_fields($id, ['field']);
$this->cache->get($id);   // returns the WHOLE collection, typed by the Redis key
```

## Publishing messages (backend → websocket clients)

`publish()` is the one pub/sub method backend code uses. Arrays are auto
JSON-encoded and the configured channel prefix is applied for you:

```php
$this->load->driver('cache');
$this->cache->publish($channel, ['event' => 'report_ready', 'id' => $report_id]);
// returns subscriber count, -1 on error
```

`subscribe()` / `psubscribe()` are **blocking** — they exist for the long-running
websocket service only. Never call them from web/REST/CLI request code.

## WebSockets for async tasks

The websocket server is a separate long-running service (`websocket_lib->serve()`,
run from the `manager` module) — its internals are out of scope; you interact with
it only through `generateLink()` and `publish()`.

The async-task notification flow:

```php
// 1. REST endpoint: hand the client a signed connection URL and kick off the work
$this->load->library('websocket_lib');
$ws_url = $this->websocket_lib->generateLink($user_id, $channel);   // JWT-signed

$this->load->library('async_exec_lib');
$this->async_exec_lib->cli_run_uri('reports/builder/run', [$report_id]);  // see ixaya-cli-modules

$this->response(['status' => 1, 'response' => ['ws_url' => $ws_url]], REST_Controller::HTTP_OK);

// 2. Background job (CLI controller): publish progress/completion to the same channel
$this->load->driver('cache');
$this->cache->publish($channel, ['progress' => 100, 'status' => 'done']);
```

The channel passed to `generateLink()` and to `publish()` must match — the
websocket service relays published messages to the clients connected on that
channel.
