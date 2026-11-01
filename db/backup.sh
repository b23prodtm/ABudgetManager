#!/bin/sh
# Nightly backup of /data/budget.sqlite to /data/backups, keeping the 14 most recent.
DB="${DB_PATH:-/data/budget.sqlite}"
DIR="${BACKUP_DIR:-/data/backups}"
KEEP="${BACKUP_KEEP:-14}"
HOUR="${BACKUP_HOUR:-03}"
mkdir -p "$DIR"

backup() {
    [ -f "$DB" ] || return 0
    out="$DIR/budget-$(date +%Y%m%d-%H%M%S).sqlite"
    sqlite3 "$DB" "VACUUM INTO '$out'" && echo "Backup written: $out"
    ls -1t "$DIR"/budget-*.sqlite 2>/dev/null | tail -n +$((KEEP + 1)) | while read -r old; do rm -f "$old"; done
}

while true; do
    backup
    now=$(date +%s)
    next=$(date -d "tomorrow ${HOUR}:00" +%s 2>/dev/null || echo $((now + 86400)))
    sleep $((next - now))
done
