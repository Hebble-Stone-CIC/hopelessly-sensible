<?php
/**
 * Block remote publishing.
 *
 * XML-RPC is the old interface that let desktop and phone apps post to a site.
 * Almost nothing uses it now, and system.multicall lets one HTTP request carry
 * hundreds of login attempts, which defeats anything counting requests rather
 * than attempts.
 *
 * The endpoint is refused outright rather than merely disabled, which is also
 * what closes the incoming pingback vector. See refs/gotchas.md, "XML-RPC".
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses XML-RPC requests and stops the site advertising the endpoint.
 */
class Xmlrpc {

	/**
	 * Registers the hooks for this feature.
	 *
	 * The refusal comes first because on an XML-RPC request nothing after it will
	 * ever run.
	 *
	 * @return void
	 */
	public static function init() {
		self::refuse_request();

		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );

		remove_action( 'wp_head', 'rsd_link' );
	}

	/**
	 * Refuses the request on plugins_loaded, before the theme is loaded.
	 *
	 * The one feature wired outside Plugin::boot(), because this one ends the
	 * request rather than shaping it, so everything between plugins_loaded and
	 * init would be spent on a request we are about to close.
	 *
	 * That earliness is why the stored flag is read straight from the option
	 * rather than through Settings. Settings reaches the registry to learn its
	 * defaults, the registry carries translatable copy, and WordPress 6.7 warns
	 * about translating before init. Reading the raw option is safe for this one
	 * question because a missing key means off, and off is what we want when we
	 * cannot tell. Nothing here asks whether the site uses XML-RPC either: that is
	 * asked once per ordinary request in Plugin::retreat(), where the answer can
	 * be trusted, so a feature still on by the time this runs is one that should
	 * still be enforcing.
	 *
	 * @return void
	 */
	public static function refuse_early() {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}

		$stored = get_option( HOPSEN_OPTION, array() );

		if ( ! is_array( $stored ) || empty( $stored['features']['block_xmlrpc'] ) ) {
			return;
		}

		self::refuse_request();
	}

	/**
	 * Ends the request if this one arrived at xmlrpc.php.
	 *
	 * Core defines XMLRPC_REQUEST before it loads WordPress, so no guessing from
	 * the request URI is needed. 403 rather than 404: the file is plainly there.
	 * The wording is core's own.
	 *
	 * @return void
	 */
	private static function refuse_request() {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}

		if ( ! headers_sent() ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
			nocache_headers();
		}

		echo esc_html( __( 'XML-RPC services are disabled on this site.', 'default' ) );

		exit;
	}

	/**
	 * Stops single posts advertising the pingback endpoint.
	 *
	 * The endpoint is already refused, so this only stops the site inviting the
	 * traffic in the first place.
	 *
	 * @param array<string, string> $headers The headers about to be sent.
	 * @return array<string, string> The headers without X-Pingback.
	 */
	public static function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );

		return $headers;
	}
}
