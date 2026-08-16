<?php
/**
 * A WooCommerce that is not WooCommerce.
 *
 * The plugin asks whether WooCommerce is active by looking for this class and
 * nothing else, so an empty one is enough to exercise every WooCommerce branch
 * in the tests. Loading a shop to answer a question about one post type would
 * be a great deal of machinery for very little more confidence.
 *
 * A class cannot be undeclared, so this makes WooCommerce active for the whole
 * suite. Tests that need a product register the post type themselves, and the
 * core test case unregisters it again afterwards.
 *
 * @package HopelesslySensible
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {

	/**
	 * Stands in for the real class, which is never loaded here.
	 */
	class WooCommerce {}
}

/*
 * A version to go with it, because one branch of this plugin cares which one is
 * running: review moderation moved to its own screen under Products in 6.7, and
 * below that it still lives on the comments screen. A modern version here makes
 * the ordinary path the default in the suite. The old path cannot be reached by
 * changing this, since a constant is a constant for the whole process, so the
 * version comparison takes an argument and is tested directly.
 */
if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '11.0.1' );
}
