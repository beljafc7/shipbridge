<?php

define( 'SHIPBRIDGE_TESTING', true );

$GLOBALS['shipbridge_test_options'] = array();
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['shipbridge_test_options'] ) ? $GLOBALS['shipbridge_test_options'][ $key ] : $default;
}
function wp_parse_args( $value, $defaults ) {
	return array_merge( $defaults, is_array( $value ) ? $value : array() );
}
function delete_option( $key ) {
	unset( $GLOBALS['shipbridge_test_options'][ $key ] );
	return true;
}

require dirname( __DIR__ ) . '/includes/class-shipbridge-rate-normalizer.php';
require dirname( __DIR__ ) . '/includes/class-shipbridge-csv-importer.php';
require dirname( __DIR__ ) . '/includes/class-shipbridge-rate-repository.php';
require dirname( __DIR__ ) . '/includes/class-shipbridge-zone-manager.php';
require dirname( __DIR__ ) . '/includes/class-shipbridge-backup-manager.php';
require dirname( __DIR__ ) . '/includes/class-shipbridge-sync-service.php';

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$normalizer = new ShipBridge_Rate_Normalizer();
$result     = $normalizer->normalize(
	array(
		array( 'zip' => '33009', 'city' => 'Hallandale', 'price' => '$110' ),
		array( 'zip' => '33009', 'city' => 'Hallandale Beach', 'price' => '140.00' ),
		array( 'zip' => '33010-1234', 'city' => 'Hialeah', 'price' => '185' ),
		array( 'zip' => '0000', 'city' => 'Bad', 'price' => '10' ),
	)
);
assert_same( 2, count( $result['rates'] ), 'Two valid unique ZIPs are retained.' );
assert_same( '140.00', $result['rates']['33009']['price'], 'Highest duplicate price wins.' );
assert_same( '33010', $result['rates']['33010']['zip'], 'ZIP+4 becomes a five-digit ZIP.' );
assert_same( 1, $result['duplicates'], 'Duplicate count is reported.' );
assert_same( 1, count( $result['rejected'] ), 'Invalid ZIP is rejected.' );
assert_same( 4, $result['total_rows'], 'Normalizer reports the total number of source rows.' );

$groups = $normalizer->group_by_price( $result['rates'] );
assert_same( array( '33009' ), $groups['140.00'], 'Rates are grouped by canonical price.' );
assert_same( array( '33010' ), $groups['185.00'], 'Second price group is present.' );

$temp = tempnam( sys_get_temp_dir(), 'shipbridge-' );
file_put_contents( $temp, "ZIP Code,City,Price\n33001,Long Key,740\n33002,Hialeah,165\n33002,Hialeah,170\n" );
$csv = ( new ShipBridge_CSV_Importer( $normalizer ) )->import_file( $temp );
unlink( $temp );
assert_same( 2, count( $csv['rates'] ), 'CSV importer reads expected rows.' );
assert_same( '170.00', $csv['rates']['33002']['price'], 'CSV importer applies duplicate rule.' );
assert_same( 3, $csv['total_rows'], 'CSV importer reports the total number of data rows.' );

$repository = new ShipBridge_Rate_Repository();
$zones      = new ShipBridge_Zone_Manager( $repository );
assert_same( 'Zone {price}', $repository->get_settings()['zone_name_template'], 'Custom zone name template has a safe default.' );
$sync       = new ShipBridge_Sync_Service( $repository, $zones, new ShipBridge_Backup_Manager( $repository ) );
$zone_name  = new ReflectionMethod( 'ShipBridge_Sync_Service', 'zone_name' );
$zone_name->setAccessible( true );
assert_same( 'Delivery 95', $zone_name->invoke( $sync, '95.00', array( 'zone_name_format' => 'custom', 'zone_name_template' => 'Delivery {price}' ) ), 'Custom zone names support a trimmed price token.' );
assert_same( 'Delivery $95.00', $zone_name->invoke( $sync, '95.00', array( 'zone_name_format' => 'custom', 'zone_name_template' => 'Delivery {price_formatted}' ) ), 'Custom zone names support a formatted price token.' );
$GLOBALS['shipbridge_test_options'][ ShipBridge_Rate_Repository::OPTION_RATES ] = array( '33001' => array( 'zip' => '33001', 'price' => '95.00' ) );
$GLOBALS['shipbridge_test_options'][ ShipBridge_Rate_Repository::OPTION_RATE_META ] = array( 'source' => 'csv' );
$repository->clear_rates();
assert_same( array(), $repository->get_rates(), 'Clearing an import removes stored rates.' );
assert_same( array(), $repository->get_rate_meta(), 'Clearing an import removes stored metadata.' );
$settings   = array(
	'protected_patterns' => "Samples\nTL Price",
	'legacy_patterns'    => "Broward Zone\nMiami Zone",
);
$methods    = array(
	array( 'id' => 'flat_rate', 'enabled' => 'yes', 'cost' => '95', 'instance_id' => 1, 'title' => 'Flat rate' ),
	array( 'id' => 'local_pickup', 'enabled' => 'yes', 'cost' => null, 'instance_id' => 2, 'title' => 'Local pickup' ),
);
$legacy = $zones->legacy_migration_candidates(
	array(
		10 => array(
			'id'        => 10,
			'name'      => 'Broward Zone 1',
			'locations' => array(
				array( 'type' => 'continent', 'code' => 'NA' ),
				array( 'type' => 'country', 'code' => 'US' ),
				array( 'type' => 'postcode', 'code' => '33022' ),
				array( 'type' => 'postcode', 'code' => '33022' ),
				array( 'type' => 'postcode', 'code' => '33023' ),
			),
			'methods'   => $methods,
		),
		11 => array(
			'id'        => 11,
			'name'      => 'Unsafe wildcard zone',
			'locations' => array(
				array( 'type' => 'country', 'code' => 'US' ),
				array( 'type' => 'postcode', 'code' => '3302*' ),
			),
			'methods'   => $methods,
		),
		12 => array(
			'id'        => 12,
			'name'      => 'Miami Zone 14',
			'locations' => array(
				array( 'type' => 'continent', 'code' => 'NA' ),
				array( 'type' => 'country', 'code' => 'US' ),
				array( 'type' => 'postcode', 'code' => '12' ),
			),
			'methods'   => $methods,
		),
	),
	array( '33022', '33023' ),
	$settings
);
assert_same( array( 10, 12 ), array_keys( $legacy ), 'Named legacy zones are eligible for explicit migration.' );

echo "ShipBridge tests passed.\n";

