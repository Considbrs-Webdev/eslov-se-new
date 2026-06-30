#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTPUT="${ROOT}/output"
RUN_ID="${1:-}"

if [[ -z "${RUN_ID}" ]]; then
  RUN_ID="$(ls -1 "${OUTPUT}" 2>/dev/null | sort | tail -1 || true)"
fi

if [[ -z "${RUN_ID}" ]]; then
  echo "No runs found. Run: npm run run:docker" >&2
  exit 1
fi

REPORT="${OUTPUT}/${RUN_ID}/index.html"

if [[ ! -f "${REPORT}" ]]; then
  echo "No index.html in output/${RUN_ID}/" >&2
  echo "That folder may be from an interrupted run (screenshots only)." >&2
  echo "Finish it: npm run diff -- --run=${RUN_ID}" >&2
  echo "Or start fresh: npm run run:docker" >&2
  exit 1
fi

echo "Report: ${REPORT}"

if grep -qi microsoft /proc/version 2>/dev/null && command -v explorer.exe >/dev/null 2>&1; then
  explorer.exe "$(wslpath -w "${REPORT}")"
elif command -v xdg-open >/dev/null 2>&1; then
  xdg-open "${REPORT}"
else
  echo ""
  echo "Open in your browser:"
  echo "  file://${REPORT}"
fi
