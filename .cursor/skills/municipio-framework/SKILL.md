---
name: municipio-framework
description: >-
  Mental model and source index for the Municipio WordPress theme framework.
  Use when you need to understand how Municipio works, where to read in the codebase,
  or which hooks/filters exist — there is no external documentation; the theme IS the spec.
---

# Municipio framework (codebase as documentation)

There is **no official online documentation** for Municipio. The installed theme at
`wp-content/themes/municipio/` is the authoritative reference. **Never edit it** — read
it and extend via hooks, filters, or plugins.

**This install (target):** Municipio via `helsingborg-stad/municipio`. Prod DB imported from LTS — errors often mean **data shape mismatch**, not missing LTS plugins.

For migration context: `eslov-migration-workspace` skill and `.cursor/plans/db-migration.md`.
For extension recipes, see the `municipio-extend-via-hooks` skill.

## Mental model

Municipio is a WordPress **parent theme** (Helsingborg Stad) with several layers:

| Layer | Role |
|-------|------|
| **Template** | Resolves which Blade view to render; loads controller data; applies viewData filters |
| **Blade + Component Library** | Templates use `@typography`, `@button`, `c-*` BEM components |
| **Modularity** | Page builder: modules are custom post types; drag-and-drop in block editor |
| **Schema / PostObject** | Structured content types with schema.org metadata |
| **ACF** | Field groups for theme options, blocks, modules, content types |
| **Plugins** | Where **all site customisation lives** |

### Request lifecycle (simplified)

1. WordPress routes the request.
2. `Municipio\Template` registers view paths (`Municipio/viewPaths` filter).
3. A controller builds `$viewData` for the current template.
4. Filters run: `Municipio/Template/viewData` (+ post-type/template variants).
5. Blade renders from `views/v3/`.
6. Modularity modules render via `\Modularity\Module` subclasses.

## Source index — where to read

| Question | Start here (in theme) |
|----------|------------|
| Boot order, services, ACF import | `library/Bootstrap.php` |
| Page rendering, controllers, viewData | `library/Template.php` |
| View path resolution | `library/Helper/Template.php` |
| Render a Blade partial in PHP | `library/Public.php` → `render_blade_view()` |
| REST endpoint for Blade HTML | `library/Api/View/Render.php` |
| Blade templates | `views/v3/` |
| Modularity core | `Modularity/source/php/ModuleManager.php` |
| Module base class | `Modularity/source/php/Module.php` |
| **Hook/filter catalog (Municipio)** | `readme.md` § Filters |
| **Hook/filter catalog (Modularity)** | `Modularity/readme.md` |
| **Version upgrade migrations** | `library/Upgrade/` |

### Discover hooks

```bash
rg "apply_filters\(['\"]Municipio/" wp-content/themes/municipio/library --no-heading
rg "apply_filters\(['\"]Modularity/" wp-content/themes/municipio/Modularity/source --no-heading
```

## Curated hooks

### Template & views

| Hook | Purpose |
|------|---------|
| `Municipio/viewPaths` | Prepend directories for Blade overrides |
| `Municipio/Template/viewData` | Modify data passed to any template |
| `Municipio/Template/single/viewData` | Single post templates |
| `Municipio/Template/archive/viewData` | Archive templates |
| `Municipio/blade/view_paths` | Extra paths for `render_blade_view()` |

### Modularity

| Hook / function | Purpose |
|-----------------|---------|
| `modularity_register_module($path, $class)` | Register custom module |
| `/Modularity/externalViewPath` | Map module slug → Blade view directory |
| `Modularity/Modules` | Filter registered module list |
| `Modularity/save_block` | Fires when module layout is saved |

Module class extends `\Modularity\Module`, implements `data()` and `template()`.

## Agent workflow (migration)

1. **Read the error** — fatal, empty module, missing field, wrong template?
2. **Check if data or code** — usually old meta/module JSON; grep LTS forensics repo (`eslov-se/`)
3. **Fix in eslov-customisation** — CLI migration first, runtime shim only if needed
4. **Log** — add a row to `.cursor/plans/db-migration.md`
5. **Verify** — `ddev wp` + browser at `https://eslov-se-new.ddev.site`

## Related skills

| Skill | When |
|-------|------|
| `eslov-adaptation-plugin` | Write migration commands and shims |
| `eslov-migration-workspace` | Strategy and agent loop |
| `municipio-extend-via-hooks` | Runtime shims |
| `ddev-wp-cli` | DB import, WP-CLI |
| `create-modularity-module` | Only if module type missing from deployment |
