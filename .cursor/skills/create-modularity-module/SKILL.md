---
name: create-modularity-module
description: >-
  Scaffold a new Modularity module via the official boilerplate. Use only if migration
  reveals a missing module type that cannot be solved by data transform or deployment
  packages — not for porting LTS custom modules.
---

# Create a Modularity Module

**Migration context:** Do **not** port LTS modules (`mod-open-hours`, `ws-branded-border`, etc.) by default. Transform their DB data to deployment module packages first.

Use this skill only when:

- Deployment lacks an equivalent module for required content, **and**
- Data migration cannot map to an existing `helsingborg-stad/modularity-*` package

## 1. Scaffolding

1. Navigate to `wp-content/plugins/` in **eslov-se-new**.
2. `git clone git@github.com:viktor7ltz/modularity-boilerplate.git modularity-{module-name}`
3. `rm -rf modularity-{module-name}/.git`
4. `php rename.php "{New Module Name}"`
5. Delete `rename.php`.

## 2. Registration (App.php)

```php
add_action('init', array($this, 'registerModule'));

public function registerModule() {
    if (function_exists('modularity_register_module')) {
        modularity_register_module(MODULE_PATH . 'source/php/Module/', 'ModuleClassName');
    }
}

add_filter('/Modularity/externalViewPath', function ($arr) {
    $arr['mod-{module-slug}'] = MODULE_VIEW_PATH;
    return $arr;
}, 10, 3);
```

## 3. Register in Composer

Add to `composer.local.json` if VCS-hosted, or commit in deployment fork if site-specific.

## LTS reference

`eslov-se/web/app/plugins/mod-open-hours/` — grep for **meta/module JSON shape**, do not copy the plugin wholesale.
