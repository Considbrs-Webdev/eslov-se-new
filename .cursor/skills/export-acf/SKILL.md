---
name: export-acf
description: >-
  Add, export, or modify ACF field groups using AcfExportManager. Use when migration
  requires new field definitions — not for porting LTS ACF; prefer meta key transforms
  in eslov-customisation CLI commands.
---

# Manage Advanced Custom Fields (ACF)

**Migration context:** After DB import, ACF field groups live in the database. Usually fix via **meta key migration** in `eslov-customisation`, not new field exports.

Use this skill when:

- Adding new ACF groups to a **new** module in eslov-customisation
- Defining fields after data structure is settled

## Rules

- Fields stored in `source/php/AcfFields/`.
- Use `AcfExportManager` for export/import.

## Pattern

```php
add_action('acf/init', function() {
    $acfExportManager = new \AcfExportManager\AcfExportManager();
    $acfExportManager->setTextdomain('eslov-customisation');
    $acfExportManager->setExportFolder(ESLOV_CUSTOMISATION_PATH . 'source/php/AcfFields/');
    $acfExportManager->autoExport(['field-group-name' => 'group_id']);
    $acfExportManager->import();
});
```

## LTS forensics

Grep LTS for field keys to migrate, don't copy exports blindly:

`eslov-se/web/app/plugins/mod-open-hours/source/acf-export/php/mod-open-hours.php`
