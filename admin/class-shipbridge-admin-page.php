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
		add_action( 'admin_post_shipbridge_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_shipbridge_import_csv', array( $this, 'import_csv' ) );
		add_action( 'admin_post_shipbridge_import_formidable', array( $this, 'import_formidable' ) );
		add_action( 'admin_post_shipbridge_export_csv', array( $this, 'export_csv' ) );
		add_action( 'admin_post_shipbridge_sync', array( $this, 'run_sync' ) );
		add_action( 'admin_post_shipbridge_cleanup', array( $this, 'cleanup' ) );
		add_action( 'admin_post_shipbridge_download_backup', array( $this, 'download_backup' ) );
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ShipBridge — Shipping Rate Sync', 'shipbridge' ); ?></h1>
			<p><?php esc_html_e( 'Import ZIP-based rates, inspect every proposed change, and synchronize only after a verified backup.', 'shipbridge' ); ?></p>

			<?php $this->notice(); ?>

			<style>
				.shipbridge-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;max-width:1200px}.shipbridge-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px}.shipbridge-card h2{margin-top:0}.shipbridge-stat{font-size:28px;font-weight:600}.shipbridge-danger{border-left:4px solid #d63638}.shipbridge-good{border-left:4px solid #00a32a}.shipbridge-list{max-height:220px;overflow:auto;background:#f6f7f7;padding:10px}.shipbridge-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
			</style>

			<div class="shipbridge-grid">
				<section class="shipbridge-card">
					<h2><?php esc_html_e( '1. Data source', 'shipbridge' ); ?></h2>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="shipbridge_save_settings">
						<?php wp_nonce_field( 'shipbridge_save_settings' ); ?>
						<table class="form-table"><tbody>
						<tr><th><label for="source"><?php esc_html_e( 'Default source', 'shipbridge' ); ?></label></th><td><select id="source" name="source"><option value="csv" <?php selected( $settings['source'], 'csv' ); ?>>CSV</option><option value="formidable" <?php selected( $settings['source'], 'formidable' ); ?>>Formidable Forms</option></select></td></tr>
						<tr><th><?php esc_html_e( 'Formidable IDs', 'shipbridge' ); ?></th><td>
							<label>Form <input class="small-text" type="number" min="1" name="form_id" value="<?php echo esc_attr( $settings['form_id'] ); ?>"></label>
							<label>ZIP <input class="small-text" type="number" min="1" name="zip_field_id" value="<?php echo esc_attr( $settings['zip_field_id'] ); ?>"></label>
							<label>Price <input class="small-text" type="number" min="1" name="price_field_id" value="<?php echo esc_attr( $settings['price_field_id'] ); ?>"></label>
							<label>City <input class="small-text" type="number" min="0" name="city_field_id" value="<?php echo esc_attr( $settings['city_field_id'] ); ?>"></label>
						</td></tr>
						<tr><th><label for="protected_patterns"><?php esc_html_e( 'Protected zone names', 'shipbridge' ); ?></label></th><td><textarea id="protected_patterns" name="protected_patterns" rows="4" class="large-text"><?php echo esc_textarea( $settings['protected_patterns'] ); ?></textarea><p class="description"><?php esc_html_e( 'One case-insensitive name fragment per line. These zones are never adopted or changed.', 'shipbridge' ); ?></p></td></tr>
						</tbody></table>
						<?php submit_button( __( 'Save settings', 'shipbridge' ), 'secondary', 'submit', false ); ?>
					</form>
				</section>

				<section class="shipbridge-card">
					<h2><?php esc_html_e( '2. Import rates', 'shipbridge' ); ?></h2>
					<p><?php printf( esc_html__( 'Current list: %1$d ZIP codes. Source: %2$s.', 'shipbridge' ), count( $rates ), esc_html( isset( $meta['source'] ) ? $meta['source'] : 'none' ) ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
						<input type="hidden" name="action" value="shipbridge_import_csv">
						<?php wp_nonce_field( 'shipbridge_import_csv' ); ?>
						<input type="file" name="rates_csv" accept=".csv,text/csv" required>
						<?php submit_button( __( 'Import CSV', 'shipbridge' ), 'primary', 'submit', false ); ?>
					</form>
					<p class="description"><?php esc_html_e( 'Required columns: ZIP Code and Price. City is optional. Duplicate ZIPs keep the highest price.', 'shipbridge' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="shipbridge-actions">
						<input type="hidden" name="action" value="shipbridge_import_formidable">
						<?php wp_nonce_field( 'shipbridge_import_formidable' ); ?>
						<?php submit_button( __( 'Read Formidable now', 'shipbridge' ), 'secondary', 'submit', false ); ?>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=shipbridge_export_csv' ), 'shipbridge_export_csv' ) ); ?>"><?php esc_html_e( 'Download current CSV', 'shipbridge' ); ?></a>
					</form>
				</section>

				<section class="shipbridge-card <?php echo ! empty( $preview['can_sync'] ) ? 'shipbridge-good' : 'shipbridge-danger'; ?>">
					<h2><?php esc_html_e( '3. Preview', 'shipbridge' ); ?></h2>
					<?php if ( $preview ) : ?>
						<p><span class="shipbridge-stat"><?php echo esc_html( $preview['rates'] ); ?></span> <?php esc_html_e( 'ZIP codes', 'shipbridge' ); ?><br><span class="shipbridge-stat"><?php echo esc_html( $preview['groups'] ); ?></span> <?php esc_html_e( 'price groups', 'shipbridge' ); ?></p>
						<p><?php printf( esc_html__( '%1$d managed zones; %2$d eligible existing zones must be explicitly adopted before sync.', 'shipbridge' ), $preview['managed'], count( $preview['adoptable'] ) ); ?></p>
						<?php if ( $preview['conflicts'] ) : ?><p><strong><?php esc_html_e( 'Blocked:', 'shipbridge' ); ?></strong> <?php esc_html_e( 'Some imported ZIP codes are in unmanaged zones that are not safe to adopt.', 'shipbridge' ); ?></p><div class="shipbridge-list"><?php foreach ( $preview['conflicts'] as $conflict ) : ?><div><?php echo esc_html( $conflict['name'] . ': ' . implode( ', ', array_slice( $conflict['zips'], 0, 20 ) ) ); ?></div><?php endforeach; ?></div><?php endif; ?>
						<?php if ( $preview['protected'] ) : ?><p><strong><?php esc_html_e( 'Protected:', 'shipbridge' ); ?></strong> <?php echo esc_html( implode( ', ', $preview['protected'] ) ); ?></p><?php endif; ?>
					<?php else : ?><p><?php esc_html_e( 'WooCommerce is not active.', 'shipbridge' ); ?></p><?php endif; ?>
				</section>

				<section class="shipbridge-card shipbridge-danger">
					<h2><?php esc_html_e( '4. Synchronize WooCommerce', 'shipbridge' ); ?></h2>
					<p><?php esc_html_e( 'This changes live shipping zones. ShipBridge creates an internal JSON backup first, but you should also have a full site/database backup.', 'shipbridge' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm('Apply these shipping changes to WooCommerce?');">
						<input type="hidden" name="action" value="shipbridge_sync">
						<?php wp_nonce_field( 'shipbridge_sync' ); ?>
						<p><label><input type="checkbox" name="backup_ack" value="1" required> <?php esc_html_e( 'I have a current full backup.', 'shipbridge' ); ?></label></p>
						<p><label><input type="checkbox" name="adopt" value="1"> <?php esc_html_e( 'Adopt the eligible existing postcode-only zones shown in the preview.', 'shipbridge' ); ?></label></p>
						<p><label><?php esc_html_e( 'Type SYNC to confirm:', 'shipbridge' ); ?> <input type="text" name="confirmation" required></label></p>
						<?php submit_button( __( 'Sync WooCommerce', 'shipbridge' ), 'primary', 'submit', false, empty( $preview['can_sync'] ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
					</form>
				</section>

				<section class="shipbridge-card">
					<h2><?php esc_html_e( 'Archived zones cleanup', 'shipbridge' ); ?></h2>
					<p><?php printf( esc_html__( '%d verified ShipBridge archive zones are eligible for deletion.', 'shipbridge' ), count( $candidates ) ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm('Permanently delete only the verified archived zones?');">
						<input type="hidden" name="action" value="shipbridge_cleanup">
						<?php wp_nonce_field( 'shipbridge_cleanup' ); ?>
						<label><?php esc_html_e( 'Type DELETE ARCHIVED:', 'shipbridge' ); ?> <input type="text" name="confirmation" required></label>
						<?php submit_button( __( 'Delete verified archives', 'shipbridge' ), 'delete', 'submit', false, $candidates ? array() : array( 'disabled' => 'disabled' ) ); ?>
					</form>
				</section>

				<section class="shipbridge-card">
					<h2><?php esc_html_e( 'Internal backups', 'shipbridge' ); ?></h2>
					<?php if ( ! $backups ) : ?><p><?php esc_html_e( 'No internal backups yet.', 'shipbridge' ); ?></p><?php else : ?><ul><?php foreach ( $backups as $backup ) : ?><li><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=shipbridge_download_backup&backup_id=' . rawurlencode( $backup['id'] ) ), 'shipbridge_download_backup' ) ); ?>"><?php echo esc_html( $backup['created_at'] . ' — ' . $backup['reason'] ); ?></a></li><?php endforeach; ?></ul><?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}

	public function save_settings() {
		$this->guard( 'shipbridge_save_settings' );
		$this->repository->save_settings( array(
			'source'             => isset( $_POST['source'] ) && 'formidable' === $_POST['source'] ? 'formidable' : 'csv',
			'form_id'            => absint( $_POST['form_id'] ),
			'zip_field_id'       => absint( $_POST['zip_field_id'] ),
			'price_field_id'     => absint( $_POST['price_field_id'] ),
			'city_field_id'      => absint( $_POST['city_field_id'] ),
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
			$this->store_import( $result, 'csv' );
		} catch ( RuntimeException $exception ) {
			$this->redirect( $exception->getMessage(), 'error' );
		}
		$this->redirect( 'rates_imported' );
	}

	public function import_formidable() {
		$this->guard( 'shipbridge_import_formidable' );
		try {
			$result = ( new ShipBridge_Formidable_Adapter( new ShipBridge_Rate_Normalizer() ) )->import( $this->repository->get_settings() );
			$this->store_import( $result, 'formidable' );
		} catch ( RuntimeException $exception ) {
			$this->redirect( $exception->getMessage(), 'error' );
		}
		$this->redirect( 'rates_imported' );
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
			$result = $this->sync->synchronize( ! empty( $_POST['adopt'] ) );
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

	private function store_import( array $result, $source ) {
		if ( empty( $result['rates'] ) ) {
			throw new RuntimeException( 'No valid rates were found.' );
		}
		$this->repository->save_rates( $result['rates'], array(
			'source'      => $source,
			'imported_at' => current_time( 'mysql', true ),
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

