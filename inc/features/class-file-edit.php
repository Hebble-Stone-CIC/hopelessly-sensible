<?php
/**
 * Lock the file editor.
 *
 * WordPress ships an editor that writes to theme and plugin files from the
 * dashboard, which turns a compromised administrator account into a shell.
 *
 * The lock is core's own constant rather than a capability change: taking
 * edit_themes and edit_plugins off the administrator role would write to the
 * roles option and survive deactivation, which this plugin promises never to do.
 *
 * @package HopelesslySensible
 */

namespace HopelesslySensible\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Takes the theme and plugin file editors out of the dashboard.
 */
class File_Edit {

	/**
	 * Whether the constant was already defined when this feature started.
	 *
	 * Null until the feature runs, which on a site where it is switched off is
	 * for ever.
	 *
	 * @var bool|null
	 */
	private static $config_defined = null;

	/**
	 * Whether wp-config.php, rather than this plugin, defines the constant.
	 *
	 * The question the settings screen needs, and defined() alone cannot answer
	 * it: once this feature has run the constant is defined either way. With the
	 * feature switched off, init() never runs and defined() is the whole answer.
	 *
	 * @return bool True when the constant comes from outside this plugin.
	 */
	public static function config_defines_it() {
		if ( null !== self::$config_defined ) {
			return self::$config_defined;
		}

		return defined( 'DISALLOW_FILE_EDIT' );
	}

	/**
	 * Defines the core constant that turns the editors off.
	 *
	 * Core consults DISALLOW_FILE_EDIT in map_meta_cap() and when it builds the
	 * Appearance and Plugins menus, both later than init. The defined() guard
	 * keeps sites that set this in wp-config.php from taking a redefinition notice
	 * on every request. A constant cannot be undefined, so switching the feature
	 * back off restores the editors on the next page load rather than this one.
	 *
	 * @return void
	 */
	public static function init() {
		/*
		 * Recorded once and then left alone. Asking again on a second call would
		 * answer yes on every site, because by then this feature has defined the
		 * constant itself, and the settings screen would go on to tell somebody
		 * about a line in their wp-config.php that they never wrote.
		 */
		if ( null === self::$config_defined ) {
			self::$config_defined = defined( 'DISALLOW_FILE_EDIT' );
		}

		if ( defined( 'DISALLOW_FILE_EDIT' ) ) {
			return;
		}

		define( 'DISALLOW_FILE_EDIT', true );
	}
}
