# WebSocket server

> Scope: the current runtime behavior of `MGR_Websocket_lib`'s long-running
> server process — log levels and connection-capacity handling. For how
> application code calls `generateLink()`/`publish()` to use the feature,
> see the `mgr-cache-websockets` skill instead; this document doesn't repeat
> that.

## Overview

The WebSocket server is a separate long-running process
(`bin/cli_run.sh manager websockets serve`), built on
`amphp/websocket-server` with Redis pub/sub (`amphp/redis`) as the fan-out
transport. Backend code never talks to it directly — it publishes to Redis
via `cache->publish()`, and the server relays matching messages to the
clients connected on that channel.

## Log levels

- **info/notice** — process lifecycle only: server start/stop, cleanup, and
  Redis subscribe start/reconnect state transitions. Each fires at most once
  per process lifetime, or once per reconnect (a failure/recovery event, not
  steady-state traffic).
- **debug** — everything that scales with traffic: per-client
  connect/subscribe/disconnect, per-message Redis receive/broadcast, and
  per-channel gateway creation. At `info` in production these dominate the
  log and bury the signal — a 90-second production sample showed roughly 20
  client connect/disconnect events, each producing 2-3 `info` lines, on a
  quiet system.
- **warning/error/critical** — unchanged, reserved for real failure or
  denial conditions (Redis errors, invalid client input, broadcast
  failures, denied connections). These survive raising the production log
  floor to `warning`.

## Connection capacity

- **Per-IP limit** (`max_connections_per_ip`) is enforced and logged by the
  vendor library itself
  (`Amp\Http\Server\Driver\ConnectionLimitingClientFactory`) — a denied IP
  gets `WARNING: Client denied: too many existing connections from {ip}`
  through the same logger instance passed to `SocketHttpServer`. No
  framework code is involved.
- **Global limit** (`max_connections`) is NOT a rejection. It's enforced by
  a semaphore in `ConnectionLimitingServerSocket::accept()` that blocks the
  raw TCP accept until a slot frees — new connections queue silently,
  invisible to application code, and the vendor library logs no signal when
  the cap is hit. `MGRWebsocketsClientHandler` tracks its own live
  connection count and logs:
  - `WARNING: WebSocket connections at capacity ({n}/{max}); new
    connections will start queuing` when a connect fills the last slot
  - `WARNING: WebSocket connections back below capacity ({n}/{max})` when a
    disconnect frees one

## Operational nuance: abandoned queued connections

Observed via a live Docker smoke test (`max_connections=1`): a client that
gives up waiting client-side while queued behind the global limit still
gets processed once a slot frees — the TCP connection was already accepted
into the OS/amphp backlog, only the WebSocket-level handshake was blocked.
The server completes the handshake, tries to send the welcome message, and
logs `ERROR: Failed to send message to client: Client unexpectedly closed;
Code 1006 (ABNORMAL_CLOSE)` before immediately disconnecting. This is
expected, harmless behavior under sustained capacity pressure, not a bug —
worth knowing so it doesn't read as a real failure while debugging an
unrelated connectivity issue.

## Decisions

- **2026-07-27: split `info` into lifecycle-only `info`/`notice` vs
  traffic-scaled `debug`, and added the two capacity warnings above.**
  Production logs at `info` were dominated by per-client-connect and
  per-message broadcast lines, burying the signal needed to debug a
  "starts responding slowly" issue. The global `max_connections` limit had
  no logging at all before this — the vendor library only covers the
  per-IP case (see Connection capacity above).
