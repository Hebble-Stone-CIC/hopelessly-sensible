<?php
/**
 * Keep login errors vague.
 *
 * WordPress tells a failed login whether the account exists or the password was
 * wrong, which turns a list of guesses into a list of targets. The lost-password
 * form is deliberately not covered. See refs/gotchas.md, "Authentication".
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Gives the same answer whether the username or the password was wrong.
 */
class Login_Errors {

	/**
	 * The three codes that answer "does this account exist?" out loud.
	 *
	 * @var string[]
	 */
	private static $revealing = array( 'invalid_username', 'invalid_email', 'incorrect_password' );

	/**
	 * Registers the hooks for this feature.
	 *
	 * Priority 30 on authenticate, and both halves of that matter. The hook has to
	 * be authenticate rather than login_errors so that every route through
	 * wp_signon() is covered, including the WooCommerce my-account form and
	 * application passwords, rather than the login screen alone. The priority has
	 * to be above 20, where core registers wp_authenticate_username_password,
	 * because before that runs there is no error here to replace.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'authenticate', array( __CLASS__, 'genericise' ), 30 );
	}

	/**
	 * Replaces the three errors that confirm whether an account exists.
	 *
	 * Anything else an authentication handler has to say is carried across
	 * untouched, so two-factor prompts and captcha failures still reach the person
	 * logging in. The replacement is core's own code and wording, which keeps it
	 * translated and keeps login limiters counting.
	 *
	 * @param null|\WP_User|\WP_Error $user The result so far from the authenticate filter.
	 * @return null|\WP_User|\WP_Error The result, with any revealing error made vague.
	 */
	public static function genericise( $user ) {
		if ( ! is_wp_error( $user ) ) {
			return $user;
		}

		$codes = $user->get_error_codes();

		if ( empty( array_intersect( $codes, self::$revealing ) ) ) {
			return $user;
		}

		$vague = new \WP_Error();

		foreach ( $codes as $code ) {
			if ( in_array( $code, self::$revealing, true ) ) {
				continue;
			}

			foreach ( $user->get_error_messages( $code ) as $message ) {
				$vague->add( $code, $message, $user->get_error_data( $code ) );
			}
		}

		$vague->add(
			'authentication_failed',
			__( '<strong>Error:</strong> Invalid username, email address or incorrect password.', 'default' )
		);

		return $vague;
	}
}
