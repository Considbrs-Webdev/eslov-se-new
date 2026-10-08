---
name: eslov-migration-workspace
description: >-
  Historical pointer only. The LTS cutover is done and production is standard
  Municipio. Do not import old dumps, resume a migration loop, or extend
  wp eslov migrate.
disable-model-invocation: true
---

# Eslöv cutover (historical)

Production (`eslov.se`) runs this standard Municipio repo. The main site is not LTS.

`wp eslov migrate` is a frozen record of that cutover. Keep the commands. Do not add new ones, and do not re-run `migrate all` for bugs found after go-live.

New breakage is fixed in `eslov-customisation`. See `eslov-adaptation-plugin`.

There is no reference database dump to import, and no second repo to implement in.
