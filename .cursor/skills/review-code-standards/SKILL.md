---
name: review-code-standards
description: >-
  Review eslov-customisation and migration code for Municipio best practices:
  migrate vs shim choice, no core edits, idempotent CLI, logging. Explicitly
  invoked via slash command.
disable-model-invocation: true
---

# review-code-standards

Review the specified context for Municipio and **Eslöv migration** standards.

## Instructions

1. **Identify the context** — path referenced or open file.

2. **Report**
   - What is correct.
   - Issues with file/line and fix.
   - Flag core theme edits, LTS plugin ports, or non-idempotent migrations.

3. **Summary** — "Ready to ship" / "Minor fixes" / "Needs refactor" + top actions.

## Migration-specific checks

- [ ] Code is in `eslov-se-new/`, not `eslov-se/`
- [ ] Migration fixes live in `eslov-customisation`, not scattered scripts
- [ ] One-time migrations have `--dry-run` and are idempotent
- [ ] Runtime shims are documented (which LTS behaviour they replace; removable?)
- [ ] Fix logged in `.cursor/plans/db-migration.md`
- [ ] No blind search-replace on serialized meta
- [ ] No edits to `wp-content/themes/municipio/`
- [ ] Custom packages in `composer.local.json`, not `composer.json`

## Municipio checks

- Hooks match target Municipio version (see theme `readme.md`)
- PHPDoc on public methods; no chat-context inline comments
