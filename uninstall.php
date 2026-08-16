<?php
/**
 * Uninstall: removes the one row this plugin ever wrote.
 *
 * WordPress runs this file when the plugin is deleted, in a request where the
 * plugin itself is not loaded. Nothing here can use HOPSEN_OPTION or any other
 * constant from the bootstrap, so the option name is written out in full. It is
 * the only place in the plugin where it is.
 *
 * A file rather than register_uninstall_hook(), which stores a callback in the
 * uninstall_plugins option and therefore writes outside our own row to arrange
 * the deletion of our own row.
 *
 * @package HopelesslySensible
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * The single option this plugin stores. Kept in step with HOPSEN_OPTION by hand.
 */
const HOPSEN_UNINSTALL_OPTION = 'hopsen_settings';

if ( ! is_multisite() ) {
	delete_option( HOPSEN_UNINSTALL_OPTION );
	return;
}

/*
 * On a network the settings are per site, so there is one row per site to
 * remove, and deleting only the current site's row would leave the rest behind
 * on a plugin that promises to leave nothing behind.
 *
 * Read in batches. A network with several thousand sites is unusual but not
 * rare, and asking for every site at once on one of those is how an uninstall
 * runs out of memory halfway through, having deleted some of the rows.
 */
$hopsen_batch  = 200;
$hopsen_offset = 0;

do {
	$hopsen_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => $hopsen_batch,
			'offset' => $hopsen_offset,
		)
	);

	$hopsen_found = count( $hopsen_sites );

	foreach ( $hopsen_sites as $hopsen_site_id ) {
		switch_to_blog( $hopsen_site_id );
		delete_option( HOPSEN_UNINSTALL_OPTION );
		restore_current_blog();
	}

	$hopsen_offset += $hopsen_batch;
} while ( $hopsen_found === $hopsen_batch );
