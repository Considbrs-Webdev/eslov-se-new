#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.61.1-jammy}"
ARGS="${*:-run}"

docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "${ROOT}:/work" \
  -w /work \
  --network host \
  "${IMAGE}" \
  bash -lc "npm install --silent && node src/run.mjs ${ARGS}"
