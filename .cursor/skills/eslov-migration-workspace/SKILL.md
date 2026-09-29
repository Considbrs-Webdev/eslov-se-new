---
name: eslov-migration-workspace
description: >-
  Eslöv DB-first migration from Municipio LTS to municipio-deployment. Covers
  import-and-fix loop, URL/multisite ddev setup, eslov-customisation, dual-repo
  forensics, and which repo to edit. Invoke with /eslov-migration-workspace
  when starting migration work or classifying errors.
disable-model-invocation: true
---

# Eslöv migration workspace

Slash-only. Do not treat ordinary bugs as migration incompatibilities after
cutover. Delete this skill when the site is live.

## Strategy

| | |
|---|---|
| Method | Import `eslov-2026-06-23-77d6623-lean.sql` → fix incompatibilities iteratively |
| Code | **No LTS plugin porting** — all fixes in `eslov-customisation` |
| LTS repo | Forensics only — understand old **data**, not port PHP |

## Two repos

| Repo | Path | Role |
|------|------|------|
| Target | `eslov-se-new/` | municipio-deployment — **edit here** |
| Forensics | `eslov-se/` | LTS Bedrock — grep for meta keys, module slugs, ACF fields |

## Reference database

**File:** `eslov-2026-06-23-77d6623-lean.sql` (repo root, ~610 MB, MariaDB 11.8 lean, gitignored)

| | |
|---|---|
| Git | Local only — do not commit |
| Dump `siteurl` | `https://storatorg.eslov.w8e.se` |
| Lean | Log/audit/cache tables: structure only (incl. `*_aryo_activity_log`) |

## DB import and URL fix

```bash
cd eslov-se-new && ddev start

ddev import-db --file=eslov-2026-06-23-77d6623-lean.sql

ddev wp search-replace 'https://storatorg.eslov.w8e.se' 'https://eslov-se-new.ddev.site' --all-tables
ddev wp search-replace 'http://storatorg.eslov.w8e.se' 'https://eslov-se-new.ddev.site' --all-tables
ddev wp search-replace 'https://eslov.se' 'https://eslov-se-new.ddev.site' --all-tables
ddev wp search-replace 'http://eslov.se' 'https://eslov-se-new.ddev.site' --all-tables
```

Multisite domain tables — search-replace does **not** update `wp_site` / `wp_blogs`.domain.
Missing this causes a misleading "Error establishing a database connection".

```bash
ddev exec mysql -udb -pdb db -e "
UPDATE eslovwp1_site SET domain = 'eslov-se-new.ddev.site' WHERE id = 1;
UPDATE eslovwp1_blogs SET domain = 'eslov-se-new.ddev.site' WHERE domain = 'eslov.se';
UPDATE eslovwp1_blogs SET domain = REPLACE(domain, '.eslov.se', '.eslov-se-new.ddev.site') WHERE domain LIKE '%.eslov.se';
"
```

`siteurl` must include `/wp` (core lives in `wp/`). `home` stays at site root.
Without `/wp` on `siteurl`, login posts to root `wp-login.php` and loops.

```bash
ddev exec mysql -udb -pdb db -e "
UPDATE eslovwp1_options o JOIN eslovwp1_blogs b ON b.blog_id=1 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_options o JOIN eslovwp1_blogs b ON b.blog_id=1 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_3_options o JOIN eslovwp1_blogs b ON b.blog_id=3 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_3_options o JOIN eslovwp1_blogs b ON b.blog_id=3 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_4_options o JOIN eslovwp1_blogs b ON b.blog_id=4 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_4_options o JOIN eslovwp1_blogs b ON b.blog_id=4 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_7_options o JOIN eslovwp1_blogs b ON b.blog_id=7 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_7_options o JOIN eslovwp1_blogs b ON b.blog_id=7 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_8_options o JOIN eslovwp1_blogs b ON b.blog_id=8 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_8_options o JOIN eslovwp1_blogs b ON b.blog_id=8 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_10_options o JOIN eslovwp1_blogs b ON b.blog_id=10 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_10_options o JOIN eslovwp1_blogs b ON b.blog_id=10 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_11_options o JOIN eslovwp1_blogs b ON b.blog_id=11 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_11_options o JOIN eslovwp1_blogs b ON b.blog_id=11 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_12_options o JOIN eslovwp1_blogs b ON b.blog_id=12 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_12_options o JOIN eslovwp1_blogs b ON b.blog_id=12 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_13_options o JOIN eslovwp1_blogs b ON b.blog_id=13 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_13_options o JOIN eslovwp1_blogs b ON b.blog_id=13 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_14_options o JOIN eslovwp1_blogs b ON b.blog_id=14 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_14_options o JOIN eslovwp1_blogs b ON b.blog_id=14 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_15_options o JOIN eslovwp1_blogs b ON b.blog_id=15 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_15_options o JOIN eslovwp1_blogs b ON b.blog_id=15 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
UPDATE eslovwp1_16_options o JOIN eslovwp1_blogs b ON b.blog_id=16 SET o.option_value=CONCAT('https://',b.domain) WHERE o.option_name='home';
UPDATE eslovwp1_16_options o JOIN eslovwp1_blogs b ON b.blog_id=16 SET o.option_value=CONCAT('https://',b.domain,'/wp') WHERE o.option_name='siteurl';
"
```

Config: `config/multisite.php` → `SUBDOMAIN_INSTALL` true.

Add to `.ddev/config.yaml` then `ddev restart`:

```yaml
additional_hostnames:
  - "*.eslov-se-new"
```

Without this, subsites 404.

```bash
ddev wp cache flush
ddev wp rewrite flush
ddev export-db --file=after-import.sql.gz
```

Remote media (nginx proxies missing files from production):

```bash
cp .ddev/env/remote-media.env.example .ddev/env/remote-media.env
ddev restart
```

Default upstream: `https://eslov.se`.

```bash
ddev wp plugin activate eslov-customisation
ddev wp eslov migrate --help
ddev wp eslov migrate modules --dry-run
```

Rollback: `ddev import-db --file=after-import.sql.gz && ddev wp cache flush`

**Never** blind `search-replace` on serialized meta — use `eslov-customisation` migrators.
Deactivate LTS-only plugins active in DB but absent from deployment `composer.json`.

## Agent loop

```
1. Import eslov-2026-06-23-77d6623-lean.sql (or restore after-import snapshot)
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

## LTS forensics

When you need to understand **what old data means**:

1. Read the error — post type, meta key, module slug, or option
2. Grep `eslov-se/web/app/plugins/` and `web/app/mu-plugins/`
3. Read `ARCHITECTURE.md` § "Legacy LTS artefacts — data impact map"
4. Implement transform or shim in `eslov-customisation`
5. **Do not** copy the LTS plugin into `eslov-se-new`

LTS `web/app/mu-plugins/settings.php` used filters like
`Municipio/Hook/showSiteNameInSearchResult` and
`Municipio/Helper/Post/EmptyExcerpt`. Prefer data migration over replicating
view-path hacks (`Modularity/Module/TemplatePath`).

Do **not** port LTS modules (`mod-open-hours`, `ws-branded-border`, …) by
scaffolding them. Transform DB data to deployment packages first. Use
`create-modularity-module` only if deployment has no equivalent.

## Package naming (LTS → deployment)

| LTS | Deployment |
|-----|------------|
| `municipio/municipio` | `helsingborg-stad/municipio` |
| `municipio/wp-plugin-modularity-sections` | `helsingborg-stad/modularity-sections` |
| `municipio/wp-plugin-modularity-timeline` | `helsingborg-stad/modularity-timeline` |

## Review checks (when reviewing migration code)

- [ ] Code is in `eslov-se-new/`, not `eslov-se/`
- [ ] Fixes live in `eslov-customisation`
- [ ] One-time migrations have `--dry-run` and are idempotent
- [ ] Runtime shims document which LTS behaviour they replace
- [ ] Fix logged in `.cursor/plans/db-migration.md`
- [ ] Custom packages in `composer.local.json`, not `composer.json`

## Related

- `.cursor/plans/db-migration.md` — breakage matrix
- `ARCHITECTURE.md` — data impact map
- `eslov-adaptation-plugin` — how to write fixes
- `composer-local-merge` — `composer.local.json`
- `ddev-wp-cli` — generic WP-CLI
- `municipio-framework` — new structure
