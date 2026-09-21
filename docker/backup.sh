#!/usr/bin/env bash
#
# Backs up the database and the uploaded files, keeping the last 14 days.
# Run by hand or from cron (as the user who runs docker), e.g. nightly at 02:00:
#
#   0 2 * * * /home/YOU/council_app/docker/backup.sh >> /home/YOU/council-backup.log 2>&1
#
# Backups land in $BACKUP_DIR (default ~/council-backups). They are on the
# same server, so ALSO copy them somewhere else (your PC, cloud storage) —
# a backup that dies with the server is not much of a backup.

set -euo pipefail

cd "$(dirname "$0")/.."

BACKUP_DIR="${BACKUP_DIR:-$HOME/council-backups}"
STAMP="$(date +%F)"
mkdir -p "$BACKUP_DIR"

docker compose exec -T db sh -c 'exec mysqldump --single-transaction -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"

docker compose exec -T app tar -C /app/storage -czf - app \
    > "$BACKUP_DIR/files-$STAMP.tar.gz"

find "$BACKUP_DIR" -type f -mtime +14 -delete

echo "$(date -Is) backup written to $BACKUP_DIR"
