<?php
/**
 * The settings screen: menu registration and the page itself.
 *
 * A submenu under Settings, not a top-level menu item: this plugin is looked at
 * once and then left alone.
 *
 * A plain form posting to options.php. No AJAX, no REST call, no JavaScript, so
 * the whole rendering layer can be replaced without a feature file changing.
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Admin;

use HopelesslySensible\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the settings screen.
 */
class Page {

	/**
	 * The menu slug, and the last part of the screen's hook suffix.
	 *
	 * @var string
	 */
	const SLUG = 'hopelessly-sensible';

	/**
	 * The capability required to see or save these settings.
	 *
	 * The same capability the option group is registered with, so the check on
	 * this screen and the check options.php makes when the form is posted cannot
	 * drift apart.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Adds the screen under Settings.
	 *
	 * @return void
	 */
	public static function register() {
		add_options_page(
			__( 'Hopelessly Sensible', 'hopelessly-sensible' ),
			__( 'Hopelessly Sensible', 'hopelessly-sensible' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Loads the stylesheet, on this screen only.
	 *
	 * One stylesheet, no script. The version is the plugin version, so an update
	 * that changes the CSS is not read from a cache.
	 *
	 * @param string $hook_suffix The screen being loaded.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'settings_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'hopsen-admin',
			HOPSEN_URL . 'assets/admin.css',
			array(),
			HOPSEN_VERSION
		);
	}

	/**
	 * Prints the screen.
	 *
	 * The capability is checked again rather than trusted from the menu: the
	 * callback is reachable by anyone who knows the URL.
	 *
	 * Saving is core's, through settings_fields() and options.php. So is the
	 * "Settings saved" message, which core prints for any screen under
	 * options-general.php, so calling settings_errors() here would double it.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		?>
		<div class="wrap hopsen">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php Notices::render(); ?>

			<form class="hopsen-form" action="options.php" method="post">
				<?php settings_fields( 'hopsen' ); ?>
				<?php self::sections(); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Prints the two sections and the rows in each.
	 *
	 * Both the sections and their order come from the registry, so nothing here
	 * lists features by name. Every feature is printed, always: one that cannot be
	 * switched on is rendered in place, disabled, saying what is stopping it.
	 *
	 * @return void
	 */
	private static function sections() {
		$features = Registry::all();

		foreach ( Registry::groups() as $group => $section ) {
			$rows = array();

			foreach ( $features as $key => $feature ) {
				if ( $group !== $feature['group'] ) {
					continue;
				}

				$rows[ $key ] = $feature;
			}

			if ( empty( $rows ) ) {
				continue;
			}

			?>
			<section class="hopsen-section">
				<h2 class="hopsen-section__heading"><?php echo esc_html( $section['heading'] ); ?></h2>

				<?php if ( '' !== $section['note'] ) : ?>
					<p class="hopsen-section__note"><?php echo esc_html( $section['note'] ); ?></p>
				<?php endif; ?>

				<?php
				foreach ( $rows as $key => $feature ) {
					Row::render( $key, $feature );
				}
				?>
			</section>
			<?php
		}
	}
}
