# syntax=docker/dockerfile:1
#
# Production image for Oceancy.
#
# FrankenPHP = Caddy + PHP 8.3 in one process, so the container serves the
# app AND handles HTTPS (automatic Let's Encrypt certificates) by itself.
# Chromium + Node are included because every PDF export uses Spatie
# Browsershot, which drives headless Chrome through Puppeteer.
#
# Build/run instructions: see docker-compose.yml.

# ---------------------------------------------------------------- 1. PHP
# Composer dependencies and the optimised autoloader. Scripts are skipped
# here (they boot the app); the entrypoint runs the needed ones at start-up.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts --no-interaction

# -------------------------------------------------------------- 2. assets
# Vite/Tailwind build. It needs the whole source tree (Tailwind scans it for
# class names) and vendor/ (app.css has an @source into laravel/framework).
FROM node:22-bookworm-slim AS assets
WORKDIR /app
ENV PUPPETEER_SKIP_DOWNLOAD=true
COPY --from=vendor /app ./
RUN npm ci && npm run build

# ------------------------------------------------------ 3. runtime Node deps
# Only Puppeteer is needed at runtime; Chrome itself comes from apt below.
FROM node:22-bookworm-slim AS nodemods
WORKDIR /app
ENV PUPPETEER_SKIP_DOWNLOAD=true
COPY package.json package-lock.json ./
RUN npm ci --omit=dev

# --------------------------------------------------------------- 4. final
FROM dunglas/frankenphp:1-php8.3-bookworm

RUN install-php-extensions pdo_mysql intl gd zip bcmath opcache

# Debian's chromium package pulls in every library Chrome needs, so there is
# no hand-maintained dependency list. The fonts matter for PDF text
# (fonts-noto-core includes Thaana for Dhivehi).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        chromium libcap2-bin ca-certificates \
        fonts-liberation fonts-dejavu-core fonts-noto-core fonts-noto-color-emoji \
    && rm -rf /var/lib/apt/lists/*

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node

# Puppeteer uses the system Chromium instead of downloading its own.
ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-app.ini"

# Run as a normal user, not root. The capability lets it bind ports 80/443.
RUN useradd --create-home --shell /bin/bash appuser \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R appuser:appuser /data/caddy /config/caddy

WORKDIR /app
COPY --from=vendor --chown=appuser:appuser /app ./
COPY --from=assets --chown=appuser:appuser /app/public/build ./public/build
COPY --from=nodemods --chown=appuser:appuser /app/node_modules ./node_modules

# Laravel's writable folders (the storage/ volume is created from these).
RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs storage/app/private \
        storage/app/public bootstrap/cache \
    && chown -R appuser:appuser storage bootstrap/cache public

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER appuser

# entrypoint.sh prepares Laravel, then starts FrankenPHP itself.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
