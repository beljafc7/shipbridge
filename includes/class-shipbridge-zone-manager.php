<?php

defined( 'ABSPATH' ) || exit;

final class ShipBridge_Zone_Manager {
	const ARCHIVE_ZIP = '00000';

	/** @var ShipBridge_Rate_Repository */
	private $repository;

	public function __construct( ShipBridge_Rate_Repository $repository ) {
		$this->repository = $repository;
	}

	/** @return array<int,array<string,mixed>> */
	public function snapshot_all() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}

		$result = array();
		foreach ( WC_Shipping_Zones::get_zones() as $row ) {
			$zone = WC_Shipping_Zones::get_zone( (int) $row['zone_id'] );
			if ( $zone ) {
				$result[ $zone->get_id() ] = $this->snapshot_zone( $zone );
			}
		}
		return $result;
	}

	/** @return array<string,mixed> */
	public function snapshot_zone( WC_Shipping_Zone $zone ) {
		$locations = array();
		foreach ( $zone->get_zone_locations() as $location ) {
			$locations[] = array( 'code' => $location->code, 'type' => $location->type );
		}

		$methods = array();
		foreach ( $zone->get_shipping_methods( false ) as $method ) {
			$methods[] = array(
				'instance_id' => (int) $method->get_instance_id(),
				'id'          => $method->id,
				'enabled'     => $method->enabled,
				'title'       => $method->title,
				'cost'        => isset( $method->instance_settings['cost'] ) ? (string) $method->instance_settings['cost'] : null,
			);
		}

		return array(
			'id'        => $zone->get_id(),
			'name'      => $zone->get_zone_name(),
			'order'     => $zone->get_zone_order(),
			'locations' => $locations,
			'methods'   => $methods,
		);
	}

	public function is_protected( array $zone, array $settings ) {
		$patterns = preg_split( '/\r\n|\r|\n/', (string) $settings['protected_patterns'] );
		foreach ( $patterns as $pattern ) {
			$pattern = trim( $pattern );
			if ( '' !== $pattern && false !== stripos( $zone['name'], $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	public function is_postcode_only( array $zone ) {
		if ( empty( $zone['locations'] ) ) {
			return false;
		}
		foreach ( $zone['locations'] as $location ) {
			if ( 'postcode' !== $location['type'] ) {
				return false;
			}
		}
		return true;
	}

	/** @return string|null */
	public function flat_rate_cost( array $zone ) {
		foreach ( $zone['methods'] as $method ) {
			if ( 'flat_rate' === $method['id'] && 'yes' === $method['enabled'] && null !== $method['cost'] && is_numeric( $method['cost'] ) ) {
				return number_format( (float) $method['cost'], 2, '.', '' );
			}
		}
		return null;
	}

	/** @return array<int,string> */
	public function postcodes( array $zone ) {
		$codes = array();
		foreach ( $zone['locations'] as $location ) {
			if ( 'postcode' === $location['type'] && preg_match( '/^[0-9]{5}$/', $location['code'] ) ) {
				$codes[] = $location['code'];
			}
		}
		return array_values( array_unique( $codes ) );
	}

	/**
	 * Determine safe adoption candidates. A zone must be postcode-only, have an enabled numeric flat rate,
	 * overlap imported ZIPs, and not match a protected name.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function adoption_candidates( array $all_zones, array $source_zips, array $settings ) {
		$managed = $this->repository->get_managed_zones();
		$result  = array();
		foreach ( $all_zones as $id => $zone ) {
			if ( isset( $managed[ $id ] ) || $this->is_protected( $zone, $settings ) || ! $this->is_postcode_only( $zone ) || null === $this->flat_rate_cost( $zone ) ) {
				continue;
			}
			$zone_zips = $this->postcodes( $zone );
			if ( array_intersect( $zone_zips, $source_zips ) && ! array_diff( $zone_zips, $source_zips ) ) {
				$result[ $id ] = $zone;
			}
		}
		return $result;
	}

	public function update_zone( $zone_id, array $postcodes, $price, $name ) {
		$zone = WC_Shipping_Zones::get_zone( (int) $zone_id );
		if ( ! $zone ) {
			throw new RuntimeException( 'A managed shipping zone no longer exists.' );
		}

		$zone->set_zone_name( $name );
		$zone->clear_locations( array( 'postcode' ) );
		foreach ( $postcodes as $postcode ) {
			$zone->add_location( $postcode, 'postcode' );
		}
		$zone->save();
		$this->set_flat_rate( $zone, $price );
		return $zone->get_id();
	}

	public function create_zone( array $postcodes, $price, $name ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( $name );
		foreach ( $postcodes as $postcode ) {
			$zone->add_location( $postcode, 'postcode' );
		}
		$zone->save();
		$instance_id = $zone->add_shipping_method( 'flat_rate' );
		if ( ! $instance_id ) {
			throw new RuntimeException( 'WooCommerce could not add a flat rate method.' );
		}
		$this->set_flat_rate( $zone, $price );
		return $zone->get_id();
	}

	public function archive_zone( $zone_id ) {
		$zone = WC_Shipping_Zones::get_zone( (int) $zone_id );
		if ( ! $zone ) {
			return;
		}
		$name = $zone->get_zone_name();
		if ( 0 !== strpos( $name, 'ARCHIVED - ' ) ) {
			$zone->set_zone_name( 'ARCHIVED - ' . $name );
		}
		$zone->clear_locations( array( 'postcode' ) );
		$zone->add_location( self::ARCHIVE_ZIP, 'postcode' );
		$zone->save();
	}

	public function delete_zone( $zone_id ) {
		$zone = WC_Shipping_Zones::get_zone( (int) $zone_id );
		if ( $zone ) {
			$zone->delete();
		}
	}

	private function set_flat_rate( WC_Shipping_Zone $zone, $price ) {
		$flat_rate = null;
		foreach ( $zone->get_shipping_methods( false ) as $method ) {
			if ( 'flat_rate' === $method->id ) {
				$flat_rate = $method;
				break;
			}
		}

		if ( ! $flat_rate ) {
			$zone->add_shipping_method( 'flat_rate' );
			foreach ( $zone->get_shipping_methods( false ) as $method ) {
				if ( 'flat_rate' === $method->id ) {
					$flat_rate = $method;
					break;
				}
			}
		}

		if ( ! $flat_rate ) {
			throw new RuntimeException( 'The flat rate method could not be found.' );
		}

		$settings         = $flat_rate->instance_settings;
		$settings['cost'] = number_format( (float) $price, 2, '.', '' );
		$settings['title'] = isset( $settings['title'] ) && '' !== $settings['title'] ? $settings['title'] : __( 'Flat rate', 'woocommerce' );
		update_option( $flat_rate->get_instance_option_key(), $settings );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}

