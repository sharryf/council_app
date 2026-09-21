#!/usr/bin/env bash
#
# Confirms PDF generation works the way the web server will run it:
# same user (www-data), same home folder, same Chrome install.
#
# Run as root (or with sudo) after deploy.sh has finished:
#   sudo bash /var/www/council-app/deploy/check-pdf.sh
#
# Exits non-zero and says what is wrong if anything fails.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/council-app}"
export PUPPETEER_CACHE_DIR=/opt/puppeteer
OUT=/tmp/council-pdf-check.pdf

[ "$(id -u)" -eq 0 ] || { echo "Run this with sudo." >&2; exit 1; }

CHROME="$(find "$PUPPETEER_CACHE_DIR" -type f -name chrome -perm -u+x 2>/dev/null | head -n1 || true)"
if [ -z "$CHROME" ]; then
    echo "FAIL: Chrome not found in $PUPPETEER_CACHE_DIR. Did deploy.sh finish 'npm ci'?" >&2
    exit 1
fi
echo "Chrome: $CHROME"

MISSING="$(ldd "$CHROME" 2>/dev/null | grep 'not found' || true)"
if [ -n "$MISSING" ]; then
    echo "FAIL: Chrome is missing system libraries:" >&2
    echo "$MISSING" >&2
    exit 1
fi

cd "$APP_DIR"
rm -f "$OUT"

sudo -H -u www-data env PUPPETEER_CACHE_DIR="$PUPPETEER_CACHE_DIR" php -r '
require "vendor/autoload.php";
Spatie\Browsershot\Browsershot::html("<h1>PDF OK</h1>")->noSandbox()->savePdf("/tmp/council-pdf-check.pdf");
'

if [ -s "$OUT" ] && [ "$(head -c 4 "$OUT")" = "%PDF" ]; then
    echo "PASS: generated a $(stat -c %s "$OUT")-byte PDF as www-data."
    rm -f "$OUT"
else
    echo "FAIL: no valid PDF was produced." >&2
    exit 1
fi
