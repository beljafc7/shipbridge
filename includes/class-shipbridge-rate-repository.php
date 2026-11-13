<?php

defined( 'ABSPATH' ) || exit;

final class ShipBridge_Rate_Repository {
	const OPTION_RATES         = 'shipbridge_rates';
	const OPTION_RATE_META     = 'shipbridge_rate_meta';
	const OPTION_SETTINGS      = 'shipbridge_settings';
	const OPTION_MANAGED_ZONES = 'shipbridge_managed_zones';
	const OPTION_BACKUPS       = 'shipbridge_backups';
	const OPTION_LAST_SYNC     = 'shipbridge_last_sync';

	public function get_rates() {
		$rates = get_option( self::OPTION_RATES, array() );
		return is_array( $rates ) ? $rates : array();
	}

	public function save_rates( array $rates, array $meta ) {
		update_option( self::OPTION_RATES, $rates, false );
		update_option( self::OPTION_RATE_META, $meta, false );
	}

	public function clear_rates() {
		delete_option( self::OPTION_RATES );
		delete_option( self::OPTION_RATE_META );
	}

	public function get_rate_meta() {
		$meta = get_option( self::OPTION_RATE_META, array() );
		return is_array( $meta ) ? $meta : array();
	}

	public function get_settings() {
		$defaults = array(
			'source'             => 'csv',
			'form_id'            => 3,
			'zip_field_id'       => 10,
			'price_field_id'     => 12,
			'city_field_id'      => 0,
			'zone_name_format'   => 'shipbridge',
			'zone_name_template' => 'Zone {price}',
			'add_local_pickup'   => 0,
			'legacy_patterns'    => '',
			'protected_patterns' => "Samples\nTL Price",
		);
		$value = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $value ) ? $value : array(), $defaults );
	}

	public function save_settings( array $settings ) {
		update_option( self::OPTION_SETTINGS, $settings, false );
	}

	/** @return array<int,array<string,mixed>> */
	public function get_managed_zones() {
		$value = get_option( self::OPTION_MANAGED_ZONES, array() );
		return is_array( $value ) ? $value : array();
	}

	public function save_managed_zones( array $zones ) {
		update_option( self::OPTION_MANAGED_ZONES, $zones, false );
	}

	public function get_backups() {
		$value = get_option( self::OPTION_BACKUPS, array() );
		return is_array( $value ) ? $value : array();
	}

	public function save_backups( array $backups ) {
		update_option( self::OPTION_BACKUPS, $backups, false );
	}

	public function save_last_sync( array $result ) {
		update_option( self::OPTION_LAST_SYNC, $result, false );
	}

	public function get_last_sync() {
		$value = get_option( self::OPTION_LAST_SYNC, array() );
		return is_array( $value ) ? $value : array();
	}
}

