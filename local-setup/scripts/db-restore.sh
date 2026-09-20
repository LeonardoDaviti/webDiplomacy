#!/usr/bin/env bash
# Restore the webDiplomacy database from a dump taken by db-backup.sh (issue 002).
#
#   local-setup/scripts/db-restore.sh /home/normie/webdiplomacy-backups/webdiplomacy-<stamp>.sql.gz
#
# Takes either a .sql.gz or a plain .sql. The dumps are --databases dumps, so they carry their own
# CREATE DATABASE and DROP TABLE statements: this overwrites the live database in place and does
# not need the database dropped first.
#
# Afterwards the redis cache is flushed, because it may still hold rows from before the restore.

set -euo pipefail

REPO_DIR="${REPO_DIR:-/home/normie/Documents/Projects/webDiplomacy}"
DB_NAME="${DB_NAME:-webdiplomacy}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-mypassword123}"

FILE="${1:-}"
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
  echo "usage: $(basename "$0") <dump.sql.gz|dump.sql>" >&2
  exit 2
fi

cd "$REPO_DIR"

if ! docker compose ps --format '{{.Name}} {{.State}}' | grep -q 'webdiplomacy-db running'; then
  echo "the mariadb container is not running: docker compose --profile core up -d" >&2
  exit 1
fi

case "$FILE" in
  *.gz) CAT=(gzip -dc) ;;
  *)    CAT=(cat) ;;
esac

echo "restoring $DB_NAME from $FILE"
"${CAT[@]}" "$FILE" | docker compose exec -T mariadb mysql -u root "--password=$DB_ROOT_PASSWORD"

# The site caches database rows in redis; stale entries would survive the restore.
docker compose exec -T redis redis-cli FLUSHALL >/dev/null 2>&1 || true

echo "restored. Sanity check:"
docker compose exec -T mariadb mysql -u root "--password=$DB_ROOT_PASSWORD" "$DB_NAME" \
  -e "SELECT COUNT(*) AS users FROM wD_Users; SELECT COUNT(*) AS games FROM wD_Games; SELECT name,value FROM wD_Misc WHERE name IN ('Version','LastProcessTime');" 2>/dev/null
