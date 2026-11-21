<?php
/**
 * DFX Plugin Showcase: the "More plugins" tab shared by every DaveFX plugin.
 *
 * Every plugin bundles a copy of this library and requires this file. Each copy announces its
 * version, and only the newest copy's class is ever loaded, as Action Scheduler and the
 * Freemius SDK do. So two plugins with different copies never declare the class twice.
 *
 * The functions below are the library's whole public API. They are defined by whichever copy
 * loads first, so they must stay small and backward compatible: change them only by adding.
 *
 * @package DFX_Plugin_Showcase
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['dfx_plugin_showcase']['candidates'][] = array(
	'version' => '1.0.1',
	'file'    => __DIR__ . '/class-dfx-plugin-showcase.php',
);

if ( ! function_exists( 'dfx_plugin_showcase_boot' ) ) {

	/**
	 * Loads the newest announced copy of the library, once per request.
	 *
	 * It runs on plugins_loaded, when every plugin has announced its copy. The render functions
	 * also call it, in case a plugin renders before that.
	 *
	 * @return bool Whether the library is loaded.
	 */
	function dfx_plugin_showcase_boot() {
		if ( class_exists( 'DFX_Plugin_Showcase', false ) ) {
			return true;
		}

		$candidates = isset( $GLOBALS['dfx_plugin_showcase']['candidates'] ) && is_array( $GLOBALS['dfx_plugin_showcase']['candidates'] ) ? $GLOBALS['dfx_plugin_showcase']['candidates'] : array();

		usort(
			$candidates,
			static function ( $a, $b ) {
				return version_compare( (string) $b['version'], (string) $a['version'] );
			}
		);

		foreach ( $candidates as $candidate ) {
			if ( is_readable( $candidate['file'] ) ) {
				require_once $candidate['file'];
				$GLOBALS['dfx_plugin_showcase']['version'] = (string) $candidate['version'];
				break;
			}
		}

		return class_exists( 'DFX_Plugin_Showcase', false );
	}

	add_action( 'plugins_loaded', 'dfx_plugin_showcase_boot', 1 );

	/**
	 * The tab's label, in the admin user's language: "More plugins" by default.
	 *
	 * @return string Plain text; escape it on output.
	 */
	function dfx_plugin_showcase_label() {
		return dfx_plugin_showcase_boot() ? DFX_Plugin_Showcase::instance()->label() : 'More plugins';
	}

	/**
	 * Prints the catalog of the author's other plugins.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type string|string[] $exclude Catalog ids not to show: at least the calling plugin's own.
	 * }
	 */
	function dfx_plugin_showcase_render( $args = array() ) {
		if ( dfx_plugin_showcase_boot() ) {
			DFX_Plugin_Showcase::instance()->render( is_array( $args ) ? $args : array() );
		}
	}
}
