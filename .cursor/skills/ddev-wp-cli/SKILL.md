---
name: ddev-wp-cli
description: >-
  Run WordPress CLI in the Eslöv migration workspace via ddev. Covers DB import
  workflow, search-replace, migration commands, and both repo layouts. Use for
  local debugging, database import, cache, or WP-CLI migration tasks.
---

# ddev & WP-CLI (Eslöv migration workspace)

Primary work happens in **`eslov-se-new`** with the reference database **`eslov-db.sql`**.

## Reference database

| | |
|---|---|
| File | `eslov-db.sql` (repo root, ~1.6 GB) |
| Format | MariaDB 10.11 dump |
| Git | Local only — do not commit |
| Dump `siteurl` | `https://storatorg.eslov.w8e.se` |

## Two sites

| Repo | ddev project | URL | Role |
|------|-------------|-----|------|
| `eslov-se-new/` | `eslov-se-new` | `https://eslov-se-new.ddev.site` | **Work here** — prod DB import |
| `eslov-se/` | `eslov-se` | `https://eslov-se.ddev.site` | Forensics only (optional) |

```bash
cd eslov-se-new && ddev start
```

## DB migration workflow

Import the reference dump, then fix URLs:

```bash
cd eslov-se-new

# Import (~1.6 GB — allow several minutes)
ddev import-db --file=eslov-db.sql

# URL fix — dump siteurl is storatorg.eslov.w8e.se; content may use eslov.se
ddev wp search-replace 'https://storatorg.eslov.w8e.se' 'https://eslov-se-new.ddev.site' --all-tables
ddev wp search-replace 'http://storatorg.eslov.w8e.se' 'https://eslov-se-new.ddev.site' --all-tables
ddev wp search-replace 'https://eslov.se' 'https://eslov-se-new.ddev.site' --all-tables
ddev wp search-replace 'http://eslov.se' 'https://eslov-se-new.ddev.site' --all-tables

# Multisite domain tables — search-replace does NOT update wp_site/wp_blogs.domain.
# Missing this causes misleading "Error establishing a database connection".
ddev exec mysql -udb -pdb db -e "
UPDATE eslovwp1_site SET domain = 'eslov-se-new.ddev.site' WHERE id = 1;
UPDATE eslovwp1_blogs SET domain = 'eslov-se-new.ddev.site' WHERE domain = 'eslov.se';
UPDATE eslovwp1_blogs SET domain = REPLACE(domain, '.eslov.se', '.eslov-se-new.ddev.site') WHERE domain LIKE '%.eslov.se';
"

# siteurl must include /wp (WordPress core lives in wp/). home stays at site root.
# Without /wp on siteurl, login form posts to root wp-login.php (redirect stub) and loops.
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

# Config must match: config/multisite.php → SUBDOMAIN_INSTALL true (Eslöv is subdomain multisite)

# DDEV routing — subsites are {name}.eslov-se-new.ddev.site (subdomain multisite).
# Add to .ddev/config.yaml then ddev restart:
#
#   additional_hostnames:
#     - "*.eslov-se-new"
#
# Without this, subsites return 404 (only the main hostname is routed).

# Reset caches
ddev wp cache flush
ddev wp rewrite flush

# Snapshot for rollback before experimenting
ddev export-db --file=after-import.sql.gz
```

### Remote media (skip local uploads copy)

Like Piteå: nginx proxies missing files from production; mu-plugin rewrites prod URLs to your DDEV host.

```bash
cp .ddev/env/remote-media.env.example .ddev/env/remote-media.env
ddev restart
```

Default upstream is `https://eslov.se` (`/app/uploads/` on prod; local `/wp-content/uploads/` is remapped to prod `/app/uploads/`). No credentials needed for public prod media.

After restart, verify:

```bash
curl -sI "https://eslov-se-new.ddev.site/app/uploads/dashicon-event.svg" | head -3
```

### Investigate imported data

```bash
ddev wp plugin list --status=active
ddev wp option list --search="*municipio*"
ddev wp post list --post_type=page --fields=ID,post_title --posts_per_page=5
ddev wp post meta list {ID} --keys=*
ddev wp post meta get {ID} _modularity
```

### Run adaptation plugin commands

```bash
ddev wp plugin activate eslov-customisation
ddev wp eslov migrate --help
ddev wp eslov migrate modules --dry-run
```

### Rollback

```bash
ddev import-db --file=after-import.sql.gz
ddev wp cache flush
```

## Target layout (`eslov-se-new`)

| Path | What |
|------|------|
| Repo root | ddev docroot |
| `wp/` | WordPress core |
| `wp-content/` | Themes, plugins, uploads |
| `wp-cli.yml` | `path: wp/` |

## Forensics layout (`eslov-se`)

| Path | What |
|------|------|
| `web/` | ddev docroot |
| `web/wp/` | WordPress core |
| `web/app/` | LTS plugins — grep for meta keys, not for porting |
| `wp-cli.yml` | `path: web/wp`, `docroot: web` |

## Common tasks

```bash
ddev wp plugin list
ddev wp plugin deactivate orphan-plugin-slug
ddev wp cache flush
ddev wp rewrite flush
ddev wp user list
```

## Debug log

```bash
ddev exec tail -f wp-content/debug.log
```

Enable `WP_DEBUG` in `config/developer.php` if not already on.

## Composer install

```bash
cd eslov-se-new
composer install
```

See `composer-local-merge` skill for `composer.local.json` packages.

## Agent notes

- **Never** blind `search-replace` on serialized meta — use `eslov-customisation` migrators.
- Deactivate LTS-only plugins active in DB but absent from deployment `composer.json`.
- Log every fix in `.cursor/plans/db-migration.md`.
