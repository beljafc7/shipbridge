<?php

defined( 'ABSPATH' ) || exit;

final class ShipBridge_Sync_Service {
	/** @var ShipBridge_Rate_Repository */ private $repository;
	/** @var ShipBridge_Zone_Manager */ private $zones;
	/** @var ShipBridge_Backup_Manager */ private $backups;
	/** @var ShipBridge_Rate_Normalizer */ private $normalizer;

	public function __construct( ShipBridge_Rate_Repository $repository, ShipBridge_Zone_Manager $zones, ShipBridge_Backup_Manager $backups ) {
		$this->repository = $repository;
		$this->zones      = $zones;
		$this->backups    = $backups;
		$this->normalizer = new ShipBridge_Rate_Normalizer();
	}

	/** @return array<string,mixed> */
	public function preview() {
		$rates     = $this->repository->get_rates();
		$groups    = $this->normalizer->group_by_price( $rates );
		$all       = $this->zones->snapshot_all();
		$settings  = $this->repository->get_settings();
		$managed   = $this->repository->get_managed_zones();
		$source    = array_keys( $rates );
		$adoptable = $this->zones->adoption_candidates( $all, $source, $settings );
		$legacy    = $this->zones->legacy_migration_candidates( $all, $source, $settings );
		$protected = array();
		$conflicts = array();

		foreach ( $all as $id => $zone ) {
			$is_protected = $this->zones->is_protected( $zone, $settings );
			if ( $is_protected ) {
				$protected[ $id ] = $zone['name'];
			}
			if ( ! isset( $managed[ $id ] ) && ! isset( $adoptable[ $id ] ) && ! isset( $legacy[ $id ] ) ) {
				$overlap = array_values( array_intersect( $source, $this->zones->postcodes( $zone ) ) );
				if ( $overlap || ( ! $is_protected && $this->zones->has_broad_location_rules( $zone ) ) ) {
					$conflicts[ $id ] = array( 'name' => $zone['name'], 'zips' => $overlap );
				}
			}
		}

		$active_managed = array_filter( $managed, static function ( $item ) { return empty( $item['archived'] ); } );
		return array(
			'rates'        => count( $rates ),
			'groups'       => count( $groups ),
			'managed'      => count( $active_managed ),
			'create'       => max( 0, count( $groups ) - count( $active_managed ) - count( $adoptable ) - count( $legacy ) ),
			'adoptable'    => $adoptable,
			'legacy'       => $legacy,
			'protected'    => $protected,
			'conflicts'    => $conflicts,
			'can_sync'     => ! empty( $rates ) && empty( $conflicts ),
		);
	}

	/** @return array<string,mixed> */
	public function synchronize( $adopt, $migrate_legacy = false ) {
		$preview = $this->preview();
		if ( ! $preview['can_sync'] ) {
			throw new RuntimeException( 'Synchronization is blocked until unmanaged ZIP conflicts are resolved.' );
		}
		if ( ! $adopt && ! empty( $preview['adoptable'] ) ) {
			throw new RuntimeException( 'Eligible existing zones contain imported ZIPs. Review the preview and explicitly adopt them before synchronizing.' );
		}
		if ( ! $migrate_legacy && ! empty( $preview['legacy'] ) ) {
			throw new RuntimeException( 'Legacy zones contain imported ZIPs. Review the preview and explicitly authorize their migration before synchronizing.' );
		}

		$rates    = $this->repository->get_rates();
		$groups   = $this->normalizer->group_by_price( $rates );
		$all      = $this->zones->snapshot_all();
		$settings = $this->repository->get_settings();
		$registry = $this->repository->get_managed_zones();
		$backup   = $this->backups->create( $all, $migrate_legacy ? 'legacy-migration' : 'sync' );

		$available = array();
		foreach ( $registry as $id => $entry ) {
			if ( empty( $entry['archived'] ) && isset( $all[ $id ] ) ) {
				$available[ $id ] = $all[ $id ];
			}
		}
		if ( $adopt ) {
			$available += $preview['adoptable'];
		}
		if ( $migrate_legacy ) {
			$available += $preview['legacy'];
		}
		$legacy_ids = array_fill_keys( array_keys( $preview['legacy'] ), true );

		$assigned = array();
		$created = 0;
		$updated = 0;
		$adopted = 0;
		$migrated = 0;
		foreach ( $groups as $price => $postcodes ) {
			$zone_id = $this->find_price_match( $available, $assigned, $price );
			if ( ! $zone_id ) {
				$zone_id = $this->find_unused( $available, $assigned );
			}

			$name = $this->zone_name( $price, $settings );
			if ( $zone_id ) {
				if ( ! isset( $registry[ $zone_id ] ) ) {
					if ( isset( $legacy_ids[ $zone_id ] ) ) {
						++$migrated;
					} else {
						++$adopted;
					}
				}
				$this->zones->update_zone( $zone_id, $postcodes, $price, $name, isset( $legacy_ids[ $zone_id ] ) );
				++$updated;
			} else {
				$zone_id = $this->zones->create_zone( $postcodes, $price, $name, ! empty( $settings['add_local_pickup'] ) );
				++$created;
			}

			$assigned[ $zone_id ] = true;
			$registry[ $zone_id ] = array( 'price' => $price, 'archived' => false );
		}

		$archived = 0;
		foreach ( $available as $id => $zone ) {
			if ( ! isset( $assigned[ $id ] ) ) {
				$this->zones->archive_zone( $id, isset( $legacy_ids[ $id ] ) );
				$registry[ $id ]['archived'] = true;
				++$archived;
			}
		}

		$this->repository->save_managed_zones( $registry );
		$result = compact( 'created', 'updated', 'adopted', 'migrated', 'archived' );
		$result['backup_id'] = $backup['id'];
		$result['synced_at'] = current_time( 'mysql', true );
		$this->repository->save_last_sync( $result );
		return $result;
	}

	/** @return array<int,array<string,mixed>> */
	public function cleanup_candidates() {
		$registry = $this->repository->get_managed_zones();
		$all      = $this->zones->snapshot_all();
		$result   = array();
		foreach ( $registry as $id => $entry ) {
			if ( empty( $entry['archived'] ) || ! isset( $all[ $id ] ) ) {
				continue;
			}
			$zone = $all[ $id ];
			if ( 0 === strpos( $zone['name'], 'ARCHIVED - ' ) && array( ShipBridge_Zone_Manager::ARCHIVE_ZIP ) === $this->zones->postcodes( $zone ) ) {
				$result[ $id ] = $zone;
			}
		}
		return $result;
	}

	public function cleanup_archives() {
		$candidates = $this->cleanup_candidates();
		if ( ! $candidates ) {
			return 0;
		}
		$this->backups->create( $candidates, 'cleanup' );
		$registry = $this->repository->get_managed_zones();
		foreach ( $candidates as $id => $zone ) {
			$this->zones->delete_zone( $id );
			unset( $registry[ $id ] );
		}
		$this->repository->save_managed_zones( $registry );
		return count( $candidates );
	}

	private function find_price_match( array $zones, array $assigned, $price ) {
		foreach ( $zones as $id => $zone ) {
			if ( ! isset( $assigned[ $id ] ) && $this->zones->flat_rate_cost( $zone ) === $price ) {
				return $id;
			}
		}
		return null;
	}

	private function find_unused( array $zones, array $assigned ) {
		foreach ( $zones as $id => $zone ) {
			if ( ! isset( $assigned[ $id ] ) ) {
				return $id;
			}
		}
		return null;
	}

	private function zone_name( $price, array $settings ) {
		if ( isset( $settings['zone_name_format'] ) && 'price' === $settings['zone_name_format'] ) {
			$value = number_format( (float) $price, 2, '.', '' );
			return 'Zone ' . rtrim( rtrim( $value, '0' ), '.' );
		}
		return sprintf( 'ShipBridge — $%s', number_format( (float) $price, 2 ) );
	}
}

