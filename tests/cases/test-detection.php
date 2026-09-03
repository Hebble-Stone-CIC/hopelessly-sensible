<?php
/**
 * The questions the plugin asks a site, and what follows from the answers.
 *
 * Every one of them is asked in the present tense and none of the answers is
 * stored, so an answer that is wrong is wrong on the screen in front of
 * somebody rather than quietly wrong in a row nobody reads.
 *
 * @package HopelesslySensible
 */

use HopelesslySensible\Detection;

/**
 * Counting authors and comments, spotting XML-RPC, and the blockers.
 */
class Test_Detection extends WP_UnitTestCase {

	/**
	 * Creates a writer with a published post, and returns the user id.
	 *
	 * @param string $type The post type to publish.
	 * @return int The new user's id.
	 */
	private function writer( $type = 'post' ) {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
				'post_type'   => $type,
			)
		);

		return $user_id;
	}

	/**
	 * One writer is the case that switches author pages on at activation.
	 *
	 * @return void
	 */
	public function test_one_author_is_counted_as_one() {
		$this->writer();

		$this->assertSame( 1, Detection::authors() );
		$this->assertTrue( Detection::opening_states()['author_urls'] );
	}

	/**
	 * Six writers is the case that leaves them alone.
	 *
	 * @return void
	 */
	public function test_six_authors_are_counted_as_six() {
		for ( $i = 0; $i < 6; $i++ ) {
			$this->writer();
		}

		$this->assertSame( 6, Detection::authors() );
		$this->assertFalse( Detection::opening_states()['author_urls'] );
	}

	/**
	 * Accounts that have never written anything are not counted.
	 *
	 * This is the whole reason for asking has_published_posts rather than
	 * count_users(): on a shop, the second answer is the customer list.
	 *
	 * @return void
	 */
	public function test_customers_are_not_authors() {
		$this->writer();

		self::factory()->user->create_many( 20, array( 'role' => 'customer' ) );

		$this->assertSame( 1, Detection::authors() );
	}

	/**
	 * Drafts are not bylines either.
	 *
	 * @return void
	 */
	public function test_unpublished_work_does_not_count() {
		$this->writer();

		$other = self::factory()->user->create( array( 'role' => 'author' ) );

		self::factory()->post->create(
			array(
				'post_author' => $other,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( 1, Detection::authors() );
	}

	/**
	 * A site with nothing published reads differently from one with several
	 * writers, and gets its own sentence.
	 *
	 * @return void
	 */
	public function test_an_empty_site_has_no_authors() {
		$this->assertSame( 0, Detection::authors() );
		$this->assertFalse( Detection::opening_states()['author_urls'] );
		$this->assertSame( 'off_none', Detection::state_variant( 'author_urls', false ) );
	}

	/**
	 * One writer and several writers are different sentences too.
	 *
	 * @return void
	 */
	public function test_the_author_line_matches_the_number_of_writers() {
		$this->writer();

		$this->assertSame( 'off_one', Detection::state_variant( 'author_urls', false ) );

		$this->writer();

		$this->assertSame( 'off_many', Detection::state_variant( 'author_urls', false ) );
		$this->assertSame( array( 'authors' => 2 ), Detection::counts( 'author_urls', 'off_many' ) );
	}

	/**
	 * Author pages that are already hidden have nothing to report.
	 *
	 * @return void
	 */
	public function test_hidden_author_pages_say_nothing() {
		$this->writer();

		$this->assertNull( Detection::state_variant( 'author_urls', true ) );
	}

	/**
	 * No approved comments is the case that closes comments at activation.
	 *
	 * @return void
	 */
	public function test_a_quiet_site_has_no_comments() {
		$this->assertSame( 0, Detection::comments_recent() );
		$this->assertTrue( Detection::opening_states()['disable_comments'] );
	}

	/**
	 * Forty of them is the case that leaves comments open.
	 *
	 * @return void
	 */
	public function test_a_busy_site_counts_all_of_them() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create_many( 40, array( 'comment_post_ID' => $post_id ) );

		$this->assertSame( 40, Detection::comments_recent() );
		$this->assertFalse( Detection::opening_states()['disable_comments'] );
		$this->assertSame( 'off_in_use', Detection::state_variant( 'disable_comments', false ) );
	}

	/**
	 * Spam says nothing about intent, and neither does anything unapproved.
	 *
	 * @return void
	 */
	public function test_only_approved_comments_count() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '0',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 'spam',
			)
		);

		$this->assertSame( 0, Detection::comments_recent() );
		$this->assertSame( 0, Detection::comments_hidden() );
	}

	/**
	 * Reviews and order notes are not comments for this purpose.
	 *
	 * A shop with a thousand reviews and no conversation is a quiet site, and
	 * closing comments must never claim to have hidden a review.
	 *
	 * @return void
	 */
	public function test_reviews_and_order_notes_are_not_counted() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'review',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'order_note',
			)
		);

		$this->assertSame( 0, Detection::comments_recent() );
		$this->assertSame( 0, Detection::comments_hidden() );
	}

	/**
	 * A conversation that ended years ago is not a conversation.
	 *
	 * @return void
	 */
	public function test_old_comments_are_not_recent() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( 2 * YEAR_IN_SECONDS ) ),
				'comment_date'     => gmdate( 'Y-m-d H:i:s', time() - ( 2 * YEAR_IN_SECONDS ) ),
			)
		);

		$this->assertSame( 0, Detection::comments_recent() );
	}

	/**
	 * It is still being hidden, though, which is a different question.
	 *
	 * The count under a switch that is on has no date bound on purpose: the
	 * question there is not whether comments are in use but whether anything is
	 * invisible, and age has nothing to do with it.
	 *
	 * @return void
	 */
	public function test_old_comments_are_still_hidden() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( 2 * YEAR_IN_SECONDS ) ),
				'comment_date'     => gmdate( 'Y-m-d H:i:s', time() - ( 2 * YEAR_IN_SECONDS ) ),
			)
		);

		$this->assertSame( 1, Detection::comments_hidden() );
		$this->assertSame( 'on_one', Detection::state_variant( 'disable_comments', true ) );

		self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		$this->assertSame( 'on_many', Detection::state_variant( 'disable_comments', true ) );
		$this->assertSame( array( 'comments' => 2 ), Detection::counts( 'disable_comments', 'on_many' ) );
	}

	/**
	 * Closed comments with nothing to hide say nothing at all.
	 *
	 * @return void
	 */
	public function test_closed_comments_with_nothing_hidden_say_nothing() {
		$this->assertNull( Detection::state_variant( 'disable_comments', true ) );
	}

	/**
	 * Creates the comment WordPress installs every site with.
	 *
	 * Approved, dated now, and attached to the first post, which is what core
	 * does in wp_install_defaults().
	 *
	 * @return int The new comment's id.
	 */
	private function sample_comment() {
		return self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::factory()->post->create(),
				'comment_author'       => 'A WordPress Commenter',
				'comment_author_email' => 'wapuu@wordpress.example',
				'comment_author_url'   => 'https://wordpress.org/',
				'comment_content'      => 'Hi, this is a comment.',
			)
		);
	}

	/**
	 * The comment WordPress installs with is not evidence of anything.
	 *
	 * The bug this guards is on every new site there has ever been: core writes
	 * one approved comment dated the day of the install, so a brand new site
	 * counted as a site with comments in use, and the row went on to say that
	 * somebody was reading them. Nobody approved it and nobody is reading it.
	 *
	 * @return void
	 */
	public function test_the_sample_comment_is_not_counted() {
		$this->sample_comment();

		$this->assertTrue( Detection::sample_comment_exists() );
		$this->assertSame( 0, Detection::comments_recent() );
		$this->assertSame( 0, Detection::comments_hidden() );
	}

	/**
	 * So a fresh install gets its comments closed, which is the whole point.
	 *
	 * @return void
	 */
	public function test_a_fresh_install_closes_its_comments() {
		$this->sample_comment();

		$this->assertTrue( Detection::opening_states()['disable_comments'] );
	}

	/**
	 * And the row names it rather than counting it.
	 *
	 * "One comment is hidden" would send somebody hunting for something they
	 * never wrote.
	 *
	 * @return void
	 */
	public function test_the_sample_comment_is_named_not_counted() {
		$this->sample_comment();

		$this->assertSame( 'on_sample_only', Detection::state_variant( 'disable_comments', true ) );
		$this->assertSame( 'off_none', Detection::state_variant( 'disable_comments', false ) );
	}

	/**
	 * A real comment alongside it still counts, and the sample still does not.
	 *
	 * @return void
	 */
	public function test_a_real_comment_beside_the_sample_still_counts() {
		$this->sample_comment();

		self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );

		$this->assertSame( 1, Detection::comments_recent() );
		$this->assertSame( 1, Detection::comments_hidden() );
		$this->assertFalse( Detection::opening_states()['disable_comments'] );
	}

	/**
	 * One of anything gets a sentence written for one of anything.
	 *
	 * "1 comments have been approved" reached a real screen, because one is the
	 * number that arrives most often.
	 *
	 * @return void
	 */
	public function test_one_comment_reads_as_one_comment() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		$this->assertSame( 'off_in_use_one', Detection::state_variant( 'disable_comments', false ) );
		$this->assertSame( 'on_one', Detection::state_variant( 'disable_comments', true ) );

		self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		$this->assertSame( 'off_in_use', Detection::state_variant( 'disable_comments', false ) );
	}

	/**
	 * A site where something has extended XML-RPC cannot block it.
	 *
	 * The question is asked of WordPress, not of a list of plugin names, so it
	 * does not matter which plugin hooked it or what that plugin is called.
	 *
	 * @return void
	 */
	public function test_a_site_using_xmlrpc_is_blocked_from_blocking_it() {
		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		$this->assertTrue( Detection::xmlrpc_in_use() );

		$blocker = Detection::xmlrpc_blocker();

		$this->assertSame( 'blocked', $blocker['variant'] );
		$this->assertTrue( $blocker['retreat'], 'A site using XML-RPC must have the switch turned off, not merely explained.' );
	}

	/**
	 * A hook registered at priority zero still counts.
	 *
	 * Core answers with the priority it found, and zero is a perfectly ordinary
	 * priority that is also falsy.
	 *
	 * @return void
	 */
	public function test_a_hook_at_priority_zero_still_counts() {
		add_filter( 'xmlrpc_methods', '__return_empty_array', 0 );

		$this->assertTrue( Detection::xmlrpc_in_use() );
	}

	/**
	 * WordPress hooking its own filter is not a site using XML-RPC.
	 *
	 * WordPress 7.1 added this callback to default-filters.php, so from that
	 * release has_filter() answers yes on every site alive and the old test
	 * blocked the feature everywhere. The callback only ever unsets
	 * pingback.ping, so it reduces the method table and can never be evidence
	 * that something wants the endpoint.
	 *
	 * Registered here by name rather than skipped on old versions. add_filter()
	 * stores whatever string it is given, so this reproduces 7.1 on a 6.8.3
	 * codebase where the function does not exist.
	 *
	 * @return void
	 */
	public function test_core_hooking_the_filter_is_not_a_site_using_xmlrpc() {
		add_filter( 'xmlrpc_methods', 'wp_maybe_disable_xmlrpc_pingback_for_environment' );

		$this->assertFalse( Detection::xmlrpc_in_use() );
		$this->assertNull( Detection::xmlrpc_blocker() );
	}

	/**
	 * Core's callback alongside somebody else's is still somebody else's.
	 *
	 * The version that answered on has_filter() alone could not see past the
	 * first registration. Order must not matter either, so core goes on first.
	 *
	 * @return void
	 */
	public function test_core_does_not_hide_a_real_user_of_xmlrpc() {
		add_filter( 'xmlrpc_methods', 'wp_maybe_disable_xmlrpc_pingback_for_environment' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		$this->assertTrue( Detection::xmlrpc_in_use() );
	}

	/**
	 * A core function is not core's hook, and the difference is the whole point.
	 *
	 * Deciding what belongs to core by asking where a function is defined would
	 * read this as core, because __return_empty_array lives in wp-includes. It
	 * is the site that hooked it, that site has taken a decision about XML-RPC,
	 * and overruling it silently is exactly the failure this pins.
	 *
	 * @return void
	 */
	public function test_a_core_function_hooked_by_the_site_still_counts() {
		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		$this->assertTrue( Detection::xmlrpc_in_use() );
	}

	/**
	 * A closure counts, because nothing can prove it is core's.
	 *
	 * Core registers named functions. Anything this class cannot name is
	 * somebody else's, and the unrecognised case has to cost a switch rather
	 * than a site.
	 *
	 * @return void
	 */
	public function test_an_unnameable_callback_counts() {
		add_filter(
			'xmlrpc_methods',
			function ( $methods ) {
				return $methods;
			}
		);

		$this->assertTrue( Detection::xmlrpc_in_use() );
	}

	/**
	 * A site where nothing uses XML-RPC is free to block it.
	 *
	 * Including a site carrying the wreckage of a shop somebody removed years
	 * ago: abandoned rows in the options table hook nothing.
	 *
	 * @return void
	 */
	public function test_an_ordinary_site_can_block_xmlrpc() {
		update_option( 'active_plugins', array( 'akismet/akismet.php', 'woocommerce/woocommerce.php' ) );
		update_option( 'woocommerce_db_version', '9.9.9' );

		$this->assertFalse( Detection::xmlrpc_in_use() );
		$this->assertNull( Detection::xmlrpc_blocker() );
		$this->assertSame( 'off_idle', Detection::state_variant( 'block_xmlrpc', false ) );
	}

	/**
	 * A plugin named after Jetpack proves nothing on its own.
	 *
	 * The old test looked for the name and would have passed here while the
	 * site had no use for XML-RPC at all. What matters is the hook.
	 *
	 * @return void
	 */
	public function test_a_name_is_not_evidence() {
		update_option( 'active_plugins', array( 'jetpack-lite-seo-helper/plugin.php' ) );

		$this->assertFalse( Detection::xmlrpc_in_use() );
	}

	/**
	 * Blocking remote publishing is never switched on for anybody.
	 *
	 * It sits in the second group, and the second group is the promise that we
	 * do not make these decisions.
	 *
	 * @return void
	 */
	public function test_the_second_group_is_never_switched_on() {
		$states = Detection::opening_states();

		$this->assertArrayNotHasKey( 'block_xmlrpc', $states );
		$this->assertArrayNotHasKey( 'disable_woo_reviews', $states );
		$this->assertArrayNotHasKey( 'disallow_file_edit', $states );
	}

	/**
	 * A shop that is present blocks nothing.
	 *
	 * The stub in tests/stubs is the whole of what this plugin looks for, so
	 * WooCommerce is permanently present in the suite. The absent case is covered
	 * in Test_Settings, by swapping the blocker itself.
	 *
	 * @return void
	 */
	public function test_a_site_with_woocommerce_can_close_reviews() {
		$this->assertNull( Detection::woo_blocker() );
	}

	/**
	 * Features with nothing to report get no line, whatever state they are in.
	 *
	 * @return void
	 */
	public function test_a_working_switch_says_nothing() {
		$this->assertNull( Detection::state_variant( 'rest_users', true ) );
		$this->assertNull( Detection::state_variant( 'rest_users', false ) );
		$this->assertNull( Detection::state_variant( 'login_errors', true ) );
		$this->assertNull( Detection::state_variant( 'disallow_file_edit', false ) );
		$this->assertSame( array(), Detection::counts( 'rest_users', 'anything' ) );
	}
}
