<?php

defined( 'ABSPATH' ) || defined( 'SHIPBRIDGE_TESTING' ) || exit;

final class ShipBridge_CSV_Importer {
	/** @var ShipBridge_Rate_Normalizer */
	private $normalizer;

	public function __construct( ShipBridge_Rate_Normalizer $normalizer ) {
		$this->normalizer = $normalizer;
	}

	/**
	 * @return array{rates:array,duplicates:int,rejected:array}
	 * @throws RuntimeException When the file cannot be parsed.
	 */
	public function import_file( $path ) {
		if ( ! is_readable( $path ) ) {
			throw new RuntimeException( 'The CSV file is not readable.' );
		}

		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw new RuntimeException( 'The CSV file could not be opened.' );
		}

		$first_line = fgets( $handle );
		if ( false === $first_line ) {
			fclose( $handle );
			throw new RuntimeException( 'The CSV file is empty.' );
		}

		$delimiter = $this->detect_delimiter( $first_line );
		rewind( $handle );
		$headers = fgetcsv( $handle, 0, $delimiter );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			throw new RuntimeException( 'The CSV header could not be read.' );
		}

		$map = $this->map_headers( $headers );
		if ( ! isset( $map['zip'], $map['price'] ) ) {
			fclose( $handle );
			throw new RuntimeException( 'The CSV must contain ZIP Code and Price columns.' );
		}

		$rows = array();
		while ( false !== ( $values = fgetcsv( $handle, 0, $delimiter ) ) ) {
			if ( 1 === count( $values ) && '' === trim( (string) $values[0] ) ) {
				continue;
			}
			$rows[] = array(
				'zip'   => isset( $values[ $map['zip'] ] ) ? $values[ $map['zip'] ] : '',
				'city'  => isset( $map['city'], $values[ $map['city'] ] ) ? $values[ $map['city'] ] : '',
				'price' => isset( $values[ $map['price'] ] ) ? $values[ $map['price'] ] : '',
			);
		}
		fclose( $handle );

		return $this->normalizer->normalize( $rows );
	}

	private function detect_delimiter( $line ) {
		$counts = array(
			','  => substr_count( $line, ',' ),
			"\t" => substr_count( $line, "\t" ),
			';'  => substr_count( $line, ';' ),
		);
		arsort( $counts );
		return (string) key( $counts );
	}

	/** @return array<string,int> */
	private function map_headers( array $headers ) {
		$map = array();
		foreach ( $headers as $index => $header ) {
			$key = strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header ) ) );
			$key = preg_replace( '/[^a-z0-9]+/', '', $key );
			if ( in_array( $key, array( 'zip', 'zipcode', 'postalcode', 'postcode' ), true ) ) {
				$map['zip'] = $index;
			} elseif ( in_array( $key, array( 'price', 'shippingprice', 'shippingcost', 'cost', 'rate' ), true ) ) {
				$map['price'] = $index;
			} elseif ( in_array( $key, array( 'city', 'town' ), true ) ) {
				$map['city'] = $index;
			}
		}
		return $map;
	}
}

