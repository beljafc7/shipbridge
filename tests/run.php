<?php

define( 'SHIPBRIDGE_TESTING', true );
require dirname( __DIR__ ) . '/includes/class-shipbridge-rate-normalizer.php';
require dirname( __DIR__ ) . '/includes/class-shipbridge-csv-importer.php';

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

$groups = $normalizer->group_by_price( $result['rates'] );
assert_same( array( '33009' ), $groups['140.00'], 'Rates are grouped by canonical price.' );
assert_same( array( '33010' ), $groups['185.00'], 'Second price group is present.' );

$temp = tempnam( sys_get_temp_dir(), 'shipbridge-' );
file_put_contents( $temp, "ZIP Code,City,Price\n33001,Long Key,740\n33002,Hialeah,165\n33002,Hialeah,170\n" );
$csv = ( new ShipBridge_CSV_Importer( $normalizer ) )->import_file( $temp );
unlink( $temp );
assert_same( 2, count( $csv['rates'] ), 'CSV importer reads expected rows.' );
assert_same( '170.00', $csv['rates']['33002']['price'], 'CSV importer applies duplicate rule.' );

echo "ShipBridge tests passed.\n";

