# syntax=docker/dockerfile:1.7
#
# taskStarEx backend (src/be, Laravel) — multi-stage build.
# Stages: base -> deps -> build -> test -> runtime (runtime branches off base
# again with a --no-dev install so the final image carries no compiler/dev
# tooling; see docs/BASELINE.md for the currently-known pre-existing defect
# that blocks `deps`/`build`/`test`/`runtime` from completing, tracked as
# P0-1 in docs/TECHNICAL_DEBT.md, owned by Phase 6, not fixed here).
#
# Build context is the repository root (see docker-compose.yml: context: .).

# ---- base: PHP runtime + required extensions + composer binary ------------
FROM php:8.5.12-fpm@sha256:baf3ea13a8207fa18e1011bad1016e8b5e59fc974c6966d0efa6f36a16f875f0 AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        libpq-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libsqlite3-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql pdo_sqlite pgsql mbstring exif pcntl bcmath gd zip \
    && apt-get purge -y --no-install-recommends build-essential \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.11.2@sha256:0c8e663f14c94d07b3c45b175c51f5a14718508f21fe4dd7e54f09488dd0ed71 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/be

# ---- deps: full composer install (incl. require-dev), feeds build/test ----
FROM base AS deps
COPY src/be/composer.json src/be/composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-interaction --no-scripts --no-progress --prefer-dist

# ---- build: full application source on top of deps ------------------------
FROM deps AS build
COPY src/be/ ./
RUN composer dump-autoload --optimize

# ---- test: run the existing PHPUnit suite; build fails if tests fail ------
FROM build AS test
ENV APP_ENV=testing \
    APP_DEBUG=false \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    DB_CONNECTION=sqlite \
    DB_DATABASE=":memory:" \
    MAIL_MAILER=array
RUN cp -n .env.example .env \
    && php artisan key:generate --ansi --force \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache
RUN php artisan test
CMD ["php", "artisan", "test"]

# ---- runtime: production-only deps, no dev tools, non-root ----------------
FROM base AS runtime
COPY src/be/composer.json src/be/composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-interaction --no-scripts --no-progress --prefer-dist \
        --no-dev --optimize-autoloader
COPY src/be/ ./
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD pgrep -x php-fpm > /dev/null || exit 1
ENTRYPOINT ["php-fpm"]
