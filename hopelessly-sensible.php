<?php
/**
 * Plugin Name:       Hopelessly Sensible: Simple Security Hardening
 * Plugin URI:        https://github.com/Hebble-Stone-CIC/hopelessly-sensible
 * Description:       Security hardening for people who have better things to do.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Hebble & Stone
 * Author URI:        https://hebblestone.org
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hopelessly-sensible
 *
 * Copyright (C) 2026 Hebble & Stone.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * @package HopelesslySensible
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version. Also used to bust the admin stylesheet cache.
 */
define( 'HOPSEN_VERSION', '1.0.0' );

/**
 * Absolute path to the main plugin file.
 */
define( 'HOPSEN_FILE', __FILE__ );

/**
 * Absolute path to the plugin directory, with a trailing slash.
 */
define( 'HOPSEN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Public URL of the plugin directory, with a trailing slash.
 */
define( 'HOPSEN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The single option name this plugin writes to. It writes to nothing else.
 */
define( 'HOPSEN_OPTION', 'hopsen_settings' );

/**
 * Current settings schema.
 *
 * Bumped whenever a feature is added to the registry. Existing installs are
 * brought up to the new schema with the new features switched off, so an
 * automatic update never changes how a live site behaves.
 */
define( 'HOPSEN_SCHEMA', 2 );

/*
 * Explicit requires, in dependency order. No autoloader ships with this plugin:
 * the load order being readable in one place is worth more than the brevity.
 */
require_once HOPSEN_DIR . 'inc/class-registry.php';
require_once HOPSEN_DIR . 'inc/class-settings.php';
require_once HOPSEN_DIR . 'inc/class-detection.php';
require_once HOPSEN_DIR . 'inc/class-plugin.php';
require_once HOPSEN_DIR . 'inc/features/class-rest-users.php';
require_once HOPSEN_DIR . 'inc/features/class-login-errors.php';
require_once HOPSEN_DIR . 'inc/features/class-author-urls.php';
require_once HOPSEN_DIR . 'inc/features/class-xmlrpc.php';
require_once HOPSEN_DIR . 'inc/features/class-comments.php';
require_once HOPSEN_DIR . 'inc/features/class-file-edit.php';

/*
 * The settings screen is never loaded on a front-end request. Feature code must
 * not know the admin screen exists, and on the front end it literally does not.
 */
if ( is_admin() ) {
	require_once HOPSEN_DIR . 'inc/admin/class-page.php';
	require_once HOPSEN_DIR . 'inc/admin/class-row.php';
	require_once HOPSEN_DIR . 'inc/admin/class-notices.php';
}

register_activation_hook( __FILE__, array( 'HopelesslySensible\Plugin', 'activate' ) );

/*
 * The one feature wired outside Plugin::boot(). Refusing an XML-RPC request is
 * the only thing this plugin does that ends a request rather than shaping one,
 * so everything WordPress loads between here and init would be wasted on it. See
 * refuse_early() for why it cannot go through Settings.
 */
add_action( 'plugins_loaded', array( 'HopelesslySensible\Features\Xmlrpc', 'refuse_early' ), 0 );

add_action( 'init', array( 'HopelesslySensible\Plugin', 'boot' ), 0 );
add_action( 'init', array( 'HopelesslySensible\Settings', 'register' ) );
add_action( 'update_option_' . HOPSEN_OPTION, array( 'HopelesslySensible\Settings', 'flush' ) );
add_action( 'add_option_' . HOPSEN_OPTION, array( 'HopelesslySensible\Settings', 'flush' ) );

if ( is_admin() ) {
	add_action( 'admin_menu', array( 'HopelesslySensible\Admin\Page', 'register' ) );
	add_action( 'admin_enqueue_scripts', array( 'HopelesslySensible\Admin\Page', 'enqueue' ) );

	/*
	 * The one thing this plugin shows outside its own page, and the only reason
	 * it earns that: it announces a switch we turned off without being asked.
	 */
	add_action( 'admin_notices', array( 'HopelesslySensible\Admin\Notices', 'switched_off_banner' ) );
	add_action( 'admin_init', array( 'HopelesslySensible\Admin\Notices', 'handle_dismiss' ) );
}
