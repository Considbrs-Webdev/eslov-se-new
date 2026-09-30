#!/bin/bash
#
# Run a Typesense command (index or rebuild) for every active site in the
# multisite network.
#
# Usage: wp-typesense.sh index|rebuild
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
LOG_FILE="$HOME/web/logs/typesense.log"
LOCK_FILE="$HOME/web/logs/.typesense.lock"

# Max run time per site before it is killed, so one hanging site can't block the rest.
SITE_TIMEOUT="2h"

# Sites to skip, matched against the first part of the hostname
# (e.g. "event" matches both event.eslov.se and event.eslov.dev).
EXCLUDE_SUBDOMAINS=("event")

wp() {
    "$PHP" "$WP_CLI" --path="$WP_PATH" "$@"
}

ts() {
    date '+%Y-%m-%d %H:%M:%S'
}

is_excluded() {
    local host="${1#*://}"
    host="${host%%/*}"
    local sub="${host%%.*}"
    local ex
    for ex in "${EXCLUDE_SUBDOMAINS[@]}"; do
        [ "$sub" = "$ex" ] && return 0
    done
    return 1
}

ACTION="${1:-}"
case "$ACTION" in
    index|rebuild) ;;
    *)
        echo "Usage: $0 index|rebuild" >&2
        exit 2
        ;;
esac

exec >>"$LOG_FILE" 2>&1

# Skip this run if a previous index/rebuild is still going.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "[$(ts)] typesense $ACTION: previous run still in progress, skipping."
    exit 0
fi

RUN_START=$(date +%s)
echo "================================================================"
echo "[$(ts)] typesense $ACTION: run started"

# Active sites = not archived, not deleted, not marked as spam.
SITES=$(wp site list --archived=0 --deleted=0 --spam=0 --field=url)
LIST_STATUS=$?

if [ $LIST_STATUS -ne 0 ] || [ -z "$SITES" ]; then
    echo "[$(ts)] ERROR: could not fetch site list (exit $LIST_STATUS)"
    exit 1
fi

total=0
failed=0
skipped=0

while IFS= read -r url; do
    [ -n "$url" ] || continue

    if is_excluded "$url"; then
        skipped=$((skipped + 1))
        echo "[$(ts)] Skipping excluded site: $url"
        continue
    fi

    total=$((total + 1))

    echo "----------------------------------------------------------------"
    echo "[$(ts)] Site: $url"

    site_start=$(date +%s)
    timeout "$SITE_TIMEOUT" "$PHP" "$WP_CLI" --path="$WP_PATH" --url="$url" typesense "$ACTION" --yes </dev/null
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
echo "[$(ts)] typesense $ACTION: run finished: $total sites, $failed failed, $skipped skipped, $(( $(date +%s) - RUN_START ))s total"

exit 0
