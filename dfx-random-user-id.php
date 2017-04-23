<?php
/*
Plugin Name: Random User Ids
Plugin URI:  https://davefx.com/random-user-id
Description: Sets random user IDs for created users
Version:     20170423
Author:      David Marín Carreño (DaveFX)
Author URI:  https://davefx.com
License:     GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: davefx
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! function_exists( 'dfx_random_user_id_user_register' ) ) {

	function dfx_random_user_id_user_register( $data, $update ) {

		if ( ! $update )  {
			do {
				$ID = random_int( 1, PHP_INT_MAX );
			} while ( get_userdata( $ID ) );

			$data += compact( 'ID' );
		}

		return $data;
	}

}

add_filter( 'wp_insert_user', 'dfx_random_user_id_user_register', 10, 2 );

