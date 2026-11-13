<?php

defined( 'ABSPATH' ) || defined( 'SHIPBRIDGE_TESTING' ) || exit;

final class ShipBridge_Rate_Normalizer {
	/**
	 * Normalize rows and keep the highest price for duplicate ZIP codes.
	 *
	 * @param array<int,array<string,mixed>> $rows Input rows.
	 * @return array{rates:array<string,array{zip:string,city:string,price:string}>,duplicates:int,rejected:array<int,array{row:mixed,reason:string}>,total_rows:int}
	 */
	public function normalize( array $rows ) {
		$rates      = array();
		$duplicates = 0;
		$rejected   = array();

		foreach ( $rows as $index => $row ) {
			$zip   = $this->normalize_zip( isset( $row['zip'] ) ? $row['zip'] : '' );
			$price = $this->normalize_price( isset( $row['price'] ) ? $row['price'] : '' );

			if ( null === $zip ) {
				$rejected[] = array( 'row' => $index + 1, 'reason' => 'invalid_zip' );
				continue;
			}

			if ( null === $price ) {
				$rejected[] = array( 'row' => $index + 1, 'reason' => 'invalid_price' );
				continue;
			}

			$city = isset( $row['city'] ) ? trim( strip_tags( (string) $row['city'] ) ) : '';
			if ( isset( $rates[ $zip ] ) ) {
				++$duplicates;
				if ( (float) $price <= (float) $rates[ $zip ]['price'] ) {
					continue;
				}
			}

			$rates[ $zip ] = array(
				'zip'   => $zip,
				'city'  => $city,
				'price' => $price,
			);
		}

		ksort( $rates, SORT_STRING );

		$total_rows = count( $rows );
		return compact( 'rates', 'duplicates', 'rejected', 'total_rows' );
	}

	/** @return string|null */
	public function normalize_zip( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^([0-9]{5})(?:-[0-9]{4})?$/', $value, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/** @return string|null */
	public function normalize_price( $value ) {
		$value = trim( (string) $value );
		$value = preg_replace( '/[^0-9.\-]/', '', $value );
		if ( '' === $value || ! is_numeric( $value ) || (float) $value < 0 ) {
			return null;
		}

		return number_format( (float) $value, 2, '.', '' );
	}

	/**
	 * @param array<string,array{zip:string,city:string,price:string}> $rates Rates keyed by ZIP.
	 * @return array<string,array<int,string>> Groups keyed by canonical price.
	 */
	public function group_by_price( array $rates ) {
		$groups = array();
		foreach ( $rates as $rate ) {
			$price = number_format( (float) $rate['price'], 2, '.', '' );
			if ( ! isset( $groups[ $price ] ) ) {
				$groups[ $price ] = array();
			}
			$groups[ $price ][] = $rate['zip'];
		}

		uksort( $groups, static function ( $a, $b ) { return (float) $a <=> (float) $b; } );
		return $groups;
	}
}

