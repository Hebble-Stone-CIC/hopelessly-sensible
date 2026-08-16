<?php
/**
 * Boots the WordPress core test suite with this plugin loaded.
 *
 * The plugin is loaded from the repository rather than from any WordPress
 * installation, so what runs here is the code being edited and not a copy of it
 * somewhere else.
 *
 * @package HopelesslySensible
 */

/*
 * Point the suite at our config and load it here, rather than through the
 * WP_PHPUNIT__TESTS_CONFIG environment variable. That variable routes the load
 * through a shim in the package, which would leave the config loaded twice
 * under two different paths and every constant in it redefined. Naming the file
 * outright means the suite's own require_once finds it already loaded.
 *
 * This define comes before the requires for a duller reason: a file docblock
 * followed immediately by a require is read as documenting the require rather
 * than the file, and phpcs then reports the file comment as missing.
 */
define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once WP_TESTS_CONFIG_FILE_PATH;

/*
 * The core test suite empties the database it is given before every run. This
 * suite points at a real MySQL server, on a machine that also has real sites on
 * it, so the one mistake worth making impossible is pointing it at one of them.
 * A name check is crude, and it is also the check that would have saved the
 * site.
 */
$hopsen_forbidden = array( 'local', 'wordpress', 'wp' );

if ( in_array( strtolower( DB_NAME ), $hopsen_forbidden, true ) ) {
	echo 'Refusing to run: DB_NAME is "' . DB_NAME . '", which looks like a real site. The test suite would drop every table in it.' . PHP_EOL;
	exit( 1 );
}

$hopsen_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $hopsen_tests_dir ) {
	$hopsen_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

require_once $hopsen_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin under test, as though it were a must-use plugin.
 *
 * A named function because the plugin's own rule against closures on hooks is a
 * good habit to keep in its tests as well.
 *
 * @return void
 */
function hopsen_load_plugin_for_tests() {
	require dirname( __DIR__ ) . '/hopelessly-sensible.php';
}

tests_add_filter( 'muplugins_loaded', 'hopsen_load_plugin_for_tests' );

/**
 * Declares the WooCommerce stub once WordPress is loaded.
 *
 * @return void
 */
function hopsen_load_woocommerce_stub() {
	require_once __DIR__ . '/stubs/class-woocommerce.php';
}

tests_add_filter( 'muplugins_loaded', 'hopsen_load_woocommerce_stub' );

require $hopsen_tests_dir . '/includes/bootstrap.php';
