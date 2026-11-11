<?php

defined( 'ABSPATH' ) || exit;

final class ShipBridge_Plugin {
	/** @var self|null */
	private static $instance;

	/** @var ShipBridge_Admin_Page|null */
	private $admin;

	public static function boot() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		if ( false === get_option( ShipBridge_Rate_Repository::OPTION_SETTINGS, false ) ) {
			add_option(
				ShipBridge_Rate_Repository::OPTION_SETTINGS,
				array(
					'source'             => 'csv',
					'form_id'            => 3,
					'zip_field_id'       => 10,
					'price_field_id'     => 12,
					'city_field_id'      => 0,
					'protected_patterns' => "Samples\nTL Price",
				)
			);
		}
	}

	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );

		if ( is_admin() ) {
			$repository = new ShipBridge_Rate_Repository();
			$zones      = new ShipBridge_Zone_Manager( $repository );
			$backups    = new ShipBridge_Backup_Manager( $repository );
			$sync       = new ShipBridge_Sync_Service( $repository, $zones, $backups );
			$this->admin = new ShipBridge_Admin_Page( $repository, $zones, $sync, $backups );
			$this->admin->register();
		}
	}

	public function declare_hpos_compatibility() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SHIPBRIDGE_FILE, true );
		}
	}

	public function dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) || class_exists( 'WooCommerce' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'ShipBridge requires WooCommerce to synchronize shipping zones.', 'shipbridge' ) . '</p></div>';
	}
}

