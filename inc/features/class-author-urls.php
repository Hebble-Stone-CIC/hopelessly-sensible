<?php
/**
 * Hide author pages.
 *
 * Every writer gets a public archive at an address built from their
 * user_nicename, which on most sites is their username. There are five ways that
 * name gets out, and closing only the first is the usual mistake:
 *
 * 1. /?author=1, which core answers with a redirect
 * 2. /author/slug/, the archive itself
 * 3. /author/slug/feed/, which survives being marked Not Found
 * 4. wp-sitemap-users-1.xml, listed in the sitemap index
 * 5. author_name and author_url in oEmbed responses
 *
 * See refs/gotchas.md, "Author archives".
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Answers Not Found for author archives, and keeps writers out of the places
 * WordPress otherwise publishes them.
 */
class Author_Urls {

	/**
	 * Registers the hooks for this feature.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'block_requests' ), 0 );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'drop_users_sitemap' ), 10, 2 );
		add_filter( 'oembed_response_data', array( __CLASS__, 'strip_oembed_author' ) );
	}

	/**
	 * Answers Not Found for anything asking for an author archive.
	 *
	 * Covers vectors 1 and 2, which are the same query once the request is parsed.
	 * redirect_canonical is taken off the hook rather than outrun: left on, it
	 * leaks the nicename in a Location header. Clearing is_feed by hand covers
	 * vector 3, because set_404() restores that one flag deliberately. Nothing is
	 * unhooked permanently.
	 *
	 * @return void
	 */
	public static function block_requests() {
		if ( ! is_author() ) {
			return;
		}

		remove_action( 'template_redirect', 'redirect_canonical' );

		global $wp_query;

		$wp_query->set_404();
		$wp_query->is_feed = false;

		status_header( 404 );
		nocache_headers();
		self::send_html_type();
	}

	/**
	 * Corrects the content type left behind by the feed we have just refused.
	 *
	 * WP::send_headers() has already sent application/rss+xml by the time the feed
	 * flag is cleared, leaving a Not Found page labelled as RSS.
	 *
	 * @return void
	 */
	private static function send_html_type() {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
	}

	/**
	 * Takes the users section out of the core sitemap.
	 *
	 * Covers vector 4. Returning anything that is not a provider stops core
	 * registering it.
	 *
	 * @param \WP_Sitemaps_Provider $provider The provider instance.
	 * @param string                $name     The name of the sitemap provider.
	 * @return \WP_Sitemaps_Provider|false The provider, or false to drop it.
	 */
	public static function drop_users_sitemap( $provider, $name ) {
		if ( 'users' === $name ) {
			return false;
		}

		return $provider;
	}

	/**
	 * Removes the writer's name and archive link from oEmbed responses.
	 *
	 * Covers vector 5, which is how a name reaches any site that has merely linked
	 * to yours. Both keys are optional in the oEmbed specification, so the
	 * response stays valid.
	 *
	 * @param array<string, mixed> $data The response data.
	 * @return array<string, mixed> The response data without author details.
	 */
	public static function strip_oembed_author( $data ) {
		unset( $data['author_name'], $data['author_url'] );

		return $data;
	}
}
