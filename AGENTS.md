# ShipBridge development guidance

ShipBridge is a reusable WordPress plugin that imports ZIP-based prices and synchronizes WooCommerce Shipping Zones. It is derived from the production-tested Anchor Rock Shipping Sync v0.5.0, but the legacy production plugin must not be modified by this repository.

## Verified business rules

- Every WordPress installation owns an independent current rate list.
- CSV accepts `ZIP Code, City, Price` or `ZIP Code, Price`.
- Formidable Forms is optional. The historical defaults were form 3, ZIP field 10, and Price field 12.
- A duplicate ZIP uses the highest price.
- ZIPs are grouped by price, regardless of county.
- The historical production dataset contained 276 unique ZIPs and 67 price groups. Those counts are observations, not hard-coded requirements.
- A synchronization must be previewed and explicitly confirmed after a full backup.
- Preserve unrelated zones, shipping classes, Local Pickup, Samples, TL Price, and non-ShipBridge methods.
- Never silently claim or change an unmanaged zone. Adoption must be explicit and restricted to postcode-only zones with an enabled numeric Flat Rate that overlap the imported dataset.
- Archived zones use the sentinel ZIP `00000` only temporarily and may be deleted only through the verified cleanup flow.

## Engineering rules

- WordPress capability checks and nonces are required for every action.
- Keep normalization and grouping logic framework-independent and covered by `tests/run.php`.
- Never store backups in a publicly browsable uploads path; current backups are kept in non-autoloaded options and downloadable only by a WooCommerce manager.
- Refactor incrementally and keep PHP 7.4 compatibility until the declared minimum changes.
- Run `php tests/run.php`, lint all PHP files, and build the installable ZIP before a release.
- A successful unit test or PHP lint is not proof that WooCommerce behavior is safe. First synchronization must occur on staging with checkout verification.

