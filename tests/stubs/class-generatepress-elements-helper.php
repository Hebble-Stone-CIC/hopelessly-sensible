<?php
/**
 * A GeneratePress that is not GeneratePress.
 *
 * The plugin asks whether GeneratePress can run PHP in a hook element by
 * looking for this class and nothing else, so an empty one is enough to
 * exercise every branch. See refs/gotchas.md, "GeneratePress", for why the
 * class is the right question: gp-premium.php requires the Elements module at
 * file scope, so the class is absent exactly when there is nothing to break.
 *
 * A class cannot be undeclared, so this makes the Elements module present for
 * the whole suite. That is the harder case, and the one worth having as the
 * default: every test that touches the file editor blocker now runs the
 * element query rather than skipping it. Tests that want elements register the
 * post type and create them.
 *
 * @package HopelesslySensible
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GeneratePress_Elements_Helper' ) ) {

	/**
	 * Stands in for the real class, which is never loaded here.
	 */
	class GeneratePress_Elements_Helper {}
}
