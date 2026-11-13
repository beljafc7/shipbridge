<?php

defined( 'ABSPATH' ) || exit;

final class ShipBridge_Admin_Page {
	/** @var ShipBridge_Rate_Repository */ private $repository;
	/** @var ShipBridge_Zone_Manager */ private $zones;
	/** @var ShipBridge_Sync_Service */ private $sync;
	/** @var ShipBridge_Backup_Manager */ private $backups;

	public function __construct( ShipBridge_Rate_Repository $repository, ShipBridge_Zone_Manager $zones, ShipBridge_Sync_Service $sync, ShipBridge_Backup_Manager $backups ) {
		$this->repository = $repository;
		$this->zones      = $zones;
		$this->sync       = $sync;
		$this->backups    = $backups;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_shipbridge_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_shipbridge_import_csv', array( $this, 'import_csv' ) );
		add_action( 'admin_post_shipbridge_import_formidable', array( $this, 'import_formidable' ) );
		add_action( 'admin_post_shipbridge_clear_import', array( $this, 'clear_import' ) );
		add_action( 'admin_post_shipbridge_export_csv', array( $this, 'export_csv' ) );
		add_action( 'admin_post_shipbridge_sync', array( $this, 'run_sync' ) );
		add_action( 'admin_post_shipbridge_cleanup', array( $this, 'cleanup' ) );
		add_action( 'admin_post_shipbridge_download_backup', array( $this, 'download_backup' ) );
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( 'woocommerce_page_shipbridge' !== $hook_suffix ) {
			return;
		}

		$css_path = dirname( __FILE__ ) . '/css/shipbridge-admin.css';
		$js_path  = dirname( __FILE__ ) . '/js/shipbridge-admin.js';
		wp_enqueue_style( 'shipbridge-admin', plugins_url( 'css/shipbridge-admin.css', __FILE__ ), array(), file_exists( $css_path ) ? filemtime( $css_path ) : SHIPBRIDGE_VERSION );
		wp_enqueue_script( 'shipbridge-admin', plugins_url( 'js/shipbridge-admin.js', __FILE__ ), array(), file_exists( $js_path ) ? filemtime( $js_path ) : SHIPBRIDGE_VERSION, true );
	}

	public function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'ShipBridge', 'shipbridge' ),
			__( 'ShipBridge', 'shipbridge' ),
			'manage_woocommerce',
			'shipbridge',
			array( $this, 'render' )
		);
	}

	public function render() {
		$this->authorize();
		$rates      = $this->repository->get_rates();
		$meta       = $this->repository->get_rate_meta();
		$settings   = $this->repository->get_settings();
		$preview    = class_exists( 'WooCommerce' ) ? $this->sync->preview() : array();
		$candidates = class_exists( 'WooCommerce' ) ? $this->sync->cleanup_candidates() : array();
		$backups    = $this->repository->get_backups();
		$last_sync  = $this->repository->get_last_sync();
		$groups     = ( new ShipBridge_Rate_Normalizer() )->group_by_price( $rates );
		$page       = isset( $_GET['sb_page'] ) ? max( 1, absint( $_GET['sb_page'] ) ) : 1;
		$per_page   = 25;
		$comparison = ! empty( $preview['comparison'] ) ? $preview['comparison'] : array();
		$total_pages = max( 1, (int) ceil( count( $comparison ) / $per_page ) );
		$page        = min( $page, $total_pages );
		$rows        = array_slice( $comparison, ( $page - 1 ) * $per_page, $per_page );
		$source_name = isset( $meta['source_name'] ) && $meta['source_name'] ? $meta['source_name'] : ( isset( $meta['source'] ) ? strtoupper( $meta['source'] ) : __( 'No import yet', 'shipbridge' ) );
		?>
		<div class="wrap shipbridge-admin">
			<header class="shipbridge-header">
				<div>
					<div class="shipbridge-title-row"><h1><?php esc_html_e( 'ShipBridge — Shipping Rate Sync', 'shipbridge' ); ?></h1><span class="shipbridge-version"><?php echo esc_html( 'Version ' . SHIPBRIDGE_VERSION ); ?></span></div>
					<p><?php esc_html_e( 'Import ZIP-based rates, inspect every proposed change, and synchronize only after a verified backup.', 'shipbridge' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>"><?php esc_html_e( 'View WooCommerce zones', 'shipbridge' ); ?></a>
			</header>

			<?php $this->notice(); ?>

			<?php if ( $rates && empty( $preview['conflicts'] ) ) : ?>
				<div class="shipbridge-banner shipbridge-banner--success"><span class="dashicons dashicons-yes-alt"></span><div><strong><?php esc_html_e( 'Preview ready', 'shipbridge' ); ?></strong><p><?php esc_html_e( 'Imported rates passed validation. Review every proposed change before synchronizing.', 'shipbridge' ); ?></p></div></div>
			<?php elseif ( ! empty( $preview['conflicts'] ) ) : ?>
				<div class="shipbridge-banner shipbridge-banner--warning"><span class="dashicons dashicons-warning"></span><div><strong><?php esc_html_e( 'Synchronization blocked', 'shipbridge' ); ?></strong><p><?php esc_html_e( 'Resolve the unmanaged shipping-zone conflicts shown below before synchronizing.', 'shipbridge' ); ?></p></div></div>
			<?php endif; ?>

			<div class="shipbridge-top-grid">
				<section class="shipbridge-card">
					<?php $this->card_heading( '1', __( 'Data source', 'shipbridge' ), __( 'Choose where ShipBridge reads rates and how managed zones are created.', 'shipbridge' ) ); ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="shipbridge-form">
						<input type="hidden" name="action" value="shipbridge_save_settings"><?php wp_nonce_field( 'shipbridge_save_settings' ); ?>
						<label class="shipbridge-field"><span><?php esc_html_e( 'Default source', 'shipbridge' ); ?></span><select id="source" name="source"><option value="csv" <?php selected( $settings['source'], 'csv' ); ?>>CSV</option><option value="formidable" <?php selected( $settings['source'], 'formidable' ); ?>>Formidable Forms</option></select></label>
						<div class="shipbridge-field" data-source-panel="formidable" <?php echo 'formidable' !== $settings['source'] ? 'hidden' : ''; ?>><span class="shipbridge-label-with-help"><?php esc_html_e( 'Formidable field IDs', 'shipbridge' ); ?><?php $this->help_tip( __( 'Enter the numeric Formidable form and field IDs that contain each value. Use 0 for City when the form has no city field.', 'shipbridge' ) ); ?></span><div class="shipbridge-id-grid"><label>Form<input type="number" min="1" name="form_id" value="<?php echo esc_attr( $settings['form_id'] ); ?>"></label><label>ZIP<input type="number" min="1" name="zip_field_id" value="<?php echo esc_attr( $settings['zip_field_id'] ); ?>"></label><label>Price<input type="number" min="1" name="price_field_id" value="<?php echo esc_attr( $settings['price_field_id'] ); ?>"></label><label>City<input type="number" min="0" name="city_field_id" value="<?php echo esc_attr( $settings['city_field_id'] ); ?>"></label></div></div>
						<label class="shipbridge-field"><span><?php esc_html_e( 'Zone names', 'shipbridge' ); ?></span><select id="zone_name_format" name="zone_name_format"><option value="shipbridge" <?php selected( $settings['zone_name_format'], 'shipbridge' ); ?>>ShipBridge — $95.00</option><option value="price" <?php selected( $settings['zone_name_format'], 'price' ); ?>>Zone 95</option><option value="custom" <?php selected( $settings['zone_name_format'], 'custom' ); ?>><?php esc_html_e( 'Custom template', 'shipbridge' ); ?></option></select></label>
						<label class="shipbridge-field" data-zone-template <?php echo 'custom' !== $settings['zone_name_format'] ? 'hidden' : ''; ?>><span class="shipbridge-label-with-help"><?php esc_html_e( 'Zone name template', 'shipbridge' ); ?><?php $this->help_tip( __( 'Use {price} for 95 or {price_formatted} for $95.00.', 'shipbridge' ) ); ?></span><input type="text" name="zone_name_template" value="<?php echo esc_attr( $settings['zone_name_template'] ); ?>" placeholder="Delivery {price}"></label>
						<details class="shipbridge-advanced"><summary><?php esc_html_e( 'Advanced settings', 'shipbridge' ); ?></summary>
							<label class="shipbridge-check"><input type="checkbox" name="add_local_pickup" value="1" <?php checked( ! empty( $settings['add_local_pickup'] ) ); ?>><span><?php esc_html_e( 'Add Local Pickup to newly created zones.', 'shipbridge' ); ?></span></label>
							<label class="shipbridge-field"><span class="shipbridge-label-with-help"><?php esc_html_e( 'Legacy migration zone names', 'shipbridge' ); ?><?php $this->help_tip( __( 'Enter one case-insensitive zone-name fragment per line. Matching unmanaged zones may be explicitly migrated during synchronization.', 'shipbridge' ) ); ?></span><textarea name="legacy_patterns" rows="3"><?php echo esc_textarea( $settings['legacy_patterns'] ); ?></textarea></label>
							<label class="shipbridge-field"><span class="shipbridge-label-with-help"><?php esc_html_e( 'Protected zone names', 'shipbridge' ); ?><?php $this->help_tip( __( 'Enter one case-insensitive zone-name fragment per line. Matching zones are never adopted, migrated, updated, or deleted.', 'shipbridge' ) ); ?></span><textarea name="protected_patterns" rows="3"><?php echo esc_textarea( $settings['protected_patterns'] ); ?></textarea></label>
						</details>
						<button class="button button-secondary" type="submit"><?php esc_html_e( 'Save settings', 'shipbridge' ); ?></button>
					</form>
				</section>

				<section class="shipbridge-card">
					<?php $this->card_heading( '2', __( 'Import rates', 'shipbridge' ), __( 'Upload a CSV or read the configured Formidable Forms fields.', 'shipbridge' ) ); ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data" class="shipbridge-import-form" data-source-panel="csv" <?php echo 'csv' !== $settings['source'] ? 'hidden' : ''; ?>>
						<input type="hidden" name="action" value="shipbridge_import_csv"><?php wp_nonce_field( 'shipbridge_import_csv' ); ?>
						<label class="shipbridge-dropzone"><span class="dashicons dashicons-upload"></span><strong><?php esc_html_e( 'Choose a CSV file', 'shipbridge' ); ?></strong><small><?php esc_html_e( 'Required: ZIP Code and Price. City is optional.', 'shipbridge' ); ?></small><span class="shipbridge-file-name"><?php esc_html_e( 'No file selected', 'shipbridge' ); ?></span><input type="file" name="rates_csv" accept=".csv,text/csv" required></label>
						<button class="button button-primary" type="submit"><?php esc_html_e( 'Import CSV', 'shipbridge' ); ?></button>
					</form>
					<div class="shipbridge-import-summary">
						<div class="shipbridge-summary-title"><div><strong><?php echo esc_html( $source_name ); ?></strong><small><?php echo ! empty( $meta['imported_at'] ) ? esc_html( $this->format_date( $meta['imported_at'] ) ) : esc_html__( 'Import a source to begin.', 'shipbridge' ); ?></small></div><?php if ( $rates || $meta ) : ?><div class="shipbridge-summary-actions"><?php if ( $rates ) : ?><span class="shipbridge-pill shipbridge-pill--success"><?php esc_html_e( 'Ready', 'shipbridge' ); ?></span><?php endif; ?><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="shipbridge-clear-import-form" data-confirm-message="<?php esc_attr_e( 'Remove the current imported rates? WooCommerce zones and backups will not be changed.', 'shipbridge' ); ?>"><input type="hidden" name="action" value="shipbridge_clear_import"><?php wp_nonce_field( 'shipbridge_clear_import' ); ?><button type="submit" class="shipbridge-icon-button" aria-label="<?php esc_attr_e( 'Remove current import', 'shipbridge' ); ?>" title="<?php esc_attr_e( 'Remove current import', 'shipbridge' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></form></div><?php endif; ?></div>
						<div class="shipbridge-summary-grid"><span><strong><?php echo esc_html( isset( $meta['total_rows'] ) ? $meta['total_rows'] : count( $rates ) ); ?></strong><?php esc_html_e( 'Total rows', 'shipbridge' ); ?></span><span><strong><?php echo esc_html( count( $rates ) ); ?></strong><?php esc_html_e( 'Unique ZIPs', 'shipbridge' ); ?></span><span><strong><?php echo esc_html( isset( $meta['duplicates'] ) ? $meta['duplicates'] : 0 ); ?></strong><?php esc_html_e( 'Duplicates', 'shipbridge' ); ?></span><span><strong><?php echo esc_html( isset( $meta['rejected'] ) ? $meta['rejected'] : 0 ); ?></strong><?php esc_html_e( 'Rejected', 'shipbridge' ); ?></span><span><strong><?php echo esc_html( count( $groups ) ); ?></strong><?php esc_html_e( 'Price groups', 'shipbridge' ); ?></span></div>
					</div>
					<div class="shipbridge-actions"><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-source-panel="formidable" <?php echo 'formidable' !== $settings['source'] ? 'hidden' : ''; ?>><input type="hidden" name="action" value="shipbridge_import_formidable"><?php wp_nonce_field( 'shipbridge_import_formidable' ); ?><button class="button button-primary" type="submit"><?php esc_html_e( 'Read Formidable now', 'shipbridge' ); ?></button></form><?php if ( $rates ) : ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=shipbridge_export_csv' ), 'shipbridge_export_csv' ) ); ?>"><?php esc_html_e( 'Download current CSV', 'shipbridge' ); ?></a><?php endif; ?></div>
				</section>

				<section class="shipbridge-card">
					<?php $this->card_heading( '3', __( 'Preview', 'shipbridge' ), __( 'Understand the impact before ShipBridge changes WooCommerce.', 'shipbridge' ) ); ?>
					<div class="shipbridge-metrics"><div><strong><?php echo esc_html( isset( $preview['rates'] ) ? $preview['rates'] : 0 ); ?></strong><span><?php esc_html_e( 'ZIP codes', 'shipbridge' ); ?></span></div><div><strong><?php echo esc_html( isset( $preview['groups'] ) ? $preview['groups'] : 0 ); ?></strong><span><?php esc_html_e( 'Price groups', 'shipbridge' ); ?></span></div><div><strong><?php echo esc_html( isset( $preview['create'] ) ? $preview['create'] : 0 ); ?></strong><span><?php esc_html_e( 'New zones', 'shipbridge' ); ?></span></div><div><strong><?php echo esc_html( ! empty( $preview['conflicts'] ) ? count( $preview['conflicts'] ) : 0 ); ?></strong><span><?php esc_html_e( 'Conflicts', 'shipbridge' ); ?></span></div></div>
					<div class="shipbridge-preview-status <?php echo ! empty( $preview['can_sync'] ) ? 'is-ready' : 'is-blocked'; ?>"><span class="dashicons <?php echo ! empty( $preview['can_sync'] ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span><div><strong><?php echo ! empty( $preview['can_sync'] ) ? esc_html__( 'Ready for review', 'shipbridge' ) : esc_html__( 'Action required', 'shipbridge' ); ?></strong><p><?php printf( esc_html__( '%1$d managed zones, %2$d adoptable zones, and %3$d legacy migration candidates.', 'shipbridge' ), isset( $preview['managed'] ) ? $preview['managed'] : 0, ! empty( $preview['adoptable'] ) ? count( $preview['adoptable'] ) : 0, ! empty( $preview['legacy'] ) ? count( $preview['legacy'] ) : 0 ); ?></p></div></div>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=shipbridge' ) ); ?>"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Refresh preview', 'shipbridge' ); ?></a>
				</section>
			</div>

			<details class="shipbridge-section shipbridge-collapsible" open>
				<summary class="shipbridge-section-heading"><div><h2><?php esc_html_e( 'Proposed zone changes', 'shipbridge' ); ?></h2><p><?php esc_html_e( 'The exact actions ShipBridge will perform for every price group.', 'shipbridge' ); ?></p></div><span class="shipbridge-collapsible-actions"><span class="shipbridge-count"><?php echo esc_html( ! empty( $preview['zone_changes'] ) ? count( $preview['zone_changes'] ) : 0 ); ?></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></span></summary>
				<div class="shipbridge-table-wrap"><table class="widefat shipbridge-table"><thead><tr><th><?php esc_html_e( 'Action', 'shipbridge' ); ?></th><th><?php esc_html_e( 'Zone', 'shipbridge' ); ?></th><th><?php esc_html_e( 'ZIP codes', 'shipbridge' ); ?></th><th><?php esc_html_e( 'Current rate', 'shipbridge' ); ?></th><th><?php esc_html_e( 'Proposed rate', 'shipbridge' ); ?></th><th><?php esc_html_e( 'Result', 'shipbridge' ); ?></th></tr></thead><tbody>
				<?php if ( empty( $preview['zone_changes'] ) ) : ?><tr><td colspan="6" class="shipbridge-empty"><?php esc_html_e( 'Import rates to generate a proposed synchronization plan.', 'shipbridge' ); ?></td></tr><?php else : foreach ( $preview['zone_changes'] as $change ) : ?><tr><td><span class="shipbridge-pill shipbridge-pill--<?php echo esc_attr( $change['action'] ); ?>"><?php echo esc_html( ucfirst( $change['action'] ) ); ?></span></td><td><strong><?php echo esc_html( $change['proposed_name'] ); ?></strong><?php if ( $change['current_name'] && $change['current_name'] !== $change['proposed_name'] ) : ?><small><?php echo esc_html( $change['current_name'] ); ?></small><?php endif; ?></td><td><?php echo esc_html( $change['zip_count'] ); ?></td><td><?php echo null === $change['current_price'] ? '—' : esc_html( $this->money( $change['current_price'] ) ); ?></td><td><strong><?php echo esc_html( $this->money( $change['price'] ) ); ?></strong></td><td><?php echo esc_html( $this->change_result( $change['action'] ) ); ?></td></tr><?php endforeach; endif; ?>
				</tbody></table></div>
			</details>

			<details class="shipbridge-guidance" <?php echo ! empty( $preview['conflicts'] ) ? 'open' : ''; ?>><summary><span class="dashicons dashicons-info-outline"></span><span><strong><?php esc_html_e( 'Conflict guidance', 'shipbridge' ); ?></strong><small><?php echo empty( $preview['conflicts'] ) ? esc_html__( 'No blocking conflicts detected.', 'shipbridge' ) : esc_html__( 'Review the zones preventing synchronization.', 'shipbridge' ); ?></small></span><span class="dashicons dashicons-arrow-down-alt2"></span></summary><div class="shipbridge-guidance-body">
			<?php if ( empty( $preview['conflicts'] ) ) : ?><p><?php esc_html_e( 'Every imported ZIP can be safely assigned using the current protection and migration rules.', 'shipbridge' ); ?></p><?php else : ?><p><?php esc_html_e( 'These unmanaged zones overlap imported ZIPs or contain broader location rules:', 'shipbridge' ); ?></p><ul><?php foreach ( $preview['conflicts'] as $conflict ) : ?><li><strong><?php echo esc_html( $conflict['name'] ); ?></strong> — <?php echo $conflict['zips'] ? esc_html( implode( ', ', array_slice( $conflict['zips'], 0, 20 ) ) ) : esc_html__( 'broader location rules', 'shipbridge' ); ?></li><?php endforeach; ?></ul><?php endif; ?>
			<?php if ( ! empty( $preview['protected'] ) ) : ?><p><strong><?php esc_html_e( 'Protected zones:', 'shipbridge' ); ?></strong> <?php echo esc_html( implode( ', ', $preview['protected'] ) ); ?></p><?php endif; ?></div></details>

			<details class="shipbridge-section shipbridge-collapsible" open>
				<summary class="shipbridge-section-heading"><div><h2><?php esc_html_e( 'ZIP-level comparison', 'shipbridge' ); ?></h2><p><?php esc_html_e( 'Imported rates compared with the first matching exact-ZIP WooCommerce zone.', 'shipbridge' ); ?></p></div><span class="shipbridge-collapsible-actions"><span class="shipbridge-status-counts"><?php foreach ( array( 'match', 'different', 'missing', 'review' ) as $status ) : ?><span class="shipbridge-pill shipbridge-pill--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) . ': ' . ( isset( $preview['status_counts'][ $status ] ) ? $preview['status_counts'][ $status ] : 0 ) ); ?></span><?php endforeach; ?></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></span></summary>
				<div class="shipbridge-table-wrap"><table class="widefat shipbridge-table"><thead><tr><th><?php esc_html_e( 'ZIP', 'shipbridge' ); ?></th><th><?php esc_html_e( 'Imported rate', 'shipbridge' ); ?></th><th><?php esc_html_e( 'WooCommerce flat rate', 'shipbridge' ); ?></th><th><?php esc_html_e( 'Matching zones', 'shipbridge' ); ?></th><th><?php esc_html_e( 'Status', 'shipbridge' ); ?></th></tr></thead><tbody>
				<?php if ( ! $rows ) : ?><tr><td colspan="5" class="shipbridge-empty"><?php esc_html_e( 'No ZIP codes available for comparison.', 'shipbridge' ); ?></td></tr><?php else : foreach ( $rows as $row ) : ?><tr><td><strong><?php echo esc_html( $row['zip'] ); ?></strong></td><td><?php echo esc_html( $this->money( $row['price'] ) ); ?></td><td><?php echo null === $row['current_price'] ? '—' : esc_html( $this->money( $row['current_price'] ) ); ?></td><td><?php echo $row['matching_zones'] ? esc_html( implode( ', ', $row['matching_zones'] ) ) : '—'; ?></td><td><span class="shipbridge-pill shipbridge-pill--<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span></td></tr><?php endforeach; endif; ?>
				</tbody></table></div><?php $this->pagination( $page, $total_pages, count( $comparison ) ); ?>
			</details>

			<div class="shipbridge-bottom-grid">
				<section class="shipbridge-card shipbridge-card--sync"><h2><?php esc_html_e( 'Synchronize WooCommerce', 'shipbridge' ); ?></h2><p><?php esc_html_e( 'This changes live shipping zones. ShipBridge creates an internal JSON backup first.', 'shipbridge' ); ?></p><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="shipbridge-confirm-form" data-ready-class="is-ready" data-confirm-message="<?php esc_attr_e( 'Apply these shipping changes to WooCommerce?', 'shipbridge' ); ?>"><input type="hidden" name="action" value="shipbridge_sync"><?php wp_nonce_field( 'shipbridge_sync' ); ?><label class="shipbridge-check"><input type="checkbox" name="backup_ack" value="1" required><span><?php esc_html_e( 'I have a current full backup.', 'shipbridge' ); ?></span></label><?php if ( ! empty( $preview['adoptable'] ) ) : ?><label class="shipbridge-check"><input type="checkbox" name="adopt" value="1"><span><?php esc_html_e( 'Adopt eligible existing postcode-only zones.', 'shipbridge' ); ?></span></label><?php endif; ?><?php if ( ! empty( $preview['legacy'] ) ) : ?><label class="shipbridge-check"><input type="checkbox" name="migrate_legacy" value="1" required><span><?php esc_html_e( 'Migrate the listed legacy zones and replace their broader rules with exact ZIP codes.', 'shipbridge' ); ?></span></label><?php endif; ?><label class="shipbridge-field"><span><?php esc_html_e( 'Type SYNC to confirm', 'shipbridge' ); ?></span><input type="text" name="confirmation" data-confirm-value="SYNC" required></label><button class="button button-primary shipbridge-button--sync" type="submit" <?php disabled( empty( $preview['can_sync'] ) ); ?>><?php echo ! empty( $preview['legacy'] ) ? esc_html__( 'Migrate and sync WooCommerce', 'shipbridge' ) : esc_html__( 'Sync WooCommerce', 'shipbridge' ); ?></button></form></section>
				<section class="shipbridge-card"><h2><?php esc_html_e( 'Archived zones cleanup', 'shipbridge' ); ?></h2><p><?php printf( esc_html__( '%d verified ShipBridge archive zones are eligible for deletion.', 'shipbridge' ), count( $candidates ) ); ?></p><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="shipbridge-confirm-form" data-confirm-message="<?php esc_attr_e( 'Permanently delete only the verified archived zones?', 'shipbridge' ); ?>"><input type="hidden" name="action" value="shipbridge_cleanup"><?php wp_nonce_field( 'shipbridge_cleanup' ); ?><label class="shipbridge-field"><span><?php esc_html_e( 'Type DELETE ARCHIVED', 'shipbridge' ); ?></span><input type="text" name="confirmation" data-confirm-value="DELETE ARCHIVED" required></label><button class="button shipbridge-button--delete" type="submit" <?php disabled( empty( $candidates ) ); ?>><?php esc_html_e( 'Delete verified archives', 'shipbridge' ); ?></button></form></section>
				<section class="shipbridge-card"><h2><?php esc_html_e( 'Internal backups', 'shipbridge' ); ?></h2><p><?php esc_html_e( 'Download a JSON snapshot created before ShipBridge changed WooCommerce.', 'shipbridge' ); ?></p><?php if ( ! $backups ) : ?><div class="shipbridge-empty-state"><span class="dashicons dashicons-database"></span><p><?php esc_html_e( 'No internal backups yet.', 'shipbridge' ); ?></p></div><?php else : ?><div class="shipbridge-backup-list"><?php foreach ( array_slice( $backups, 0, 4 ) as $backup ) : ?><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=shipbridge_download_backup&backup_id=' . rawurlencode( $backup['id'] ) ), 'shipbridge_download_backup' ) ); ?>"><span class="dashicons dashicons-download"></span><span><strong><?php echo esc_html( strtoupper( $backup['reason'] ) ); ?></strong><small><?php echo esc_html( $this->format_date( $backup['created_at'] ) . ' · ' . count( $backup['zones'] ) . ' zones' ); ?></small></span></a><?php endforeach; ?></div><?php endif; ?></section>
			</div>

			<?php if ( $last_sync ) : ?><section class="shipbridge-last-sync"><span class="dashicons dashicons-yes-alt"></span><div><h2><?php esc_html_e( 'Last synchronization completed', 'shipbridge' ); ?></h2><p><?php echo esc_html( $this->format_date( $last_sync['synced_at'] ) ); ?></p><div class="shipbridge-sync-stats"><span><strong><?php echo esc_html( isset( $last_sync['created'] ) ? $last_sync['created'] : 0 ); ?></strong><?php esc_html_e( 'Created', 'shipbridge' ); ?></span><span><strong><?php echo esc_html( isset( $last_sync['updated'] ) ? $last_sync['updated'] : 0 ); ?></strong><?php esc_html_e( 'Updated', 'shipbridge' ); ?></span><span><strong><?php echo esc_html( isset( $last_sync['adopted'] ) ? $last_sync['adopted'] : 0 ); ?></strong><?php esc_html_e( 'Adopted', 'shipbridge' ); ?></span><span><strong><?php echo esc_html( isset( $last_sync['archived'] ) ? $last_sync['archived'] : 0 ); ?></strong><?php esc_html_e( 'Archived', 'shipbridge' ); ?></span></div></div><div class="shipbridge-actions"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>"><?php esc_html_e( 'Review shipping zones', 'shipbridge' ); ?></a><?php if ( ! empty( $last_sync['backup_id'] ) ) : ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=shipbridge_download_backup&backup_id=' . rawurlencode( $last_sync['backup_id'] ) ), 'shipbridge_download_backup' ) ); ?>"><?php esc_html_e( 'Download backup', 'shipbridge' ); ?></a><?php endif; ?></div></section><?php endif; ?>
		</div>
		<?php
	}

	private function card_heading( $step, $title, $description ) {
		?><div class="shipbridge-card-heading"><span><?php echo esc_html( $step ); ?></span><div><h2><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $description ); ?></p></div></div><?php
	}

	private function help_tip( $text ) {
		?><span class="shipbridge-tooltip" tabindex="0" aria-label="<?php echo esc_attr( $text ); ?>"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span><span class="shipbridge-tooltip-content" role="tooltip"><?php echo esc_html( $text ); ?></span></span><?php
	}

	private function money( $value ) {
		return '$' . number_format( (float) $value, 2 );
	}

	private function format_date( $value ) {
		$timestamp = strtotime( $value . ' UTC' );
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' · ' . get_option( 'time_format' ), $timestamp ) : $value;
	}

	private function change_result( $action ) {
		$labels = array(
			'create'  => __( 'New managed zone', 'shipbridge' ),
			'update'  => __( 'Managed zone updated', 'shipbridge' ),
			'adopt'   => __( 'Existing zone adopted', 'shipbridge' ),
			'migrate' => __( 'Legacy zone migrated', 'shipbridge' ),
		);
		return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
	}

	private function pagination( $page, $total_pages, $total_items ) {
		if ( $total_pages <= 1 ) {
			return;
		}
		$base = admin_url( 'admin.php?page=shipbridge' );
		?><nav class="shipbridge-pagination" aria-label="<?php esc_attr_e( 'ZIP comparison pagination', 'shipbridge' ); ?>"><span><?php printf( esc_html__( '%d ZIP codes', 'shipbridge' ), $total_items ); ?></span><div><a class="button" <?php echo $page > 1 ? 'href="' . esc_url( add_query_arg( 'sb_page', $page - 1, $base ) ) . '"' : 'aria-disabled="true"'; ?>><?php esc_html_e( 'Previous', 'shipbridge' ); ?></a><span><?php printf( esc_html__( 'Page %1$d of %2$d', 'shipbridge' ), $page, $total_pages ); ?></span><a class="button" <?php echo $page < $total_pages ? 'href="' . esc_url( add_query_arg( 'sb_page', $page + 1, $base ) ) . '"' : 'aria-disabled="true"'; ?>><?php esc_html_e( 'Next', 'shipbridge' ); ?></a></div></nav><?php
	}

	public function save_settings() {
		$this->guard( 'shipbridge_save_settings' );
		$allowed_formats = array( 'shipbridge', 'price', 'custom' );
		$name_format     = isset( $_POST['zone_name_format'] ) ? sanitize_key( wp_unslash( $_POST['zone_name_format'] ) ) : 'shipbridge';
		$name_format     = in_array( $name_format, $allowed_formats, true ) ? $name_format : 'shipbridge';
		$name_template   = isset( $_POST['zone_name_template'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_name_template'] ) ) : 'Zone {price}';
		if ( false === strpos( $name_template, '{price}' ) && false === strpos( $name_template, '{price_formatted}' ) ) {
			$name_template = 'Zone {price}';
		}
		$this->repository->save_settings( array(
			'source'             => isset( $_POST['source'] ) && 'formidable' === $_POST['source'] ? 'formidable' : 'csv',
			'form_id'            => absint( $_POST['form_id'] ),
			'zip_field_id'       => absint( $_POST['zip_field_id'] ),
			'price_field_id'     => absint( $_POST['price_field_id'] ),
			'city_field_id'      => absint( $_POST['city_field_id'] ),
			'zone_name_format'   => $name_format,
			'zone_name_template' => $name_template,
			'add_local_pickup'   => empty( $_POST['add_local_pickup'] ) ? 0 : 1,
			'legacy_patterns'    => sanitize_textarea_field( wp_unslash( $_POST['legacy_patterns'] ) ),
			'protected_patterns' => sanitize_textarea_field( wp_unslash( $_POST['protected_patterns'] ) ),
		) );
		$this->redirect( 'settings_saved' );
	}

	public function import_csv() {
		$this->guard( 'shipbridge_import_csv' );
		if ( empty( $_FILES['rates_csv']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['rates_csv']['error'] ) {
			$this->redirect( 'import_failed', 'error' );
		}
		$check = wp_check_filetype_and_ext( $_FILES['rates_csv']['tmp_name'], sanitize_file_name( $_FILES['rates_csv']['name'] ), array( 'csv' => 'text/csv' ) );
		if ( 'csv' !== $check['ext'] ) {
			$this->redirect( 'invalid_csv', 'error' );
		}
		try {
			$result = ( new ShipBridge_CSV_Importer( new ShipBridge_Rate_Normalizer() ) )->import_file( $_FILES['rates_csv']['tmp_name'] );
			$this->store_import( $result, 'csv', sanitize_file_name( wp_unslash( $_FILES['rates_csv']['name'] ) ) );
		} catch ( RuntimeException $exception ) {
			$this->redirect( $exception->getMessage(), 'error' );
		}
		$this->redirect( 'rates_imported' );
	}

	public function import_formidable() {
		$this->guard( 'shipbridge_import_formidable' );
		try {
			$result = ( new ShipBridge_Formidable_Adapter( new ShipBridge_Rate_Normalizer() ) )->import( $this->repository->get_settings() );
			$this->store_import( $result, 'formidable', 'Formidable Forms' );
		} catch ( RuntimeException $exception ) {
			$this->redirect( $exception->getMessage(), 'error' );
		}
		$this->redirect( 'rates_imported' );
	}

	public function clear_import() {
		$this->guard( 'shipbridge_clear_import' );
		$this->repository->clear_rates();
		$this->redirect( __( 'Imported rates cleared.', 'shipbridge' ) );
	}

	public function export_csv() {
		$this->guard( 'shipbridge_export_csv', '_wpnonce', false );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=shipbridge-rates-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'wb' );
		fputcsv( $out, array( 'ZIP Code', 'City', 'Price' ) );
		foreach ( $this->repository->get_rates() as $rate ) {
			fputcsv( $out, array( $rate['zip'], $rate['city'], $rate['price'] ) );
		}
		fclose( $out );
		exit;
	}

	public function run_sync() {
		$this->guard( 'shipbridge_sync' );
		if ( empty( $_POST['backup_ack'] ) || 'SYNC' !== strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['confirmation'] ) ) ) ) ) {
			$this->redirect( 'confirmation_required', 'error' );
		}
		try {
			$result = $this->sync->synchronize( ! empty( $_POST['adopt'] ), ! empty( $_POST['migrate_legacy'] ) );
			$this->redirect( 'sync_complete_' . implode( '_', array_map( 'absint', array( $result['created'], $result['updated'], $result['archived'] ) ) ) );
		} catch ( RuntimeException $exception ) {
			$this->redirect( $exception->getMessage(), 'error' );
		}
	}

	public function cleanup() {
		$this->guard( 'shipbridge_cleanup' );
		if ( 'DELETE ARCHIVED' !== strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['confirmation'] ) ) ) ) ) {
			$this->redirect( 'confirmation_required', 'error' );
		}
		$this->redirect( 'archives_deleted_' . $this->sync->cleanup_archives() );
	}

	public function download_backup() {
		$this->guard( 'shipbridge_download_backup', '_wpnonce', false );
		$id     = isset( $_GET['backup_id'] ) ? sanitize_text_field( wp_unslash( $_GET['backup_id'] ) ) : '';
		$backup = $this->backups->find( $id );
		if ( ! $backup ) {
			wp_die( esc_html__( 'Backup not found.', 'shipbridge' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=shipbridge-backup-' . sanitize_file_name( $id ) . '.json' );
		echo wp_json_encode( $backup, JSON_PRETTY_PRINT );
		exit;
	}

	private function store_import( array $result, $source, $source_name = '' ) {
		if ( empty( $result['rates'] ) ) {
			throw new RuntimeException( 'No valid rates were found.' );
		}
		$this->repository->save_rates( $result['rates'], array(
			'source'      => $source,
			'source_name' => $source_name,
			'imported_at' => current_time( 'mysql', true ),
			'total_rows'  => isset( $result['total_rows'] ) ? absint( $result['total_rows'] ) : count( $result['rates'] ) + absint( $result['duplicates'] ) + count( $result['rejected'] ),
			'duplicates'  => $result['duplicates'],
			'rejected'    => count( $result['rejected'] ),
		) );
	}

	private function guard( $action, $nonce_field = '_wpnonce', $post = true ) {
		$this->authorize();
		$source = $post ? $_POST : $_GET;
		$nonce  = isset( $source[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $source[ $nonce_field ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'shipbridge' ) );
		}
	}

	private function authorize() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage shipping rates.', 'shipbridge' ) );
		}
	}

	private function redirect( $message, $type = 'success' ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'shipbridge', 'shipbridge_notice' => rawurlencode( $message ), 'shipbridge_type' => $type ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function notice() {
		if ( empty( $_GET['shipbridge_notice'] ) ) {
			return;
		}
		$type = isset( $_GET['shipbridge_type'] ) && 'error' === $_GET['shipbridge_type'] ? 'error' : 'success';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['shipbridge_notice'] ) ) ) . '</p></div>';
	}
}

