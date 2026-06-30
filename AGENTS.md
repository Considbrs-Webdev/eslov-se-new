# Agent guide — Eslöv Municipio migration workspace

Entry point for AI agents working on **Eslöv municipio LTS → standard Municipio deployment**.

## Migration strategy

| | |
|---|---|
| **Method** | Import reference database into `eslov-se-new`, fix incompatibilities iteratively |
| **Scope** | No LTS plugin porting — all new code goes in **`eslov-customisation`** |
| **Goal** | Fully functional standardized Municipio using old production data |

### The migration loop

```
import eslov-2026-06-23-77d6623-lean.sql → boot site → read error/symptom → classify fix → implement in eslov-customisation → verify → log in db-migration plan
```

### Reference database

| | |
|---|---|
| **File** | `eslov-2026-06-23-77d6623-lean.sql` (repo root, ~610 MB) |
| **Format** | MariaDB 11.8 lean dump (`eslov` database) |
| **Lean** | Log/audit/cache table **structures** kept; **data omitted** (incl. Aryo Activity Log / failed-login rows) |
| **Git** | Local only — listed in `.gitignore`, do not commit |
| **Import** | `ddev import-db --file=eslov-2026-06-23-77d6623-lean.sql` |

After import, run URL search-replace (dump `siteurl` is `https://storatorg.eslov.w8e.se`; post content may also reference `https://eslov.se`). See `ddev-wp-cli` skill.

See `.cursor/plans/db-migration.md` for the living breakage matrix and phase checklist.

### Fix types (eslov-customisation)

| Type | When | Example |
|------|------|---------|
| **One-time migration** | Old data shape must be rewritten in DB | WP-CLI: `wp eslov migrate meta-keys` |
| **Runtime shim** | Temporary bridge until data is migrated, or permanent edge case | Filter that reads legacy meta key |
| **Site preference** | Ongoing Municipio behaviour (like Piteå customisation) | `Municipio/Template/viewData` filter |

**Project preference:** prefer **one-time migrations** that rewrite DB data to new Municipio/Modularity standards. Avoid permanent legacy shims that translate LTS meta at runtime — use shims only for upstream bugs, ongoing site preferences, or cases where migration is unsafe or lossy.

## Workspace layout

Multi-root workspace — open via `eslov-migration-workspace.code-workspace`:

| Folder | Role |
|--------|------|
| `eslov-se-new/` | **Target** — municipio-deployment fork. **All work happens here.** |
| `eslov-se/` | **Forensics** — LTS Bedrock repo for understanding old data semantics (meta keys, module config). Not for porting plugins. |

## Where to read first

1. **`.cursor/plans/db-migration.md`** — migration phases, breakage matrix, baseline
2. **`ARCHITECTURE.md`** — layout, legacy data inventory, structural diff
3. **`.cursor/skills/eslov-migration-workspace/SKILL.md`** — dual-repo model and agent loop
4. **`.cursor/skills/eslov-adaptation-plugin/SKILL.md`** — where and how to write fixes
5. **`.cursor/skills/municipio-framework/SKILL.md`** — new Municipio structure (why data breaks)

## Hard rules

- **Never edit** `wp-content/themes/municipio/`.
- **All migration code** in `eslov-customisation` (`wp-content/plugins/eslov-customisation/` or Composer package via `composer.local.json`).
- **Do not port LTS plugins** unless explicitly decided — transform data or shim instead.
- **Target repo only** for implementation — `eslov-se/` is read-only forensics.
- **Log every fix** in `.cursor/plans/db-migration.md` breakage matrix.
- **Composer local merge** — site packages in `composer.local.json` (see `composer-local-merge` skill).
- **Block editor** — Municipio uses Gutenberg.

## Skills index

| Skill | When to use |
|-------|-------------|
| `eslov-migration-workspace` | Start here; strategy, loop, repo roles |
| `eslov-adaptation-plugin` | WP-CLI migrations, shims, plugin structure |
| `municipio-framework` | Understand new theme/modules; diagnose errors |
| `municipio-extend-via-hooks` | Runtime shims and ongoing site hooks |
| `ddev-wp-cli` | DB import, search-replace, WP-CLI |
| `composer-local-merge` | Register eslov-customisation in Composer |
| `review-code-standards` | Review adaptation plugin code |
| `create-modularity-module` | Only if a missing module type is discovered |
| `export-acf` | Only when defining new field structures |

## Local development

Primary work site:

| Site | URL | ddev project |
|------|-----|--------------|
| Target (work here) | `https://eslov-se-new.ddev.site` | `eslov-se-new` |
| LTS (forensics, optional) | `https://eslov-se.ddev.site` | `eslov-se` |

```bash
cd eslov-se-new && ddev start
ddev import-db --file=eslov-2026-06-23-77d6623-lean.sql
```
