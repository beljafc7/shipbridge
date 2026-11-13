=== ShipBridge — Shipping Rate Sync for WooCommerce ===
Contributors: shipbridge
Tags: woocommerce, shipping, zones, csv, formidable
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import ZIP-based shipping rates from CSV or Formidable Forms, preview the impact, and safely synchronize WooCommerce shipping zones.

== Description ==

ShipBridge keeps a per-site rate list and groups ZIP codes by price. Duplicate ZIPs use the highest price. Existing zones are never changed unless they are already managed by ShipBridge or the administrator explicitly adopts zones that pass strict safety checks.

Features:

* CSV imports with ZIP Code, optional City, and Price.
* Optional Formidable Forms source.
* Highest-price rule for duplicate ZIP codes.
* Preview, conflict detection, manual confirmation, and internal JSON backups.
* Explicit migration of legacy ZIP zones that include broader location rules.
* Configurable price-based zone names and optional Local Pickup for new zones.
* Protection patterns for Samples, TL Price, and other special zones.
* Controlled archive cleanup.
* Downloadable current-rate CSV.

== Installation ==

1. Upload the ShipBridge ZIP from Plugins > Add New Plugin > Upload Plugin.
2. Activate WooCommerce and ShipBridge.
3. Open WooCommerce > ShipBridge.
4. Configure protected zone names and import a CSV.
5. Review the preview on a staging site before synchronizing.

== Changelog ==

= 1.2.0 =
* Redesign the WordPress admin experience to match the ShipBridge Figma specification.
* Add proposed zone-change and ZIP-level comparison tables.
* Add import summaries, conflict guidance, pagination, backup details, and last-sync results.

= 1.1.0 =
* Add guarded migration for legacy country, state, and continent shipping zones.
* Add Zone {price} naming and optional Local Pickup for newly created zones.
* Preserve existing shipping methods while replacing legacy location rules with exact ZIP codes.

= 1.0.0 =
* Initial public beta promoted into a modular, GitHub-ready release.

