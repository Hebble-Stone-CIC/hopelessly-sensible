<?php
/**
 * Plugin lifecycle: what happens at activation, and what happens each request.
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible;

defined( 'ABSPATH' ) || exit;

/**
 * Starts the plugin, and sets it up the first time it is switched on.
 */
class Plugin {

	/**
	 * Starts every feature the site owner has switched on.
	 *
	 * On init rather than plugins_loaded because the registry carries translatable
	 * copy, and WordPress 6.7 and later warns about translating before init. Every
	 * hook a feature needs fires later than this.
	 *
	 * @return void
	 */
	public static function boot() {
		Settings::maybe_upgrade_schema();

		add_action( 'wp_loaded', array( self::class, 'retreat' ) );

		foreach ( Registry::all() as $key => $feature ) {
			if ( true !== Settings::is_enabled( $key ) ) {
				continue;
			}

			if ( null !== Registry::blocker( $feature ) ) {
				continue;
			}

			call_user_func( $feature['callback'] );
		}
	}

	/**
	 * Switches off any feature that something has since made unsafe to run.
	 *
	 * On wp_loaded, because that is the first moment every plugin has registered
	 * what it is going to register. Asking at init would retreat from things
	 * nobody is doing yet. Nothing is written unless a switch actually moves.
	 *
	 * @return void
	 */
	public static function retreat() {
		foreach ( Registry::all() as $key => $feature ) {
			if ( true !== Settings::is_enabled( $key ) ) {
				continue;
			}

			$blocker = Registry::blocker( $feature );

			if ( null === $blocker || false === $blocker['retreat'] ) {
				continue;
			}

			Settings::switch_off( $key );
		}
	}

	/**
	 * Looks at the site and writes the option, once.
	 *
	 * Re-activating a plugin that has already been set up leaves every choice the
	 * site owner has made alone.
	 *
	 * Network activation is refused rather than half honoured: the hook fires once
	 * for the whole network, in whichever site the network administrator happened
	 * to be looking at, so detecting there would set that one site up from its own
	 * evidence and leave every other site undetected. The settings screen says so
	 * instead.
	 *
	 * @param bool $network_wide Whether the plugin was activated across a network.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( true === $network_wide ) {
			return;
		}

		if ( true === Settings::exists() ) {
			return;
		}

		$settings = Settings::defaults();

		foreach ( Detection::opening_states() as $key => $enabled ) {
			$settings['features'][ $key ] = $enabled;
		}

		/*
		 * Autoload is spelled out because the option is read on init of every
		 * request. The empty third argument is core's long-deprecated parameter.
		 */
		add_option( HOPSEN_OPTION, $settings, '', 'yes' );
		Settings::flush();
	}
}
