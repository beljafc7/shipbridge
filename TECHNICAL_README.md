# ShipBridge Technical Documentation

## Overview

ShipBridge is a modular WordPress plugin that converts ZIP-based rate data into managed WooCommerce Shipping Zones. It supports CSV as a standalone source and Formidable Forms as an optional source.

The plugin is derived from the verified behavior of Anchor Rock Shipping Sync v0.5.0. The historical production dataset contained 276 unique ZIP codes and 67 price groups, but ShipBridge does not hard-code those counts.

## Requirements

- WordPress 6.4 or newer
- WooCommerce 8.0 or newer
- PHP 7.4 or newer
- Formidable Forms only when using the Formidable source

## Architecture

```text
shipbridge/
├── shipbridge.php
├── admin/
│   └── class-shipbridge-admin-page.php
├── includes/
│   ├── class-shipbridge-backup-manager.php
│   ├── class-shipbridge-csv-importer.php
│   ├── class-shipbridge-formidable-adapter.php
│   ├── class-shipbridge-plugin.php
│   ├── class-shipbridge-rate-normalizer.php
│   ├── class-shipbridge-rate-repository.php
│   ├── class-shipbridge-sync-service.php
│   └── class-shipbridge-zone-manager.php
├── examples/
│   └── shipping-prices.csv
├── tests/
│   └── run.php
└── scripts/
    └── build-zip.sh
```

## Main components

### `ShipBridge_Plugin`

Bootstraps the plugin, registers compatibility declarations, validates the WooCommerce dependency, and initializes the administration layer.

### `ShipBridge_Rate_Normalizer`

Normalizes five-digit and ZIP+4 values, validates prices, resolves duplicate ZIP codes using the highest price, and groups ZIP codes by canonical price.

### `ShipBridge_CSV_Importer`

Reads comma-, tab-, or semicolon-delimited files. It accepts common ZIP, city, and price column names.

### `ShipBridge_Formidable_Adapter`

Reads configured Formidable entries and sends them through the same normalization pipeline used by CSV imports.

### `ShipBridge_Rate_Repository`

Stores the current rate list, settings, managed zone registry, internal backups, and last synchronization result in non-autoloaded WordPress options.

### `ShipBridge_Zone_Manager`

Reads WooCommerce zone state and performs guarded zone creation, updates, adoption, archiving, and deletion.

### `ShipBridge_Sync_Service`

Builds previews, detects conflicts, coordinates synchronization, creates backups, updates the managed zone registry, and validates archive cleanup candidates.

### `ShipBridge_Backup_Manager`

Stores up to ten JSON-compatible zone snapshots in WordPress options. Backups are downloaded only through an authenticated WooCommerce management action.

## Data sources

### CSV

Required columns:

- `ZIP Code`
- `Price`

Optional columns:

- `City`

Supported aliases include `ZIP`, `Postcode`, `Postal Code`, `Shipping Price`, `Shipping Cost`, `Cost`, `Rate`, and `Town`.

### Formidable Forms

The adapter requires a form ID, ZIP field ID, and price field ID. City is optional.

Historical defaults:

- Form ID: `3`
- ZIP field ID: `10`
- Price field ID: `12`

These values are configurable and are not business constants.

## Normalization rules

- Five-digit ZIP codes are stored unchanged.
- ZIP+4 values are reduced to their first five digits.
- Invalid ZIP codes are rejected.
- Currency symbols and formatting characters are removed from prices.
- Negative or nonnumeric prices are rejected.
- Prices are stored with two decimal places.
- When a ZIP appears multiple times, the highest valid price wins.
- ZIP codes are grouped by price without using county boundaries.

## Synchronization workflow

### Preview

The preview reports:

- imported ZIP count
- price-group count
- currently managed zones
- eligible existing zones
- protected zones
- unmanaged conflicts

Synchronization is blocked when an imported ZIP overlaps an unmanaged zone that cannot be safely adopted.

### Zone adoption

ShipBridge never silently claims an existing zone. An adoption candidate must:

- contain only postcode locations
- contain at least one imported ZIP
- contain no ZIP outside the imported dataset
- have an enabled Flat Rate with a numeric cost
- not match a protected name pattern

The administrator must explicitly enable adoption during synchronization.

### Legacy zone migration

Legacy migration candidates may contain country, state, or continent rules in addition to exact postcodes. A candidate must:

- contain at least one imported exact five-digit ZIP
- contain no ZIP outside the imported dataset
- use only supported WooCommerce location types
- have an enabled Flat Rate with a numeric cost
- not match a protected name pattern

Administrators can explicitly scope a planned migration with case-insensitive legacy zone name patterns. A matching zone may contain malformed historical postcode rules because every location is replaced during migration, but it must still use only supported WooCommerce location types and have a numeric Flat Rate.

The administrator must explicitly authorize legacy migration. ShipBridge creates a complete zone snapshot first, replaces all broader location rules with exact ZIP codes, and preserves existing shipping methods.

### Zone updates

For every price group, ShipBridge reuses a managed or adopted zone when possible. It updates only postcode locations and the Flat Rate cost. Other shipping methods, including Local Pickup, remain in place.

If no eligible zone is available, ShipBridge creates a new zone and Flat Rate method. Administrators can optionally add Local Pickup to every newly created zone and use `Zone {price}` names.

### Archiving

Managed zones that are no longer needed are renamed with the `ARCHIVED - ` prefix and temporarily assigned the sentinel ZIP `00000`.

Archive cleanup is separate from synchronization. A zone can be deleted only when it:

- exists in the ShipBridge managed-zone registry
- is marked as archived
- begins with `ARCHIVED - `
- contains only the sentinel ZIP `00000`

A backup is created before verified archives are deleted.

## Protected behavior

Default protected name patterns:

- `Samples`
- `TL Price`

Administrators can add additional patterns. Matching is case-insensitive.

Protected zones are not adopted or modified. Non-postcode zones and unrelated WooCommerce methods are also left untouched.

## Security

- Administration requires the `manage_woocommerce` capability.
- Every state-changing action requires a WordPress nonce.
- Uploaded files are checked as CSV files.
- Request values are sanitized before use.
- Synchronization requires backup acknowledgement and the confirmation text `SYNC`.
- Archive deletion requires the confirmation text `DELETE ARCHIVED`.
- Backup downloads require capability and nonce validation.
- Internal backups are not written to a public uploads directory.

## WordPress options

ShipBridge currently uses these options:

- `shipbridge_rates`
- `shipbridge_rate_meta`
- `shipbridge_settings`
- `shipbridge_managed_zones`
- `shipbridge_backups`
- `shipbridge_last_sync`

All plugin-created options are saved without autoloading.

## Development

Install the local WordPress development tooling:

```bash
npm install
```

Start WordPress Playground with WooCommerce and the current plugin directory mounted directly:

```bash
npm run dev
```

The admin dashboard is available at `http://localhost:8888/wp-admin/` with username `admin` and password `password`. Refresh the browser after source changes; rebuilding or reinstalling the ZIP is not required.

Use `npm run stop` to stop the environment. Use `npm run dev:docker` when MySQL-backed parity is required and a Docker runtime is available.

Run the framework-independent tests:

```bash
php tests/run.php
```

Lint every PHP file:

```bash
find . -name '*.php' -not -path './build/*' -print0 | xargs -0 -n1 php -l
```

Build an installable WordPress ZIP:

```bash
sh scripts/build-zip.sh
```

The package is written to:

```text
build/shipbridge-1.2.0.zip
```

## Continuous integration

The CI workflow runs on pushes and pull requests. It validates:

- PHP syntax
- framework-independent tests
- compatibility with PHP 7.4, 8.1, and 8.3
- ZIP packaging on PHP 8.3

## Release process

The release workflow runs for tags matching `v*`.

For version 1.2.0:

```bash
git tag v1.2.0
git push origin v1.2.0
```

GitHub Actions builds `shipbridge-1.2.0.zip` and attaches it to the generated GitHub Release. The public README download link uses that release asset.

## Manual QA

Before the first production synchronization:

1. Clone the production website into staging.
2. Create a full files-and-database backup.
3. Review every protected zone pattern.
4. Import the complete rate source.
5. Resolve every unmanaged ZIP conflict.
6. Review zones proposed for adoption.
7. Run synchronization once.
8. Refresh the preview and verify the expected ZIP and price counts.
9. Test a normal checkout with known ZIP codes.
10. Verify Local Pickup, Samples, shipping classes, and special shipping rules.
11. Review archived zones before running cleanup.

Automated tests and PHP linting do not replace WooCommerce staging validation.
