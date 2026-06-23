---
name: eslov-migration-workspace
description: >-
  Eslöv DB-first migration from Municipio LTS to municipio-deployment. Covers
  import-and-fix loop, eslov-customisation plugin, dual-repo forensics, and which
  repo to edit. Use when starting migration work, classifying errors, or unsure
  where to implement a fix.
---

# Eslöv migration workspace

## Strategy

| | |
|---|---|
| Method | Import `eslov-db.sql` → fix incompatibilities iteratively |
| Code | **No LTS plugin porting** — all fixes in `eslov-customisation` |
| LTS repo | Forensics only — understand old **data**, not port PHP |

## Two repos

| Repo | Path | Role |
|------|------|------|
| Target | `eslov-se-new/` | municipio-deployment — **edit here** |
| Forensics | `eslov-se/` | LTS Bedrock — grep for meta keys, module slugs, ACF fields |

## Reference database

**File:** `eslov-db.sql` (repo root, ~1.6 GB, MariaDB 10.11, gitignored)

```bash
ddev import-db --file=eslov-db.sql
```

Dump `siteurl`: `https://storatorg.eslov.w8e.se`. Also search-replace `https://eslov.se` in content. See `ddev-wp-cli` skill.

## Agent loop

```
1. Import eslov-db.sql (or restore after-import snapshot)
2. Boot site — note first fatal/error/warning
3. Classify fix type (see eslov-adaptation-plugin skill)
4. Implement in eslov-customisation
5. Verify — reload, WP-CLI, or wp eslov migrate --dry-run
6. Log row in .cursor/plans/db-migration.md
7. Repeat until site is functional
```

## Fix classification

| Symptom | Likely fix | Where |
|---------|------------|-------|
| Fatal: class not found | Orphan plugin active in DB | `wp plugin deactivate` or migration command |
| Module renders empty | Old module slug / JSON shape | One-time CLI transform |
| Wrong template/data | Legacy meta key | CLI transform, then remove shim |
| Site preference (search, CPT) | Filter needed ongoing | Runtime shim in eslov-customisation |
| Admin upgrade prompt | Municipio version jump | Run upgrade; may need data migration |

## LTS forensics workflow

When you need to understand **what old data means**:

1. Read error — identify post type, meta key, module slug, or option
2. Grep `eslov-se/web/app/plugins/` and `web/app/mu-plugins/` for that key
3. Read `ARCHITECTURE.md` § "Legacy LTS artefacts — data impact map"
4. Implement transform or shim in `eslov-customisation`
5. **Do not** copy the LTS plugin into `eslov-se-new`

## Package naming (LTS → deployment)

| LTS | Deployment |
|-----|------------|
| `municipio/municipio` | `helsingborg-stad/municipio` |
| `municipio/wp-plugin-modularity-sections` | `helsingborg-stad/modularity-sections` |
| `municipio/wp-plugin-modularity-timeline` | `helsingborg-stad/modularity-timeline` |

Module **data in DB** may still use old slugs even when deployment packages are installed.

## Local dev

| Repo | ddev | URL |
|------|------|-----|
| Target | `eslov-se-new` | `https://eslov-se-new.ddev.site` |
| Forensics | `eslov-se` | `https://eslov-se.ddev.site` |

Most work: `cd eslov-se-new && ddev start`.

## Related docs & skills

- `.cursor/plans/db-migration.md` — breakage matrix
- `ARCHITECTURE.md` — data impact map
- `AGENTS.md` — entry point
- `eslov-adaptation-plugin` — how to write fixes
- `ddev-wp-cli` — DB import workflow
- `municipio-framework` — new structure
