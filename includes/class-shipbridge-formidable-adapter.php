<?php

defined( 'ABSPATH' ) || exit;

final class ShipBridge_Formidable_Adapter {
	/** @var ShipBridge_Rate_Normalizer */
	private $normalizer;

	public function __construct( ShipBridge_Rate_Normalizer $normalizer ) {
		$this->normalizer = $normalizer;
	}

	public function is_available() {
		return class_exists( 'FrmEntry' ) && class_exists( 'FrmEntryMeta' );
	}

	/**
	 * @return array{rates:array,duplicates:int,rejected:array,total_rows:int}
	 * @throws RuntimeException If Formidable is unavailable or settings are invalid.
	 */
	public function import( array $settings ) {
		if ( ! $this->is_available() ) {
			throw new RuntimeException( 'Formidable Forms is not active.' );
		}

		$form_id        = absint( $settings['form_id'] );
		$zip_field_id   = absint( $settings['zip_field_id'] );
		$price_field_id = absint( $settings['price_field_id'] );
		$city_field_id  = absint( $settings['city_field_id'] );
		if ( ! $form_id || ! $zip_field_id || ! $price_field_id ) {
			throw new RuntimeException( 'Form ID, ZIP field ID, and price field ID are required.' );
		}

		$entries = FrmEntry::getAll( array( 'it.form_id' => $form_id ), ' ORDER BY it.id ASC', '', true );
		$rows    = array();
		foreach ( (array) $entries as $entry ) {
			$rows[] = array(
				'zip'   => FrmEntryMeta::get_entry_meta_by_field( $entry->id, $zip_field_id, true ),
				'price' => FrmEntryMeta::get_entry_meta_by_field( $entry->id, $price_field_id, true ),
				'city'  => $city_field_id ? FrmEntryMeta::get_entry_meta_by_field( $entry->id, $city_field_id, true ) : '',
			);
		}

		return $this->normalizer->normalize( $rows );
	}
}

