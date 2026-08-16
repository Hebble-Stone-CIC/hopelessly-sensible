<?php
/**
 * All five ways a writer's name gets out.
 *
 * Closing only the first is the usual mistake, and the ones most often left
 * open are the feed and the oEmbed response, because neither is visible from
 * the page you were looking at when you thought you had finished.
 *
 * @package HopelesslySensible
 */

use HopelesslySensible\Features\Author_Urls;

/**
 * Author archives, their feed, the sitemap and the link previews.
 */
class Test_Author_Urls extends WP_UnitTestCase {

	/**
	 * A user with something published.
	 *
	 * @var int
	 */
	private $author_id;

	/**
	 * Creates a writer with a post to their name.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->author_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_login'    => 'janewriter',
				'user_nicename' => 'janewriter',
			)
		);

		self::factory()->post->create(
			array(
				'post_author' => $this->author_id,
				'post_status' => 'publish',
			)
		);

		Author_Urls::init();
	}

	/**
	 * Vectors one and two: the archive itself, however it was asked for.
	 *
	 * By the time WordPress has parsed the request, /?author=1 and
	 * /author/slug/ are the same query, which is why one assertion covers both.
	 *
	 * @return void
	 */
	public function test_the_author_archive_is_not_found() {
		$this->go_to( '/?author=' . $this->author_id );

		$this->assertTrue( is_author(), 'The test did not reach an author archive.' );

		Author_Urls::block_requests();

		$this->assertTrue( is_404() );
	}

	/**
	 * Core's canonical redirect is taken off the hook, so the username never
	 * leaves in a Location header and no guessed permalink is offered instead.
	 *
	 * @return void
	 */
	public function test_the_canonical_redirect_is_removed() {
		$this->go_to( '/?author=' . $this->author_id );

		$this->assertNotFalse( has_action( 'template_redirect', 'redirect_canonical' ) );

		Author_Urls::block_requests();

		$this->assertFalse( has_action( 'template_redirect', 'redirect_canonical' ) );
	}

	/**
	 * Vector three: the feed, which survives being marked Not Found.
	 *
	 * Core's set_404() resets every query flag except is_feed, which it restores
	 * on purpose, and the template loader answers feeds before it looks at is_404.
	 * Left alone this sends a 404 header and then serves the writer's posts
	 * underneath it.
	 *
	 * @return void
	 */
	public function test_the_author_feed_is_not_served() {
		$this->go_to( '/?author=' . $this->author_id . '&feed=rss2' );

		$this->assertTrue( is_feed(), 'The test did not reach a feed.' );

		Author_Urls::block_requests();

		$this->assertTrue( is_404() );
		$this->assertFalse( is_feed(), 'The feed flag survived, so the feed would still be served.' );
	}

	/**
	 * Vector four: the users sitemap, and its entry in the sitemap index.
	 *
	 * @return void
	 */
	public function test_the_users_sitemap_is_dropped() {
		$provider = new WP_Sitemaps_Users();

		$this->assertFalse( apply_filters( 'wp_sitemaps_add_provider', $provider, 'users' ) );
	}

	/**
	 * Other sitemap providers are left alone.
	 *
	 * @return void
	 */
	public function test_other_sitemaps_are_untouched() {
		$provider = new WP_Sitemaps_Posts();

		$this->assertSame( $provider, apply_filters( 'wp_sitemaps_add_provider', $provider, 'posts' ) );
	}

	/**
	 * Vector five: the author in an oEmbed response, which is how a name
	 * reaches sites that have merely linked to yours.
	 *
	 * @return void
	 */
	public function test_the_oembed_response_carries_no_author() {
		$post_id = self::factory()->post->create( array( 'post_author' => $this->author_id ) );

		$data = array(
			'title'       => 'A post',
			'author_name' => 'Jane Writer',
			'author_url'  => 'http://example.org/author/janewriter/',
		);

		// Four arguments, because core has its own callback on this filter.
		$filtered = apply_filters( 'oembed_response_data', $data, get_post( $post_id ), 600, 338 );

		$this->assertArrayNotHasKey( 'author_name', $filtered );
		$this->assertArrayNotHasKey( 'author_url', $filtered );
		$this->assertSame( 'A post', $filtered['title'] );
	}

	/**
	 * An ordinary page is not touched by any of this.
	 *
	 * @return void
	 */
	public function test_ordinary_requests_are_left_alone() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->go_to( '/?p=' . $post_id );

		Author_Urls::block_requests();

		$this->assertFalse( is_404() );
		$this->assertNotFalse( has_action( 'template_redirect', 'redirect_canonical' ) );
	}
}
