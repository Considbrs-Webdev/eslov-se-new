#!/bin/bash
#
# Nightly full database backup (mysqldump via wp-cli), gzip-compressed.
#
# Intended to be run from crontab (no login shell, minimal environment),
# so every path is absolute and PATH is set explicitly.
#
# Runs once per night. If it fails, it aborts and logs the error - unlike
# the per-site cron/typesense scripts, there is nothing to "skip and
# continue" here since this is a single, all-or-nothing export.
#
# Rotation policy (applied after every successful backup):
#   0-7 days old:    keep every backup (one per day already, at one run/night)
#   8-37 days old:   keep the latest backup per ISO week
#   38-219 days old: keep the latest backup per calendar month
#   220+ days old:   deleted

set -Eeuo pipefail

export PATH="/usr/local/bin:/usr/bin:/bin"
export HOME="/home/httpd/consid"

PHP="/usr/bin/php"
WP_CLI="$HOME/bin/wp"
WP_PATH="$HOME/web/current/wp"
BACKUP_DIR="$HOME/web/backups/db"
LOG_FILE="$HOME/web/logs/db-backup.log"
LOCK_FILE="$HOME/web/logs/.db-backup.lock"

PREFIX="eslov-se"

wp() {
    "$PHP" "$WP_CLI" --path="$WP_PATH" "$@"
}

ts() {
    date '+%Y-%m-%d %H:%M:%S'
}

mkdir -p "$BACKUP_DIR"

exec >>"$LOG_FILE" 2>&1

trap 'echo "[$(ts)] FAILED: backup aborted (line $LINENO)"' ERR

# Skip this run if a previous backup is still going.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "[$(ts)] Previous run still in progress, skipping."
    exit 0
fi

RUN_START=$(date +%s)
echo "================================================================"
echo "[$(ts)] Backup started"

TIMESTAMP="$(date '+%Y-%m-%d_%H-%M-%S')"
SQL_FILE="$BACKUP_DIR/${PREFIX}_${TIMESTAMP}.sql"
GZ_FILE="${SQL_FILE}.gz"

wp db export "$SQL_FILE"
gzip -f "$SQL_FILE"

echo "[$(ts)] Backup created: $(basename "$GZ_FILE")"

# --------------------------------------------------
# Rotation
# --------------------------------------------------

declare -A KEEP_WEEK
declare -A KEEP_MONTH

NOW_EPOCH=$(date +%s)
deleted=0

mapfile -t FILES < <(find "$BACKUP_DIR" -maxdepth 1 -type f -name "${PREFIX}_*.sql.gz" | sort -r)

for FILE in "${FILES[@]}"; do
    BASENAME="$(basename "$FILE")"

    if [[ ! "$BASENAME" =~ ^${PREFIX}_([0-9]{4}-[0-9]{2}-[0-9]{2})_([0-9]{2}-[0-9]{2}-[0-9]{2})\.sql\.gz$ ]]; then
        echo "[$(ts)] Skipping unrecognized file name: $BASENAME"
        continue
    fi

    FILE_DATE="${BASH_REMATCH[1]}"
    FILE_TIME="${BASH_REMATCH[2]//-/:}"

    FILE_EPOCH=$(date -d "$FILE_DATE $FILE_TIME" +%s)
    AGE_DAYS=$(( (NOW_EPOCH - FILE_EPOCH) / 86400 ))

    # Keep everything for the first 7 days.
    if (( AGE_DAYS <= 7 )); then
        continue
    fi

    # 8-37 days: keep only the latest per ISO week.
    if (( AGE_DAYS <= 37 )); then
        WEEK_KEY=$(date -d "$FILE_DATE" +'%G-W%V')
        if [[ -z "${KEEP_WEEK[$WEEK_KEY]+x}" ]]; then
            KEEP_WEEK[$WEEK_KEY]=1
        else
            rm -f "$FILE"
            deleted=$((deleted + 1))
            echo "[$(ts)] Deleted (weekly rotation): $BASENAME"
        fi
        continue
    fi

    # 38-219 days: keep only the latest per calendar month.
    if (( AGE_DAYS <= 219 )); then
        MONTH_KEY="${FILE_DATE:0:7}"
        if [[ -z "${KEEP_MONTH[$MONTH_KEY]+x}" ]]; then
            KEEP_MONTH[$MONTH_KEY]=1
        else
            rm -f "$FILE"
            deleted=$((deleted + 1))
            echo "[$(ts)] Deleted (monthly rotation): $BASENAME"
        fi
        continue
    fi

    # 220+ days: not kept at all.
    rm -f "$FILE"
    deleted=$((deleted + 1))
    echo "[$(ts)] Deleted (older than 220 days): $BASENAME"
done

echo "[$(ts)] Backup finished: $deleted old backup(s) removed, $(( $(date +%s) - RUN_START ))s total"

exit 0
