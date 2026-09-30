#!/bin/bash
#
# Run due WP-Cron events for every active site in the multisite network.
#
# Intended to be run from crontab (no login shell, minimal environment),
# so every path is absolute and PATH is set explicitly.
#
# A failing site never aborts the run - all sites are always processed.
# Output (stdout + stderr) for each site is appended to $LOG_FILE.

set -u

export PATH="/usr/local/bin:/usr/bin:/bin"
export HOME="/home/httpd/consid"

PHP="/usr/bin/php"
WP_CLI="$HOME/bin/wp"
WP_PATH="$HOME/web/current/wp"
LOG_FILE="$HOME/web/logs/wp-cron.log"
LOCK_FILE="$HOME/web/logs/.wp-cron.lock"

# Max run time per site before it is killed, so one hanging site can't block the rest.
SITE_TIMEOUT="60m"

wp() {
    "$PHP" "$WP_CLI" --path="$WP_PATH" "$@"
}

ts() {
    date '+%Y-%m-%d %H:%M:%S'
}

exec >>"$LOG_FILE" 2>&1

# Skip this run if the previous one is still going.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "[$(ts)] Previous run still in progress, skipping."
    exit 0
fi

RUN_START=$(date +%s)
echo "================================================================"
echo "[$(ts)] Run started"

# Active sites = not archived, not deleted, not marked as spam.
SITES=$(wp site list --archived=0 --deleted=0 --spam=0 --field=url)
LIST_STATUS=$?

if [ $LIST_STATUS -ne 0 ] || [ -z "$SITES" ]; then
    echo "[$(ts)] ERROR: could not fetch site list (exit $LIST_STATUS)"
    exit 1
fi

total=0
failed=0

while IFS= read -r url; do
    [ -n "$url" ] || continue
    total=$((total + 1))

    echo "----------------------------------------------------------------"
    echo "[$(ts)] Site: $url"

    site_start=$(date +%s)
    timeout "$SITE_TIMEOUT" "$PHP" "$WP_CLI" --path="$WP_PATH" --url="$url" cron event run --due-now </dev/null
    status=$?
    duration=$(( $(date +%s) - site_start ))

    if [ $status -eq 0 ]; then
        echo "[$(ts)] OK: $url (${duration}s)"
    else
        failed=$((failed + 1))
        [ $status -eq 124 ] && echo "[$(ts)] TIMEOUT after $SITE_TIMEOUT"
        echo "[$(ts)] FAILED: $url (exit $status, ${duration}s)"
    fi
done <<< "$SITES"

echo "----------------------------------------------------------------"
echo "[$(ts)] Run finished: $total sites, $failed failed, $(( $(date +%s) - RUN_START ))s total"

exit 0
