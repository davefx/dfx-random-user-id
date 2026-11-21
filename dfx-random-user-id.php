<?php
/**
 * Plugin Name:       DFX Random User IDs
 * Plugin URI:        https://davefx.com/random-user-id
 * Description:       Sets random user IDs for created users. Randomizes the user ID for the default user, if it exists.
 * Version:           20261121
 * Requires at least: 4.9
 * Requires PHP:      7.4
 * Author:            David Marín Carreño (DaveFX)
 * Author URI:        https://davefx.com
 * License:           GPL3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       random-user-ids
 * Domain Path:       /languages
 *
 * @package DFX_Random_User_IDs
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

// Javascript's MAX_SAFE_INTEGER is 9007199254740991, so the largest ID is one bit shorter.
if ( ! defined( 'DFX_RANDOM_USER_ID_DEFAULT_MAX' ) ) {
	define( 'DFX_RANDOM_USER_ID_DEFAULT_MAX', 4503599627370495 );
}

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/libraries/dfx-plugin-showcase/load.php';

if ( ! function_exists( 'dfx_random_user_id_get_max_id' ) ) {

	/**
	 * The largest random user ID: the one saved in the settings page, else the default, and
	 * then whatever the dfx_random_user_id_max_id filter says.
	 *
	 * @return int
	 */
	function dfx_random_user_id_get_max_id() {

		$range = dfx_random_user_id_saved_range();

		return apply_filters( 'dfx_random_user_id_max_id', $range ? $range['max'] : DFX_RANDOM_USER_ID_DEFAULT_MAX );
	}
}

if ( ! function_exists( 'dfx_random_user_id_get_min_id' ) ) {

	/**
	 * The smallest random user ID: the one saved in the settings page, else 1, and then
	 * whatever the dfx_random_user_id_min_id filter says.
	 *
	 * @return int
	 */
	function dfx_random_user_id_get_min_id() {

		$range = dfx_random_user_id_saved_range();

		return apply_filters( 'dfx_random_user_id_min_id', $range ? $range['min'] : 1 );
	}
}

if ( ! function_exists( 'dfx_random_user_id_user_register' ) ) {

	/**
	 * Randomizes the user_id for new created users.
	 *
	 * @param array $data   User data about to be inserted.
	 * @param bool  $update Whether the user is being updated rather than created.
	 *
	 * @return array
	 */
	function dfx_random_user_id_user_register( $data, $update ) {

		if ( ! $update ) {

			// Locate a yet-unused user_id.
			do {
				$random_id = random_int( dfx_random_user_id_get_min_id(), dfx_random_user_id_get_max_id() );
			} while ( get_userdata( $random_id ) );

			$data += array( 'ID' => $random_id );
		}

		return $data;
	}
}

add_filter( 'wp_pre_insert_user_data', 'dfx_random_user_id_user_register', 10, 2 );

if ( ! function_exists( 'dfx_random_user_randomize_first_user' ) ) {

	/**
	 * Randomizes the first admin user id, with ID=1.
	 * This function is only reliable for just-installed WP installations.
	 */
	function dfx_random_user_randomize_first_user() {

		global $wpdb;

		update_option( 'dfx_randomuserid_activation_show_activation_notice', true, true );

		// Check that user with ID=1 exists.
		$user = get_userdata( 1 );
		if ( ! $user ) {

			return;
		}

		// Locate a yet-unused user_id.
		do {
			$new_id = random_int( dfx_random_user_id_get_min_id(), dfx_random_user_id_get_max_id() );
		} while ( get_userdata( $new_id ) );

		// Update the database to randomize the first user ID. No WordPress API changes a user's ID,
		// so the users, usermeta and posts tables are updated directly.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Users table, ID field.
		$wpdb->update( $wpdb->users, array( 'ID' => $new_id ), array( 'ID' => 1 ) );

		// UserMeta table, user_id field.
		$wpdb->update( $wpdb->usermeta, array( 'user_id' => $new_id ), array( 'user_id' => 1 ) );

		// Posts table, post_author field.
		$wpdb->update( $wpdb->posts, array( 'post_author' => $new_id ), array( 'post_author' => 1 ) );

		// phpcs:enable

		// Hook to allow other plugins to update their tables.
		do_action( 'dfx_randomuserid_first_user_id_changed', 1, $new_id );

		update_option( 'dfx_randomuserid_first_user_moved_to', $new_id, false );
	}
}

register_activation_hook( __FILE__, 'dfx_random_user_randomize_first_user' );

if ( ! function_exists( 'dfx_random_user_show_activation_msg' ) ) {

	/**
	 * Tells the administrators who manage the plugin what happened on activation.
	 */
	function dfx_random_user_show_activation_msg() {

		if ( ! get_option( 'dfx_randomuserid_activation_show_activation_notice' ) || ! current_user_can( dfx_random_user_id_capability() ) ) {
			return;
		}

		$first_user_new_id = (int) get_option( 'dfx_randomuserid_first_user_moved_to' );
		$admin_user        = $first_user_new_id ? get_userdata( $first_user_new_id ) : false;
		$paragraphs        = array( __( 'Random User IDs plugin has been successfully activated.', 'random-user-ids' ) );

		if ( ! $first_user_new_id ) {
			$paragraphs[] = __( 'There was no user with ID=1 already, so no user ID has been randomized.', 'random-user-ids' );
		} else {
			/* translators: 1: the user's nickname, 2: the user's new ID. */
			$paragraphs[] = sprintf( __( 'The ID for user `%1$s` has been moved from 1 to %2$d.', 'random-user-ids' ), $admin_user ? $admin_user->nickname : '', $first_user_new_id );
			$paragraphs[] = __( 'This plugin is designed to better being activated in first place, before any other plugin or theme', 'random-user-ids' );
			/* translators: %d: the user's new ID. */
			$paragraphs[] = sprintf( __( 'If there are any previously installed theme or plugin that added tables to the database, you should check if any field in these tables includes user_ids, and manually replace all the rows referencing user_id 1 with the new user_id %d', 'random-user-ids' ), $first_user_new_id );
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'dfx_randomuserid_dismiss_notice', '1' ), 'dfx_randomuserid_dismiss_notice' );

		echo '<div class="updated notice is-dismissible" data-notice="dfx_random_user_activation_msg">';

		foreach ( $paragraphs as $paragraph ) {
			echo '<p>' . esc_html( $paragraph ) . '</p>';
		}

		echo '<p style="text-align: right;"><a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'random-user-ids' ) . '</a></p>';
		echo '</div>';
	}
}

add_action( 'admin_notices', 'dfx_random_user_show_activation_msg' );
add_action( 'network_admin_notices', 'dfx_random_user_show_activation_msg' );

if ( ! function_exists( 'dfx_random_user_dismissed_activation_msg' ) ) {

	/**
	 * Dismisses the activation notice. It needs the link's nonce and the capability that manages
	 * the plugin, so that no one can dismiss it for an administrator with a crafted link.
	 */
	function dfx_random_user_dismissed_activation_msg() {

		if ( ! isset( $_GET['dfx_randomuserid_dismiss_notice'] ) || ! current_user_can( dfx_random_user_id_capability() ) ) {
			return;
		}

		check_admin_referer( 'dfx_randomuserid_dismiss_notice' );

		// Delete both the option to show the notice and the option keeping the new user id
		// to avoid database clutter. The less information in the database, the better.

		delete_option( 'dfx_randomuserid_activation_show_activation_notice' );
		delete_option( 'dfx_randomuserid_first_user_moved_to' );
	}
}

add_action( 'admin_init', 'dfx_random_user_dismissed_activation_msg' );

if ( ! function_exists( 'dfx_random_user_load_textdomain' ) ) {

	/**
	 * Loads the plugin's translations.
	 */
	function dfx_random_user_load_textdomain() {
		load_plugin_textdomain( 'random-user-ids', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}
add_action( 'plugins_loaded', 'dfx_random_user_load_textdomain' );
