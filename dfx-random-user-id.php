<?php
/*
Plugin Name: Random User IDs
Plugin URI:  https://davefx.com/random-user-id
Description: Sets random user IDs for created users. Randomizes the user ID for the default user, if it exists.
Version:     20201115.1
Author:      David Marín Carreño (DaveFX)
Author URI:  https://davefx.com
License:     GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: random-user-ids
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! function_exists( 'dfx_random_user_id_get_max_id' ) ) {

	function dfx_random_user_id_get_max_id() {

		// Javascript MAX_SAFE_INTEGER = 9007199254740991
		// so we define the maximum ID to be one bit shorter

		return apply_filters( 'dfx_random_user_id_max_id', ( ( 9007199254740991 + 1 ) / 2 ) - 1 );
	}
}

if ( ! function_exists( 'dfx_random_user_id_get_min_id' ) ) {

    function dfx_random_user_id_get_min_id() {

        // Javascript MAX_SAFE_INTEGER = 9007199254740991
        // so we define the maximum ID to be one bit shorter

        return apply_filters( 'dfx_random_user_id_min_id', 1 );
    }
}

if ( ! function_exists( 'dfx_random_user_id_user_register' ) ) {

	/**
	 * Randomizes the user_id for new created users
	 *
	 * @param array $data
	 * @param bool $update
	 *
	 * @return array
	 */
	function dfx_random_user_id_user_register( $data, $update ) {

		if ( ! $update )  {

			// Locate a yet-unused user_id
			do {
				$ID = random_int( dfx_random_user_id_get_min_id(), dfx_random_user_id_get_max_id() );
			} while ( get_userdata( $ID ) );

			$data += compact( 'ID' );
		}

		return $data;
	}

}

add_filter( 'wp_pre_insert_user_data', 'dfx_random_user_id_user_register', 10, 2 );


if ( ! function_exists( 'dfx_random_user_randomize_first_user' ) ) {

	/**
	 * Randomizes the first admin user id, with ID=1
	 * This function is only reliable for just-installed WP installations
	 */
	function dfx_random_user_randomize_first_user() {

		global $wpdb;

		update_option( 'dfx_randomuserid_activation_show_activation_notice', true, true );

		// Check that user with ID=1 exists
		$user = get_userdata( 1 );
		if ( ! $user ) {

			return;
		}

		// Locate a yet-unused user_id
		do {
			$new_id = random_int( 1, dfx_random_user_id_get_max_id() );
		} while ( get_userdata( $new_id ) );


		// Update database to randomize first user ID

		// Users table. ID field
		$wpdb->update( $wpdb->users, array( 'ID' => $new_id ), array( 'ID' => 1 ) );

		// UserMeta table. user_id field
		$wpdb->update( $wpdb->usermeta, array( 'user_id' => $new_id ), array( 'user_id' => 1 ) );

		// Posts table. post_author field
		$wpdb->update( $wpdb->posts, array( 'post_author' => $new_id ), array( 'post_author' => 1 ) );

		// Hook to allow other plugins to update their tables
		do_action( 'dfx_randomuserid_first_user_id_changed', 1, $new_id );

		update_option('dfx_randomuserid_first_user_moved_to', $new_id, false );

	}

}

register_activation_hook( __FILE__, 'dfx_random_user_randomize_first_user' );

if ( ! function_exists( 'dfx_random_user_show_activation_msg') ) {
	function dfx_random_user_show_activation_msg() {

		if ( get_option( 'dfx_randomuserid_activation_show_activation_notice' ) ) {

			$first_user_new_id = get_option( 'dfx_randomuserid_first_user_moved_to' );

			$message = '<p>' . __( 'Random User IDs plugin has been successfully activated.', 'random-user-ids' ) . '</p>';

			if ( ! $first_user_new_id ) {
				$message .= '<p>' . __( 'There was no user with ID=1 already, so no user ID has been randomized.', 'random-user-ids' ) . '</p>';
			} else {

				$admin_user = get_userdata( $first_user_new_id );

				$message .= sprintf( '<p>' . __( 'The ID for user `%s` has been moved from 1 to %d.', 'random-user-ids' ) . '</p>', $admin_user->nickname, $first_user_new_id );

				$message .= '<p>' . __( 'This plugin is designed to better being activated in first place, before any other plugin or theme', 'random-user-ids' ) . '</p>';

				$message .= '<p>' . sprintf( __( 'If there are any previously installed theme or plugin that added tables to the database, you should check if any field in these tables includes user_ids, and manually replace all the rows referencing user_id 1 with the new user_id %d', 'random-user-ids' ), $first_user_new_id ) . '</p>';

			}

			?>
			<div class="updated notice is-dismissible" data-notice="dfx_random_user_activation_msg">
				<p><?= $message ?></p>
				<p style="text-align: right;"><a href="?dfx_randomuserid_dismiss_notice"><?= __( 'Dismiss', 'random-user-ids' ) ?></a></p>
			</div>
			<?php
		}
	}
}

add_action( 'admin_notices', 'dfx_random_user_show_activation_msg' );

if ( ! function_exists( 'dfx_random_user_dismissed_activation_msg') ) {
	function dfx_random_user_dismissed_activation_msg() {

		if (isset($_GET['dfx_randomuserid_dismiss_notice'])) {

			// Delete both the option to show the notice and the option keeping the new user id
			// to avoid database clutter. The less information in the database, the better.

			delete_option( 'dfx_randomuserid_activation_show_activation_notice' );
			delete_option( 'dfx_randomuserid_first_user_moved_to' );

		}
	}
}

add_action( 'admin_init', 'dfx_random_user_dismissed_activation_msg' );

if ( ! function_exists( 'dfx_random_user_load_textdomain' ) ) {
	function dfx_random_user_load_textdomain() {
		load_plugin_textdomain( 'random-user-ids', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );
	}
}
add_action('plugins_loaded', 'dfx_random_user_load_textdomain');
