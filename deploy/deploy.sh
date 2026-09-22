#!/usr/bin/env bash
#
# Deploy or update Oceancy. Run as the `deploy` user (not root).
#
# First deploy (clones the repo, creates .env, builds, migrates):
#   REPO_URL=git@github.com:sharryf/council_app.git DOMAIN=council.example.com bash deploy.sh
#   Add APP_KEY='base64:...' to reuse your existing key (recommended when
#   moving real data — copy it from your local .env).
#   For a private repo, first run `ssh-keygen -t ed25519` on the server and
#   add ~/.ssh/id_ed25519.pub to GitHub as a read-only deploy key
#   (repo > Settings > Deploy keys).
#
# Later updates:
#   bash /var/www/council-app/deploy/deploy.sh
#
# Moving your existing data (do this after the first deploy):
#   Database — on your PC:  mysqldump -u root -p council_app > council_app.sql
#              scp council_app.sql deploy@SERVER_IP:
#              on the server:  mysql -u council_app -p council_app < council_app.sql
#              (password: see ~/.council-credentials), then re-run this script
#              so any newer migrations are applied.
#   Files    — scp -r storage/app/. deploy@SERVER_IP:/var/www/council-app/storage/app/

set -euo pipefail

# New files must be group-writable so PHP-FPM (www-data) and this user can
# both write to storage/ and bootstrap/cache/.
umask 002

APP_DIR="${APP_DIR:-/var/www/council-app}"
BRANCH="${BRANCH:-master}"
PHP_VERSION="8.3"
CREDS="$HOME/.council-credentials"

# Chrome for PDF exports is installed here so PHP-FPM can find it too.
export PUPPETEER_CACHE_DIR=/opt/puppeteer

log() { printf '\n==> %s\n' "$*"; }

[ "$(id -u)" -ne 0 ] || { echo "Run this as the deploy user, not root." >&2; exit 1; }

# ----------------------------------------------------------------- code
if [ ! -d "$APP_DIR/.git" ]; then
    : "${REPO_URL:?First deploy needs REPO_URL, e.g. REPO_URL=git@github.com:you/repo.git}"
    log "Cloning $REPO_URL"
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
else
    log "Pulling latest code"
    git -C "$APP_DIR" fetch origin "$BRANCH"
    git -C "$APP_DIR" merge --ff-only "origin/$BRANCH"
fi

cd "$APP_DIR"

# ------------------------------------------------------------------ .env
set_env() {
    local key="$1" value="$2"
    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

if [ ! -f .env ]; then
    : "${DOMAIN:?First deploy needs DOMAIN, e.g. DOMAIN=council.example.com}"
    [ -f "$CREDS" ] || { echo "Missing $CREDS — run provision.sh first." >&2; exit 1; }

    log "Creating .env"
    cp .env.example .env

    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "https://${DOMAIN}"
    set_env LOG_LEVEL warning
    set_env SESSION_SECURE_COOKIE true
    set_env DB_HOST 127.0.0.1

    while IFS='=' read -r key value; do
        [ -n "$key" ] && set_env "$key" "$value"
    done < "$CREDS"

    if [ -n "${APP_KEY:-}" ]; then
        set_env APP_KEY "$APP_KEY"
    else
        php artisan key:generate --force
    fi

    chgrp www-data .env
    chmod 640 .env
fi

# ----------------------------------------------------------------- build
log "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

log "Installing Node dependencies (also downloads Chrome for PDFs)"
npm ci

log "Building assets"
npm run build

# ------------------------------------------------------------ permissions
# Only touch files this user owns — files PHP-FPM created (logs, cache) belong
# to www-data, and changing those as a non-owner would fail. PHP-FPM's own
# UMask=0002 (see provision.sh) keeps those group-writable already.
ME="$(id -un)"
find storage bootstrap/cache -user "$ME" -exec chgrp www-data {} +
find storage bootstrap/cache -user "$ME" -exec chmod ug+rwX {} +
find storage bootstrap/cache -type d -user "$ME" -exec chmod g+s {} +

# ------------------------------------------------------------------ laravel
log "Migrating and caching"
php artisan storage:link 2>/dev/null || true
php artisan migrate --force
php artisan optimize:clear
php artisan optimize

log "Reloading PHP"
sudo /usr/bin/systemctl reload "php${PHP_VERSION}-fpm"

log "Deployed. Open https://${DOMAIN:-your-domain} and run deploy/check-pdf.sh (as root) to confirm PDFs work."
