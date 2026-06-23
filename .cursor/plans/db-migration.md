---
name: Eslöv DB migration (LTS → municipio-deployment)
overview: >-
  Import eslov-db.sql into eslov-se-new, fix boot blockers and data
  incompatibilities iteratively via eslov-customisation. No LTS plugin porting.
todos:
  - id: env-setup
    content: "ddev start, composer install, activate eslov-customisation, record baseline below"
    status: pending
  - id: db-import
    content: "Import eslov-db.sql, search-replace URLs, flush cache, export after-import snapshot"
    status: pending
  - id: boot-blockers
    content: "Fix PHP fatals and white screen only — deactivate orphan plugins, missing deps"
    status: pending
  - id: municipio-upgrade
    content: "Run Municipio upgrade routines in admin or WP-CLI; note library/Upgrade/ changes"
    status: pending
  - id: modularity-data
    content: "Transform Modularity module slugs/JSON for deployment module packages"
    status: pending
  - id: meta-options
    content: "Migrate post meta keys, options, ACF references identified in breakage matrix"
    status: pending
  - id: search-media
    content: "Reindex search (Algolia/Typesense), verify uploads/S3 paths"
    status: pending
  - id: visual-pass
    content: "Walk key page types; fill Severity/Status on matrix rows"
    status: pending
  - id: shim-audit
    content: "Review runtime shims — convert to one-time migrations where possible"
    status: pending
isProject: true
---

# Eslöv DB migration — living checklist

**Strategy:** Import `eslov-db.sql` → fix errors iteratively → all code in `eslov-customisation`.

**Agent skills:** `eslov-migration-workspace`, `eslov-adaptation-plugin`, `municipio-framework`, `ddev-wp-cli`

### Project preference: migrate data, not legacy shims

**Default fix:** rewrite imported DB data (WP-CLI one-time migration) so it matches **new Municipio / Modularity expectations**. After migration, the site should run on standard theme/module code without reading legacy meta keys or LTS-only field shapes at runtime.

**Avoid:** permanent runtime shims that translate old LTS data on every request. Treat shims as temporary bridges only — convert to migrations during the shim-audit phase, or delete once data is transformed.

**Still valid without migration:** upstream core bugs (patch/shim until upstream fix), ongoing **site preferences** (filters that are not data transforms), missing modules forked in `eslov-customisation`, and editor UI that extends standard ACF (not a DB rewrite).

When proposing a fix, state why migration is or is not feasible before adding a shim.

---

## Baseline

Record when starting (update after each fresh import):

| Item | Value |
|------|-------|
| Reference DB file | `eslov-db.sql` (~1.6 GB, MariaDB 10.11) |
| Dump `siteurl` | `https://storatorg.eslov.w8e.se` |
| Import date | |
| Post-import snapshot | `after-import.sql.gz` (local, gitignored) |
| `helsingborg-stad/municipio` version | |
| WordPress version | |
| Site boots without fatal? | |
| eslov-customisation active? | Yes (2025-06-02) |

---

## Phase 0 — Environment

- [ ] `cd eslov-se-new && ddev start`
- [ ] `composer install` (merge script runs automatically)
- [x] Scaffold / activate `eslov-customisation`
- [ ] Copy `config-example/` → `config/` if not done
- [ ] Confirm site loads on fresh DB before prod import

## Phase 1 — Database import

Reference dump: **`eslov-db.sql`** (repo root, ~1.6 GB, not in git).

- [ ] Confirm `eslov-db.sql` is present at repo root
- [ ] `ddev import-db --file=eslov-db.sql` (may take several minutes)
- [ ] `ddev wp search-replace 'https://storatorg.eslov.w8e.se' 'https://eslov-se-new.ddev.site' --all-tables`
- [ ] `ddev wp search-replace 'https://eslov.se' 'https://eslov-se-new.ddev.site' --all-tables`
- [ ] **Multisite domains** — update `eslovwp1_site` + `eslovwp1_blogs` (see `ddev-wp-cli` skill)
- [ ] **siteurl `/wp` suffix** — `siteurl` must be `{home}/wp` on every blog or login loops (form posts to redirect stub)
- [ ] Confirm `config/multisite.php` has `SUBDOMAIN_INSTALL true`
- [ ] **DDEV subsite routing** — add `additional_hostnames: ["*.eslov-se-new"]` in `.ddev/config.yaml`, run `ddev restart`
- [ ] `ddev wp cache flush`
- [ ] `ddev wp rewrite flush`
- [ ] **Remote media** — `cp .ddev/env/remote-media.env.example .ddev/env/remote-media.env && ddev restart` (proxies `/app/uploads/` from `eslov.se`; no local uploads copy)
- [ ] Export snapshot: `ddev export-db --file=after-import.sql.gz`
- [ ] Note first error (fatal, warning, or blank module)

## Phase 2 — Boot blockers (fatals only)

Fix only what prevents the site from loading. No visual polish.

- [ ] Read `wp-content/debug.log`
- [ ] `ddev wp plugin list --status=active` — deactivate plugins not in deployment
- [ ] Check Municipio `library/Upgrade/` for pending migrations
- [ ] `ddev wp modularity upgrade` — see **Modularity upgrade** below
- [ ] Site loads admin + front page?

### Modularity upgrade (`ddev wp modularity upgrade`)

Runs Modularity V5→V8 migrations (module post types, grid classes, manual-input repeater, etc.).

**Upstream bug (patched locally):** `AcfModuleMigrationHandler::migrateFieldByType()` declared `: bool` but `update_field()` can return an `int` meta ID on first write. Patch: cast `(bool)` on migrator return in `wp-content/themes/municipio/Modularity/source/php/Upgrade/Migrators/Module/AcfModuleMigrationHandler.php`. Re-apply after `composer update` on municipio theme until upstream fixes it.

**Expected warnings (non-fatal):**
- `No pages or fields found for block migration` — no block data to migrate
- `Failed to update field columns` on modules already migrated (e.g. 556809)
- `Failed to update repeater field manual_inputs` — modules where repeater sub-fields could not be mapped (investigate individually if content missing)

**Success:** `Success: Database migration complete; upgraded to version 8.`

## Phase 3 — Data migration (iterative)

Work the breakage matrix below. One row per fix.

- [ ] `ddev wp eslov migrate all --network --dry-run` then `ddev wp eslov migrate all --network` (every blog in the network; network-activates `eslov-customisation`)
- [ ] Or per task: `ddev wp eslov migrate design-tokens --network`
- [ ] Modularity module slug/JSON transforms
- [ ] Job listing post meta / templates
- [ ] Legacy options and theme mods
- [ ] ACF field key alignment
- [ ] CPT/taxonomy args (e.g. post_tag on custom types)
- [ ] Orphan meta cleanup

## Phase 4 — Integrations

- [ ] Search reindex
- [ ] Media / S3 paths
- [ ] SAML / AD (if applicable in local)
- [ ] Deferred: Mediaflow, SimpleView — separate decisions

## Phase 5 — Visual regression

- [ ] Home, section page, news, event, job listing, search
- [ ] Modularity modules render with content
- [ ] Admin: edit and save a page (block editor)

---

## Breakage matrix

Add a row for every error fixed. Agents: update Status when done.

| # | Error / symptom | Page / context | Root cause (old data) | Fix type | Command / hook | Status |
|---|-----------------|----------------|----------------------|----------|----------------|--------|
| 1 | `modularity upgrade` fatal: Return value must be of type bool, int returned | WP-CLI | ACF `update_field()` returns meta ID (int); handler expects bool | config | Patch `AcfModuleMigrationHandler.php` (bool cast) | done |
| 2 | `Failed to update repeater field manual_inputs` | mod-posts → mod-manualinput modules | Repeater sub-field mapping failed on some modules | migrate | Investigate per module ID if content missing | pending |
| 3 | Broken images locally without full `uploads/` copy | Front / admin media | DB uses `/app/uploads/` (LTS); prod files on `eslov.se` | config | DDEV `ddev-remote-media.php` + nginx uploads proxy | done |
| 4 | `mod-navigation` modules blank / missing in editor | ~52 module posts (children/menu/manual sources) | LTS `municipio-extended` ModNavigation only; views use `tailwind` + `mxui.*` | config | `eslov-customisation`: `Modules/Navigation` + `AcfFields/ModNavigationFields` + Municipio components (no DB migration) | done |
| 4b | `mod-navigation` layout wrong (single-column cards; footer bar empty) | Homepage grid blocks (457769); footer widget bar (458006) | Blade used stacked `@card` without `o-grid-4@md` / `c-card--flat`; bar used `@card` icon cells (broken on primary bg) | config | `Modules/Navigation/views`: `grid/blocks`, `grid/default`, `cards`, `bar/solid`, `bar/outline` — `o-grid` + flat cards; bar → flex `@icon` + link | done |
| 4c | Footer bar icons/text black on primary purple | Bar module 458006 (`format=bar`, solid) | Layer/hover + layout drift from LTS | config | LTS-parity bar: `li.contents`, grid link, 54px icon wrap, hover on link, `@layer theme` CSS | done |
| 4d | Tree child links wrong color / utilities inconsistent across formats | Tree highlighted (and bar hover) | Inline utilities + `@button` chips fight global `.c-link` | config | `Navigation::style()` + Vite `source/sass/mod-navigation.scss` (scoped `.modularity-mod-navigation`, `var(--color--primary)`) | done |
| 4e | Grid format missing top spacing / module wrapper without column class | Homepage grid 457769 | Imported `columnWidth: ""` in `modularity-modules`; no `u-margin__top` on grid roots | config | `ModularityColumnWidth` → `o-grid-12@md` on empty/legacy widths; grid blades `u-margin__top--4` (matches `--o-grid-gap`) | done |
| 4f | `mod-navigation` bar stacks vertically on most pages; widget editor red `modularity-module` errors | Help menu 458006 in `bottom-sidebar`; 6 inactive classic notice widgets | Modularity `hasModule()` only scans `widget_block`; block editor cannot edit legacy `widget_modularity-module` | migrate | `wp eslov migrate widgets` — active classic → block shortcode; inactive classic purged from `wp_inactive_widgets` | done |
| 5 | No child-page pills / taxonomy taglist above content | Singular pages/posts (e.g. kartor section hubs, nyheter with ämne) | LTS `button-navigation.php` + `post.php` (`mxui.taglist`); `below_title` theme mod not handled by core | shim | `eslov-customisation`: `ChildPageLinksBelowTitle` + `TaxonomyTaglist` on `article_content_before` / `_after`; `@tags` via `TaglistRenderer` | in_progress |
| 6 | Top-sidebar section hubs full-bleed, no secondary band / cramped vertical spacing / square buttons | 6 pages with `mod-navigation` buttons in `top-sidebar` (e.g. [Omsorg och stöd](https://eslov.se/omsorg-stod/)) | LTS `municipio-extended` CSS `#sidebar-top-sidebar { background: var(--color-complementary-light) }` + Modularity `o-container` wrapper; core excludes `mod-navigation` from container whitelist; old `.module-title { margin: calc(var(--base)*3) 0 }` removed in Municipio 6; `@group` child-normalization zeros `border-radius` on `@button` children | shim | `TopSidebarLayout` + `views/partials/sidebar.blade.php` (`u-color__bg--secondary`, `u-padding__y--3`) + `module-title` (`u-margin__bottom--3`) + `c-group--skip-child-normalization` on button groups | done |
| 7 | mod-posts lists wrong/unfiltered (Navet multi-tax, bygglov, events) | ~76 live `mod-posts` with LTS `mod_posts_filtering` repeater | LTS replaced standard `posts_taxonomy_*` with multi-row AND/`NOT IN` filter; deployment reads standard fields only | shim | `eslov-customisation`: `AcfFields/ModPostsFilteringFields` + `ModPostsTaxonomyFiltering` on `Modularity/Module/Posts/GetPosts/Args` + `Posts/template`; no DB migration | done |
| 8 | mod-posts cards missing taxonomy tags (e.g. “Råd och stödsamtal”) | Navet/mod-posts segment sliders | LTS `taxonomy_selection_in_fields`; Municipio expects `taxonomy_display` | migrate | `wp eslov migrate mod-posts-taxonomy-display` | done |
| 8b | mod-posts cards show colored term icons (person squares); comment bubble when “Antal kommentarer” unchecked | Navet segment/card layouts | New Municipio `TermIconResolver`; LTS only used empty `page_navigation_icon`. `BackwardsCompatiblePostObject::__get` also leaks `legacyPost->commentCount` over `setPostViewData()` false | shim | `ModPostsHideTermIcons` + `PostObjectWithoutIcon` on `Modularity/Module/Posts/template` | done |
| 8c | `array_merge(): Argument #1 must be of type array, null given` in mod-posts `list.blade.php` | mod-posts list layout (e.g. 458024) | `PostObjectWithoutIcon` wrapped posts after `ListTemplate` set `attributeList` on `BackwardsCompatiblePostObject`; decorator `__get` did not expose those dynamic view props | shim | `PostObjectWithoutIcon::__get` forwards `property_exists` props before delegating to inner object | done |
| 8d | LTS "Kort och lista" (`posts_display_as = mixed`) posts modules render as list | ~25 mod-posts with Eslöv `municipio-extended` mixed layout | `mixed` not in Municipio allowed templates → falls back to `list` | migrate | `wp eslov migrate mod-posts-mixed-display` → `index` + `show_as_slider` + `posts_columns = grid-md-4` | done |
| 8 | `Term::getTermColor(): Return value must be of type string\|false, array returned` | mod-posts segment/card; posts with taxonomy term icons (Navet, anslagstyp) | Municipio core: `getTermIcon()` and `getTermColor()` share one static `Term::$cache` with identical keys; `TermIconResolver` calls both in sequence | shim | `eslov-customisation`: preload `Shim/Municipio/Helper/Term/Term.php` via `MunicipioTermCacheFix` (separate `$iconCache` / `$colorCache`); no DB migration | done |
| 9 | Header tab links font-weight 500 vs prod 700; header default/basic buttons black on primary bg; header sm search submit square right; footer widget links dark on #2d2d2d | Header tabs; Meny button; `#header-search-form` sm field+submit; footer widget plain `<a>` | V4.1 skipped typography/search tokens; scoped header button tokens lose to `.c-button`; `@group` child-normalization zeros direct-child `border-radius` on submit (field inner keeps radius); styleguide never consumes `--c-search-form-border-radius`; generic `a:not([class^=c-])` link rule (later in cascade) beats `.c-footer a` so `--c-link-link-color-mix` uses `--color--background-contrast` (#000) not footer contrast | migrate + config | `wp eslov migrate design-tokens` (SearchFormShapeCorrection, FooterLinkContrastCorrection, PrimaryPaletteCorrection); blade `c-group--skip-child-normalization` on search `@group`; `components/search-forms.scss` consumes `--c-search-form-border-radius`; `components/header-buttons.scss` for basic buttons | done |
| 10 | Mobile drawer / sidebar submenus not indented; missing `c-nav--indent-sublevels` padding | Header mobile menu drawer; sidebar vertical nav | LTS import has no `vetical_menu_indent_sublevels` theme mod (Municipio default false); reference sites (e.g. helsingborg.se) enable Customizer “Indent each level” | migrate | `wp eslov migrate theme-mods` → `vetical_menu_indent_sublevels` = true | done |

**Fix type:** `migrate` = one-time WP-CLI; `shim` = runtime hook; `config` = wp-config/options; `drop` = data not needed

**Status:** `pending` | `in_progress` | `done` | `wontfix`

---

## Shim registry

Runtime shims that remain after migration (aim to keep this list short):

| Hook / filter | Replaces (LTS) | Can remove after? |
|---------------|----------------|-------------------|
| `article_content_before` → `ChildPageLinksBelowTitle` | `municipio-extended/autoload/button-navigation.php` | After native Municipio supports `below_title` + same UX |
| `article_content_before` / `_after` → `TaxonomyTaglist` | `municipio-extended/autoload/post.php` | After core taxonomy placement equivalent exists |
| `Modularity/Module/Posts/GetPosts/Args` + `Posts/template` → `ModPostsTaxonomyFiltering` | `municipio-extended/autoload/mod-posts-filtering.php` | Permanent Eslöv site feature (multi-taxonomy mod-posts filter) |
| `AcfFields/ModPostsFilteringFields` | Same — ACF repeater UI | Permanent — editors create/edit filter rows |
| `Modularity/Module/Posts/template` → `ModPostsHideTermIcons` | LTS post cards (no term icons); respects `posts_fields` for comment count | Permanent — Municipio has no “hide term icon” setting; commentCount __get bug on `BackwardsCompatiblePostObject` |
| Preload patched `Municipio\Helper\Term\Term` (`MunicipioTermCacheFix`) | Municipio core icon/colour cache collision | After upstream Municipio ships separate icon/colour caches |

---

## mod-navigation — fork decision

LTS-only module (52 instances). **No DB migration** — fork render layer in **`eslov-customisation`** (`wp-content/plugins/eslov-customisation/`), same slug `mod-navigation` and ACF keys. Implemented 2025-06-02.

### Approach

| Layer | Decision |
|-------|----------|
| **Data** | Keep all `mod-navigation` posts, `modularity-modules` refs, ACF/meta keys unchanged |
| **PHP** | Port `ModNavigation` item resolution (`children`, `menu`, `manual`, `siblings`) from `municipio-extended`; extend `\Modularity\Module` (not `MxModule`) |
| **Adapters** | Replace `mx_get_image` / `mx_get_menu_item` with Municipio image/icon contracts (as in `mod-posts` / `mod-manualinput`) |
| **Views** | Drop `<div class="tailwind">` + utility layout; use **styleguide layout primitives** (`o-grid`, `@group`) + **components** (`@card`, `@button`, …) |
| **Customizer** | Kirki `mod_navigation_*_style` — re-home or drop if unused on Eslöv |
| **Package** | `eslov-customisation`: `source/php/Modules/Navigation/` + `AcfFields/ModNavigationFields.php`; register in `eslov-customisation.php` |

### Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Migrate all → `mod-quick-links` | Static links only; loses menu/children sources (39+ instances) |
| Migrate → `mod-manualinput` | Same — no live child-page or menu resolution |
| Port LTS `mxui/navigation/*` blades as-is | Whitespace Tailwind hook + `mxui.button` / `mxui.image` dependency |

### Format → Municipio mapping (blade rewrite)

| Format | ~Count | Source (typical) | Layout primitive | Components | Example URL (prod) |
|--------|--------|------------------|------------------|------------|-------------------|
| `grid` | 1 | menu | `o-grid` | `@card` + icon (launcher tiles) | [eslov.se](https://eslov.se/) (457769) |
| `cards` | 33 | children / manual | `o-grid` | `@card` (as `mod-posts` card partial) | [Förskolor](https://eslov.se/utbildning-barnomsorg/forskola/forskolor/) |
| `buttons` | 8 | manual | `@group` / flex row | `@button` | [Utbildning](https://eslov.se/utbildning-barnomsorg/) |
| `tree` | 7 | children | composed sections | `@card`/`@segment` + `@button` or `@collection` for children | Same section hubs as buttons |
| `bar` | 1 | menu (widget) | `@group` grid | `@link` / `@card` icon cells; ref `mod-menu` listing | Any page footer ([eslov.se](https://eslov.se/)) |
| `list` / `inline` | 0 in DB | — | TBD if discovered | `@collection` / `@button` | — |

`mod-quick-links` remains for **new** static card grids only, not a replacement for this module.

### Forensics (LTS paths)

| Item | Location (`eslov-se`) |
|------|------------------------|
| Module class | `web/app/mu-plugins/municipio-extended/psr-4/Modularity/ModNavigation/ModNavigation.php` |
| ACF | `web/app/mu-plugins/municipio-extended/autoload/mod-navigation.php` |
| Views | `views/mod-navigation.blade.php` → `views/mxui/navigation/*` |
| Orphans | 554071 (no host), 554074 (draft host) |

---

## Article taglist / below-title child links

LTS `municipio-extended` features restored in `eslov-customisation` (no Tailwind / `mxui.taglist`).

| Feature | LTS source | Trigger | Render |
|---------|------------|---------|--------|
| Child page pills below title | `autoload/button-navigation.php` | `secondary_navigation_position` = `below_title`, not `page_hide_secondary_menu` | `ChildPageLinksBelowTitle` → `@button` (secondary, filled) |
| Taxonomy term pills | `autoload/post.php` | Per CPT theme mods `…_taxonomies` + `…_taxonomy_placement` | `TaxonomyTaglist` → `@tags` (`beforeLabel` empty; `href` from `redirect_to` or term link) |

| Setting (theme mod) | Example |
|---------------------|---------|
| `secondary_navigation_position` | `below_title` |
| `municipio_customizer_panel_content_types_{post_type}_taxonomies` | e.g. `nyheter` → `['amne']` |
| `municipio_customizer_panel_content_types_{post_type}_taxonomy_placement` | `under_header` → before content; `after_content` → after |

**Verify:** [Kartor, adresser och mätning](https://eslov.se/bygga-bo-miljo/kartor-adresser-och-matning/) (child pills); nyheter single with ämne (taxonomy pills if terms assigned).

---

## Rollback

```bash
ddev import-db --file=after-import.sql.gz   # or earlier snapshot
ddev wp cache flush
```
