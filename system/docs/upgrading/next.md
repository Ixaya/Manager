# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### nginx trusts no proxy by default: set `NGINX_TRUSTED_PROXIES` before deploying the new image

Reaches a project when it reconciles `docker/Dockerfile`,
`docker/nginx/nginx.conf`, `docker/nginx/conf.d/app.conf`, the new
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
- **FPM's `/status` and `/ping` no longer pass through nginx**; both now
  reach the app, which answers 404. The healthchecks never used them. A
  monitor that polled them over HTTP connects over FastCGI to `php:9000`
  instead, or the project re-adds a loopback-only location (same section).
