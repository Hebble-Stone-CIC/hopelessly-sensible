<?php
/**
 * The notices this plugin prints.
 *
 * Two kinds, and the difference is where they appear. The admin username warning
 * and the network activation notice belong to the settings screen and are shown
 * nowhere else. Both report and neither acts, and neither stores a dismissal.
 *
 * The third is the exception, and it is the only thing this plugin shows outside
 * its own page: when a switch has been turned off on the owner's behalf, saying
 * so on a screen nobody has to visit would not be saying so at all. It appears
 * across the dashboard, once per administrator, and can be dismissed for good.
 *
 * Dismissal is a link rather than core's is-dismissible class, which hangs off
 * core's JavaScript and forgets the dismissal on the next page load.
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Admin;

use HopelesslySensible\Registry;
use HopelesslySensible\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the admin username warning, the network activation notice, and the
 * banner announcing a switch this plugin turned off.
 */
class Notices {

	/**
	 * The query argument carrying a dismissal, and the nonce action behind it.
	 *
	 * @var string
	 */
	const DISMISS = 'hopsen_dismiss';

	/**
	 * Prints the notices belonging to the settings screen.
	 *
	 * @return void
	 */
	public static function render() {
		self::network_activation();
		self::admin_username();
	}

	/**
	 * Whether the screen being rendered is this plugin's own settings page.
	 *
	 * The rendering layer is allowed to know this; feature code is not.
	 *
	 * get_current_screen() is undefined early in the request, and its absence
	 * answers no, which shows the extra line rather than hiding it.
	 *
	 * @return bool True on the Hopelessly Sensible settings page.
	 */
	private static function on_our_own_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return false;
		}

		return 'settings_page_' . Page::SLUG === $screen->id;
	}

	/**
	 * Explains why nothing was set up on a network-activated site.
	 *
	 * Activation across a network fires once, in whichever site the network
	 * administrator was looking at, so this plugin refuses to detect anything
	 * rather than set one site up from its own evidence. That leaves every option
	 * at its default with no state line to explain it.
	 *
	 * @return void
	 */
	private static function network_activation() {
		if ( false === self::is_network_activated() ) {
			return;
		}

		self::notice(
			'info',
			__( 'This plugin was activated for the whole network', 'hopelessly-sensible' ),
			array(
				__( 'Activating across a network means the plugin could not look at your sites one by one, so none of the options below were chosen for you: they are all sitting where they start. What you set here applies to this site only, and each of your other sites is set separately.', 'hopelessly-sensible' ),
				__( 'If you would rather it looked at each site and made a start for you, deactivate it for the network and activate it on each site instead.', 'hopelessly-sensible' ),
			)
		);
	}

	/**
	 * Warns about a user called "admin", and does nothing else about it.
	 *
	 * No toggle and no stored flag, because both would be a way of pretending this
	 * was dealt with. Nothing is changed either: WordPress will not rename a user,
	 * and the alternative belongs to whoever owns the site.
	 *
	 * @return void
	 */
	private static function admin_username() {
		if ( false === get_user_by( 'login', 'admin' ) ) {
			return;
		}

		self::notice(
			'warning',
			__( 'You have a user called "admin"', 'hopelessly-sensible' ),
			array(
				__( '"admin" is the first username automated guessing tools try, so that account attracts far more attempts than any other on your site.', 'hopelessly-sensible' ),
				__( 'We have not changed anything, and this plugin will not: WordPress does not let you rename a user once it exists. If you want to deal with it, the usual route is to create a second administrator account with a different username, log in as that one, then delete the "admin" account and hand its posts over to the new one when WordPress asks.', 'hopelessly-sensible' ),
			)
		);
	}

	/**
	 * Announces, across the dashboard, any switch this plugin has turned off.
	 *
	 * Shown to administrators only, because nobody else can act on it and nobody
	 * else moved the switch in the first place.
	 *
	 * @return void
	 */
	public static function switched_off_banner() {
		if ( ! current_user_can( Page::CAPABILITY ) ) {
			return;
		}

		$lines = self::retreat_lines();

		if ( empty( $lines ) ) {
			return;
		}

		/*
		 * Once, however many settings were switched off. Each sentence above
		 * describes an event and can never go out of date; this one is about what
		 * to do next, and pointing at the screen rather than promising the setting
		 * can be turned on is what keeps it true where the blocker still stands.
		 *
		 * Left off on that screen, where it would direct somebody to where they
		 * already are.
		 */
		if ( false === self::on_our_own_screen() ) {
			$lines[] = __( 'You can switch these back on under Settings, Hopelessly Sensible, and that screen will tell you if anything is still in the way.', 'hopelessly-sensible' );
		}

		$lines[] = self::dismiss_link();

		self::notice(
			'warning',
			__( 'Hopelessly Sensible has changed a setting', 'hopelessly-sensible' ),
			$lines,
			true
		);
	}

	/**
	 * The sentences owed to this administrator, if any.
	 *
	 * Empty where nothing has been switched off, where this administrator has
	 * already waved it away, or where a feature named in the record has since
	 * lost its copy, which is what would happen if a future version dropped it.
	 *
	 * @return string[] One sentence per feature switched off.
	 */
	private static function retreat_lines() {
		$switched_off = Settings::live( 'switched_off' );
		$causes       = Settings::live( 'switched_off_by' );
		$dismissed    = Settings::live( 'dismissed' );

		if ( ! is_array( $switched_off ) || empty( $switched_off ) ) {
			return array();
		}

		if ( is_array( $dismissed ) && in_array( get_current_user_id(), $dismissed, true ) ) {
			return array();
		}

		$features = Registry::all();
		$lines    = array();

		foreach ( $switched_off as $key ) {
			if ( empty( $features[ $key ]['retreat_line'] ) ) {
				continue;
			}

			$cause = isset( $causes[ $key ] ) ? $causes[ $key ] : '';
			$line  = Registry::retreat_line( $features[ $key ], $cause );

			if ( '' === $line ) {
				continue;
			}

			$lines[] = $line;
		}

		return $lines;
	}

	/**
	 * Records a dismissal and sends the administrator back to a clean address.
	 *
	 * The redirect is not tidiness. Left in place, the argument would sit in the
	 * address bar to be bookmarked, shared or reloaded, and every reload would
	 * spend a nonce on a dismissal that has already happened.
	 *
	 * @return void
	 */
	public static function handle_dismiss() {
		if ( ! isset( $_GET[ self::DISMISS ] ) ) {
			return;
		}

		if ( ! current_user_can( Page::CAPABILITY ) ) {
			return;
		}

		check_admin_referer( self::DISMISS );

		Settings::dismiss( get_current_user_id() );

		wp_safe_redirect( remove_query_arg( array( self::DISMISS, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * The dismissal link, as a paragraph of its own.
	 *
	 * @return string An anchor, already escaped, for printing unescaped.
	 */
	private static function dismiss_link() {
		$url = wp_nonce_url( add_query_arg( self::DISMISS, '1' ), self::DISMISS );

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Understood, hide this', 'hopelessly-sensible' )
		);
	}

	/**
	 * Whether this plugin was activated across a network.
	 *
	 * The core function this asks lives in an admin include already loaded on
	 * every admin screen, but the guard keeps the class safe to call from
	 * anywhere.
	 *
	 * @return bool True when the plugin is active for the network.
	 */
	private static function is_network_activated() {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( plugin_basename( HOPSEN_FILE ) );
	}

	/**
	 * Prints one notice.
	 *
	 * Core's own notice markup, so these look like part of WordPress and inherit
	 * whatever the dashboard does with notices next.
	 *
	 * @param string   $type       'info' or 'warning'.
	 * @param string   $title      Heading, or an empty string for a notice without one.
	 * @param string[] $paragraphs The body, one string per paragraph.
	 * @param bool     $linked     Whether the last paragraph is already-escaped markup.
	 * @return void
	 */
	private static function notice( $type, $title, array $paragraphs, $linked = false ) {
		$last = count( $paragraphs ) - 1;

		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> hopsen-notice">
			<?php if ( '' !== $title ) : ?>
				<h2 class="hopsen-notice__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>

			<?php foreach ( $paragraphs as $index => $paragraph ) : ?>
				<?php if ( true === $linked && $index === $last ) : ?>
					<?php // Built by dismiss_link(), where both halves were escaped. ?>
					<p><?php echo wp_kses( $paragraph, array( 'a' => array( 'href' => array() ) ) ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html( $paragraph ); ?></p>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
