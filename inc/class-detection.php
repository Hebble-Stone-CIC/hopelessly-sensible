<?php
/**
 * Detection: the questions this plugin asks about a site.
 *
 * Nothing here is ever stored. The questions are asked at activation to choose
 * opening states, again on the settings screen, and again wherever a blocker has
 * to be tested. Two calls a second apart may disagree, and the later one is
 * right.
 *
 * Every question goes through a core API rather than $wpdb. See
 * refs/gotchas.md, "Queries".
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible;

defined( 'ABSPATH' ) || exit;

/**
 * Looks at a site and answers questions about it, always in the present tense.
 */
class Detection {

	/**
	 * The states a fresh install starts each feature in.
	 *
	 * The only moment a switch is ever turned on for the owner. Features not named
	 * here are never switched on automatically at all.
	 *
	 * @return array<string, bool> Feature key to the state activation chose.
	 */
	public static function opening_states() {
		return array(
			'author_urls'      => ( 1 === self::authors() ),
			'disable_comments' => ( 0 === self::comments_recent() ),
		);
	}

	/**
	 * What is worth saying underneath a feature, right now, if anything.
	 *
	 * Returns a key into the feature's state_line array, or null where there is
	 * nothing to report. Blockers are decided elsewhere.
	 *
	 * @param string $key     A feature key from the registry.
	 * @param bool   $enabled Whether that feature is currently switched on.
	 * @return string|null A key into the feature's state_line array, or null.
	 */
	public static function state_variant( $key, $enabled ) {
		if ( 'author_urls' === $key && false === $enabled ) {
			return self::author_variant();
		}

		if ( 'disable_comments' === $key ) {
			return self::comment_variant( $enabled );
		}

		if ( 'block_xmlrpc' === $key && false === $enabled ) {
			return 'off_idle';
		}

		return null;
	}

	/**
	 * Which sentence belongs under author pages while they are being shown.
	 *
	 * Nobody at all is not a small number of writers. It is a site with nothing
	 * published yet, and the copy says so separately.
	 *
	 * @return string A key into the feature's state_line array.
	 */
	private static function author_variant() {
		$authors = self::authors();

		if ( 0 === $authors ) {
			return 'off_none';
		}

		return 1 === $authors ? 'off_one' : 'off_many';
	}

	/**
	 * Which sentence belongs under comments, on or off.
	 *
	 * The two states ask different questions, which is why this is not one count
	 * used twice. Off asks about the last twelve months: does anybody still use
	 * comments. On asks about all time: is anything being hidden.
	 *
	 * @param bool $enabled Whether comments are currently closed.
	 * @return string|null A key into the feature's state_line array, or null.
	 */
	private static function comment_variant( $enabled ) {
		if ( false === $enabled ) {
			$recent = self::comments_recent();

			if ( 0 === $recent ) {
				return 'off_none';
			}

			return 1 === $recent ? 'off_in_use_one' : 'off_in_use';
		}

		$hidden = self::comments_hidden();

		if ( 0 === $hidden ) {
			return self::sample_comment_exists() ? 'on_sample_only' : null;
		}

		return 1 === $hidden ? 'on_one' : 'on_many';
	}

	/**
	 * The numbers a given state line wants substituted into it.
	 *
	 * Only the query that sentence actually quotes is run.
	 *
	 * @param string $key     A feature key from the registry.
	 * @param string $variant A key into that feature's state_line array.
	 * @return array<string, int> Token name, without braces, to number.
	 */
	public static function counts( $key, $variant ) {
		if ( 'author_urls' === $key && 'off_many' === $variant ) {
			return array( 'authors' => self::authors() );
		}

		if ( 'disable_comments' === $key && 'on_many' === $variant ) {
			return array( 'comments' => self::comments_hidden() );
		}

		if ( 'disable_comments' === $key && 'off_in_use' === $variant ) {
			return array( 'comments' => self::comments_recent() );
		}

		return array();
	}

	/**
	 * Why remote publishing cannot be blocked at the moment, if it cannot.
	 *
	 * A retreat, because the damage lands on whatever is using XML-RPC rather than
	 * on anything the owner would see.
	 *
	 * @return array<string, mixed>|null A blocker, or null when nothing is stopping it.
	 */
	public static function xmlrpc_blocker() {
		if ( false === self::xmlrpc_in_use() ) {
			return null;
		}

		return array(
			'variant' => 'blocked',
			'retreat' => true,
		);
	}

	/**
	 * Why product reviews cannot be closed at the moment, if they cannot.
	 *
	 * @return array<string, mixed>|null A blocker, or null when nothing is stopping it.
	 */
	public static function woo_blocker() {
		if ( true === Registry::woocommerce_active() ) {
			return null;
		}

		return array(
			'variant' => 'blocked',
			'retreat' => true,
		);
	}

	/**
	 * Why the file editor switch cannot be moved, if it cannot.
	 *
	 * Defined true means the editor is already locked and our switch has nothing
	 * left to do, so the row reads as on and nothing is written. Defined false
	 * means wp-config beats us. Only the second is a retreat.
	 *
	 * @return array<string, mixed>|null A blocker, or null when nothing is stopping it.
	 */
	public static function file_edit_blocker() {
		if ( false === Features\File_Edit::config_defines_it() ) {
			return null;
		}

		if ( true === (bool) constant( 'DISALLOW_FILE_EDIT' ) ) {
			return array(
				'variant' => 'blocked_locked',
				'retreat' => false,
				'checked' => true,
			);
		}

		return array(
			'variant' => 'blocked_forced',
			'retreat' => true,
		);
	}

	/**
	 * Counts the distinct people who appear in bylines on published content.
	 *
	 * Deliberately not count_users(), which counts every registered account and is
	 * slow on a shop. Core turns has_published_posts into a subquery on distinct
	 * post_author, so only people who have actually written are counted.
	 *
	 * @return int The number of distinct published authors.
	 */
	public static function authors() {
		$authors = get_users(
			array(
				'has_published_posts' => array( 'post', 'page' ),
				'fields'              => 'ID',
			)
		);

		return count( $authors );
	}

	/**
	 * Counts approved comments left in the past twelve months.
	 *
	 * Approved only: spam volume says nothing about intent, whereas approval is a
	 * decision somebody made. A type of 'comment' is core's own name for
	 * comment_type IN ( '', 'comment' ), which keeps reviews and order notes out.
	 *
	 * @return int The number of approved comments in the past year.
	 */
	public static function comments_recent() {
		$count = get_comments(
			array(
				'status'          => 'approve',
				'type'            => 'comment',
				'count'           => true,
				'comment__not_in' => self::sample_comment_ids(),
				'date_query'      => array(
					array(
						'column'    => 'comment_date_gmt',
						'after'     => gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ),
						'inclusive' => true,
					),
				),
			)
		);

		return (int) $count;
	}

	/**
	 * The sample comment WordPress installs with, if it is still there.
	 *
	 * Core writes one into every new site, already approved, so counting it would
	 * make every new site on earth read as a site with comments in use. It is
	 * packaging, not content.
	 *
	 * Matched on the email address, which core fixes and never translates, unlike
	 * the author name. A network can override it, and a site where it has been
	 * overridden counts the comment as ordinary, which takes nothing away.
	 *
	 * @return array<int, int> The ids of any sample comments, for excluding.
	 */
	private static function sample_comment_ids() {
		$ids = get_comments(
			array(
				'status'       => 'approve',
				'type'         => 'comment',
				'author_email' => 'wapuu@wordpress.example',
				'fields'       => 'ids',
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Whether the site still carries the sample comment.
	 *
	 * Lets the screen name it rather than count it. "One comment is hidden" sends
	 * somebody hunting for boilerplate they never wrote.
	 *
	 * @return bool True when an approved sample comment is present.
	 */
	public static function sample_comment_exists() {
		return array() !== self::sample_comment_ids();
	}

	/**
	 * Counts every approved comment, whenever it was left.
	 *
	 * No date bound, deliberately: a comment from years ago is hidden exactly as
	 * hard as one from last week.
	 *
	 * @return int The number of approved comments of the types this plugin hides.
	 */
	public static function comments_hidden() {
		$count = get_comments(
			array(
				'status'          => 'approve',
				'type'            => 'comment',
				'count'           => true,
				'comment__not_in' => self::sample_comment_ids(),
			)
		);

		return (int) $count;
	}

	/**
	 * Whether anything on this site is using XML-RPC.
	 *
	 * Asked of WordPress rather than of a list of plugin names. Core builds the
	 * XML-RPC method table from everything hooked to xmlrpc_methods, so having
	 * hooked it is what "needs XML-RPC" means. Names fail in both directions: see
	 * refs/gotchas.md, "XML-RPC".
	 *
	 * @return bool True when something on this site extends XML-RPC.
	 */
	public static function xmlrpc_in_use() {
		if ( false !== has_filter( 'xmlrpc_methods' ) ) {
			return true;
		}

		return self::jetpack_connected();
	}

	/**
	 * Whether this site holds a live connection to WordPress.com.
	 *
	 * A safety net for ordering rather than a second opinion: the connection
	 * package hooks xmlrpc_methods from its own setup routine, which may not have
	 * run yet. Every failure answers no, and the class is addressed fully
	 * qualified so this plugin gains no dependency on code it does not ship.
	 *
	 * @return bool True when a WordPress.com connection is established.
	 */
	private static function jetpack_connected() {
		$manager = '\Automattic\Jetpack\Connection\Manager';

		if ( ! class_exists( $manager ) || ! method_exists( $manager, 'is_connected' ) ) {
			return false;
		}

		try {
			$connection = new $manager();

			return (bool) $connection->is_connected();
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
