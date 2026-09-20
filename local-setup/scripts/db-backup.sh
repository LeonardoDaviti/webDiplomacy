#!/usr/bin/env bash
# Nightly dump of the webDiplomacy database (issue 002).
#
# Writes a gzipped, timestamped mysqldump to BACKUP_DIR, which is deliberately OUTSIDE the
# repository working tree so that a `git clean -xdf` cannot take the backups with it. Keeps the
# most recent KEEP dumps and prunes the rest.
#
# Run by hand, or from the cron entry installed by issue 002:
#   17 3 * * * /home/normie/Documents/Projects/webDiplomacy/local-setup/scripts/db-backup.sh >> ~/webdiplomacy-backups/backup.log 2>&1
#
# --single-transaction gives a consistent snapshot without locking, so a dump taken in the middle
# of an adjudication is coherent (every table here is InnoDB).

set -euo pipefail

REPO_DIR="${REPO_DIR:-/home/normie/Documents/Projects/webDiplomacy}"
BACKUP_DIR="${BACKUP_DIR:-/home/normie/webdiplomacy-backups}"
KEEP="${KEEP:-30}"
DB_NAME="${DB_NAME:-webdiplomacy}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-mypassword123}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$BACKUP_DIR/webdiplomacy-$STAMP.sql.gz"
TMP="$OUT.partial"

cd "$REPO_DIR"

# Written to .partial first and moved into place only on success, so a failed or half-finished
# dump is never mistaken for a backup (and is never what retention keeps).
if ! docker compose exec -T mariadb \
      mysqldump -u root "--password=$DB_ROOT_PASSWORD" \
        --single-transaction --quick --routines --triggers --events \
        --databases "$DB_NAME" 2>"$TMP.err" | gzip -9 > "$TMP"; then
  echo "$(date -Is) BACKUP FAILED - mysqldump: $(cat "$TMP.err")" >&2
  rm -f "$TMP" "$TMP.err"
  exit 1
fi

# A dump that never reached the closing marker is a truncated dump.
if ! gzip -dc "$TMP" | tail -5 | grep -q "Dump completed"; then
  echo "$(date -Is) BACKUP FAILED - dump has no completion marker" >&2
  rm -f "$TMP" "$TMP.err"
  exit 1
fi

rm -f "$TMP.err"
mv "$TMP" "$OUT"
chmod 600 "$OUT"
echo "$(date -Is) backup ok: $OUT ($(du -h "$OUT" | cut -f1))"

# Retention: keep the newest $KEEP, delete the rest.
ls -1t "$BACKUP_DIR"/webdiplomacy-*.sql.gz 2>/dev/null | tail -n "+$((KEEP + 1))" | while read -r old; do
  echo "$(date -Is) pruning $old"
  rm -f "$old"
done
