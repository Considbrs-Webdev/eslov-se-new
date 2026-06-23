# Eslöv Municipio — Architecture (agent reference)

Concise map for agents migrating **production data** from Municipio LTS to standard Municipio deployment. Deep workflows live in `.cursor/skills/` and `.cursor/plans/db-migration.md`.

## Migration strategy

- **Import prod DB** into `eslov-se-new`, fix errors iteratively.
- **No LTS plugin porting** — use `eslov-customisation` for data transforms and runtime shims.
- **LTS repo (`eslov-se/`)** is a forensics dictionary for old meta keys, module slugs, and option names.

## Reference database

All migration work uses **`eslov-db.sql`** at the repo root:

| Property | Value |
|----------|-------|
| File | `eslov-db.sql` (~1.6 GB) |
| Format | MariaDB 10.11 dump |
| Source DB name | `wordpress` |
| `siteurl` / `home` in dump | `https://storatorg.eslov.w8e.se` |
| Content URLs | Also contains `https://eslov.se` in posts/options |

**Git:** local reference only — in `.gitignore`. Replace the file to refresh from a newer export; update the baseline table in `.cursor/plans/db-migration.md`.

**Import:**

```bash
cd eslov-se-new
ddev import-db --file=eslov-db.sql
ddev wp search-replace 'https://storatorg.eslov.w8e.se' 'https://eslov-se-new.ddev.site' --all-tables
ddev wp search-replace 'https://eslov.se' 'https://eslov-se-new.ddev.site' --all-tables
ddev wp cache flush && ddev wp rewrite flush

# Multisite: update domain columns (search-replace skips these)
ddev exec mysql -udb -pdb db -e "
UPDATE eslovwp1_site SET domain = 'eslov-se-new.ddev.site' WHERE id = 1;
UPDATE eslovwp1_blogs SET domain = 'eslov-se-new.ddev.site' WHERE domain = 'eslov.se';
UPDATE eslovwp1_blogs SET domain = REPLACE(domain, '.eslov.se', '.eslov-se-new.ddev.site') WHERE domain LIKE '%.eslov.se';
"
```

**Multisite:** Eslöv is subdomain multisite — `config/multisite.php` must have `SUBDOMAIN_INSTALL true`. If `eslovwp1_blogs.domain` still points at `eslov.se`, WordPress shows a misleading **"Error establishing a database connection"** (actually: site not found in network).

## Dual-repository workspace

```
eslov-migration-workspace/
├── eslov-se-new/          ← TARGET: edit here, prod DB lives here
└── eslov-se/              ← FORENSICS: understand old data semantics only
```

| | **eslov-se** (LTS forensics) | **eslov-se-new** (deployment target) |
|---|---|---|
| Role | Read old plugin code to learn **data shapes** | Import DB, run new Municipio, write fixes |
| Upstream | [municipio-lts-deployment v25.x](https://github.com/municipio-se/municipio-lts-deployment) | [municipio-deployment](https://github.com/municipio-se/municipio-deployment) |
| Structure | Bedrock (Roots) | Municipio standard deployment |
| Docroot | `web/` | repo root |
| WordPress core | `web/wp/` | `wp/` |
| Content path | `web/app/` | `wp-content/` |
| Custom deps | `composer.json` | `composer.local.json` |
| Theme package | `municipio/municipio` | `helsingborg-stad/municipio` |
| ddev | `eslov-se` | `eslov-se-new` |

### Path mapping (forensics only)

When grepping LTS code to understand data, translate paths mentally:

| LTS (`eslov-se`) | Deployment (`eslov-se-new`) |
|---|---|
| `web/app/plugins/{name}/` | `wp-content/plugins/{name}/` (usually **not** installed) |
| `web/app/mu-plugins/` | Legacy loose PHP — behaviour may need shim in `eslov-customisation` |
| `web/app/uploads/` | `wp-content/uploads/` (imported with DB) |

## Target repo layout (`eslov-se-new`)

| Path | Purpose |
|------|---------|
| `index.php` | Front controller → `wp/wp-blog-header.php` |
| `wp/` | WordPress core (Composer-managed) |
| `wp-content/` | Themes, plugins, mu-plugins, uploads |
| `wp-content/plugins/eslov-customisation/` | **All Eslöv migration and site adaptation code** |
| `config/` | Split wp-config |
| `composer.json` | Upstream Municipio packages — do not add site deps here |
| `composer.local.json` | Eslöv packages (e.g. eslov-customisation as VCS) |
| `.cursor/plans/db-migration.md` | Living breakage matrix and phase checklist |

**WP-CLI:** `ddev wp …` from repo root. See `ddev-wp-cli` skill.

## Where to put code

```
Need to…                                    → Put it in…
──────────────────────────────────────────────────────────────────
Rewrite old post meta / options / modules   → eslov-customisation CLI command (one-time)
Temporary bridge for unmigrated data        → eslov-customisation hook/filter (shim)
Ongoing site Municipio preferences          → eslov-customisation hooks
Register plugin via Composer                → composer.local.json
New Modularity module (rare)                  → Separate plugin — only if explicitly needed
Theme behaviour                               → Never edit theme — hook in eslov-customisation
```

## Legacy LTS artefacts — data impact map

Use `eslov-se/` to grep **meta keys, option names, module slugs, ACF field names** — not to port PHP.

### Custom plugins — migration disposition

| LTS plugin | Likely data in DB | Disposition |
|------------|-------------------|-------------|
| `content-insights-for-editors` | Editor analytics meta, options | **Drop or replace** — evaluate if data needed |
| `mod-open-hours` | Modularity module instances (`mod-open-hours`) | **Transform** — map to `helsingborg-stad/modularity-*` equivalent or shim |
| `swimport` | SimpleView event/post meta | **Transform** — migrate meta to new event integration if used |
| `ws-branded-border` | Module instances | **Transform** — map module JSON to new module slug |
| `mfpconnect` / `mfpconnectmedia` | Mediaflow attachment meta | **Defer** — separate decision; may need plugin later |

### Mu-plugin PHP files — migration disposition

| LTS file | What it did | Disposition |
|----------|-------------|-------------|
| `settings.php` | Municipio filters (search site name, excerpt) | **Shim** in eslov-customisation if still wanted |
| `views.php` | View path registration | **Unlikely needed** — new theme handles views; fix data not paths |
| `custom-post-types.php` | Adds `post_tag` to CPT args | **Shim or migrate** — filter in eslov-customisation |
| `job-listings.php` | Job listing template/data | **Transform** — job-listing post meta + template hooks |
| `cife.php` | CIFE bootstrap | **Drop** unless CIFE reinstalled |
| `url-extractor.php` | URL utility | **Drop** unless still needed |
| `db-dumps.php` | Dev tooling | **Ignore** |
| `disable-gutenberg.php` | Widget block editor off | **Shim** if still required |

### Blade overrides (LTS mu-plugins/views/)

| View | Disposition |
|------|-------------|
| `single-job-listing.blade.php` | **Shim** via `Municipio/viewPaths` only if data migration alone isn't enough |
| `mxui/job-listing/article-content-before.blade.php` | Same |

### municipio-extended

`municipio-extended/autoload/mod-notice.php` — check notice module data in DB; **transform** module instances or **shim**.

### LTS Composer packages vs deployment

| LTS package | Deployment equivalent |
|-------------|----------------------|
| `municipio/municipio` | `helsingborg-stad/municipio` |
| `municipio/wp-plugin-modularity-sections` | `helsingborg-stad/modularity-sections` |
| `municipio/wp-plugin-modularity-timeline` | `helsingborg-stad/modularity-timeline` |
| `municipio/wp-plugin-hbg-open-hours` | Check deployment `composer.json` |
| WPackagist plugins | May differ — compare both composer files |

Module slug or meta structure may differ between LTS and deployment versions even when package names match.

## Data forensics — what to grep in LTS

When an error references unknown meta, modules, or options:

```bash
# From eslov-se/ — find meta keys a plugin reads/writes
rg "get_post_meta|update_post_meta|get_option" web/app/plugins/{plugin}/
rg "modularity_register_module|'mod-" web/app/plugins/
rg "acf_add_local_field_group|field_" web/app/plugins/
```

On imported DB (in eslov-se-new):

```bash
ddev wp db query "SELECT DISTINCT meta_key FROM wp_postmeta WHERE meta_key LIKE '%mod%' LIMIT 50"
ddev wp option list --search="*municipio*"
ddev wp post list --post_type=mod-* --fields=ID,post_title,post_type
```

Document findings in `.cursor/plans/db-migration.md`.

## Common breakage categories (after DB import)

| Category | Symptoms | Typical fix location |
|----------|----------|---------------------|
| PHP fatals on boot | White screen, missing class | Deactivate orphan plugin refs, Municipio upgrade routine |
| Modularity module mismatch | Empty module, wrong template | Transform `postmeta` module JSON / slug in CLI command |
| ACF field mismatch | Missing fields in admin | Transform field keys or run ACF sync after migration |
| Options / theme mods | Wrong search, logo, colors | `wp option update` or migration command |
| Search index | No search results | Reindex Algolia/Typesense per deployment docs |
| URLs | Broken links, mixed content | `wp search-replace` + config |
| Municipio upgrade | Admin upgrade prompts | `wp-content/themes/municipio/library/Upgrade/` |

## Municipio framework

No external docs — the theme codebase is the spec. See `municipio-framework` skill.

For runtime shims: `municipio-extend-via-hooks` skill.

## Agent skills index

| Skill | When |
|-------|------|
| `eslov-migration-workspace` | Strategy, loop, repo roles |
| `eslov-adaptation-plugin` | Write fixes in eslov-customisation |
| `municipio-framework` | Diagnose new-structure errors |
| `municipio-extend-via-hooks` | Runtime shims |
| `ddev-wp-cli` | DB import, WP-CLI |
| `composer-local-merge` | Composer packages |
| `review-code-standards` | Code review |
