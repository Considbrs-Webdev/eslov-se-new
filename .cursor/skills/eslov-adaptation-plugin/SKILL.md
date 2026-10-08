---
name: eslov-adaptation-plugin
description: >-
  Extend eslov-customisation for the live Eslöv Municipio site. Production
  cutover is done. Fix newly found breakage here (hooks, views, CSS, narrow
  data repairs). Do not add wp eslov migrate commands; that CLI is frozen.
---

# eslov-customisation (adaptation plugin)

All Eslöv-specific site code lives in **one plugin**, similar to Piteå's `pitea-customisation`.

**Location:** `wp-content/plugins/eslov-customisation/` (its own git repo, installed under `wp-content/plugins/`).

**Cutover status:** production is live on standard Municipio. `wp eslov migrate` already ran. The command classes stay in the plugin as a frozen record. Do not register new migrate commands, and do not re-run `migrate all` to fix a newly reported bug.

## Where new fixes go

| Kind of bug | Where |
|-------------|--------|
| Rendering, layout, missing behaviour, site preference | Hook, view, or CSS under `source/php/Customisations/` (or the relevant module views) |
| One post or one field still wrong | A narrow data repair that is **not** added to `MigrationRegistry` |
| Something the old cutover script already knew how to rewrite | Leave the frozen command alone unless someone explicitly asks to re-run it |

The old default — "add a one-time `wp eslov migrate` task" — applied during cutover. It does not apply to bugs found after go-live.

## Where code goes

```
eslov-customisation/
├── eslov-customisation.php          # Bootstrap
├── source/php/
│   ├── App.php                      # Registers runtime classes
│   ├── Customisations/              # Hooks, views data, site behaviour
│   ├── Cli/Migrate/                 # FROZEN cutover commands — do not add files
│   └── Migration/                   # FROZEN cutover transforms — do not add files
└── source/sass/                     # Site CSS
```

Add a runtime fix by creating a class in `source/php/Customisations/`, wiring hooks in its constructor, and adding the class to `App::registerInstances()`.

`source/php/Cli/` and `source/php/Migration/` are the frozen cutover suite (`CliBootstrap`, `MigrationRegistry`). Leave them in the repo. Do not register another command there.

## Serialized meta

If a narrow repair must write post meta, WordPress stores serialized PHP arrays in `postmeta`. Use:

- `maybe_unserialize()` when reading
- `update_post_meta()` when writing (WordPress re-serializes)
- Never blind `search-replace` on serialized values

For Modularity layouts, inspect actual JSON/meta structure on a sample post before writing a repair:

```bash
wp post meta get {ID} _modularity
wp post meta list {ID} --keys=*
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

For local development without VCS, place the plugin in `wp-content/plugins/eslov-customisation/` and activate it with `wp plugin activate eslov-customisation`.

## Logging fixes

Log new production bugs under **Post-cutover breakage** in `.cursor/plans/db-migration.md`:

| Error / symptom | Page / context | Cause | Fix | Status |
|-----------------|----------------|-------|-----|--------|

The older breakage matrix above that section is the cutover log. Do not reopen it as the work queue.

## What NOT to put here

- New `wp eslov migrate` commands
- Copies of old LTS plugins
- Theme edits
- One-off scripts outside the plugin (hard for agents to find)

## Related skills

| Skill | When |
|-------|------|
| `municipio-extend-via-hooks` | Hook and view-override reference |
| `municipio-framework` | Understand what current Municipio expects |
| `composer-local-merge` | VCS package registration |
