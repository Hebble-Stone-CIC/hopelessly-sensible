<?php
/**
 * Keeping the user list private, without breaking the block editor.
 *
 * The permission is filtered rather than the route removed, so what these
 * tests are really guarding is the difference: an anonymous caller is refused,
 * and everyone else is left exactly as core left them.
 *
 * @package HopelesslySensible
 */

use HopelesslySensible\Features\Rest_Users;

/**
 * Who gets the user list and who does not.
 */
class Test_Rest_Users extends WP_UnitTestCase {

	/**
	 * Puts a request through the filter the feature registers.
	 *
	 * @param string $route  The REST route being asked for.
	 * @param mixed  $result The result so far, usually null.
	 * @return mixed What the filter made of it.
	 */
	private function dispatch( $route, $result = null ) {
		Rest_Users::init();

		$request = new WP_REST_Request( 'GET', $route );

		return apply_filters( 'rest_pre_dispatch', $result, rest_get_server(), $request );
	}

	/**
	 * An anonymous request for the user list is refused.
	 *
	 * @return void
	 */
	public function test_anonymous_requests_are_refused() {
		$result = $this->dispatch( '/wp/v2/users' );

		$this->assertWPError( $result );
		$this->assertSame( 'rest_user_cannot_view', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * A single user by id is refused too.
	 *
	 * @return void
	 */
	public function test_a_single_user_is_refused() {
		$this->assertWPError( $this->dispatch( '/wp/v2/users/1' ) );
	}

	/**
	 * A trailing slash does not get past it.
	 *
	 * @return void
	 */
	public function test_a_trailing_slash_does_not_help() {
		$this->assertWPError( $this->dispatch( '/wp/v2/users/' ) );
	}

	/**
	 * Anyone logged in is left entirely alone, so core's own rules about who
	 * may see what continue to apply unchanged.
	 *
	 * @return void
	 */
	public function test_logged_in_requests_are_untouched() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertNull( $this->dispatch( '/wp/v2/users' ) );
	}

	/**
	 * Every other route is untouched.
	 *
	 * @return void
	 */
	public function test_other_routes_are_untouched() {
		$this->assertNull( $this->dispatch( '/wp/v2/posts' ) );
		$this->assertNull( $this->dispatch( '/wp/v2/categories' ) );
	}

	/**
	 * The route describing the caller to themselves is deliberately left open.
	 *
	 * It tells an anonymous caller nothing it does not already know, and core
	 * refuses it anyway.
	 *
	 * @return void
	 */
	public function test_the_me_route_is_left_to_core() {
		$this->assertNull( $this->dispatch( '/wp/v2/users/me' ) );
	}

	/**
	 * A result somebody else has already produced is handed straight back.
	 *
	 * @return void
	 */
	public function test_an_existing_result_is_not_replaced() {
		$response = new WP_REST_Response( array( 'handled' => true ) );

		$this->assertSame( $response, $this->dispatch( '/wp/v2/users', $response ) );
	}
}
