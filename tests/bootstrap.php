<?php
/**
 * PHPUnit bootstrap file for the WordPress integration test suite.
 *
 * @package DFX_Random_User_IDs
 */

// Allow the PHPUnit Polyfills path to be provided from the environment (CI).
$_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_polyfills_path );
}

// Composer autoload — provides the Yoast PHPUnit Polyfills the WP suite needs.
$_composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $_composer_autoload ) ) {
	require_once $_composer_autoload;
}

// Locate the WordPress test library.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php." . PHP_EOL;
	echo "Have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/dfx-random-user-id.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require "{$_tests_dir}/includes/bootstrap.php";
