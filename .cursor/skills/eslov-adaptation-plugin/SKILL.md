---
name: eslov-adaptation-plugin
description: >-
  Build and extend eslov-customisation — the single plugin for Eslöv DB migration
  (WP-CLI commands) and runtime shims. Covers migrate-once vs hook-forever, plugin
  structure, and logging fixes. Use when fixing post-import errors or adding
  data transforms.
---

# eslov-customisation (adaptation plugin)

All Eslöv-specific migration and adaptation code lives in **one plugin**, similar to Piteå's `pitea-customisation`.

**Reference data:** `eslov-2026-06-23-77d6623-lean.sql` at repo root (~610 MB lean dump). Import with `ddev import-db --file=eslov-2026-06-23-77d6623-lean.sql` before running migration commands. Log/audit/cache table data (incl. Aryo failed-login rows) is omitted — content and module data are intact.

**Location:** `wp-content/plugins/eslov-customisation/` (or Composer VCS package in `composer.local.json`).

## Two fix types — choose deliberately

| Type | Purpose | Lifecycle |
|------|---------|-----------|
| **One-time migration** | Rewrite DB: meta keys, module JSON, options | WP-CLI command; idempotent; `--dry-run` support; can deprecate after prod run |
| **Runtime shim** | Bridge unmigrated rows or permanent site preference | Hook/filter in plugin bootstrap; document why it stays |

**Project owner preference:** default to **one-time migration** — transform imported data so new Municipio/Modularity reads it natively. Do **not** add permanent legacy shims that translate old LTS meta on every request unless migration is infeasible.

Use shims only when:
- Data cannot be rewritten safely (ambiguous/lossy transform, serialized edge cases)
- The issue is an **upstream core bug**, not old data shape
- The fix is an **ongoing site preference** (e.g. old `settings.php` filters), not a DB incompatibility
- Editors need ongoing custom UI (ACF extensions) that standard fields do not provide

If you reach for a shim, document in the breakage matrix **why migration was rejected**.

## Recommended plugin structure

```
eslov-customisation/
├── eslov-customisation.php      # Bootstrap, load CLI + hooks
├── source/php/
│   ├── App.php                  # Hook registration
│   ├── Cli/
│   │   ├── MigrateCommand.php   # wp eslov migrate …
│   │   └── …                    # One command per migration concern
│   ├── Migration/
│   │   ├── MetaKeyMigrator.php  # Pure transform logic (testable)
│   │   └── ModuleJsonMigrator.php
│   └── Shim/
│       └── …                    # Runtime filters (minimal)
└── composer.json                # PSR-4 autoload if needed
```

## WP-CLI command pattern

Register when `WP_CLI` is defined:

```php
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('eslov migrate', MigrateCommand::class);
}
```

Command conventions:

- Support `--dry-run` (log changes, don't write)
- Support `--post-id=` for single-post debugging
- Log counts: `Migrated 142 posts, skipped 0, errors 2`
- Make commands **idempotent** (safe to re-run)

Example invocations:

```bash
ddev wp eslov migrate modules --dry-run
ddev wp eslov migrate meta-keys --post-id=123
ddev wp eslov migrate options
```

## Runtime shim pattern

For ongoing Municipio preferences (from LTS `settings.php` etc.):

```php
add_filter('Municipio/Hook/showSiteNameInSearchResult', '__return_false');
```

Document in plugin README or docblock **which LTS file** this replaces and whether it can be removed after data migration.

## Serialized meta

WordPress stores serialized PHP arrays in `postmeta`. Use:

- `maybe_unserialize()` when reading
- `update_post_meta()` when writing (WordPress re-serializes)
- Never blind `search-replace` on serialized values — use dedicated migrator classes

For Modularity layouts, inspect actual JSON/meta structure on a sample post before writing transforms:

```bash
ddev wp post meta get {ID} _modularity
ddev wp post meta list {ID} --keys=*
```

## Register in Composer (optional)

If the plugin is its own Git repo:

```json
// composer.local.json
{
  "repositories": [{ "type": "vcs", "url": "https://github.com/org/eslov-customisation" }],
  "require": { "org/eslov-customisation": "dev-main" }
}
```

For local development without VCS, place directly in `wp-content/plugins/eslov-customisation/` and activate:

```bash
ddev wp plugin activate eslov-customisation
```

## Logging fixes

Every fix must get a row in `.cursor/plans/db-migration.md`:

| Error / symptom | Root cause | Fix type | Command/hook | Status |
|-----------------|------------|----------|--------------|--------|

## What NOT to put here

- LTS plugin copies (`mod-open-hours`, `swimport`, etc.) — transform their **data** instead
- Theme edits
- One-off scripts outside the plugin (hard for agents to find)

## Related skills

| Skill | When |
|-------|------|
| `municipio-extend-via-hooks` | Shim hook reference |
| `ddev-wp-cli` | Run commands, import DB |
| `municipio-framework` | Understand what new code expects |
| `composer-local-merge` | VCS package registration |
