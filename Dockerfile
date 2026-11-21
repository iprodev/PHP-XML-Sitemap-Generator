# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.3

# ---------------------------------------------------------------------------
# Dependencies: installed in a separate stage so Composer and its cache are not
# part of the final image
# ---------------------------------------------------------------------------
FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock* ./
# Platform requirements are checked against the runtime image below, not this one
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader --ignore-platform-reqs
COPY src/ src/
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------------------------------------------------------------------------
# Runtime
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli-alpine

LABEL org.opencontainers.image.title="PHP XML Sitemap Generator" \
      org.opencontainers.image.source="https://github.com/iprodev/PHP-XML-Sitemap-Generator" \
      org.opencontainers.image.licenses="MIT"

# JavaScript rendering is not functional yet; Chromium is only installed on request
ARG INSTALL_CHROMIUM=false

# curl, dom, mbstring, pdo_sqlite, xml, xmlwriter and zlib are part of the base image;
# add the MySQL/PostgreSQL drivers and pcntl (Ctrl+C saves a checkpoint)
RUN set -eux; \
    apk add --no-cache libpq; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpq-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql pcntl; \
    apk del .build-deps; \
    if [ "$INSTALL_CHROMIUM" = "true" ]; then apk add --no-cache chromium; fi; \
    echo "memory_limit = 512M" > "$PHP_INI_DIR/conf.d/sitemap.ini"

# Unprivileged user; /data is its working directory for output, reports,
# checkpoints and SQLite databases (mount a volume there)
RUN addgroup -S sitemap && adduser -S -G sitemap -H -h /data sitemap \
    && mkdir -p /data && chown sitemap:sitemap /data

WORKDIR /app
COPY --from=vendor /app/vendor/ vendor/
COPY composer.json ./
COPY src/ src/
COPY bin/ bin/
RUN chmod 0755 bin/sitemap bin/scheduler

ENV PATH="/app/bin:${PATH}"

USER sitemap
WORKDIR /data

ENTRYPOINT ["php", "/app/bin/sitemap"]
CMD ["--help"]
