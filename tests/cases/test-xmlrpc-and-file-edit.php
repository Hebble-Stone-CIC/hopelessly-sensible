<?php
/**
 * The two features that act on the request rather than on its content.
 *
 * Neither can be tested the whole way through. Refusing XML-RPC ends the
 * request, and locking the file editor defines a constant that cannot be
 * undefined, so what is tested here is everything up to those two points.
 *
 * The constant is also why the order of these tests matters, and why the ones
 * that need it absent say so rather than assuming.
 *
 * @package HopelesslySensible
 */

use HopelesslySensible\Detection;
use HopelesslySensible\Features\File_Edit;
use HopelesslySensible\Features\Xmlrpc;
use HopelesslySensible\Plugin;
use HopelesslySensible\Settings;

/**
 * Blocking remote publishing, retreating from it, and taking the file editors
 * away.
 */
class Test_Xmlrpc_And_File_Edit extends WP_UnitTestCase {

	/**
	 * How many times the option has been written during a test.
	 *
	 * @var int
	 */
	private $writes = 0;

	/**
	 * Clears the cached settings between tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		Settings::flush();
		$this->set_config_defined( null );

		parent::tear_down();
	}

	/**
	 * Tells File_Edit what it saw when it started.
	 *
	 * The constant survives a test while the class's memory of who defined it is
	 * meant to. Setting it by hand is how a site whose wp-config.php defines the
	 * constant is simulated in a process where this plugin has already defined it
	 * itself.
	 *
	 * @param bool|null $defined What the feature should believe.
	 * @return void
	 */
	private function set_config_defined( $defined ) {
		$property = new ReflectionProperty( File_Edit::class, 'config_defined' );
		$property->setAccessible( true );
		$property->setValue( null, $defined );
	}

	/**
	 * Switches the feature on, through the sanitise callback rather than around
	 * it.
	 *
	 * Deliberately not removing the filter first. The retreat writes through
	 * update_option like everything else, so the callback is part of the path
	 * being tested: a version of it that restored a blocked feature's stored
	 * value would undo every retreat, and a test that wrote around it would not
	 * notice.
	 *
	 * @param string $key A feature key.
	 * @return void
	 */
	private function switch_on( $key ) {
		$settings                     = Settings::defaults();
		$settings['features'][ $key ] = true;

		update_option( HOPSEN_OPTION, $settings );
		Settings::flush();
	}

	/**
	 * Counts a write to the option.
	 *
	 * @return void
	 */
	public function count_write() {
		++$this->writes;
	}

	/**
	 * A site that starts using XML-RPC has the switch turned off, not paused.
	 *
	 * The heart of the redesign. Schema 1 left the setting reading as on while
	 * quietly enforcing nothing, which is the dishonesty the plugin argues
	 * against everywhere else.
	 *
	 * @return void
	 */
	public function test_a_site_that_starts_using_xmlrpc_has_the_switch_turned_off() {
		$this->switch_on( 'block_xmlrpc' );

		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		Plugin::retreat();

		$this->assertFalse( Settings::is_enabled( 'block_xmlrpc' ), 'The switch was left on while doing nothing.' );
		$this->assertSame( array( 'block_xmlrpc' ), Settings::live( 'switched_off' ) );
	}

	/**
	 * The retreat survives its own trip through the sanitise callback.
	 *
	 * A blocked row's checkbox is disabled and posts nothing, so the callback
	 * keeps the stored value rather than reading the silence as off. Applied to
	 * the plugin's own write, that rule puts back the exact value the retreat
	 * had just cleared, and the setting reads as on for ever while the banner
	 * insists it was turned off.
	 *
	 * @return void
	 */
	public function test_the_retreat_is_not_undone_by_the_sanitise_callback() {
		$this->switch_on( 'block_xmlrpc' );

		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		Plugin::retreat();
		Settings::flush();

		$stored = get_option( HOPSEN_OPTION );

		$this->assertFalse( $stored['features']['block_xmlrpc'], 'The retreat was written and then undone on its way to the database.' );
	}

	/**
	 * The announcement outlives the thing that caused it.
	 *
	 * Deliberate, and the reason the banner's copy is in the past tense. Somebody
	 * whose setting was switched off needs telling whether or not the reason has
	 * since gone away: WooCommerce off for an afternoon leaves the reviews
	 * setting off afterwards, and retiring the notice when the blocker cleared
	 * would mean nobody ever found out.
	 *
	 * @return void
	 */
	public function test_the_announcement_outlives_the_blocker() {
		$this->switch_on( 'block_xmlrpc' );

		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		Plugin::retreat();

		remove_filter( 'xmlrpc_methods', '__return_empty_array' );
		Settings::flush();

		$this->assertFalse( Detection::xmlrpc_in_use(), 'The blocker should have cleared.' );
		$this->assertFalse( Settings::is_enabled( 'block_xmlrpc' ), 'The switch stays off until somebody puts it back.' );
		$this->assertSame( array( 'block_xmlrpc' ), Settings::live( 'switched_off' ) );
	}

	/**
	 * A site that needs nothing of the sort keeps its switch.
	 *
	 * @return void
	 */
	public function test_a_site_not_using_xmlrpc_keeps_the_switch() {
		$this->switch_on( 'block_xmlrpc' );

		Plugin::retreat();

		$this->assertTrue( Settings::is_enabled( 'block_xmlrpc' ) );
		$this->assertSame( array(), Settings::live( 'switched_off' ) );
	}

	/**
	 * Nothing to retreat from writes nothing at all.
	 *
	 * This runs on every request on every site using the plugin, so a write here
	 * would be a write on every page load.
	 *
	 * @return void
	 */
	public function test_an_unchanged_answer_writes_nothing() {
		$this->switch_on( 'block_xmlrpc' );

		add_action( 'update_option_' . HOPSEN_OPTION, array( $this, 'count_write' ) );

		$this->writes = 0;

		Plugin::retreat();
		Plugin::retreat();
		Plugin::retreat();

		$this->assertSame( 0, $this->writes );

		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		Plugin::retreat();
		Plugin::retreat();

		$this->assertSame( 1, $this->writes, 'The answer changed once, so it should have been written once.' );
	}

	/**
	 * A feature already switched off is not retreated from twice.
	 *
	 * @return void
	 */
	public function test_a_feature_already_off_is_left_alone() {
		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		Plugin::retreat();

		$this->assertSame( array(), Settings::live( 'switched_off' ) );
	}

	/**
	 * The site stops answering XML-RPC, through core's own flag as well.
	 *
	 * The flag is not what closes the endpoint, since core reads it inside the
	 * server after the request has been parsed, but anything asking politely
	 * whether XML-RPC is available should be told no.
	 *
	 * @return void
	 */
	public function test_xmlrpc_reports_itself_disabled() {
		$this->assertTrue( apply_filters( 'xmlrpc_enabled', true ) );

		Xmlrpc::init();

		$this->assertFalse( apply_filters( 'xmlrpc_enabled', true ) );
	}

	/**
	 * The site stops advertising the pingback endpoint, and keeps its other
	 * headers.
	 *
	 * @return void
	 */
	public function test_the_pingback_header_is_removed() {
		$headers = array(
			'X-Pingback'   => 'http://example.org/xmlrpc.php',
			'Content-Type' => 'text/html; charset=UTF-8',
		);

		$filtered = Xmlrpc::remove_pingback_header( $headers );

		$this->assertArrayNotHasKey( 'X-Pingback', $filtered );
		$this->assertSame( 'text/html; charset=UTF-8', $filtered['Content-Type'] );
	}

	/**
	 * The RSD link comes out of the page head.
	 *
	 * @return void
	 */
	public function test_the_rsd_link_is_removed() {
		Xmlrpc::init();

		$this->assertFalse( has_action( 'wp_head', 'rsd_link' ) );
	}

	/**
	 * An ordinary request is not an XML-RPC request, and survives being asked.
	 *
	 * This test passing at all is the assertion: the early refusal ends the
	 * request with exit, so if its guard were wrong the suite would stop here
	 * rather than fail.
	 *
	 * @return void
	 */
	public function test_an_ordinary_request_is_not_refused() {
		$this->assertFalse( defined( 'XMLRPC_REQUEST' ) );

		Xmlrpc::refuse_early();

		$this->assertTrue( true );
	}

	/**
	 * The file editor lock is core's constant, and nothing else.
	 *
	 * The only test in the suite that gets a process where DISALLOW_FILE_EDIT
	 * has never been defined, since defining it is a one-way door. So it is also
	 * where the feature's memory of who defined it is checked: everything after
	 * this point has to simulate that state rather than arrive at it.
	 *
	 * @return void
	 */
	public function test_locking_the_file_editor_defines_cores_constant() {
		$this->assertFalse( defined( 'DISALLOW_FILE_EDIT' ), 'Something defined the constant before this test, so the rest of it proves nothing.' );
		$this->assertFalse( File_Edit::config_defines_it() );
		$this->assertNull( Detection::file_edit_blocker(), 'A site that defines the constant nowhere has nothing blocking this row.' );

		File_Edit::init();

		$this->assertTrue( defined( 'DISALLOW_FILE_EDIT' ) );
		$this->assertTrue( DISALLOW_FILE_EDIT );
		$this->assertFalse( File_Edit::config_defines_it(), 'We defined it, so wp-config.php did not.' );

		/*
		 * A second call must not fatal, and must not change its mind. Asking
		 * defined() again would answer yes, and the row would start telling
		 * somebody about a line in their wp-config.php that they never wrote.
		 */
		File_Edit::init();

		$this->assertTrue( DISALLOW_FILE_EDIT );
		$this->assertFalse( File_Edit::config_defines_it(), 'A second call rewrote who defined the constant.' );
	}

	/**
	 * Nothing was written to the roles option to achieve that.
	 *
	 * @return void
	 */
	public function test_no_capability_was_changed() {
		File_Edit::init();

		$administrator = get_role( 'administrator' );

		$this->assertTrue( $administrator->has_cap( 'edit_themes' ) );
		$this->assertTrue( $administrator->has_cap( 'edit_plugins' ) );
	}

	/**
	 * Our own lock does not block our own row.
	 *
	 * The state a site is in whenever this feature is switched on and wp-config
	 * says nothing: the constant is defined, and by us. Set by hand rather than
	 * by calling init(), because in this process the constant already exists and
	 * a fresh init() could not honestly tell the two situations apart.
	 *
	 * @return void
	 */
	public function test_our_own_lock_does_not_block_the_row() {
		$this->set_config_defined( false );

		$this->assertNull( Detection::file_edit_blocker() );
	}

	/**
	 * A site whose wp-config.php already locks the editor is told so, and
	 * nothing is switched off, because the outcome is the one the label promises.
	 *
	 * @return void
	 */
	public function test_a_lock_in_wp_config_blocks_the_row_without_a_retreat() {
		File_Edit::init();
		$this->set_config_defined( true );

		$blocker = Detection::file_edit_blocker();

		$this->assertSame( 'blocked_locked', $blocker['variant'] );
		$this->assertFalse( $blocker['retreat'], 'An editor that is already locked is not a reason to switch anything off.' );
		$this->assertTrue( $blocker['checked'], 'The row should read as on, because the editor is locked.' );
	}
}
