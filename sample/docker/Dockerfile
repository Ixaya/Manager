# syntax=docker/dockerfile:1
#
# Two real build targets: php-app (php, ws, cron, cli services), nginx-app.
# Always build and tag both together with the same IMAGE_TAG — mismatched
# tags mean nginx serves static assets that don't match the running PHP code.

FROM alpine:3.24.2 AS supercronic-bin
# Cron daemon binary, safe to run as PID 1. Used by the cron service only.
# TARGETARCH is populated automatically by BuildKit (amd64 or arm64). The
# binary is verified against a SHA256 pin per arch. To bump: change the
# version below and regenerate pins with bin/pin-checksums.sh supercronic <version>.
ARG TARGETARCH
RUN set -eux; \
    case "$TARGETARCH" in \
      amd64) sha256="a53ae236602c7338aba3fbaff40bda6300eae3b9fedb8261eb06cfe3724430c1" ;; \
      arm64) sha256="02aa0cb229ba09050cba6638059dadb9eedc2276632ea43d6a57a2f8c1629dd5" ;; \
      *) echo "unsupported TARGETARCH: $TARGETARCH"; exit 1 ;; \
    esac; \
    wget -qO /usr/local/bin/supercronic \
      "https://github.com/aptible/supercronic/releases/download/v0.2.49/supercronic-linux-${TARGETARCH}"; \
    echo "${sha256}  /usr/local/bin/supercronic" | sha256sum -c -; \
    chmod +x /usr/local/bin/supercronic

FROM php:8.4.27-fpm-alpine3.24 AS php-base
# php-base: PHP + every extension + production php.ini. Shared by
# vendor-builder and php-app so the extension build runs once.

RUN set -eux; \
    apk add --no-cache \
        bash \
        fcgi \
        procps \
        libpng libjpeg-turbo freetype \
        libzip \
        oniguruma \
        libpq; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        libtool \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pgsql \
        pdo_mysql \
        pdo_pgsql \
        pcntl \
        sockets \
        opcache \
        gd \
        mbstring \
        zip; \
    # PIE is pinned like supercronic: bump the version in the
    # URL and regenerate the SHA256 with bin/pin-checksums.sh pie <version>. Extension
    # versions are exact; confirm a bump with `php --ri <name>` in the built image.
    pie_sha256="fe70c59738cf56ec00a818cf9cb9593da171aac86f5f1f859995b8839dfd2cc7"; \
    curl -fsSL -o /tmp/pie.phar \
        "https://github.com/php/pie/releases/download/1.5.2/pie.phar"; \
    echo "${pie_sha256}  /tmp/pie.phar" | sha256sum -c -; \
    # pie install also writes the conf.d enable files; no docker-php-ext-enable
    php /tmp/pie.phar install phpredis/phpredis:6.3.0; \
    php /tmp/pie.phar install msgpack/msgpack-php:3.0.1; \
    rm -rf /tmp/pie.phar /tmp/pear; \
    apk del --no-network .build-deps; \
    # bin/cli_run.sh (and any tooling) hard-codes /usr/bin/php; the FPM image
    # ships php at /usr/local/bin/php, so provide the expected path.
    ln -sf /usr/local/bin/php /usr/bin/php

# Activates php.ini-production as the base config — conf.d/ overrides win
# over these defaults, not the other way around.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHP runtime configuration (php.ini overrides + opcache). --chmod because the
# build context's mode is copied as-is, and PHP silently skips an unreadable
# scanned ini; 644 is safe only while conf.d/ holds no subdirectories.
COPY --chmod=644 docker/php/conf.d/  /usr/local/etc/php/conf.d/

WORKDIR /var/www/html

FROM php-base AS vendor-builder
# vendor-builder: installs composer dependencies into vendor/. Only stage
# with composer — the runtime image never has it.

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# Composer 2 binary — present only in this stage.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Only the manifests — cached until composer.{json,lock} change. The
# optional composer_auth secret only helps GitHub API rate limits;
# ixaya/manager is public on Packagist, no credentials required.
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json,required=false \
    composer install \
        --no-dev \
        --optimize-autoloader \
        --classmap-authoritative \
        --no-scripts \
        --prefer-dist

FROM php-base AS php-app
# php-app: PHP runtime for php/ws/cron/cli services. Takes vendor/ from
# vendor-builder and application code from the build context.

# supercronic (used by the cron service).
COPY --from=supercronic-bin /usr/local/bin/supercronic /usr/local/bin/supercronic

# vendor/ from vendor-builder — self-contained autoloader; the composer
# manifests are not copied into runtime.
COPY --from=vendor-builder /var/www/html/vendor ./vendor

# Application code — only the directories the runtime actually needs.
COPY application/ ./application/
COPY public/      ./public/
# CLI runner: only docker/php/bin/ is copied (Alpine paths: /bin/nice,
# /usr/local/bin/php). The repo's own bin/ is deliberately not copied.
COPY --chmod=755 docker/php/bin/ ./bin/

# Optional smoke-test module, baked only when INCLUDE_SMOKE_MODULE=true
# (local images only). Runtime-guarded too — 403s outside development.
ARG INCLUDE_SMOKE_MODULE=false
RUN --mount=type=bind,source=docker/php/smoke,target=/mnt/smoke \
    set -eux; \
    if [ "$INCLUDE_SMOKE_MODULE" = "true" ]; then \
        mkdir -p ./application/modules/smoke; \
        cp -r /mnt/smoke/. ./application/modules/smoke/; \
        echo "INCLUDE_SMOKE_MODULE=true: baked docker/php/smoke into application/modules/smoke"; \
    fi

# FPM pool: PM values are ${VAR:-default} placeholders FPM reads from the
# environment at start, so resizing needs a recreate, not a rebuild. The
# build-time test runs with them unset, validating the shipped defaults.
# No user/group in the pool: compose starts the container as APP_USER, so
# FPM has no privilege to drop. -R is needed only because this build step
# runs as root and FPM refuses to validate a user-less pool as root.
COPY --chmod=644 docker/php/fpm.d/www.conf       /usr/local/etc/php-fpm.d/www.conf
COPY --chmod=644 docker/php/fpm.d/zz-global.conf /usr/local/etc/php-fpm.d/zz-global.conf
RUN set -eux; \
    rm -f /usr/local/etc/php-fpm.d/www.conf.default \
          /usr/local/etc/php-fpm.d/zz-docker.conf; \
    php-fpm -t -R

COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

# Healthcheck: asks FPM to execute its own /ping endpoint over FastCGI.
# Requires `ping.path = /ping` in the pool config and the fcgi package.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
      cgi-fcgi -bind -connect 127.0.0.1:9000 2>&1 | grep -q pong || exit 1

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]

FROM nginx:1.30.6-alpine3.24 AS nginx-app
# nginx-app: serves static assets + reverse-proxies to php:9000. Static
# files are baked in from php-app (no shared volume needed for code/assets).

COPY --from=php-app /var/www/html/public /var/www/html/public

COPY docker/nginx/nginx.conf   /etc/nginx/nginx.conf
RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx/conf.d/      /etc/nginx/conf.d/
# Run by the stock /docker-entrypoint.sh before nginx; it skips any script
# without the exec bit, which would leave nginx.conf's include missing.
COPY --chmod=755 docker/nginx/docker-entrypoint.d/ /docker-entrypoint.d/

# HTTP on :80, WebSocket vhost on :8080. Master runs as root (stock image
# behavior, needed to bind :80); workers drop to the nginx user.
EXPOSE 80 8080
CMD ["nginx", "-g", "daemon off;"]
