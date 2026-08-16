<?php
/**
 * Renders one feature row from its registry entry.
 *
 * Everything on the row comes out of the registry, and nothing here knows which
 * feature it is looking at. Adding a feature in a later version means a registry
 * entry and a file in inc/features/, and this file does not change. If it ever
 * has to, the registry is missing something.
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Admin;

use HopelesslySensible\Detection;
use HopelesslySensible\Registry;
use HopelesslySensible\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the markup for a single feature.
 */
class Row {

	/**
	 * Prints one row: toggle, label, description, state line, warning.
	 *
	 * The toggle is a real checkbox styled by CSS alone. Deliberately not
	 * role="switch", which would need aria-checked kept up to date in JavaScript
	 * this plugin does not ship.
	 *
	 * A blocked row is never skipped. Its checkbox is disabled, its blocker stands
	 * in for the state line, and it carries no warning.
	 *
	 * @param string               $key     The feature key.
	 * @param array<string, mixed> $feature The registry entry for it.
	 * @return void
	 */
	public static function render( $key, array $feature ) {
		$id      = 'hopsen-' . str_replace( '_', '-', $key );
		$blocker = Registry::blocker( $feature );
		$enabled = Settings::is_enabled( $key );
		$open    = ( true === $feature['warning_open'] ) ? ' open' : '';

		if ( null !== $blocker && null !== $blocker['checked'] ) {
			$enabled = $blocker['checked'];
		}

		$state   = self::state_line( $key, $feature, $blocker, $enabled );
		$classes = 'hopsen-row' . ( null !== $blocker ? ' hopsen-row--blocked' : '' );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<div class="hopsen-row__switch">
				<input
					type="checkbox"
					class="hopsen-toggle"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( HOPSEN_OPTION ); ?>[features][<?php echo esc_attr( $key ); ?>]"
					value="1"
					<?php checked( $enabled ); ?>
					<?php disabled( null !== $blocker ); ?>
				/>
			</div>

			<div class="hopsen-row__body">
				<label class="hopsen-row__label" for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $feature['label'] ); ?>
				</label>

				<p class="hopsen-row__description"><?php echo esc_html( $feature['description'] ); ?></p>

				<?php if ( '' !== $state ) : ?>
					<p class="hopsen-row__state"><?php echo esc_html( $state ); ?></p>
				<?php endif; ?>

				<?php if ( null === $blocker ) : ?>
					<details class="hopsen-row__warning"<?php echo esc_attr( $open ); ?>>
						<summary><?php esc_html_e( 'What might this break?', 'hopelessly-sensible' ); ?></summary>
						<p><?php echo esc_html( $feature['warning'] ); ?></p>
					</details>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * What is worth saying about this feature right now, if anything.
	 *
	 * A blocker wins. Otherwise the question goes to Detection, which answers from
	 * the site as it is on this request rather than from anything stored. Empty is
	 * an ordinary answer, and the row does without a third line.
	 *
	 * @param string                    $key     The feature key.
	 * @param array<string, mixed>      $feature The registry entry for it.
	 * @param array<string, mixed>|null $blocker What is stopping it, if anything.
	 * @param bool                      $enabled Whether the row is reading as on.
	 * @return string The state line, or an empty string.
	 */
	private static function state_line( $key, array $feature, $blocker, $enabled ) {
		$lines = isset( $feature['state_line'] ) ? (array) $feature['state_line'] : array();

		if ( empty( $lines ) ) {
			return '';
		}

		$variant = null !== $blocker ? $blocker['variant'] : Detection::state_variant( $key, $enabled );

		if ( null === $variant || ! isset( $lines[ $variant ] ) ) {
			return '';
		}

		return Settings::fill_counts( $lines[ $variant ], Detection::counts( $key, $variant ) );
	}
}
