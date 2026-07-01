---
name: composer-local-merge
description: >-
  Add site-specific Composer packages to municipio-deployment via composer.local.json
  and the composer-local-merge.php workflow. Use when adding Eslöv custom plugins,
  VCS repositories, or require-dev packages to eslov-se-new.
---

# Composer local merge (municipio-deployment)

Custom dependencies for Eslöv go in **`composer.local.json`**, not `composer.json`. Primary use case: register **`eslov-customisation`** as a VCS package when it lives in its own repo.

For local-only development, place the plugin directly in `wp-content/plugins/eslov-customisation/` without Composer — activate with `ddev wp plugin activate eslov-customisation`.

## Add a package

Edit `composer.local.json`:

```json
{
  "name": "municipio-se/municipio-deployment-custom",
  "license": "MIT",
  "description": "Eslöv-specific additions.",
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/your-org/your-plugin"
    }
  ],
  "require": {
    "your-org/eslov-customisation": "dev-main"
  }
}
```

## Install (always wrap with merge script)

```bash
php composer-local-merge.php pre-install
composer install
php composer-local-merge.php post-install
```

Or use Composer scripts (already configured in `composer.json`):

```bash
composer install
```

The `pre-install-cmd` / `post-install-cmd` hooks run the merge automatically.

## How it works

1. **Pre-install** — backs up `composer.json` + `composer.lock`, merges `composer.local.json` entries, removes lock for fresh resolve
2. **Install** — `composer install` against merged config
3. **Post-install** — restores original `composer.json` and `composer.lock`

If `composer.local.json` has no `repositories`, `require`, or `require-dev` entries, the script exits immediately.

## Local plugins without Composer

Plugins prefixed with `local_` in `wp-content/plugins/` are preserved during deploy (not removed by rsync).

## Lockfile updates

If dependency conflicts occur, use:

```bash
composer update-lockfile
```

Commit the updated `composer.lock` after resolving.

## Do not

- Add Eslöv-specific packages directly to `composer.json` (breaks upstream sync)
- Run bare `composer install` after manually editing `composer.json` without the merge script
- Commit merged/temporary composer.json changes
