<?php
/**
 * Configuration for the WordPress core test suite.
 *
 * Dev tooling. Never shipped, and never loaded by the plugin.
 *
 * The suite needs two things this file points at: a WordPress codebase to load,
 * and a MySQL server to install a test database on. Neither has a default that
 * works on somebody else's machine, so both are configured per machine.
 *
 * There are two ways to do that:
 *
 * 1. Copy tests/wp-tests-config-local.php.example to
 *    tests/wp-tests-config-local.php and edit it. That copy is ignored by git,
 *    so your directory layout never reaches the repository.
 * 2. Set the HOPSEN_ environment variables listed in that example file, which
 *    is what continuous integration should do.
 *
 * The environment wins where both are present. The file is the setup you use
 * every day, and a variable on the command line is how one run is pointed
 * somewhere else, at an older WordPress for instance, without editing it.
 *
 * The test suite DROPS EVERY TABLE in the database it is pointed at, before
 * every run. That is what DB_NAME being its own database is for, and why
 * tests/bootstrap.php refuses to start if that name looks like a real site.
 *
 * @package HopelesslySensible
 */

$hopsen_local = array();

if ( is_readable( __DIR__ . '/wp-tests-config-local.php' ) ) {
	$hopsen_local = (array) require __DIR__ . '/wp-tests-config-local.php';
}

/**
 * Reads one setting: the environment first, then the local file, then a fallback.
 *
 * @param array<string, string> $local    Settings from the local config file.
 * @param string                $key      The setting name, without its prefix.
 * @param string                $fallback The value to use when nothing sets it.
 * @return string The configured value.
 */
function hopsen_test_setting( array $local, $key, $fallback ) {
	$value = getenv( 'HOPSEN_' . strtoupper( $key ) );

	if ( false !== $value && '' !== $value ) {
		return $value;
	}

	if ( isset( $local[ $key ] ) && '' !== $local[ $key ] ) {
		return (string) $local[ $key ];
	}

	return $fallback;
}

$hopsen_wp_root = hopsen_test_setting( $hopsen_local, 'wp_root', dirname( __DIR__, 2 ) . '/wordpress' );
$hopsen_socket  = hopsen_test_setting( $hopsen_local, 'db_socket', '' );

if ( ! is_readable( rtrim( $hopsen_wp_root, '/' ) . '/wp-settings.php' ) ) {
	echo 'No WordPress codebase found. Set wp_root in tests/wp-tests-config-local.php,';
	echo ' or HOPSEN_WP_ROOT in the environment. See the .example file next to this one.';
	echo PHP_EOL;
	exit( 1 );
}

define( 'ABSPATH', rtrim( $hopsen_wp_root, '/' ) . '/' );

define( 'DB_NAME', hopsen_test_setting( $hopsen_local, 'db_name', 'hopsen_tests' ) );
define( 'DB_USER', hopsen_test_setting( $hopsen_local, 'db_user', 'root' ) );
define( 'DB_PASSWORD', hopsen_test_setting( $hopsen_local, 'db_pass', 'root' ) );

/*
 * A socket wins where one is given, because that is how Local, MAMP, DBngin and
 * Docker Desktop all expose MySQL. Otherwise an ordinary host and port.
 */
if ( '' !== $hopsen_socket ) {
	define( 'DB_HOST', 'localhost:' . $hopsen_socket );
} else {
	define( 'DB_HOST', hopsen_test_setting( $hopsen_local, 'db_host', '127.0.0.1' ) );
}

define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Hopelessly Sensible tests' );
define( 'WP_PHP_BINARY', hopsen_test_setting( $hopsen_local, 'php_binary', 'php' ) );

/*
 * The tests want a theme that expects nothing. Overridable because the default
 * only exists on WordPress 7.0 and later, and pointing this suite at an older
 * codebase is how the plugin's minimum gets tested. A theme the installer cannot
 * find takes the whole run down before a single test has been read.
 */
define( 'WP_DEFAULT_THEME', hopsen_test_setting( $hopsen_local, 'theme', 'twentytwentyfive' ) );

define( 'WP_DEBUG', true );
