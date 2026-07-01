# Visual diff harness (Eslöv migration)

Compare rendered pages between **production/LTS** (`eslov.se`) and the **migrated DDEV site** (`eslov-se-new.ddev.site`).

## Prerequisites

- DDEV running with remote media proxy enabled (see `ddev-wp-cli` skill)
- Node.js 18+ on the host
- Network access to `https://eslov.se` and local DDEV URLs

## Setup

**Option A — Docker (recommended on WSL when Playwright system libs are missing):**

```bash
cd tools/visual-diff
npm run run:docker
```

Uses `mcr.microsoft.com/playwright:v1.61.1-jammy` with host networking so DDEV URLs resolve.

**Option B — Local Node:**

```bash
cd tools/visual-diff
npm install
npx playwright install-deps chromium   # once, requires sudo on Linux
npm run install:browsers
```

## Run full capture + diff + HTML report

```bash
cd tools/visual-diff
npm run run:docker          # recommended on WSL (~2–3 min for all pages)
npm run open-report         # opens latest report in your browser
```

Output lands in `output/{timestamp}/` (gitignored — local only):

| File | Purpose |
|------|---------|
| `index.html` | Side-by-side review report — **open this** |
| `report.json` | Mismatch percentages |
| `triage.json` | Suggested fix classification (after `npm run triage`) |
| `reference/` | Production screenshots |
| `target/` | Migrated DDEV screenshots |
| `diff/` | Pixel diff images |

### Open the report in your browser

**Easiest (WSL):**
```bash
cd tools/visual-diff
npm run open-report
```

**Specific run:**
```bash
npm run open-report -- 20260629-065909
```

**Manual:** open `tools/visual-diff/output/{latest-run}/index.html` in Chrome/Edge. In WSL you can paste the `file://` path from the terminal, or in Cursor right-click the file → Reveal in Explorer.

> If a run folder only has `reference/` and `target/` but no `index.html`, the Docker run was interrupted before the diff step. Re-run `npm run run:docker`, or finish with `npm run diff -- --run={folder-name}`.

## Options

```bash
# Single page
node src/run.mjs --pages=section-child-pills

# Re-diff an existing run
node src/run.mjs diff --run=20250629-120000

# Capture reference only (offline target work)
node src/run.mjs capture --side=reference

# Generate triage template
npm run triage -- --run=20250629-120000 --write
```

## Page set

Edit [`pages.json`](pages.json) or regenerate from the database:

```bash
npm run audit-pages   # scans modularity-modules across all subsites
```

See [`pages-audit.md`](pages-audit.md) for **how each URL was chosen** (module types, layouts, CPT templates, subsites).

**Coverage (current):** 69 URLs × **desktop + mobile** = 138 screenshots per full run. Mobile is included automatically for every page — no separate config.

### Run subsets (faster)

```bash
# Main site modules + shims only (~15 pages)
npm run run:docker -- --pages=home,section-child-pills,tree-highlighted,auto-1-558888,auto-1-97012

# One subsite (plus)
npm run run:docker -- --pages=sub16-home,sub16-event,sub16-mod-posts
```

## Triage workflow

1. Open `output/{run}/index.html`
2. For each card with meaningful visual difference (ignore dynamic dates/search noise):
   - Classify: `migrate` | `tokens` | `config` | `shim` | `accepted`
   - Implement fix in `eslov-customisation`
   - Log row in `.cursor/plans/db-migration.md`
3. Re-run `npm run run` on affected pages to verify

## Notes

- Full-page screenshots may differ in height when content length changes — diff crops to the smaller shared area.
- Search and news pages often have high mismatch from dynamic content; triage as `accepted` or `investigate` unless layout/CSS is wrong.
- Subsites use `{name}.eslov-se-new.ddev.site` (requires `additional_hostnames: ["*.eslov-se-new"]` in DDEV config).
