<?php
/*
Plugin Name: Random User Ids
Plugin URI:  https://davefx.com/random-user-id
Description: Sets random user IDs for created users. Randomizes the user ID for the default user, if it exists.
Version:     20170520
Author:      David Marín Carreño (DaveFX)
Author URI:  https://davefx.com
License:     GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: davefx
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

	}

}

register_activation_hook( __FILE__, 'dfx_random_user_randomize_first_user' );
