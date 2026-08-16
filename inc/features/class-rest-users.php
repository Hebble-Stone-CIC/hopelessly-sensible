<?php
/**
 * Keep the user list private.
 *
 * The REST users route answers anonymous requests by default, which makes it the
 * easiest way to pull every username off a site. The permission is filtered
 * rather than the route removed: removing it breaks the block editor's author
 * selector. Filtering at dispatch also covers batch and embed sub-requests. See
 * refs/gotchas.md, "REST".
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses anonymous requests for the core users route.
 */
class Rest_Users {

	/**
	 * Registers the hooks for this feature.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'pre_dispatch' ), 10, 3 );
	}

	/**
	 * Turns anonymous requests for the users route away.
	 *
	 * Anyone logged in is left alone, so core's own rules continue to apply. The
	 * refusal reuses core's error code and string, so it arrives translated.
	 *
	 * @param mixed            $result  Response to replace the requested version with.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 * @return mixed The original result, or a WP_Error.
	 */
	public static function pre_dispatch( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}

		if ( is_user_logged_in() ) {
			return $result;
		}

		if ( ! self::is_users_route( $request->get_route() ) ) {
			return $result;
		}

		return new \WP_Error(
			'rest_user_cannot_view',
			__( 'Sorry, you are not allowed to list users.', 'default' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Whether a route belongs to the core users controller.
	 *
	 * Matches the collection and a single user by ID. Deliberately not /users/me,
	 * which tells an anonymous caller nothing it does not already know, nor the
	 * application password routes, which core already refuses.
	 *
	 * @param string $route The REST route being dispatched.
	 * @return bool True when the route lists or shows users.
	 */
	private static function is_users_route( $route ) {
		$route = untrailingslashit( (string) $route );

		if ( '/wp/v2/users' === $route ) {
			return true;
		}

		return 1 === preg_match( '#^/wp/v2/users/\d+$#', $route );
	}
}
