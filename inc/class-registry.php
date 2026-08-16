<?php
/**
 * The registry: every feature defined exactly once.
 *
 * The defaults, the sanitise loop, the settings screen and the REST schema all
 * derive from this file. Adding a feature means one entry here and one file in
 * inc/features/. If a change needs the screen edited, this file is wrong.
 *
 * All user-facing text below is fixed in refs/interface-copy.md and must be used
 * verbatim. If something here reads wrong, fix that file first.
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the definition of every feature the plugin offers.
 */
class Registry {

	/**
	 * The built registry, kept for the rest of the request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $registry = null;

	/**
	 * Returns every feature this plugin offers, keyed by feature key.
	 *
	 * Each entry carries:
	 *
	 * - label        Short name shown on the screen.
	 * - description  What it does.
	 * - warning      What might break. Never empty.
	 * - warning_open Whether the details element starts expanded.
	 * - group        'decided' or 'optional'. Drives the two screen sections.
	 * - default      State on a fresh install before detection.
	 * - detectable   Whether activation detection may switch it on.
	 * - blocker      Optional callable answering why this feature cannot be
	 *                switched on. Never gates whether the row appears.
	 * - callback     The callable that registers the feature's hooks.
	 * - state_line   What is worth saying under this row, keyed by situation. A
	 *                feature with nothing to report renders no line at all.
	 * - retreat_line What the banner says when this feature has been switched off
	 *                on the owner's behalf. The one string here in the past tense:
	 *                the banner outlives the situation that caused it.
	 *
	 * State lines name any number they quote as a {token}, filled in by
	 * Settings::fill_counts(). Not printf, because a translator who mangles a %d
	 * breaks the page.
	 *
	 * @return array<string, array<string, mixed>> The feature registry.
	 */
	public static function all() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		self::$registry = array(

			'rest_users'          => array(
				'label'        => __( 'Keep the user list private', 'hopelessly-sensible' ),
				'description'  => __( 'WordPress will hand a list of everyone who writes on your site, usernames included, to anyone who asks for it. Switching this on limits that list to people logged in with permission to manage users.', 'hopelessly-sensible' ),
				'warning'      => __( 'Likely nothing at all. If you have a "meet the team" page that builds itself from your user list rather than from a page you wrote, worth a quick check that it still looks right.', 'hopelessly-sensible' ),
				'warning_open' => false,
				'group'        => 'decided',
				'default'      => true,
				'detectable'   => false,
				'blocker'      => null,
				'callback'     => array( Features\Rest_Users::class, 'init' ),
				'state_line'   => array(),
			),

			'login_errors'        => array(
				'label'        => __( 'Keep login errors vague', 'hopelessly-sensible' ),
				'description'  => __( 'When someone gets a login wrong, WordPress tells them whether the username exists or the password was wrong. That quietly confirms real usernames to anyone guessing at them. Switching this on gives the same short message either way.', 'hopelessly-sensible' ),
				'warning'      => __( 'If you mistype your own username, the login screen will no longer tell you which part you got wrong, and the "Lost your password?" link that usually appears in that message will be gone. The link underneath the login form still works as normal.', 'hopelessly-sensible' ),
				'warning_open' => false,
				'group'        => 'decided',
				'default'      => true,
				'detectable'   => false,
				'blocker'      => null,
				'callback'     => array( Features\Login_Errors::class, 'init' ),
				'state_line'   => array(),
			),

			'author_urls'         => array(
				'label'        => __( 'Hide author pages', 'hopelessly-sensible' ),
				'description'  => __( 'Everyone who writes on your site gets their own page listing their posts, at an address that also gives away their username. Switching this on makes those pages show Not Found, and keeps writers out of your sitemap and out of the previews other sites generate when they link to you.', 'hopelessly-sensible' ),
				'warning'      => __( 'If your site has several writers and you link to "posts by Jane" anywhere, those links will stop working. Look at a post: if the author\'s name underneath the title is a link, switching this on will break it.', 'hopelessly-sensible' ),
				'warning_open' => false,
				'group'        => 'decided',
				'default'      => false,
				'detectable'   => true,
				'blocker'      => null,
				'callback'     => array( Features\Author_Urls::class, 'init' ),
				'state_line'   => array(
					'off_one'  => __( 'One person publishes posts here, so these pages have nothing your visitors need.', 'hopelessly-sensible' ),
					/* translators: {authors} is replaced with the number of people publishing posts on this site. Keep the braces. */
					'off_many' => __( '{authors} people publish posts here, and sites with several writers often link to author pages from their bylines.', 'hopelessly-sensible' ),
					'off_none' => __( 'Nothing is published here yet, so there are no author pages to hide. Worth switching on before you publish.', 'hopelessly-sensible' ),
				),
			),

			'disable_comments'    => array(
				'label'        => __( 'Close comments everywhere', 'hopelessly-sensible' ),
				'description'  => __( 'Closes comments on every post and page, hides any that are already there, and takes the comment screens out of your dashboard. Comment forms attract a great deal of automated spam, most of it posted to push links to other sites.', 'hopelessly-sensible' ),
				'warning'      => __( 'Your existing comments are hidden from visitors, not deleted, so switching this back on brings them straight back. While it is on you will not be able to read or moderate them either, because the Comments screen goes as well. And if people are still talking on your site, this ends that conversation without telling them why. WooCommerce product reviews are left alone: they have their own option below.', 'hopelessly-sensible' ),
				'warning_open' => true,
				'group'        => 'decided',
				'default'      => false,
				'detectable'   => true,
				'blocker'      => null,
				'callback'     => array( Features\Comments::class, 'init' ),
				'state_line'   => array(
					'on_one'         => __( 'One comment is approved here and hidden from your visitors. If you want it gone for good, switch this off, delete it under Comments, then switch it back on.', 'hopelessly-sensible' ),
					/* translators: {comments} is replaced with the number of approved comments currently hidden. Keep the braces. */
					'on_many'        => __( '{comments} comments are approved here and hidden from your visitors. If you want them gone for good, switch this off, delete them under Comments, then switch it back on.', 'hopelessly-sensible' ),
					'on_sample_only' => __( 'The only comment here is the sample one WordPress adds when a site is installed, so nothing of yours is hidden.', 'hopelessly-sensible' ),
					'off_in_use_one' => __( 'One comment has been approved here in the past year, so somebody is reading it.', 'hopelessly-sensible' ),
					/* translators: {comments} is replaced with the number of comments approved in the past year. Keep the braces. */
					'off_in_use'     => __( '{comments} comments have been approved here in the past year, so somebody is reading them.', 'hopelessly-sensible' ),
					'off_none'       => __( 'No comments have been approved here in the past year, so switching this on would hide nothing of yours.', 'hopelessly-sensible' ),
				),
			),

			'block_xmlrpc'        => array(
				'label'        => __( 'Block remote publishing', 'hopelessly-sensible' ),
				'description'  => __( 'XML-RPC is an old way for apps to post to your site remotely. Most sites no longer use it, and it is a favourite of automated password-guessing tools because one request can try many passwords at once.', 'hopelessly-sensible' ),
				'warning'      => __( 'If anyone on your team writes posts from their phone using the WordPress app, switching this on will stop them. Jetpack needs remote publishing too, as do several plugins that connect a shop to WordPress.com. We can see anything on your site that asks WordPress for remote publishing, but there is no way for us to see the mobile app, so ask around before you switch this on.', 'hopelessly-sensible' ),
				'warning_open' => true,
				'group'        => 'optional',
				'default'      => false,
				'detectable'   => false,
				'blocker'      => array( Detection::class, 'xmlrpc_blocker' ),
				'callback'     => array( Features\Xmlrpc::class, 'init' ),
				'state_line'   => array(
					'off_idle' => __( 'Nothing on your site is asking for remote publishing at the moment.', 'hopelessly-sensible' ),
					'blocked'  => __( 'Something on your site is using remote publishing right now, so this cannot be switched on. If you know what it is and no longer need it, turn it off there first and this switch will free up.', 'hopelessly-sensible' ),
				),
				'retreat_line' => __( 'Something on your site started using remote publishing, and "Block remote publishing" would have stopped it working, so we switched that setting off.', 'hopelessly-sensible' ),
			),

			'disable_woo_reviews' => array(
				'label'        => __( 'Close product reviews', 'hopelessly-sensible' ),
				'description'  => __( 'WooCommerce reviews are comments wearing a different hat, so closing comments does not touch them. Switching this on closes the review form and hides the reviews you already have.', 'hopelessly-sensible' ),
				'warning'      => __( 'This hides existing reviews and the star ratings that come with them, not just the form for writing new ones. On a shop, those ratings are often the thing that persuades someone to buy. Switching it back on brings them back.', 'hopelessly-sensible' ),
				'warning_open' => true,
				'group'        => 'optional',
				'default'      => false,
				'detectable'   => false,
				'blocker'      => array( Detection::class, 'woo_blocker' ),
				'callback'     => array( Features\Comments::class, 'init_woo_reviews' ),
				'state_line'   => array(
					'blocked' => __( 'WooCommerce is not active on this site, so there are no product reviews to close.', 'hopelessly-sensible' ),
				),
				'retreat_line' => __( 'WooCommerce was not active, so "Close product reviews" had nothing left to act on and we switched it off.', 'hopelessly-sensible' ),
			),

			'disallow_file_edit'  => array(
				'label'        => __( 'Lock the file editor', 'hopelessly-sensible' ),
				'description'  => __( 'WordPress lets administrators edit theme and plugin files straight from the dashboard. It is a quick way to take a site down by accident, and if someone gets into an administrator account it is a quick way for them to run code of their own.', 'hopelessly-sensible' ),
				'warning'      => __( 'This takes the theme and plugin file editors out of the dashboard for everyone, including you. If you or whoever looks after your site makes small fixes that way, they will need to edit files over SFTP or through your hosting control panel instead. Switching this back on brings the editors back on the next page load.', 'hopelessly-sensible' ),
				'warning_open' => true,
				'group'        => 'optional',
				'default'      => false,
				'detectable'   => false,
				'blocker'      => array( Detection::class, 'file_edit_blocker' ),
				'callback'     => array( Features\File_Edit::class, 'init' ),
				'state_line'   => array(
					'blocked_locked' => __( 'Your wp-config.php already locks the file editor, so this switch has nothing left to do.', 'hopelessly-sensible' ),
					'blocked_forced' => __( 'Your wp-config.php sets DISALLOW_FILE_EDIT to false, and that beats anything we do. The file editor stays available until somebody changes or removes that line.', 'hopelessly-sensible' ),
				),
				'retreat_line' => __( 'Your wp-config.php set DISALLOW_FILE_EDIT to false, which beats anything we do, so we switched "Lock the file editor" off.', 'hopelessly-sensible' ),
			),
		);

		return self::$registry;
	}

	/**
	 * Returns the two screen sections, keyed by group.
	 *
	 * The axis is who made the decision. The second heading also says why we did
	 * not make it, which is the only copy on the screen belonging to a section
	 * rather than to a row.
	 *
	 * @return array<string, array<string, string>> Group key to heading and note.
	 */
	public static function groups() {
		return array(
			'decided'  => array(
				'heading' => __( 'Set up for you', 'hopelessly-sensible' ),
				'note'    => '',
			),
			'optional' => array(
				'heading' => __( 'Worth a think first', 'hopelessly-sensible' ),
				'note'    => __( 'Nothing in this section is ever switched on for you. These three take something away from you rather than from a visitor, so the decision is yours.', 'hopelessly-sensible' ),
			),
		);
	}

	/**
	 * What is stopping a feature from being switched on right now, if anything.
	 *
	 * Returns null when nothing is, or a blocker carrying three things:
	 *
	 * - variant  The sentence the row shows in place of its usual one.
	 * - retreat  Whether a feature left on in this situation has to be switched
	 *            off. False where the label's promise is being met anyway.
	 * - checked  Whether the disabled toggle reads as on regardless of what is
	 *            stored. Null to show the stored value, which is usual.
	 *
	 * A blocker that cannot be called counts as no blocker at all, so a mistyped
	 * callable leaves a working switch working. This never decides whether a row
	 * is drawn.
	 *
	 * @param array<string, mixed> $feature A registry entry.
	 * @return array<string, mixed>|null The blocker, or null when there is none.
	 */
	public static function blocker( array $feature ) {
		if ( empty( $feature['blocker'] ) || ! is_callable( $feature['blocker'] ) ) {
			return null;
		}

		$blocker = call_user_func( $feature['blocker'] );

		if ( ! is_array( $blocker ) || empty( $blocker['variant'] ) ) {
			return null;
		}

		return array(
			'variant' => (string) $blocker['variant'],
			'retreat' => ! empty( $blocker['retreat'] ),
			'checked' => isset( $blocker['checked'] ) ? (bool) $blocker['checked'] : null,
		);
	}

	/**
	 * Whether WooCommerce is active on this site.
	 *
	 * Safe to ask from init at priority zero, because WooCommerce declares its
	 * class when its main file is included, which happens before plugins_loaded.
	 *
	 * @return bool True when WooCommerce is present.
	 */
	public static function woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}
