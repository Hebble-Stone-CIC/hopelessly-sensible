<?php
/**
 * A shop keeps its reviews and its order notes, whatever else is closed.
 *
 * The rule under test: only comments whose comment_type is '' or 'comment' are
 * ever touched.
 *
 * The most valuable file in this suite. Everything else here protects a
 * promise; these protect a shop's ratings and an order's audit trail, which are
 * the two things this plugin could destroy if it ever decided by post instead
 * of by comment.
 *
 * @package HopelesslySensible
 */

use HopelesslySensible\Features\Comments;
use HopelesslySensible\Settings;

/**
 * Closing comments, closing reviews, and everything that must survive both.
 */
class Test_Comments extends WP_UnitTestCase {

	/**
	 * A published post, for ordinary comments.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * A published product, for reviews.
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Whatever $pagenow was before a test moved it.
	 *
	 * @var string|null
	 */
	private $pagenow;

	/**
	 * Registers the product post type and starts each test from a known state.
	 *
	 * The shared-hooks flag is reset by hand because it is static and survives
	 * the test case, while the hooks it remembers adding do not: the core test
	 * case rolls those back after every test. Left alone, the first test would
	 * register the hooks and every test after it would run against none.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		register_post_type(
			'product',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor', 'comments' ),
			)
		);

		$flag = new ReflectionProperty( Comments::class, 'shared_hooked' );
		$flag->setAccessible( true );
		$flag->setValue( null, false );

		$this->pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : null;

		$this->post_id    = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		$this->product_id = self::factory()->post->create(
			array(
				'post_title' => 'A product',
				'post_type'  => 'product',
			)
		);
	}

	/**
	 * Puts $pagenow back.
	 *
	 * The core test case rolls back the database and the hooks, and leaves plain
	 * globals exactly where a test left them. A stray 'edit-comments.php' would
	 * follow the suite into every file that runs after this one.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( null === $this->pagenow ) {
			unset( $GLOBALS['pagenow'] );
		} else {
			$GLOBALS['pagenow'] = $this->pagenow;
		}

		parent::tear_down();
	}

	/**
	 * Switches the named features on and everything else off, then boots them.
	 *
	 * @param string[] $enabled Feature keys to switch on.
	 * @return void
	 */
	private function features( array $enabled ) {
		$features = array();

		foreach ( array_keys( \HopelesslySensible\Registry::all() ) as $key ) {
			$features[ $key ] = in_array( $key, $enabled, true );
		}

		update_option(
			HOPSEN_OPTION,
			array(
				'schema'   => HOPSEN_SCHEMA,
				'features' => $features,
				'live'     => array(),
			)
		);

		Settings::flush();

		if ( true === in_array( 'disable_comments', $enabled, true ) ) {
			Comments::init();
		}

		if ( true === in_array( 'disable_woo_reviews', $enabled, true ) ) {
			Comments::init_woo_reviews();
		}
	}

	/**
	 * Returns the comment contents the theme would show for a post.
	 *
	 * @param int $post_id The post being displayed.
	 * @return string[] The contents of the comments a visitor would see.
	 */
	private function displayed( $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
			)
		);

		$shown = apply_filters( 'comments_array', $comments, $post_id );

		return wp_list_pluck( $shown, 'comment_content' );
	}

	/**
	 * A product review survives closing comments everywhere.
	 *
	 * @return void
	 */
	public function test_review_survives_closing_comments() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->product_id,
				'comment_type'    => 'review',
				'comment_content' => 'A genuine review',
			)
		);

		$this->features( array( 'disable_comments' ) );

		$this->assertSame( array( 'A genuine review' ), $this->displayed( $this->product_id ) );
	}

	/**
	 * A review stored as an ordinary comment survives too.
	 *
	 * WooCommerce names a review only when its own front-end form created it, so
	 * an imported or migrated review is an ordinary comment sitting on a
	 * product. Deciding by type alone would hide it, which is exactly what the
	 * warning on this feature promises will not happen.
	 *
	 * @return void
	 */
	public function test_imported_review_survives_closing_comments() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->product_id,
				'comment_type'    => 'comment',
				'comment_content' => 'An imported review',
			)
		);

		$this->features( array( 'disable_comments' ) );

		$this->assertSame( array( 'An imported review' ), $this->displayed( $this->product_id ) );
	}

	/**
	 * An order note is never touched, by either feature.
	 *
	 * @return void
	 */
	public function test_order_note_survives_everything() {
		$order_id = self::factory()->post->create( array( 'post_type' => 'product' ) );

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $order_id,
				'comment_type'    => 'order_note',
				'comment_content' => 'Refund issued',
			)
		);

		$this->features( array( 'disable_comments', 'disable_woo_reviews' ) );

		$this->assertSame( array( 'Refund issued' ), $this->displayed( $order_id ) );
	}

	/**
	 * An ordinary comment on an ordinary post is hidden.
	 *
	 * The other half of that rule: the feature still has to do its job.
	 *
	 * @return void
	 */
	public function test_ordinary_comment_is_hidden() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'Ordinary chatter',
			)
		);

		$this->features( array( 'disable_comments' ) );

		$this->assertSame( array(), $this->displayed( $this->post_id ) );
	}

	/**
	 * Closing reviews hides them, and leaves ordinary comments alone.
	 *
	 * @return void
	 */
	public function test_closing_reviews_leaves_comments_alone() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->product_id,
				'comment_type'    => 'review',
				'comment_content' => 'A genuine review',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'Ordinary chatter',
			)
		);

		$this->features( array( 'disable_woo_reviews' ) );

		$this->assertSame( array(), $this->displayed( $this->product_id ) );
		$this->assertSame( array( 'Ordinary chatter' ), $this->displayed( $this->post_id ) );
	}

	/**
	 * Hidden comments do not come back through a widget or any other query.
	 *
	 * The comments template is not the only place a comment is shown, and the
	 * filter it uses reaches none of the others.
	 *
	 * @return void
	 */
	public function test_hidden_comments_do_not_reach_other_queries() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'Ordinary chatter',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->product_id,
				'comment_type'    => 'review',
				'comment_content' => 'A genuine review',
			)
		);

		$this->features( array( 'disable_comments' ) );

		$found = wp_list_pluck( get_comments( array( 'status' => 'approve' ) ), 'comment_content' );

		$this->assertSame( array( 'A genuine review' ), $found );
	}

	/**
	 * The dashboard does not see them either, and that is the point.
	 *
	 * This reverses an earlier decision. Leaving moderation reachable produced a
	 * site with comments closed to every visitor and the dashboard's Activity
	 * panel still listing them with working Approve links, so somebody who never
	 * closed comments could approve one into total invisibility. There is no
	 * filter for that panel, so the query is the lever.
	 *
	 * @return void
	 */
	public function test_the_dashboard_does_not_see_hidden_comments() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'Ordinary chatter',
			)
		);

		$this->features( array( 'disable_comments' ) );

		set_current_screen( 'edit-comments.php' );

		$found = wp_list_pluck( get_comments( array( 'status' => 'approve' ) ), 'comment_content' );

		set_current_screen( 'front' );

		$this->assertSame( array(), $found );
	}

	/**
	 * A shop's reviews stay visible in the dashboard regardless.
	 *
	 * Taking the comment screens away must not take review moderation with them.
	 * The decision is made on comment_type before anything else, which is what
	 * keeps reviews safe while the rest of this gets stricter.
	 *
	 * @return void
	 */
	public function test_the_dashboard_still_sees_reviews() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->product_id,
				'comment_type'    => 'review',
				'comment_content' => 'A genuine review',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'Ordinary chatter',
			)
		);

		$this->features( array( 'disable_comments' ) );

		set_current_screen( 'edit-comments.php' );

		$found = wp_list_pluck( get_comments( array( 'status' => 'approve' ) ), 'comment_content' );

		set_current_screen( 'front' );

		$this->assertSame( array( 'A genuine review' ), $found );
	}

	/**
	 * Counting is left alone, so detection stays truthful about what is hidden.
	 *
	 * @return void
	 */
	public function test_counting_is_not_filtered() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'Ordinary chatter',
			)
		);

		$this->features( array( 'disable_comments' ) );

		$count = get_comments(
			array(
				'status' => 'approve',
				'type'   => 'comment',
				'count'  => true,
			)
		);

		$this->assertSame( 1, (int) $count );
	}

	/**
	 * The site-wide comment feed is refused, rather than judged by whatever post
	 * happens to be first in it.
	 *
	 * @return void
	 */
	public function test_site_wide_comment_feed_is_refused() {
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->product_id,
				'comment_type'    => 'review',
				'comment_content' => 'A genuine review',
			)
		);

		$this->features( array( 'disable_comments' ) );

		/*
		 * Query variables rather than /comments/feed/, because the test suite
		 * runs on plain permalinks and the pretty address parses as nothing.
		 * This is the same request core rewrites that address into.
		 */
		$this->go_to( '/?feed=rss2&withcomments=1' );

		$this->assertTrue( is_comment_feed(), 'The test did not reach a comment feed.' );
		$this->assertSame( 0, get_queried_object_id(), 'A site-wide feed should have no queried object.' );

		Comments::block_comment_feeds();

		$this->assertTrue( is_404() );
		$this->assertFalse( is_feed() );
	}

	/**
	 * A product's own review feed is left alone while reviews are open.
	 *
	 * @return void
	 */
	public function test_product_review_feed_survives_closing_comments() {
		$this->features( array( 'disable_comments' ) );

		$this->go_to( '/?p=' . $this->product_id . '&post_type=product&feed=rss2&withcomments=1' );

		$this->assertTrue( is_comment_feed(), 'The test did not reach a comment feed.' );
		$this->assertSame( $this->product_id, get_queried_object_id() );

		Comments::block_comment_feeds();

		$this->assertFalse( is_404() );
	}

	/**
	 * Pretends the dashboard is loading a particular screen.
	 *
	 * @param string $screen The file wp-admin is serving.
	 * @return void
	 */
	private function on_screen( $screen ) {
		$GLOBALS['pagenow'] = $screen;
	}

	/**
	 * The comments list screen is taken away, not merely unlinked.
	 *
	 * Removing the menu item removes the item and nothing else: the screen still
	 * loads for anybody who types the address.
	 *
	 * @return void
	 */
	public function test_the_comments_screen_is_closed() {
		$this->on_screen( 'edit-comments.php' );

		$this->assertTrue( Comments::comment_screen_is_closed() );
	}

	/**
	 * So is the single comment screen, which is the one that gets forgotten.
	 *
	 * Every moderation link in the dashboard points at comment.php, so closing
	 * only the list would leave the whole of moderation reachable one click
	 * further in.
	 *
	 * @return void
	 */
	public function test_the_single_comment_screen_is_closed() {
		$this->on_screen( 'comment.php' );

		$this->assertTrue( Comments::comment_screen_is_closed() );
	}

	/**
	 * Nothing else in the dashboard is touched.
	 *
	 * @return void
	 */
	public function test_other_screens_are_left_alone() {
		$this->on_screen( 'index.php' );

		$this->assertFalse( Comments::comment_screen_is_closed() );

		$this->on_screen( 'edit.php' );

		$this->assertFalse( Comments::comment_screen_is_closed() );
	}

	/**
	 * An AJAX request is not a screen and is never redirected.
	 *
	 * Handlers on admin-ajax.php run with $pagenow set to whatever the caller was
	 * looking at, so one called from the comments screen would otherwise have its
	 * answer replaced with a page.
	 *
	 * @return void
	 */
	public function test_ajax_requests_are_not_redirected() {
		$this->on_screen( 'edit-comments.php' );

		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->assertFalse( Comments::comment_screen_is_closed() );
	}

	/**
	 * A shop below WooCommerce 6.7 keeps its comments screen.
	 *
	 * The comment_type rule arriving by the side door. Review moderation lived on
	 * the comments screen until 6.7 moved it to its own screen under Products, so closing that
	 * screen on an older shop takes review moderation away from somebody who
	 * never asked for it and never switched anything on.
	 *
	 * @return void
	 */
	public function test_an_old_shop_keeps_the_comments_screen() {
		$this->assertTrue( Comments::reviews_moderated_here( '6.6.2' ) );
		$this->assertTrue( Comments::reviews_moderated_here( '6.0' ) );
		$this->assertTrue( Comments::reviews_moderated_here( '3.5.1' ) );
	}

	/**
	 * From 6.7 onwards it does not, because reviews moved.
	 *
	 * @return void
	 */
	public function test_a_modern_shop_does_not_need_it() {
		$this->assertFalse( Comments::reviews_moderated_here( '6.7' ) );
		$this->assertFalse( Comments::reviews_moderated_here( '6.7.0' ) );
		$this->assertFalse( Comments::reviews_moderated_here( '11.0.1' ) );
	}

	/**
	 * A version that cannot be read counts as old.
	 *
	 * The safe direction: guessing wrong this way leaves a screen reachable that
	 * did not need to be, and guessing wrong the other way takes a shop's review
	 * moderation away.
	 *
	 * @return void
	 */
	public function test_an_unreadable_version_counts_as_old() {
		$this->assertTrue( Comments::reviews_moderated_here( '' ) );
	}

	/**
	 * The version actually reaches the decision.
	 *
	 * Guards against the two halves being right while nothing joins them up: the
	 * suite's stub declares a modern WooCommerce, so the screen closes.
	 *
	 * @return void
	 */
	public function test_the_shop_version_decides_the_screen() {
		$this->on_screen( 'edit-comments.php' );

		$this->assertFalse( Comments::reviews_moderated_here(), 'The stub should be reading as a modern shop.' );
		$this->assertTrue( Comments::comment_screen_is_closed() );
	}
}
