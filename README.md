# ShipBridge

ShipBridge is a free, GPL-licensed WooCommerce plugin for importing ZIP-based shipping prices from CSV or Formidable Forms and synchronizing them with WooCommerce Shipping Zones.

Version 1.0.0 turns the earlier single-site beta into a modular project with a guarded migration flow, tests, documentation, and installable GitHub releases.

## Safety model

ShipBridge will only modify:

1. zones it previously created or manages; and
2. eligible postcode-only zones that an administrator explicitly chooses to adopt.

Protected name patterns, non-postcode regions, and unrelated methods remain untouched. Every sync stores an internal JSON snapshot and requires the administrator to confirm a separate full backup.

## CSV format

```csv
ZIP Code,City,Price
33001,Long Key,740.00
33002,Hialeah,165.00
```

`City` is optional. When the same ZIP appears more than once, the highest price wins.

## Development

```bash
php tests/run.php
find . -name '*.php' -not -path './build/*' -print0 | xargs -0 -n1 php -l
sh scripts/build-zip.sh
```

The generated WordPress package is written to `build/shipbridge-1.0.0.zip`.

## Before production use

- Use a staging clone.
- Make a full files-and-database backup.
- Add every special zone name to the protected patterns.
- Import rates and review conflicts.
- Verify a normal checkout, Local Pickup, Samples, and any shipping-class rules after sync.

## Free distribution

The project is intended for a public GitHub repository under GPL-2.0-or-later. Pushing a `v1.0.0` tag runs the included release workflow and attaches an installable ZIP to a free GitHub Release.

