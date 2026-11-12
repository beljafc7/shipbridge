# ShipBridge

## Import. Preview. Sync. Safely.

ShipBridge is a free WooCommerce plugin for importing ZIP-based shipping rates from CSV or Formidable Forms and safely synchronizing them with WooCommerce Shipping Zones.

[**Download ShipBridge 1.1.0**](https://github.com/beljafc7/shipbridge/releases/latest/download/shipbridge-1.1.0.zip)

## Manage shipping rates without editing zones one by one

- Import rates from a CSV file.
- Use Formidable Forms as an optional rate source.
- Keep an independent rate list on every WordPress website.
- Group ZIP codes automatically by shipping price.
- Keep the highest price when duplicate ZIP codes are found.
- Preview conflicts and proposed changes before synchronization.
- Protect special zones, Local Pickup, shipping classes, and unrelated methods.
- Migrate explicitly approved legacy zones that combine ZIP codes with broader location rules.
- Use `Zone 95` price-based names and add Local Pickup to newly created zones when configured.
- Create an internal JSON backup before changing WooCommerce.
- Export the current rate list whenever needed.

## How it works

### 1. Import

Upload a CSV containing `ZIP Code` and `Price`. A `City` column is optional. ShipBridge can also read configured fields from Formidable Forms.

### 2. Review

ShipBridge groups ZIP codes by price and shows managed zones, protected zones, eligible existing zones, and conflicts that require attention.

### 3. Synchronize

After confirming a current full backup, apply the reviewed rates to WooCommerce. ShipBridge only changes zones it manages or eligible zones that an administrator explicitly adopts.

## Download

### WordPress plugin

[**Download ShipBridge 1.1.0**](https://github.com/beljafc7/shipbridge/releases/latest/download/shipbridge-1.1.0.zip)

Requirements:

- WordPress 6.4 or newer
- WooCommerce 8.0 or newer
- PHP 7.4 or newer

ShipBridge is free software licensed under GPL-2.0-or-later.

## Installation

1. Download the latest ShipBridge ZIP.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Select the downloaded ZIP and choose **Install Now**.
4. Activate ShipBridge.
5. Open **WooCommerce → ShipBridge**.
6. Configure protected zone names before importing rates.
7. Import a CSV or connect Formidable Forms.
8. Review the complete preview before synchronization.

## CSV format

```csv
ZIP Code,City,Price
33001,Long Key,740.00
33002,Hialeah,165.00
```

`City` is optional. If a ZIP appears more than once, ShipBridge keeps the highest price.

## Safety notice

Test the first synchronization on a staging website and create a complete files-and-database backup. After synchronization, verify a normal checkout, Local Pickup, Samples, shipping classes, and every special shipping rule used by the store.

## Technical documentation

Architecture, development, testing, packaging, and release information is available in the [Technical README](TECHNICAL_README.md).
