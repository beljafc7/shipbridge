<?php
/**
 * Plugin Name: ShipBridge — Shipping Rate Sync for WooCommerce
 * Description: Imports ZIP-based shipping rates from CSV or Formidable Forms and safely synchronizes WooCommerce shipping zones.
 * Version: 1.0.0
 * Author: ShipBridge Contributors
 * License: GPL-2.0-or-later
 * Text Domain: shipbridge
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.9
 */

defined( 'ABSPATH' ) || exit;

define( 'SHIPBRIDGE_VERSION', '1.0.0' );
define( 'SHIPBRIDGE_FILE', __FILE__ );
define( 'SHIPBRIDGE_PATH', plugin_dir_path( __FILE__ ) );

require_once SHIPBRIDGE_PATH . 'includes/class-shipbridge-rate-normalizer.php';
require_once SHIPBRIDGE_PATH . 'includes/class-shipbridge-csv-importer.php';
require_once SHIPBRIDGE_PATH . 'includes/class-shipbridge-rate-repository.php';
require_once SHIPBRIDGE_PATH . 'includes/class-shipbridge-formidable-adapter.php';
require_once SHIPBRIDGE_PATH . 'includes/class-shipbridge-backup-manager.php';
require_once SHIPBRIDGE_PATH . 'includes/class-shipbridge-zone-manager.php';
require_once SHIPBRIDGE_PATH . 'includes/class-shipbridge-sync-service.php';
require_once SHIPBRIDGE_PATH . 'admin/class-shipbridge-admin-page.php';
require_once SHIPBRIDGE_PATH . 'includes/class-shipbridge-plugin.php';

register_activation_hook( __FILE__, array( 'ShipBridge_Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'ShipBridge_Plugin', 'boot' ) );

