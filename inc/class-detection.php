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
	 * How many GeneratePress hook elements hold PHP, once counted this request.
	 *
	 * The query alone, and deliberately not the answer. What the site holds cannot
	 * change between two calls on one request, and this is the only question in
	 * this class asked on an ordinary front-end request rather than only on the
	 * settings screen, so it is the only one worth not asking twice.
	 *
	 * Whether GeneratePress is listening, and whether the site has overruled it,
	 * are asked fresh every time. Both can become true partway through a request,
	 * and remembering the first answer would make a later one unreachable. Null
	 * until the query runs, which on a site without GeneratePress is for ever.
	 *
	 * @var int|null
	 */
	private static $gp_php_elements = null;

	/**
	 * The states a fresh install starts each feature in.
	 *
	 * The only moment a switch is ever turned on for the owner. Features not named
	 * here are never switched on automatically at all.
	 *
	 * Author pages open on unless the theme was built to show them. The number
	 * of writers used to decide this, and it was the wrong question: every
	 * account has an author address whether or not it has written anything, so
	 * a site with several writers leaks exactly as much as a site with one.
	 *
	 * @return array<string, bool> Feature key to the state activation chose.
	 */
	public static function opening_states() {
		return array(
			'author_urls'      => ( false === self::theme_has_author_template() ),
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
		if ( 'author_urls' === $key ) {
			return self::author_variant( $enabled );
		}

		if ( 'disable_comments' === $key ) {
			return self::comment_variant( $enabled );
		}

		if ( 'block_xmlrpc' === $key && false === $enabled ) {
			return null === self::xmlrpc_owner_closure() ? 'off_idle' : 'off_config';
		}

		return null;
	}

	/**
	 * Which sentence belongs under author pages, on or off.
	 *
	 * Off, the line says what the pages give away, or why this theme might want
	 * them. On, the one thing worth saying is to a site with several writers,
	 * whose bylines may have been links until now. A site with nothing published
	 * gets no sentence of its own: ?author=1 redirects to the username on an
	 * empty site exactly as it does on a busy one.
	 *
	 * @param bool $enabled Whether author pages are currently hidden.
	 * @return string|null A key into the feature's state_line array, or null.
	 */
	private static function author_variant( $enabled ) {
		if ( false === $enabled ) {
			return self::theme_has_author_template() ? 'off_theme' : 'off_exposed';
		}

		return self::authors() > 1 ? 'on_many' : null;
	}

	/**
	 * Whether the active theme ships a template of its own for author pages.
	 *
	 * The one signal that a theme was built to show them. Calls to the author
	 * link functions are no signal at all: every theme that prints a byline
	 * carries one, whether or not the site shows bylines, so asking that would
	 * leave author pages open on nearly every site. Four file checks, in the
	 * child theme and its parent, and no reading of theme code.
	 *
	 * @return bool True when the child or parent theme has an author template.
	 */
	public static function theme_has_author_template() {
		$directories = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );

		foreach ( $directories as $directory ) {
			if ( file_exists( $directory . '/author.php' ) ) {
				return true;
			}

			if ( file_exists( $directory . '/templates/author.html' ) ) {
				return true;
			}
		}

		return false;
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
		if ( 'author_urls' === $key && 'on_many' === $variant ) {
			return array( 'authors' => self::authors() );
		}

		if ( 'block_xmlrpc' === $key && 'off_config' === $variant ) {
			return self::closure_tokens( self::xmlrpc_owner_closure() );
		}

		if ( 'block_xmlrpc' === $key && 'blocked_closure' === $variant ) {
			return self::closure_tokens( self::xmlrpc_foreign_closure() );
		}

		if ( 'disable_comments' === $key && 'on_many' === $variant ) {
			return array( 'comments' => self::comments_hidden() );
		}

		if ( 'disable_comments' === $key && 'off_in_use' === $variant ) {
			return array( 'comments' => self::comments_recent() );
		}

		if ( 'disallow_file_edit' === $key && 'blocked_gp_many' === $variant ) {
			return array( 'elements' => self::gp_php_elements() );
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
			'variant' => null === self::xmlrpc_foreign_closure() ? 'blocked' : 'blocked_closure',
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
	 * means wp-config beats us, and also that GeneratePress is unaffected, since
	 * it tests the constant for true rather than for existence. Either way
	 * wp-config has settled the question and is answered first.
	 *
	 * @return array<string, mixed>|null A blocker, or null when nothing is stopping it.
	 */
	public static function file_edit_blocker() {
		if ( false === Features\File_Edit::config_defines_it() ) {
			return self::gp_blocker();
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
	 * Why locking the file editor would cost this site something, if it would.
	 *
	 * A retreat, for the same reason blocking remote publishing is one: the damage
	 * lands somewhere the owner would not think to look. GeneratePress reads a
	 * locked file editor as an instruction to stop running PHP in its elements,
	 * and what it does instead is print the element's source into the page rather
	 * than say anything. See refs/gotchas.md, "GeneratePress".
	 *
	 * The row reads as off because it is off: a blocked feature never starts, so
	 * the editor stays unlocked until this clears.
	 *
	 * @return array<string, mixed>|null A blocker, or null when nothing is stopping it.
	 */
	private static function gp_blocker() {
		$elements = self::gp_php_elements();

		if ( 0 === $elements ) {
			return null;
		}

		return array(
			'variant' => 1 === $elements ? 'blocked_gp_one' : 'blocked_gp_many',
			'retreat' => true,
			'checked' => false,
		);
	}

	/**
	 * Counts the published GeneratePress elements that run PHP.
	 *
	 * Two guards stand in front of the query, and neither is a plugin name.
	 *
	 * The helper class is required from gp-premium.php at file scope, so its
	 * absence means either that GeneratePress Premium is not installed or that its
	 * Elements module is switched off, and both mean there is nothing to break.
	 *
	 * The filter is GeneratePress's own escape hatch. A site that has hooked it has
	 * taken the decision away from DISALLOW_FILE_EDIT, so our switch no longer
	 * changes the answer and there is nothing here to report. Asked of WordPress
	 * rather than of a list of names, in the same way remote publishing is.
	 *
	 * Only hook elements are counted. The other three element types hold no PHP,
	 * and a draft runs nowhere.
	 *
	 * @return int The number of published hook elements set to execute PHP.
	 */
	public static function gp_php_elements() {
		if ( ! class_exists( 'GeneratePress_Elements_Helper' ) ) {
			return 0;
		}

		/*
		 * Asked on every call rather than once, and the file editor blocker is
		 * called twice: at init, while features start, and again at wp_loaded,
		 * where the retreat is decided. A theme or a snippet that hooks this
		 * after init would be invisible to the first and plain to the second, and
		 * a remembered no would hide it from both. The later answer is the one
		 * that decides whether a switch moves, which is the same reason the
		 * XML-RPC question is settled at wp_loaded and not before.
		 */
		if ( false !== has_filter( 'generate_hooks_execute_php' ) ) {
			return 0;
		}

		if ( null !== self::$gp_php_elements ) {
			return self::$gp_php_elements;
		}

		$elements = get_posts(
			array(
				'post_type'        => 'gp_elements',
				'post_status'      => 'publish',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- GeneratePress loads its own elements with the same limit, so a site past it is already past it.
				'numberposts'      => 500,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Two indexed keys on a post type that holds tens of rows, and the only way to ask this question.
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'   => '_generate_element_type',
						'value' => 'hook',
					),
					array(
						'key'   => '_generate_hook_execute_php',
						'value' => 'true',
					),
				),
			)
		);

		self::$gp_php_elements = count( $elements );

		return self::$gp_php_elements;
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
	 * Callbacks WordPress hooks to xmlrpc_methods itself.
	 *
	 * Core registers these from default-filters.php on every site, so their
	 * presence says nothing about whether anything here wants XML-RPC. Both
	 * entries only ever remove a method from the table, never add one, which is
	 * why ignoring them cannot hide a site that needs the endpoint.
	 *
	 * Naming them is not the plugin-name matching this class refuses elsewhere.
	 * A plugin name is a guess about what some third party might do. These are
	 * core's own registrations, read at the source and understood, and the list
	 * is exhaustive for the versions named in refs/gotchas.md, "XML-RPC".
	 *
	 * @var string[]
	 */
	private static $core_xmlrpc_callbacks = array(
		'wp_maybe_disable_xmlrpc_pingback_for_environment',
	);

	/**
	 * Whether anything on this site is using XML-RPC.
	 *
	 * Asked of WordPress rather than of a list of plugin names. Core builds the
	 * XML-RPC method table from everything hooked to xmlrpc_methods, so having
	 * hooked it is what "needs XML-RPC" means, once core's own hooks are set
	 * aside. Names fail in both directions: see refs/gotchas.md, "XML-RPC".
	 *
	 * WordPress 7.1 began hooking the filter itself, so "anything at all is
	 * hooked" stopped being the question on that day and has_filter() alone now
	 * answers yes on every site alive. What is asked instead is whether anything
	 * other than core is hooked.
	 *
	 * @return bool True when something on this site extends XML-RPC.
	 */
	public static function xmlrpc_in_use() {
		if ( null !== self::xmlrpc_foreign_callback() ) {
			return true;
		}

		return self::jetpack_connected();
	}

	/**
	 * The first callback on xmlrpc_methods that belongs to neither core nor the
	 * owner, if there is one.
	 *
	 * @return array<string, mixed>|null The callback's origin, or null when every
	 *                                   callback is accounted for.
	 */
	private static function xmlrpc_foreign_callback() {
		foreach ( self::unknown_callbacks( 'xmlrpc_methods', self::$core_xmlrpc_callbacks ) as $origin ) {
			if ( false === $origin['owner'] ) {
				return $origin;
			}
		}

		return null;
	}

	/**
	 * The first foreign callback on xmlrpc_methods whose file is known.
	 *
	 * Only a closure carries a file, so this is the closure the blocker line can
	 * point at. A named foreign function answers null and the line stays generic.
	 *
	 * @return array<string, mixed>|null The closure's origin, or null.
	 */
	private static function xmlrpc_foreign_closure() {
		foreach ( self::unknown_callbacks( 'xmlrpc_methods', self::$core_xmlrpc_callbacks ) as $origin ) {
			if ( false === $origin['owner'] && '' !== $origin['file'] ) {
				return $origin;
			}
		}

		return null;
	}

	/**
	 * The first closure on xmlrpc_methods written in wp-config.php, if any.
	 *
	 * @return array<string, mixed>|null The closure's origin, or null.
	 */
	private static function xmlrpc_owner_closure() {
		foreach ( self::unknown_callbacks( 'xmlrpc_methods', self::$core_xmlrpc_callbacks ) as $origin ) {
			if ( true === $origin['owner'] ) {
				return $origin;
			}
		}

		return null;
	}

	/**
	 * The placeholders a sentence about a closure wants filled.
	 *
	 * The line is passed as a string so that it is printed as written: a line
	 * number is a label rather than a quantity, and 1,234 is not one.
	 *
	 * @param array<string, mixed>|null $origin A callback origin, or null.
	 * @return array<string, string> Token name to value.
	 */
	private static function closure_tokens( $origin ) {
		if ( null === $origin ) {
			return array();
		}

		return array(
			'file' => $origin['file'],
			'line' => (string) $origin['line'],
		);
	}

	/**
	 * Every callback on a hook that is not one of core's own, with where it came
	 * from.
	 *
	 * Walks the registered callbacks rather than asking has_filter(), which
	 * cannot tell core's registrations from anybody else's.
	 *
	 * A named function is matched on its name and never on where it is defined.
	 * Reflection would report the file a function was written in rather than who
	 * hooked it, so a site whose own functions.php hooks a core helper, say
	 * __return_empty_array, would be read as core and ignored. That site has made
	 * a decision about XML-RPC and we would be overruling it silently.
	 *
	 * A closure is the one callback where the two questions have the same
	 * answer: it is written at the add_filter() call, so the file it lives in is
	 * the file that hooked it. Reflection is sound there and is the only signal
	 * there is, since a closure has no name to match. One written in
	 * wp-config.php is the owner's own, and no plugin's claim on XML-RPC: see
	 * refs/gotchas.md, "XML-RPC". A closure from anywhere else, an object method,
	 * or anything unrecognised counts as somebody using the hook, which is the
	 * direction that costs a switch rather than a site.
	 *
	 * @param string   $hook  The hook to inspect.
	 * @param string[] $known Callback names that belong to core.
	 * @return array<int, array<string, mixed>> One origin per unrecognised
	 *                                          callback: 'owner', whether it was
	 *                                          written in wp-config.php; 'file',
	 *                                          its path relative to the site root
	 *                                          or empty where unknown; 'line'.
	 */
	private static function unknown_callbacks( $hook, array $known ) {
		if ( false === has_filter( $hook ) ) {
			return array();
		}

		if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
			return array();
		}

		$registered = $GLOBALS['wp_filter'][ $hook ];
		$unknown    = array(
			'owner' => false,
			'file'  => '',
			'line'  => 0,
		);

		if ( ! isset( $registered->callbacks ) || ! is_array( $registered->callbacks ) ) {
			return array( $unknown );
		}

		$origins = array();

		foreach ( $registered->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( ! isset( $callback['function'] ) ) {
					$origins[] = $unknown;
					continue;
				}

				$function = $callback['function'];

				if ( is_string( $function ) && in_array( $function, $known, true ) ) {
					continue;
				}

				if ( ! $function instanceof \Closure ) {
					$origins[] = $unknown;
					continue;
				}

				$origins[] = self::closure_origin( $function );
			}
		}

		return $origins;
	}

	/**
	 * Where a closure was written, and whether that is the owner's own file.
	 *
	 * @param \Closure $closure The closure to look at.
	 * @return array<string, mixed> Its origin, with the file empty where
	 *                              reflection could not say.
	 */
	private static function closure_origin( \Closure $closure ) {
		$origin = array(
			'owner' => false,
			'file'  => '',
			'line'  => 0,
		);

		try {
			$reflection = new \ReflectionFunction( $closure );
		} catch ( \ReflectionException $e ) {
			return $origin;
		}

		$file = $reflection->getFileName();

		if ( ! is_string( $file ) || '' === $file ) {
			return $origin;
		}

		$real = realpath( $file );
		$real = false === $real ? $file : $real;

		$origin['owner'] = in_array( $real, self::owner_files(), true );
		$origin['file']  = self::site_relative( $real );
		$origin['line']  = (int) $reflection->getStartLine();

		return $origin;
	}

	/**
	 * A path as the owner would recognise it, relative to the site root where
	 * it is under it.
	 *
	 * @param string $path An absolute path, already resolved.
	 * @return string The path relative to ABSPATH, or unchanged if it is elsewhere.
	 */
	private static function site_relative( $path ) {
		$roots = array_unique( array( realpath( ABSPATH ), ABSPATH ) );

		foreach ( $roots as $root ) {
			if ( ! is_string( $root ) || '' === $root ) {
				continue;
			}

			$root = rtrim( $root, '/' ) . '/';

			if ( 0 === strpos( $path, $root ) ) {
				return substr( $path, strlen( $root ) );
			}
		}

		return $path;
	}

	/**
	 * The files that are the owner's own rather than any plugin's: wp-config.php,
	 * in either of the two places core looks for it. Resolved once a request.
	 *
	 * The tests point this somewhere else by reflection, because no test should
	 * be writing a closure into a real wp-config.php.
	 *
	 * @var string[]|null
	 */
	private static $owner_files = null;

	/**
	 * The resolved paths of the owner's own files.
	 *
	 * @return string[] Real paths, only of files that exist.
	 */
	private static function owner_files() {
		if ( null !== self::$owner_files ) {
			return self::$owner_files;
		}

		$candidates = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
		);

		self::$owner_files = array();

		foreach ( $candidates as $candidate ) {
			$real = realpath( $candidate );

			if ( false !== $real ) {
				self::$owner_files[] = $real;
			}
		}

		return self::$owner_files;
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
