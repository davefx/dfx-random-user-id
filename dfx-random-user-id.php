<?php
/*
Plugin Name: Random User IDs
Plugin URI:  https://davefx.com/random-user-id
Description: Sets random user IDs for created users. Randomizes the user ID for the default user, if it exists.
Version:     20170520
Author:      David Marín Carreño (DaveFX)
Author URI:  https://davefx.com
License:     GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: dfx-random-user-id
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

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
				$ID = random_int( 1, PHP_INT_MAX );
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
			$new_id = random_int( 1, PHP_INT_MAX );
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

			$message = '<p>' . __( 'Random User IDs plugin has been successfully activated.', 'dfx-random-user-id' ) . '</p>';

			if ( ! $first_user_new_id ) {
				$message .= '<p>' . __( 'There was no user with ID=1 already, so no user ID has been randomized.', 'dfx-random-user-id' ) . '</p>';
			} else {

				$admin_user = get_userdata( $first_user_new_id );

				$message .= sprintf( '<p>' . __( 'The ID for user `%s` has been moved from 1 to %d.', 'dfx-random-user-id' ) . '</p>', $admin_user->nickname, $first_user_new_id );

				$message .= '<p>' . __( 'This plugin is designed to better being activated in first place, before any other plugin or theme', 'dfx-random-user-id' ) . '</p>';

				$message .= '<p>' . sprintf( __( 'If there are any previously installed theme or plugin that added tables to the database, you should check if any field in these tables includes user_ids, and manually replace all the rows referencing user_id 1 with the new user_id %d', 'dfx-random-user-id' ), $first_user_new_id ) . '</p>';

			}

			?>
			<div class="updated notice is-dismissible" data-notice="dfx_random_user_activation_msg">
				<p><?= $message ?></p>
				<p style="text-align: right;"><a href="?dfx_randomuserid_dismiss_notice"><?= __( 'Dismiss', 'dfx-random-user-id' ) ?></a></p>
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

			delete_option( 'dfx_randomuserid_activation_notice_dismissed' );
			delete_option( 'dfx_randomuserid_first_user_moved_to' );

		}
	}
}

add_action( 'admin_init', 'dfx_random_user_dismissed_activation_msg' );
