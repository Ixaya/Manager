# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### nginx trusts no proxy by default: set `NGINX_TRUSTED_PROXIES` before deploying the new image

Reaches a project when it reconciles `docker/Dockerfile`,
`docker/nginx/nginx.conf`, `docker/nginx/conf.d/app.conf`,
`docker/nginx/conf.d/ws.conf`, the new
`docker/nginx/docker-entrypoint.d/15-real-ip.sh`, `docker/docker-compose.yml`
and `docker/env/sample.docker.env`; `composer update` alone changes nothing.
nginx's configs are baked into the image, so on a server the change lands
with the **first deploy of the rebuilt image**, whatever its compose file
says.

nginx used to trust `X-Forwarded-For` from every private range, so any
client or container inside one could pick the IP the app logs, locks out
and rate-checks against. The trust list is now rendered at every start
from two variables, read from nginx's compose `environment:` block:

- **`NGINX_TRUSTED_PROXIES`** — addresses or CIDRs of every proxy between
  the client and nginx, space- or comma-separated. **Empty, the default,
  trusts no proxy.**
- **`NGINX_REAL_IP_HEADER`** — empty means `X-Forwarded-For`, the header
  the old config used.

What an existing project does:

- **Add the nginx `environment:` block to every compose file a server runs,
  and set `NGINX_TRUSTED_PROXIES` there, before deploying the new image.**
  A server whose copy lacks it still starts, trusting no proxy: behind a
  proxy every client appears as the proxy's address in the nginx and FPM
  access logs, `api_log`, `login_attempt` and users' IPs; login lockout
  counts every failure for an identity from that one address, so failed
  attempts on a username lock it out for everyone; IP white/blacklists,
  `IP_ADDRESS` limits and `CACHE_BYPASS_IPS` see only the proxy. The
  `[real-ip]` line in nginx's startup log shows what was applied.
- **List exact proxy addresses, not the old private ranges**, unless every
  address in a range is a proxy you run. `docs/development/docker-server.md`,
  "Server readiness", covers multi-hop chains and single-value CDN headers,
  where the edge must accept only the CDN's ranges or overwrite the header.
- **The forwarded scheme follows the same list.** nginx used to honor
  `X-Forwarded-Proto` and `Front-End-Https` from any peer, so anyone reaching
  nginx past the edge could make `is_https()` return true. Now
  `X-Forwarded-Proto: https` sets `HTTPS=on` only when the peer is on
  `NGINX_TRUSTED_PROXIES`, and neither raw header reaches PHP or the WebSocket
  server. With the list empty, a TLS-terminating proxy looks like plain HTTP
  to the app: `force_https` refuses every request, and secure CSRF cookies
  are not sent. Code that read
  `$_SERVER['HTTP_X_FORWARDED_PROTO']` directly calls `is_https()` instead.
- **FPM's `/status` and `/ping` no longer pass through nginx**; both now
  reach the app, which answers 404. The healthchecks never used them. A
  monitor that polled them over HTTP connects over FastCGI to `php:9000`
  instead, or the project re-adds a loopback-only location (same section).

### nginx runs non-root and takes `APP_GROUP`

Reaches a project when it reconciles `docker/Dockerfile`,
`docker/nginx/nginx.conf`, `docker/docker-compose.yml` and
`docker/env/sample.docker.env`; `composer update` alone changes nothing.
The image and the compose file change together; neither starts with the
other's previous version: the new image on the old compose exits on
`real_ip.conf.tmp: Permission denied`, and the old image on the new compose
exits on `open() "/var/run/nginx.pid" failed (30: Read-only file system)`.

nginx used to start as root and drop its workers to the image's `nginx`
user (101:101), so it read media as "other" and a media tree that "other"
can't read returned 403. It now runs entirely as uid 101 with `APP_GROUP`
as its group, so a group-readable tree (`0750` directories, `0640` files)
serves. The default (`www-data`, gid 82) needs no change.

- **Add nginx's `user: "101:${APP_GROUP:-www-data}"`, the
  `/var/cache/nginx:uid=101,mode=0700` tmpfs, and remove the `/var/run`
  tmpfs, in every compose file a server runs, before deploying the new
  image.** Without the tmpfs options the image restart-loops on
  `real_ip.conf.tmp: Permission denied`.
- **Port the `--chmod=644` on nginx's `nginx.conf` and `conf.d/` `COPY`
  lines, and the `USER 101` before its `EXPOSE`.** A config baked `0600`
  from the checkout is unreadable to uid 101, and nginx restart-loops on
  `open() "/etc/nginx/nginx.conf" failed (13: Permission denied)`.
- **A custom named `APP_GROUP` must exist in the nginx image too**; add it
  in the `nginx-app` stage, or use a numeric group.
- `:80` binds through Docker's per-container
  `ip_unprivileged_port_start=0` (Docker 20.10 or later), which a container
  under `network_mode: host` doesn't get: nginx can't run on the host
  network.

### Build context drops secrets and server data; `cron` mounts media, `ws` no longer mounts private

Reaches a project when it reconciles `.dockerignore` and
`docker/docker-compose.yml`; `composer update` alone changes nothing.

The sample's `.dockerignore` allowlisted all of `docker/`, `private/` and
`public/` whole, so every build sent each instance's secrets, env files and
private file store to the builder — on a remote builder, uploaded on every
build — and `COPY public/` baked the checkout's `public/media/` into both
images, uploads included. The allowlist now takes only `docker/php/` and
`docker/nginx/` from `docker/`, drops `private/`, and excludes
`public/media/` and `public/.htaccess`.

- Replace `!docker/` with `!docker/php/` and `!docker/nginx/`; remove
  `!private/`; add `public/media/` and `public/.htaccess` as exclusions.
- A project whose Dockerfile reads another `docker/<dir>` (a `COPY` or a
  `--mount=type=bind`) allowlists that directory too, or the build fails
  with a not-found error on that path.
- **`cron` now mounts `MEDIA_PATH`**, read-write. Its jobs run inside the
  `cron` container, which saw only the image's build-time copy of
  `public/media` and could not write to it; once that copy is gone it has
  no `public/media` at all. Add the mount to every compose file a server
  runs before deploying the rebuilt image.
- **`ws` no longer mounts `PRIVATE_PATH`**; the WebSocket server reads no
  files. A project whose own WebSocket code reads `private/` keeps the
  mount.
- Images built before this change still carry whatever `public/media/` held
  at build time; rebuild, and prune old tags from the registry if that
  checkout held real uploads.

### nginx no longer serves dotfiles under `/assets/` and `/media/`

Reaches a project when it reconciles `docker/nginx/conf.d/app.conf`, with
the rebuilt nginx image. The dotfile deny sat below the `/assets/` and
`/media/` locations, and nginx takes the first matching regex location, so
a dotfile under either path was served. It now comes first; a project that
added its own regex locations above it moves the deny above those too.

### PHP image: `10-app.ini` may never have loaded; pool size and memory limit move to runtime

Reaches a project when it reconciles `docker/Dockerfile`,
`docker/php/fpm.d/` (`www.conf.template` is now `www.conf`),
`docker/php/conf.d/10-app.ini`, `docker/php/entrypoint.sh`,
`docker/nginx/nginx.conf` (a comment only), `docker/docker-compose.yml` and
`docker/env/sample.docker.env`; `composer update` alone changes nothing.

**Port the `--chmod=644` on the `conf.d/` and `fpm.d/` `COPY` lines even if
you skip the rest.** A `COPY` takes the build context's file mode, so an
image built from a checkout made under umask 077 has a `0600` `10-app.ini`
the app identity can't read, and PHP skips it without a warning: PHP
defaults apply instead — 2M uploads, 128M `memory_limit`,
`session.use_strict_mode` off, `expose_php` on (nginx still strips the
header). Check a running image: `php --ini` inside `php` must list
`10-app.ini`.

The pool and `memory_limit` used to be baked at build; they now come from
the environment and apply on recreate, no rebuild:

- **`PHP_PM_MAX_CHILDREN`, `PHP_PM_MODE`** (2.4.0) keep their names, and an
  instance `docker.env` that sets them keeps working. They now reach the
  container through the `php` service's `environment:` block; a server
  compose file without that block runs the shipped defaults (20,
  `dynamic`) whatever it was built with, so add the block before deploying
  the new image.
- **`PHP_MEMORY_LIMIT`** (`php`) and **`PHP_CLI_MEMORY_LIMIT`** (`ws`,
  `cron`, `cli`) are new, both 256M, the value `10-app.ini` had. A CLI job
  started from `php` gets the `php` value.
- The entrypoint refuses to start `php`/`ws` on an invalid value.
- Request body size stays baked: nginx's `client_max_body_size` and PHP's
  `upload_max_filesize`/`post_max_size` change together, with a rebuild.
