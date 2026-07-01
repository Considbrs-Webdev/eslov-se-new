#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "${ROOT}/../.." && pwd)"

cd "${REPO}"
ddev wp eval-file tools/visual-diff/scripts/audit-pages.php
cp tools/visual-diff/pages.generated.json tools/visual-diff/pages.json
echo "Updated tools/visual-diff/pages.json ($(node -e "console.log(require('${ROOT}/pages.json').pages.length)") pages)"
