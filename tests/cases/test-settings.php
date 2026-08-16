<?php
/**
 * The option: what a save keeps, what it discards, and what an update does.
 *
 * These are the failures that are invisible until weeks later. A settings form
 * that loses data looks exactly like a settings form that worked.
 *
 * @package HopelesslySensible
 */

use HopelesslySensible\Registry;
use HopelesslySensible\Settings;

/**
 * Sanitising a posted form, retreating from a blocked feature, and bringing an
 * older install up to date.
 */
class Test_Settings extends WP_UnitTestCase {

	/**
	 * Clears the cached settings and the cached registry between tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		Settings::flush();
		$this->restore_registry();

		parent::tear_down();
	}

	/**
	 * Stores a complete option row without going through sanitise.
	 *
	 * @param array<string, bool>  $features Feature states.
	 * @param array<string, mixed> $live     The live block, or an empty array.
	 * @param int                  $schema   The schema to claim.
	 * @return void
	 */
	private function store( array $features, array $live = array(), $schema = null ) {
		remove_all_filters( 'sanitize_option_' . HOPSEN_OPTION );

		update_option(
			HOPSEN_OPTION,
			array(
				'schema'   => null === $schema ? HOPSEN_SCHEMA : $schema,
				'features' => $features,
				'live'     => $live,
			)
		);

		Settings::flush();
	}

	/**
	 * Replaces one registry entry with a copy that is blocked.
	 *
	 * The plugin decides whether a row can be moved by calling the entry's
	 * blocker, so a blocker that answers yes is the whole of what "WooCommerce
	 * is not installed" means to the code being tested here. The stub in
	 * tests/stubs makes the real answer permanently no.
	 *
	 * @param string $key     The feature to block.
	 * @param bool   $retreat Whether the blocker demands the switch go off.
	 * @return void
	 */
	private function block_row( $key, $retreat = true ) {
		$registry = Registry::all();

		$registry[ $key ]['blocker'] = true === $retreat
			? array( self::class, 'blocker_retreating' )
			: array( self::class, 'blocker_standing' );

		$cache = new ReflectionProperty( Registry::class, 'registry' );
		$cache->setAccessible( true );
		$cache->setValue( null, $registry );
	}

	/**
	 * A blocker that wants the switch turned off.
	 *
	 * @return array<string, mixed> A blocker.
	 */
	public static function blocker_retreating() {
		return array(
			'variant' => 'blocked',
			'retreat' => true,
		);
	}

	/**
	 * A blocker that leaves the stored value where it is.
	 *
	 * @return array<string, mixed> A blocker.
	 */
	public static function blocker_standing() {
		return array(
			'variant' => 'blocked',
			'retreat' => false,
		);
	}

	/**
	 * Empties the registry cache so the next call rebuilds it.
	 *
	 * @return void
	 */
	private function restore_registry() {
		$cache = new ReflectionProperty( Registry::class, 'registry' );
		$cache->setAccessible( true );
		$cache->setValue( null, null );
	}

	/**
	 * Every feature key, switched off.
	 *
	 * @return array<string, bool>
	 */
	private function all_off() {
		$features = array();

		foreach ( array_keys( Registry::all() ) as $key ) {
			$features[ $key ] = false;
		}

		return $features;
	}

	/**
	 * Nothing in the stored option remembers what the site used to look like.
	 *
	 * The whole of the redesign, in one assertion. A findings block would put
	 * every sentence on the screen back into the past tense.
	 *
	 * @return void
	 */
	public function test_the_option_keeps_no_record_of_the_past() {
		$this->assertSame( array( 'schema', 'features', 'live' ), array_keys( Settings::defaults() ) );
		$this->assertSame( array( 'switched_off', 'dismissed' ), array_keys( Settings::normalise_live( array() ) ) );
	}

	/**
	 * An unchecked box posts nothing, and nothing means off.
	 *
	 * @return void
	 */
	public function test_a_missing_checkbox_reads_as_false() {
		$features                 = $this->all_off();
		$features['rest_users']   = true;
		$features['block_xmlrpc'] = true;

		$this->store( $features );

		$saved = Settings::sanitize( array( 'features' => array( 'rest_users' => '1' ) ) );

		$this->assertTrue( $saved['features']['rest_users'] );
		$this->assertFalse( $saved['features']['block_xmlrpc'] );
	}

	/**
	 * Anything not in the registry is discarded rather than stored.
	 *
	 * @return void
	 */
	public function test_unknown_keys_are_discarded() {
		$this->store( $this->all_off() );

		$saved = Settings::sanitize(
			array(
				'features' => array(
					'rest_users'      => '1',
					'delete_the_site' => '1',
				),
			)
		);

		$this->assertArrayNotHasKey( 'delete_the_site', $saved['features'] );
		$this->assertSame( array_keys( Registry::all() ), array_keys( $saved['features'] ) );
	}

	/**
	 * A blocked row keeps its stored value.
	 *
	 * Its checkbox is disabled, and a disabled checkbox posts exactly the same
	 * silence as an unchecked one while meaning the opposite. Reading it as false
	 * would reset the product reviews setting for anyone who saved this screen
	 * while WooCommerce happened to be switched off.
	 *
	 * @return void
	 */
	public function test_a_blocked_row_keeps_its_stored_value() {
		$features                        = $this->all_off();
		$features['disable_woo_reviews'] = true;

		$this->store( $features );
		$this->block_row( 'disable_woo_reviews', false );

		$saved = Settings::sanitize( array( 'features' => array( 'rest_users' => '1' ) ) );

		$this->assertTrue( $saved['features']['disable_woo_reviews'] );
	}

	/**
	 * A feature added in a later version arrives switched off on a site that
	 * already had the plugin.
	 *
	 * The auto-update guarantee, and the one test here that cannot be done by
	 * hand without waiting for a release.
	 *
	 * @return void
	 */
	public function test_a_new_feature_stays_off_when_the_schema_moves() {
		$existing = array(
			'rest_users'   => true,
			'login_errors' => true,
			'author_urls'  => true,
		);

		$this->store( $existing, array(), HOPSEN_SCHEMA - 1 );

		Settings::maybe_upgrade_schema();
		Settings::flush();

		$settings = Settings::get();

		$this->assertSame( HOPSEN_SCHEMA, $settings['schema'] );
		$this->assertTrue( $settings['features']['author_urls'], 'An existing choice was changed by the upgrade.' );

		foreach ( array_diff( array_keys( Registry::all() ), array_keys( $existing ) ) as $added ) {
			$this->assertFalse( $settings['features'][ $added ], $added . ' arrived switched on.' );
		}
	}

	/**
	 * The upgrade takes the old findings block out of the row.
	 *
	 * Left there it would be a record of the past sitting in the option of a
	 * plugin whose screen now says it keeps none, waiting for somebody to read it
	 * back one day.
	 *
	 * @return void
	 */
	public function test_the_upgrade_drops_the_old_findings_block() {
		$this->store_schema_one();

		Settings::maybe_upgrade_schema();

		$this->assertArrayNotHasKey( 'detected', get_option( HOPSEN_OPTION ) );
	}

	/**
	 * The upgrade brings the live block up to shape as well.
	 *
	 * Nothing else on that path normalises it, and the write happens before
	 * register_setting() has attached the sanitise callback, so whatever the
	 * upgrade leaves in the array is what sits in the database. Found on a live
	 * site, where a row stamped schema 2 went on holding schema 1's live block.
	 *
	 * @return void
	 */
	public function test_the_upgrade_reshapes_the_live_block() {
		$this->store_schema_one();

		Settings::maybe_upgrade_schema();

		$live = get_option( HOPSEN_OPTION )['live'];

		$this->assertSame( array( 'switched_off', 'dismissed' ), array_keys( $live ) );
		$this->assertArrayNotHasKey( 'xmlrpc_in_use', $live );
	}

	/**
	 * Stores a row in the shape the previous schema used.
	 *
	 * @return void
	 */
	private function store_schema_one() {
		remove_all_filters( 'sanitize_option_' . HOPSEN_OPTION );

		update_option(
			HOPSEN_OPTION,
			array(
				'schema'   => 1,
				'features' => $this->all_off(),
				'detected' => array(
					'time'    => 1700000000,
					'authors' => 4,
				),
				'live'     => array( 'xmlrpc_in_use' => true ),
			)
		);

		Settings::flush();
	}

	/**
	 * A save keeps the live block, which the form never posts.
	 *
	 * @return void
	 */
	public function test_saving_preserves_the_live_block() {
		$this->store( $this->all_off(), array( 'dismissed' => array( 7 ) ) );

		$saved = Settings::sanitize( array( 'features' => array( 'rest_users' => '1' ) ) );

		$this->assertSame( array( 7 ), $saved['live']['dismissed'] );
	}

	/**
	 * A blocked feature that was left on is switched off, and recorded.
	 *
	 * @return void
	 */
	public function test_switching_off_records_what_it_did() {
		$features                        = $this->all_off();
		$features['disable_woo_reviews'] = true;

		$this->store( $features, array( 'dismissed' => array( 7 ) ) );

		Settings::switch_off( 'disable_woo_reviews' );

		$this->assertFalse( Settings::is_enabled( 'disable_woo_reviews' ) );
		$this->assertSame( array( 'disable_woo_reviews' ), Settings::live( 'switched_off' ) );
	}

	/**
	 * A fresh retreat is news again, even to somebody who dismissed the last one.
	 *
	 * @return void
	 */
	public function test_switching_off_clears_old_dismissals() {
		$features                 = $this->all_off();
		$features['block_xmlrpc'] = true;

		$this->store( $features, array( 'dismissed' => array( 7, 9 ) ) );

		Settings::switch_off( 'block_xmlrpc' );

		$this->assertSame( array(), Settings::live( 'dismissed' ) );
	}

	/**
	 * Switching a feature back on retires the announcement about it.
	 *
	 * @return void
	 */
	public function test_switching_back_on_ends_the_announcement() {
		$this->store( $this->all_off(), array( 'switched_off' => array( 'block_xmlrpc' ) ) );

		$saved = Settings::sanitize( array( 'features' => array( 'block_xmlrpc' => '1' ) ) );

		$this->assertTrue( $saved['features']['block_xmlrpc'] );
		$this->assertSame( array(), $saved['live']['switched_off'] );
	}

	/**
	 * A dismissal survives a re-read from the database.
	 *
	 * @return void
	 */
	public function test_a_dismissal_is_actually_written() {
		$this->store( $this->all_off(), array( 'switched_off' => array( 'block_xmlrpc' ) ) );

		Settings::dismiss( 12 );
		Settings::flush();

		$this->assertSame( array( 12 ), Settings::live( 'dismissed' ) );
	}

	/**
	 * The live block will not carry a feature key that no longer exists.
	 *
	 * @return void
	 */
	public function test_unknown_features_are_dropped_from_the_record() {
		$live = Settings::normalise_live(
			array(
				'switched_off' => array( 'block_xmlrpc', 'a_feature_we_removed' ),
				'dismissed'    => array( 3, 3, 0, '4' ),
			)
		);

		$this->assertSame( array( 'block_xmlrpc' ), $live['switched_off'] );
		$this->assertSame( array( 3, 4 ), $live['dismissed'] );
	}

	/**
	 * Uninstalling takes the one row away, and there is nothing else to take.
	 *
	 * The promise in readme.txt, and until now the only claim in it that had
	 * never been checked anywhere: "Uninstalling removes its single row from your
	 * options table." Runs the file core runs, the way core runs it.
	 *
	 * @return void
	 */
	public function test_uninstalling_removes_the_only_row() {
		$this->store( $this->all_off() );

		$this->assertTrue( Settings::exists(), 'The row should be there to start with.' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'hopelessly-sensible/hopelessly-sensible.php' );
		}

		require_once dirname( __DIR__, 2 ) . '/uninstall.php';

		Settings::flush();

		$this->assertFalse( Settings::exists(), 'The option survived being uninstalled.' );
	}

	/**
	 * The name the uninstall file deletes is the name the plugin writes.
	 *
	 * They are kept in step by hand, because that file runs in a request where
	 * the plugin is not loaded and cannot read HOPSEN_OPTION. A typo there would
	 * leave the row behind for ever and nothing would look wrong.
	 *
	 * @return void
	 */
	public function test_the_uninstall_file_names_the_right_option() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'hopelessly-sensible/hopelessly-sensible.php' );
		}

		require_once dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame( HOPSEN_OPTION, HOPSEN_UNINSTALL_OPTION );
	}

	/**
	 * An install already at the current schema is left alone.
	 *
	 * @return void
	 */
	public function test_an_up_to_date_install_is_not_rewritten() {
		$features               = $this->all_off();
		$features['rest_users'] = true;

		$this->store( $features );

		Settings::maybe_upgrade_schema();
		Settings::flush();

		$this->assertSame( $features, Settings::get()['features'] );
	}
}
