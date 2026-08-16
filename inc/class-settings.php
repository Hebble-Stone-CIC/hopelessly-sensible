<?php
/**
 * Option shape, accessor, sanitise and registration.
 *
 * Everything the plugin stores lives in one option. Nothing else on the site is
 * written to, ever, so deactivating the plugin restores the site immediately.
 *
 * See refs/gotchas.md, "Settings".
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and writes the single option this plugin owns.
 */
class Settings {

	/**
	 * The settings as read this request, or null before the first read.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * The option as it looks on a fresh install, before detection has run.
	 *
	 * Public because activation starts from it. Building that starting point from
	 * sanitize() instead would be wrong in a way that looks right: sanitize reads
	 * absence as false, so every feature would arrive switched off, including the
	 * two meant to be on for everybody.
	 *
	 * @return array<string, mixed> A complete, valid settings array.
	 */
	public static function defaults() {
		$features = array();

		foreach ( Registry::all() as $key => $feature ) {
			$features[ $key ] = (bool) $feature['default'];
		}

		return array(
			'schema'   => HOPSEN_SCHEMA,
			'features' => $features,
			'live'     => self::normalise_live( array() ),
		);
	}

	/**
	 * Forces the live values into their declared shape.
	 *
	 * Nothing here records what the site used to look like, only what this plugin
	 * has done to itself: switched_off names features we retreated from, and
	 * dismissed the administrators who have seen the banner saying so. Per-user
	 * state would normally be user meta, which would mean writing outside our own
	 * option, so it is a list of IDs in here.
	 *
	 * @param array<string, mixed> $live Raw live values, from storage or a fresh look.
	 * @return array<string, mixed> The live values, complete and correctly typed.
	 */
	public static function normalise_live( array $live ) {
		$off       = isset( $live['switched_off'] ) && is_array( $live['switched_off'] ) ? $live['switched_off'] : array();
		$dismissed = isset( $live['dismissed'] ) && is_array( $live['dismissed'] ) ? $live['dismissed'] : array();
		$known     = Registry::all();

		$clean_off = array();

		foreach ( $off as $key ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $known ) ) {
				continue;
			}

			if ( in_array( $key, $clean_off, true ) ) {
				continue;
			}

			$clean_off[] = $key;
		}

		$clean_users = array();

		foreach ( $dismissed as $user_id ) {
			$user_id = absint( $user_id );

			if ( 0 === $user_id || in_array( $user_id, $clean_users, true ) ) {
				continue;
			}

			$clean_users[] = $user_id;
		}

		return array(
			'switched_off' => $clean_off,
			'dismissed'    => $clean_users,
		);
	}

	/**
	 * Whether this site has a stored option row at all.
	 *
	 * Core's register_setting() gives the option a default, which get_option()
	 * then substitutes whenever the fallback it was handed is itself falsy. The usual
	 * `false === get_option( $name, false )` test therefore answers wrongly on a
	 * site that has never been set up. A truthy sentinel gets a straight answer.
	 *
	 * @return bool True when the option exists in the database.
	 */
	public static function exists() {
		$sentinel = '__hopsen_no_option__';

		return get_option( HOPSEN_OPTION, $sentinel ) !== $sentinel;
	}

	/**
	 * Reads the stored settings, filling in anything missing.
	 *
	 * Feature code must go through is_enabled() rather than calling get_option().
	 *
	 * @return array<string, mixed> A complete, valid settings array.
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored   = get_option( HOPSEN_OPTION, array() );
		$defaults = self::defaults();

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$live = isset( $stored['live'] ) && is_array( $stored['live'] ) ? $stored['live'] : array();

		$settings = array(
			'schema'   => isset( $stored['schema'] ) ? (int) $stored['schema'] : 0,
			'features' => $defaults['features'],
			'live'     => self::normalise_live( $live ),
		);

		$features = isset( $stored['features'] ) && is_array( $stored['features'] ) ? $stored['features'] : array();

		foreach ( array_keys( $settings['features'] ) as $key ) {
			if ( ! array_key_exists( $key, $features ) ) {
				continue;
			}

			$settings['features'][ $key ] = (bool) $features[ $key ];
		}

		self::$cache = $settings;

		return self::$cache;
	}

	/**
	 * Discards the cached settings after the option is written.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Whether a single feature is switched on.
	 *
	 * @param string $key A feature key from the registry.
	 * @return bool True when the feature is on.
	 */
	public static function is_enabled( $key ) {
		$settings = self::get();

		return ! empty( $settings['features'][ $key ] );
	}

	/**
	 * Reads one live value.
	 *
	 * @param string $key A key from normalise_live().
	 * @return mixed The value, or false for anything unknown.
	 */
	public static function live( $key ) {
		$settings = self::get();

		return isset( $settings['live'][ $key ] ) ? $settings['live'][ $key ] : false;
	}

	/**
	 * Switches a feature off because something has made it unsafe to leave on.
	 *
	 * The one thing this plugin does to itself without being asked, and it only
	 * ever goes in this direction. Dismissals are cleared at the same time,
	 * because a fresh retreat is news again.
	 *
	 * @param string $key A feature key from the registry.
	 * @return void
	 */
	public static function switch_off( $key ) {
		$settings = self::get();

		if ( ! array_key_exists( $key, $settings['features'] ) ) {
			return;
		}

		if ( false === $settings['features'][ $key ] ) {
			return;
		}

		$stored = get_option( HOPSEN_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return;
		}

		$features = isset( $stored['features'] ) && is_array( $stored['features'] ) ? $stored['features'] : array();
		$live     = isset( $stored['live'] ) && is_array( $stored['live'] ) ? $stored['live'] : array();

		$features[ $key ] = false;

		$off = isset( $live['switched_off'] ) && is_array( $live['switched_off'] ) ? $live['switched_off'] : array();

		if ( ! in_array( $key, $off, true ) ) {
			$off[] = $key;
		}

		$live['switched_off'] = $off;
		$live['dismissed']    = array();

		$stored['features'] = $features;
		$stored['live']     = self::normalise_live( $live );

		update_option( HOPSEN_OPTION, $stored );
		self::flush();
	}

	/**
	 * Records that an administrator has seen the announcement.
	 *
	 * @param int $user_id The administrator dismissing it.
	 * @return void
	 */
	public static function dismiss( $user_id ) {
		$user_id = absint( $user_id );

		if ( 0 === $user_id ) {
			return;
		}

		$dismissed = self::live( 'dismissed' );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();

		if ( in_array( $user_id, $dismissed, true ) ) {
			return;
		}

		$dismissed[] = $user_id;

		self::write_live( array( 'dismissed' => $dismissed ) );
	}

	/**
	 * Merges a set of changes into the live block and writes the option.
	 *
	 * Reads the row again rather than writing back the cached copy, so a change
	 * made elsewhere in the same request is not undone on the way past.
	 *
	 * @param array<string, mixed> $changes Live keys to their new values.
	 * @return void
	 */
	private static function write_live( array $changes ) {
		$stored = get_option( HOPSEN_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return;
		}

		$live = isset( $stored['live'] ) && is_array( $stored['live'] ) ? $stored['live'] : array();

		foreach ( $changes as $key => $value ) {
			$live[ $key ] = $value;
		}

		$stored['live'] = self::normalise_live( $live );

		update_option( HOPSEN_OPTION, $stored );
		self::flush();
	}

	/**
	 * Substitutes {token} placeholders in a piece of copy.
	 *
	 * The copy names the number it wants, so nothing has to declare which one
	 * belongs to which sentence.
	 *
	 * @param string             $text   Copy containing {token} placeholders.
	 * @param array<string, int> $counts Token name, without braces, to number.
	 * @return string The copy with its placeholders filled in.
	 */
	public static function fill_counts( $text, array $counts ) {
		$replacements = array();

		foreach ( $counts as $token => $count ) {
			$replacements[ '{' . $token . '}' ] = number_format_i18n( $count );
		}

		return strtr( $text, $replacements );
	}

	/**
	 * Brings an older install up to the current schema.
	 *
	 * New features arrive switched off on sites that already have the plugin, so
	 * an automatic update never changes how a live site behaves. This cannot live
	 * on the activation hook, which core does not fire on an in-place update.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_schema() {
		if ( false === self::exists() ) {
			return;
		}

		$stored = get_option( HOPSEN_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return;
		}

		$schema = isset( $stored['schema'] ) ? (int) $stored['schema'] : 0;

		if ( HOPSEN_SCHEMA <= $schema ) {
			return;
		}

		$features = isset( $stored['features'] ) && is_array( $stored['features'] ) ? $stored['features'] : array();

		foreach ( array_keys( Registry::all() ) as $key ) {
			if ( array_key_exists( $key, $features ) ) {
				continue;
			}

			$features[ $key ] = false;
		}

		// Schema 1 kept a block of findings from activation. Nothing reads it now.
		unset( $stored['detected'] );

		/*
		 * The live block is normalised here because nothing else on this path does,
		 * and the write below runs before register_setting(), so the sanitise
		 * callback is not attached to catch it.
		 */
		$live = isset( $stored['live'] ) && is_array( $stored['live'] ) ? $stored['live'] : array();

		$stored['live']     = self::normalise_live( $live );
		$stored['schema']   = HOPSEN_SCHEMA;
		$stored['features'] = $features;

		update_option( HOPSEN_OPTION, $stored );
		self::flush();
	}

	/**
	 * Registers the option with the settings API and with REST.
	 *
	 * We never call REST ourselves. The schema is here so that a future JavaScript
	 * screen needs no backend work.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			'hopsen',
			HOPSEN_OPTION,
			array(
				'type'              => 'object',
				'description'       => __( 'Hopelessly Sensible settings.', 'hopelessly-sensible' ),
				'default'           => self::defaults(),
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'show_in_rest'      => array(
					'schema' => self::rest_schema(),
				),
			)
		);
	}

	/**
	 * The REST schema for the option, built from the registry.
	 *
	 * @return array<string, mixed> A JSON schema fragment.
	 */
	private static function rest_schema() {
		$features = array();

		foreach ( Registry::all() as $key => $feature ) {
			$features[ $key ] = array(
				'type'        => 'boolean',
				'description' => $feature['label'],
			);
		}

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'schema'   => array(
					'type' => 'integer',
				),
				'features' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => $features,
				),
				'live'     => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'switched_off' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
						'dismissed'    => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'integer',
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Sanitises a posted settings array.
	 *
	 * Two ways to lose data here, and they look identical on the way in:
	 *
	 * 1. Unchecked checkboxes do not post at all, so the result is built by
	 *    walking the registry rather than the posted array.
	 * 2. A disabled checkbox on a blocked row posts nothing either. Identical
	 *    silence, opposite meaning, so its stored value is kept rather than read
	 *    as false.
	 *
	 * A feature switched back on here is dropped from switched_off.
	 *
	 * @param mixed $input The raw posted value.
	 * @return array<string, mixed> A complete, valid settings array.
	 */
	public static function sanitize( $input ) {
		$stored = self::get();
		$posted = array();

		/*
		 * An input carrying a live block did not come from the form, which never
		 * posts one, so the live values are taken from it rather than from storage.
		 * That signature matters: the blocked-row rule below would otherwise put
		 * back the feature switch_off() has just set to false, and every retreat
		 * would undo itself.
		 */
		$live = $stored['live'];
		$ours = is_array( $input ) && isset( $input['live'] ) && is_array( $input['live'] );

		if ( true === $ours ) {
			$live = self::normalise_live( $input['live'] );
		}

		if ( is_array( $input ) && isset( $input['features'] ) && is_array( $input['features'] ) ) {
			$posted = $input['features'];
		}

		$features = array();

		foreach ( Registry::all() as $key => $feature ) {
			if ( false === $ours && null !== Registry::blocker( $feature ) ) {
				$features[ $key ] = ! empty( $stored['features'][ $key ] );
				continue;
			}

			$features[ $key ] = ! empty( $posted[ $key ] );
		}

		$announced = array();

		foreach ( $live['switched_off'] as $key ) {
			if ( ! empty( $features[ $key ] ) ) {
				continue;
			}

			$announced[] = $key;
		}

		$live['switched_off'] = $announced;

		return array(
			'schema'   => HOPSEN_SCHEMA,
			'features' => $features,
			'live'     => $live,
		);
	}
}
