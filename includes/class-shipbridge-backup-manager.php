<?php

defined( 'ABSPATH' ) || exit;

final class ShipBridge_Backup_Manager {
	/** @var ShipBridge_Rate_Repository */
	private $repository;

	public function __construct( ShipBridge_Rate_Repository $repository ) {
		$this->repository = $repository;
	}

	/** @return array<string,mixed> */
	public function create( array $zones, $reason ) {
		$backup = array(
			'id'         => gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false ),
			'created_at' => current_time( 'mysql', true ),
			'reason'     => sanitize_key( $reason ),
			'plugin'     => SHIPBRIDGE_VERSION,
			'site_url'   => home_url(),
			'zones'      => $zones,
		);

		$backups = $this->repository->get_backups();
		array_unshift( $backups, $backup );
		$this->repository->save_backups( array_slice( $backups, 0, 10 ) );
		return $backup;
	}

	public function find( $id ) {
		foreach ( $this->repository->get_backups() as $backup ) {
			if ( isset( $backup['id'] ) && hash_equals( (string) $backup['id'], (string) $id ) ) {
				return $backup;
			}
		}
		return null;
	}
}

