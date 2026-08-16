<?php
/**
 * Vague login errors, and the promise that nothing else changes.
 *
 * The second half matters as much as the first. This filter sits in front of
 * every authentication handler on the site, so a two-factor prompt or a
 * blocked-account message that this quietly swallowed would lock people out of
 * their own sites with no explanation.
 *
 * @package HopelesslySensible
 */

use HopelesslySensible\Features\Login_Errors;

/**
 * What gets replaced, and what gets carried through untouched.
 */
class Test_Login_Errors extends WP_UnitTestCase {

	/**
	 * Core's own generic failure message.
	 *
	 * @var string
	 */
	private $generic = '<strong>Error:</strong> Invalid username, email address or incorrect password.';

	/**
	 * Runs an error through the filter the feature registers.
	 *
	 * @param \WP_Error $error The error a handler produced.
	 * @return mixed The result after the feature has seen it.
	 */
	private function filtered( $error ) {
		Login_Errors::init();

		return apply_filters( 'authenticate', $error, '', '' );
	}

	/**
	 * The three revealing codes, each replaced with core's generic refusal.
	 *
	 * @dataProvider revealing_codes
	 *
	 * @param string $code The error code a handler produced.
	 * @return void
	 */
	public function test_revealing_errors_are_made_vague( $code ) {
		$result = $this->filtered( new WP_Error( $code, 'Something specific and revealing.' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'authentication_failed', $result->get_error_code() );
		$this->assertSame( $this->generic, $result->get_error_message() );
	}

	/**
	 * The codes that answer "does this account exist?" out loud.
	 *
	 * @return array<string, string[]>
	 */
	public function revealing_codes() {
		return array(
			'unknown username' => array( 'invalid_username' ),
			'unknown email'    => array( 'invalid_email' ),
			'wrong password'   => array( 'incorrect_password' ),
		);
	}

	/**
	 * Anything else a handler has to say is carried through untouched.
	 *
	 * @return void
	 */
	public function test_other_errors_pass_through_unchanged() {
		$error  = new WP_Error( 'two_factor_required', 'Enter the code from your app.' );
		$result = $this->filtered( $error );

		$this->assertSame( $error, $result );
		$this->assertSame( 'two_factor_required', $result->get_error_code() );
		$this->assertSame( 'Enter the code from your app.', $result->get_error_message() );
	}

	/**
	 * An error carrying both keeps the part that is not a giveaway.
	 *
	 * A plugin that adds its own reason alongside one of the three keeps its
	 * reason and loses only the half that confirms the account.
	 *
	 * @return void
	 */
	public function test_a_mixed_error_keeps_the_part_that_is_not_revealing() {
		$error = new WP_Error( 'invalid_username', 'No such user.' );
		$error->add( 'too_many_attempts', 'Locked for 20 minutes.' );

		$result = $this->filtered( $error );

		$this->assertContains( 'too_many_attempts', $result->get_error_codes() );
		$this->assertContains( 'authentication_failed', $result->get_error_codes() );
		$this->assertNotContains( 'invalid_username', $result->get_error_codes() );
		$this->assertSame( 'Locked for 20 minutes.', $result->get_error_message( 'too_many_attempts' ) );
	}

	/**
	 * A successful login is not an error, and is handed straight back.
	 *
	 * @return void
	 */
	public function test_a_successful_login_is_untouched() {
		$user   = new WP_User( self::factory()->user->create() );
		$result = $this->filtered( $user );

		$this->assertSame( $user, $result );
	}

	/**
	 * Nothing at all is also not an error.
	 *
	 * Called directly rather than through the filter, because core's own
	 * handlers sit on it too and turn an empty attempt into the empty_username
	 * and empty_password errors before this feature ever sees it. Those are two
	 * of the codes this feature deliberately leaves alone, and the fact that
	 * they arrive at all is the point of the test below.
	 *
	 * @return void
	 */
	public function test_null_is_untouched() {
		$this->assertNull( Login_Errors::genericise( null ) );
	}

	/**
	 * An empty form still says which field was left blank.
	 *
	 * Core answers an empty username with empty_username, which gives nothing
	 * away about whether any account exists, so it is not one of the three this
	 * feature replaces. Someone who mistypes their own name deserves to be told.
	 *
	 * @return void
	 */
	public function test_an_empty_form_still_names_the_empty_field() {
		$result = $this->filtered( null );

		$this->assertWPError( $result );
		$this->assertContains( 'empty_username', $result->get_error_codes() );
		$this->assertContains( 'empty_password', $result->get_error_codes() );
	}

	/**
	 * The message is core's own string, so it arrives translated wherever
	 * WordPress is, and the refusal is indistinguishable from core's.
	 *
	 * @return void
	 */
	public function test_the_message_is_cores_own() {
		$result = $this->filtered( new WP_Error( 'invalid_username', 'No such user.' ) );

		$this->assertSame(
			__( '<strong>Error:</strong> Invalid username, email address or incorrect password.', 'default' ),
			$result->get_error_message()
		);
	}
}
