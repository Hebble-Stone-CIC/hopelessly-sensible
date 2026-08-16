<?php
/**
 * Close comments everywhere, and close WooCommerce product reviews.
 *
 * Two features share this file because they act on the same thing. A product
 * review is a comment with comment_type of 'review', and a WooCommerce order
 * note is a comment with comment_type of 'order_note', so anything here that
 * touched comments indiscriminately would take a shop's star ratings and its
 * audit trail with it. Every decision is therefore made per comment type:
 *
 * - ''  and 'comment'  ordinary comments, closed by disable_comments
 * - 'review'           product reviews, closed only by disable_woo_reviews
 * - anything else      never touched, which is what protects order notes
 *
 * Nothing is deleted. Comments are hidden and the forms are closed, so switching
 * either feature back on brings everything straight back. See refs/gotchas.md,
 * "Comments".
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Features;

use HopelesslySensible\Registry;
use HopelesslySensible\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Closes and hides comments, reviews, or both, according to which of the two
 * features the site owner has switched on.
 */
class Comments {

	/**
	 * The comment types this plugin considers an ordinary comment.
	 *
	 * The same pair core means by a type of 'comment'.
	 *
	 * @var string[]
	 */
	private static $ordinary = array( '', 'comment' );

	/**
	 * Whether the hooks shared by both features have been registered.
	 *
	 * @var bool
	 */
	private static $shared_hooked = false;

	/**
	 * Registers the hooks for closing ordinary comments.
	 *
	 * @return void
	 */
	public static function init() {
		self::register_shared_hooks();

		add_action( 'init', array( __CLASS__, 'drop_post_type_support' ), 100 );
		add_action( 'admin_menu', array( __CLASS__, 'remove_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_comment_screens' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'remove_dashboard_widget' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'remove_admin_bar_node' ), 999 );

		add_filter( 'feed_links_show_comments_feed', '__return_false' );
	}

	/**
	 * Registers the hooks for closing WooCommerce product reviews.
	 *
	 * The two option filters are how WooCommerce itself asks whether reviews are
	 * on. Filtering the read rather than writing the option means the owner's
	 * stored WooCommerce settings are never altered.
	 *
	 * @return void
	 */
	public static function init_woo_reviews() {
		self::register_shared_hooks();

		add_filter( 'option_woocommerce_enable_reviews', array( __CLASS__, 'force_no' ) );
		add_filter( 'option_woocommerce_enable_review_rating', array( __CLASS__, 'force_no' ) );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'remove_reviews_tab' ), 98 );

		/*
		 * The stars, everywhere they are drawn. wc_get_rating_html() reads the
		 * stored average and asks nothing, and themes call it directly. This is the
		 * one filter every caller passes through.
		 */
		add_filter( 'woocommerce_product_get_rating_html', '__return_empty_string' );
	}

	/**
	 * Registers the hooks both features need.
	 *
	 * Each callback decides for itself which comment types it acts on, so
	 * registering them twice would be harmless. The flag makes that intentional
	 * rather than lucky.
	 *
	 * @return void
	 */
	private static function register_shared_hooks() {
		if ( true === self::$shared_hooked ) {
			return;
		}

		self::$shared_hooked = true;

		add_filter( 'comments_open', array( __CLASS__, 'close_form' ), 20, 2 );
		add_filter( 'pings_open', array( __CLASS__, 'close_form' ), 20, 2 );
		add_filter( 'comments_array', array( __CLASS__, 'hide_from_visitors' ), 20, 2 );
		add_filter( 'the_comments', array( __CLASS__, 'hide_from_queries' ), 20 );
		add_filter( 'get_comments_number', array( __CLASS__, 'hide_count' ), 20, 2 );

		add_action( 'template_redirect', array( __CLASS__, 'block_comment_feeds' ), 0 );
	}

	/**
	 * Closes the comment or review form for a post.
	 *
	 * @param bool $open    Whether the post is currently open.
	 * @param int  $post_id The post being asked about.
	 * @return bool False when this post's discussion is being closed.
	 */
	public static function close_form( $open, $post_id ) {
		if ( true === self::closing( $post_id ) ) {
			return false;
		}

		return $open;
	}

	/**
	 * Removes hidden comments from the set the theme is about to display.
	 *
	 * Filtered one comment at a time, by type, rather than emptied wholesale, so a
	 * product keeping its reviews keeps them.
	 *
	 * @param array<int, \WP_Comment> $comments The comments for this post.
	 * @param int                     $post_id  The post being displayed.
	 * @return array<int, \WP_Comment> The comments the visitor may see.
	 */
	public static function hide_from_visitors( $comments, $post_id ) {
		unset( $post_id );

		return self::without_hidden( $comments );
	}

	/**
	 * Removes hidden comments from any comment query.
	 *
	 * The comments_array filter is the comments template alone. Widgets, blocks
	 * and anything calling get_comments() go through WP_Comment_Query instead,
	 * which never touches it.
	 *
	 * The dashboard is deliberately not exempt: there is no comment equivalent of
	 * dashboard_recent_posts_query_args, so the query is the only lever that
	 * empties the Activity panel without removing the panel. A query asking for
	 * something other than whole comments is handed back untouched.
	 *
	 * @param array<int, \WP_Comment>|array<int, mixed> $comments The comments found.
	 * @return array<int, \WP_Comment>|array<int, mixed> The comments a visitor may see.
	 */
	public static function hide_from_queries( $comments ) {
		$first = is_array( $comments ) ? reset( $comments ) : false;

		if ( ! is_object( $first ) || ! isset( $first->comment_type ) ) {
			return $comments;
		}

		return self::without_hidden( $comments );
	}

	/**
	 * Drops every comment that is being hidden, keeping the rest in order.
	 *
	 * @param array<int, \WP_Comment> $comments The comments to consider.
	 * @return array<int, \WP_Comment> The comments that stay.
	 */
	private static function without_hidden( $comments ) {
		$visible = array();

		foreach ( (array) $comments as $comment ) {
			if ( true === self::hidden( $comment ) ) {
				continue;
			}

			$visible[] = $comment;
		}

		return $visible;
	}

	/**
	 * Reports no comments for a post whose comments are hidden.
	 *
	 * Left alone otherwise, so a product keeping its reviews keeps its count and
	 * the star rating that reads from it.
	 *
	 * @param string|int $count   The number of comments.
	 * @param int        $post_id The post being asked about.
	 * @return string|int Zero when this post's discussion is hidden.
	 */
	public static function hide_count( $count, $post_id ) {
		if ( true === self::closing( $post_id ) ) {
			return 0;
		}

		return $count;
	}

	/**
	 * Answers Not Found for comment feeds that would show hidden comments.
	 *
	 * Comment feeds go through a separate set of query clauses and never reach the
	 * comments_array filter, so hiding comments from the theme leaves the feed
	 * serving every one of them.
	 *
	 * @return void
	 */
	public static function block_comment_feeds() {
		if ( ! is_comment_feed() ) {
			return;
		}

		if ( false === self::feed_is_closed() ) {
			return;
		}

		/*
		 * Core's redirect_canonical runs on this hook at ten, sees a Not Found, and
		 * guesses a destination. On a post's comment feed it also issues a 301 to
		 * /hello-world/feed/feed/, so it has to come off the hook rather than be
		 * outrun.
		 */
		remove_action( 'template_redirect', 'redirect_canonical' );

		global $wp_query;

		$wp_query->set_404();
		$wp_query->is_feed         = false;
		$wp_query->is_comment_feed = false;

		status_header( 404 );
		nocache_headers();

		/*
		 * The feed content type has already gone out, sent by WP::send_headers()
		 * while this was still a feed request.
		 */
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		}
	}

	/**
	 * Takes comment and trackback support off every post type.
	 *
	 * Late on init, so post types registered at the usual priority are there to be
	 * changed. Products are always skipped while WooCommerce is active: reviews
	 * are closed through WooCommerce's own options instead, which leaves its
	 * screens working.
	 *
	 * @return void
	 */
	public static function drop_post_type_support() {
		foreach ( get_post_types() as $post_type ) {
			if ( true === self::is_product( $post_type ) ) {
				continue;
			}

			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}

	/**
	 * Removes the Comments item from the dashboard menu.
	 *
	 * WooCommerce puts product reviews on its own screen under Products, so a shop
	 * keeping its reviews can still moderate them.
	 *
	 * @return void
	 */
	public static function remove_admin_menu() {
		remove_menu_page( 'edit-comments.php' );
	}

	/**
	 * Sends anyone who reaches a comment screen back to the dashboard.
	 *
	 * Removing a menu item does not remove the screen behind it, and comment.php
	 * is the one most people forget: it is the single comment edit and reply
	 * screen, reachable from any moderation link.
	 *
	 * @return void
	 */
	public static function redirect_comment_screens() {
		if ( false === self::comment_screen_is_closed() ) {
			return;
		}

		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * Whether the screen being loaded is one this feature takes away.
	 *
	 * Separate from the redirect above so that it can be tested past the exit.
	 * AJAX is let through because admin-ajax.php is not a screen, and handlers run
	 * with $pagenow set to whatever the caller was looking at.
	 *
	 * @return bool True when this request should be sent back to the dashboard.
	 */
	public static function comment_screen_is_closed() {
		if ( wp_doing_ajax() ) {
			return false;
		}

		global $pagenow;

		if ( 'edit-comments.php' !== $pagenow && 'comment.php' !== $pagenow ) {
			return false;
		}

		return false === self::reviews_moderated_here();
	}

	/**
	 * Whether this site moderates its product reviews on the comments screen.
	 *
	 * WooCommerce moved reviews under Products in 6.7. Below that, redirecting the
	 * comments screen would take review moderation away from a shop that never
	 * asked for it. The version is an argument because WC_VERSION is a constant
	 * and a process gets one value, so the boundary could not otherwise be tested.
	 * A version that cannot be read is treated as old, which takes nothing away.
	 *
	 * @param string|null $version The WooCommerce version, or null to read it.
	 * @return bool True on a shop old enough to keep reviews there.
	 */
	public static function reviews_moderated_here( $version = null ) {
		if ( false === Registry::woocommerce_active() ) {
			return false;
		}

		if ( null === $version ) {
			$version = defined( 'WC_VERSION' ) ? constant( 'WC_VERSION' ) : '';
		}

		if ( '' === $version ) {
			return true;
		}

		return version_compare( $version, '6.7', '<' );
	}

	/**
	 * Removes the recent comments widget from the dashboard.
	 *
	 * @return void
	 */
	public static function remove_dashboard_widget() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * Removes the comment bubble from the admin bar.
	 *
	 * @param \WP_Admin_Bar $admin_bar The admin bar instance.
	 * @return void
	 */
	public static function remove_admin_bar_node( $admin_bar ) {
		$admin_bar->remove_node( 'comments' );
	}

	/**
	 * Answers no to a WooCommerce option asking whether reviews are on.
	 *
	 * @param mixed $value The stored option value, which is not consulted.
	 * @return string Always 'no'.
	 */
	public static function force_no( $value ) {
		unset( $value );

		return 'no';
	}

	/**
	 * Removes the Reviews tab from the product page.
	 *
	 * @param array<string, mixed> $tabs The product tabs.
	 * @return array<string, mixed> The tabs without Reviews.
	 */
	public static function remove_reviews_tab( $tabs ) {
		unset( $tabs['reviews'] );

		return $tabs;
	}

	/**
	 * Whether the comment feed being asked for is one we are closing.
	 *
	 * A feed hanging off a single post has that post as its queried object. The
	 * site-wide feed has none, and asking closing() about post zero is worse than
	 * useless, because get_post_type( 0 ) answers with the global post. That feed
	 * mixes both kinds of comment into clauses this plugin cannot filter, so it is
	 * refused whenever either feature is on.
	 *
	 * @return bool True when this feed is being refused.
	 */
	private static function feed_is_closed() {
		$post_id = get_queried_object_id();

		if ( 0 !== $post_id ) {
			return self::closing( $post_id );
		}

		if ( true === Settings::is_enabled( 'disable_comments' ) ) {
			return true;
		}

		return Settings::is_enabled( 'disable_woo_reviews' );
	}

	/**
	 * Whether this post's discussion is being closed and hidden.
	 *
	 * A product asks the reviews feature; everything else asks the comments
	 * feature. The single place that decision is made.
	 *
	 * @param int $post_id The post being asked about.
	 * @return bool True when this post's discussion is closed.
	 */
	private static function closing( $post_id ) {
		if ( true === self::is_product( get_post_type( $post_id ) ) ) {
			return Settings::is_enabled( 'disable_woo_reviews' );
		}

		return Settings::is_enabled( 'disable_comments' );
	}

	/**
	 * Whether a single comment is being hidden from visitors.
	 *
	 * The order of these three questions is the rule this plugin is built on, and
	 * rearranging them breaks shops.
	 *
	 * The type is asked first, and it is what protects an order note: a type this
	 * plugin does not claim reaches the middle return and is left alone, whatever
	 * it is attached to.
	 *
	 * Only then does placement come into it, and only for the two ordinary types.
	 * A shop can hold reviews stored as ordinary comments, because WooCommerce
	 * sets the review type from $_POST and therefore only names the ones its own
	 * form created. An importer or a migration leaves rows that are reviews in
	 * everything but the column, and deciding those by type alone would hide them
	 * when comments are closed, which is what that feature's warning promises will
	 * not happen. So an ordinary comment on a product asks the reviews feature.
	 *
	 * The post is taken from the comment rather than from whatever page is being
	 * rendered, because a widget lists comments from all over the site. A comment
	 * with no post is treated as ordinary, since get_post_type( 0 ) answers with
	 * the global post rather than with nothing.
	 *
	 * @param \WP_Comment $comment The comment being considered.
	 * @return bool True when this comment is hidden.
	 */
	private static function hidden( $comment ) {
		$type = isset( $comment->comment_type ) ? (string) $comment->comment_type : '';

		if ( 'review' === $type ) {
			return Settings::is_enabled( 'disable_woo_reviews' );
		}

		if ( ! in_array( $type, self::$ordinary, true ) ) {
			return false;
		}

		$post_id = isset( $comment->comment_post_ID ) ? (int) $comment->comment_post_ID : 0;

		if ( 0 !== $post_id && true === self::is_product( get_post_type( $post_id ) ) ) {
			return Settings::is_enabled( 'disable_woo_reviews' );
		}

		return Settings::is_enabled( 'disable_comments' );
	}

	/**
	 * Whether a post type is the WooCommerce product type.
	 *
	 * Guarded on WooCommerce being active, so a post type coincidentally named
	 * product on a site without a shop is treated as ordinary content.
	 *
	 * @param string $post_type The post type name.
	 * @return bool True when this is a WooCommerce product.
	 */
	private static function is_product( $post_type ) {
		if ( false === Registry::woocommerce_active() ) {
			return false;
		}

		return 'product' === $post_type;
	}
}
