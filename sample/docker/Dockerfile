# syntax=docker/dockerfile:1
#
# Two real build targets: php-app (php, ws, cron, cli services), nginx-app.
# Always build and tag both together with the same IMAGE_TAG — mismatched
# tags mean nginx serves static assets that don't match the running PHP code.

FROM alpine:3.21 AS supercronic-bin
# Cron daemon binary, safe to run as PID 1. Used by the cron service only.
# TARGETARCH is populated automatically by BuildKit (amd64 or arm64). The
# binary is verified against a SHA256 pin per arch. To bump: change the
# version below and regenerate pins with bin/supercronic-checksums.sh.
ARG TARGETARCH
RUN set -eux; \
    case "$TARGETARCH" in \
      amd64) sha256="5adff01c5a797663948e656d2b61d10932369ee437eb5cb54fa872b2960f222b" ;; \
      arm64) sha256="c0576a8eb092e3f79108ed0a2155a25c7766af78456e5a6070e54757ef513bfe" ;; \
      *) echo "unsupported TARGETARCH: $TARGETARCH"; exit 1 ;; \
    esac; \
    wget -qO /usr/local/bin/supercronic \
      "https://github.com/aptible/supercronic/releases/download/v0.2.46/supercronic-linux-${TARGETARCH}"; \
    echo "${sha256}  /usr/local/bin/supercronic" | sha256sum -c -; \
    chmod +x /usr/local/bin/supercronic

FROM php:8.4-fpm-alpine AS php-base
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
        libpng-dev libjpeg-turbo-dev freetype-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pgsql \
        pcntl \
        sockets \
        opcache \
        gd \
        mbstring \
        zip; \
    # Extension versions pinned for reproducible builds. To bump: install the
    # new version, rebuild, and confirm with `pecl list` in the built image.
    pecl install apcu-5.1.28 redis-6.3.0 msgpack-3.0.1; \
    docker-php-ext-enable apcu redis msgpack; \
    rm -rf /tmp/pear; \
    apk del --no-network .build-deps; \
    # bin/cli_run.sh (and any tooling) hard-codes /usr/bin/php; the FPM image
    # ships php at /usr/local/bin/php, so provide the expected path.
    ln -sf /usr/local/bin/php /usr/bin/php

# Activates php.ini-production as the base config — conf.d/ overrides win
# over these defaults, not the other way around.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHP runtime configuration (php.ini overrides + opcache).
COPY docker/php/conf.d/  /usr/local/etc/php/conf.d/

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

# Optional smoke-test module, baked only when INCLUDE_TEST_MODULE=true
# (local images only). Runtime-guarded too — 403s outside development.
ARG INCLUDE_TEST_MODULE=false
RUN --mount=type=bind,source=docker/php/tests,target=/mnt/tests \
    set -eux; \
    if [ "$INCLUDE_TEST_MODULE" = "true" ]; then \
        mkdir -p ./application/modules/tests; \
        cp -r /mnt/tests/. ./application/modules/tests/; \
        echo "INCLUDE_TEST_MODULE=true: baked docker/php/tests into application/modules/tests"; \
    fi

# FPM pool: {{PM_*}} placeholders rendered here at build time from
# PHP_PM_MAX_CHILDREN, baking an immutable pool for the read-only rootfs.
ARG PHP_PM_MAX_CHILDREN=20
COPY docker/php/fpm.d/www.conf.template /tmp/www.conf.template
COPY docker/php/fpm.d/zz-global.conf    /usr/local/etc/php-fpm.d/zz-global.conf
RUN set -eux; \
    mc="${PHP_PM_MAX_CHILDREN}"; \
    case "$mc" in ''|*[!0-9]*) echo "PHP_PM_MAX_CHILDREN must be a positive integer (got '$mc')"; exit 1;; esac; \
    [ "$mc" -ge 1 ] || { echo "PHP_PM_MAX_CHILDREN must be >= 1"; exit 1; }; \
    min=$(( mc / 4 )); if [ "$min" -lt 1 ]; then min=1; fi; \
    max=$(( mc / 2 )); if [ "$max" -lt "$min" ]; then max="$min"; fi; \
    start=$(( min + (max - min) / 2 )); if [ "$start" -lt 1 ]; then start=1; fi; \
    sed \
      -e "s/{{PM_MAX_CHILDREN}}/$mc/g" \
      -e "s/{{PM_START_SERVERS}}/$start/g" \
      -e "s/{{PM_MIN_SPARE}}/$min/g" \
      -e "s/{{PM_MAX_SPARE}}/$max/g" \
      /tmp/www.conf.template > /usr/local/etc/php-fpm.d/www.conf; \
    rm -f /tmp/www.conf.template \
          /usr/local/etc/php-fpm.d/www.conf.default \
          /usr/local/etc/php-fpm.d/zz-docker.conf; \
    echo "Baked FPM pool: pm.max_children=$mc start=$start spare=$min-$max"; \
    php-fpm -t

COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

# Healthcheck: asks FPM to execute its own /ping endpoint over FastCGI.
# Requires `ping.path = /ping` in the pool config and the fcgi package.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
      cgi-fcgi -bind -connect 127.0.0.1:9000 2>&1 | grep -q pong || exit 1

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]

FROM nginx:1.27-alpine AS nginx-app
# nginx-app: serves static assets + reverse-proxies to php:9000. Static
# files are baked in from php-app (no shared volume needed for code/assets).

COPY --from=php-app /var/www/html/public /var/www/html/public

COPY docker/nginx/nginx.conf   /etc/nginx/nginx.conf
RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx/conf.d/      /etc/nginx/conf.d/

# HTTP on :80, WebSocket vhost on :8080. Master runs as root (stock image
# behavior, needed to bind :80); workers drop to the nginx user.
EXPOSE 80 8080
CMD ["nginx", "-g", "daemon off;"]
