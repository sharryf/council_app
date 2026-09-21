#!/bin/sh
# Runs every time the app container starts, then hands over to FrankenPHP.
# The database is guaranteed to be up (docker-compose waits for its
# healthcheck), so migrations can run straight away.
set -e
cd /app

php artisan package:discover --ansi
php artisan filament:assets --ansi || echo "filament:assets skipped"
php artisan migrate --force
php artisan storage:link --force >/dev/null 2>&1 || true
php artisan optimize

# The image's built-in Caddyfile serves /app/public and reads SERVER_NAME.
# Its location has moved between FrankenPHP releases, so use whichever exists.
CADDYFILE=/etc/frankenphp/Caddyfile
[ -f "$CADDYFILE" ] || CADDYFILE=/etc/caddy/Caddyfile

exec frankenphp run --config "$CADDYFILE" --adapter caddyfile
