---
name: municipio-extend-via-hooks
description: >-
  Extend Municipio without editing core theme files. Covers Municipio/viewPaths,
  Municipio/Template/viewData, Modularity/externalViewPath, render_blade_view().
  Use for runtime shims in eslov-customisation or ongoing site hooks.
---

# Extend Municipio via hooks (never edit core)

**Rule:** Do not edit `wp-content/themes/municipio/`. Put hooks in **`eslov-customisation`**.

For the Municipio mental model, see `municipio-framework`.
For one-time data rewrites, prefer WP-CLI in `eslov-adaptation-plugin` skill over permanent shims.

## When to use hooks in migration

| Situation | Prefer |
|-----------|--------|
| Old meta key still in DB, new code reads new key | **One-time migration** command |
| Ongoing site preference (search, excerpts, CPT args) | **Runtime shim** in eslov-customisation |
| Template override because data can't fix rendering | **Shim** via `Municipio/viewPaths` (last resort) |

## Decision tree

| Goal | Approach |
|------|----------|
| Override a Municipio Blade template | `Municipio/viewPaths` — prepend plugin `views/` |
| Change data passed to a template | `Municipio/Template/viewData` filter |
| Modularity module Blade views | `/Modularity/externalViewPath` filter |
| AJAX/REST returning HTML fragments | `render_blade_view()` |
| Site-wide Municipio behaviour | eslov-customisation hooks |

## Municipio/viewPaths

```php
add_filter('Municipio/viewPaths', function (array $paths): array {
    array_unshift($paths, ESLOV_CUSTOMISATION_PATH . 'views');
    return $paths;
});
```

## Municipio/Template/viewData

```php
add_filter('Municipio/Template/viewData', function ($data, $template) {
    if ($template !== 'search') {
        return $data;
    }
    return $data;
}, 10, 2);
```

## /Modularity/externalViewPath

```php
add_filter('/Modularity/externalViewPath', function ($arr) {
    $arr['mod-slug'] = MODULE_VIEW_PATH;
    return $arr;
}, 10, 3);
```

## LTS forensics — old hooks in eslov-se

LTS `web/app/mu-plugins/settings.php` used filters like:

- `Municipio/Hook/showSiteNameInSearchResult`
- `Municipio/Helper/Post/EmptyExcerpt`

LTS `views.php` used legacy Modularity hooks (`Modularity/Module/TemplatePath`, `Modularity/CoreTemplatesSearchPaths`). Prefer data migration over replicating view path hacks unless necessary.

Grep LTS for the filter you need to replicate, then add equivalent shim in eslov-customisation and document in `.cursor/plans/db-migration.md` shim registry.

## Exemplar references

| LTS file | Typical disposition |
|----------|---------------------|
| `eslov-se/web/app/mu-plugins/settings.php` | Shim filters in eslov-customisation |
| `eslov-se/web/app/mu-plugins/custom-post-types.php` | Shim or migrate CPT args |
| `eslov-se/web/app/mu-plugins/views.php` | Usually unnecessary after data migration |
