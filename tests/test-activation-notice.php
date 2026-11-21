<?php
/**
 * Tests for the admin activation notice and its dismissal handler.
 *
 * @package DFX_Random_User_IDs
 */

/**
 * The activation notice and its dismissal.
 */
class Test_Activation_Notice extends WP_UnitTestCase {

	/**
	 * The notice is for the administrators who manage the plugin.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
	}

	/**
	 * No output when flag is absent.
	 */
	public function test_no_output_when_flag_is_absent() {
		delete_option( 'dfx_randomuserid_activation_show_activation_notice' );

		ob_start();
		dfx_random_user_show_activation_msg();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Notice reports when no user was moved.
	 */
	public function test_notice_reports_when_no_user_was_moved() {
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );
		delete_option( 'dfx_randomuserid_first_user_moved_to' );

		ob_start();
		dfx_random_user_show_activation_msg();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice is-dismissible', $output );
		$this->assertStringContainsString( 'no user ID has been randomized', $output );
	}

	/**
	 * Notice reports the new ID when a user was moved.
	 */
	public function test_notice_reports_the_new_id_when_a_user_was_moved() {
		$user_id = self::factory()->user->create();
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );
		update_option( 'dfx_randomuserid_first_user_moved_to', $user_id );

		ob_start();
		dfx_random_user_show_activation_msg();
		$output = ob_get_clean();

		$this->assertStringContainsString( (string) $user_id, $output );
	}

	/**
	 * The dismiss link carries a nonce, and following it clears both options.
	 */
	public function test_dismissal_clears_both_options() {
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );
		update_option( 'dfx_randomuserid_first_user_moved_to', 12345 );

		$_GET['dfx_randomuserid_dismiss_notice'] = '1';
		$_REQUEST['_wpnonce']                    = wp_create_nonce( 'dfx_randomuserid_dismiss_notice' );
		dfx_random_user_dismissed_activation_msg();
		unset( $_GET['dfx_randomuserid_dismiss_notice'], $_REQUEST['_wpnonce'] );

		$this->assertFalse( get_option( 'dfx_randomuserid_activation_show_activation_notice' ) );
		$this->assertFalse( get_option( 'dfx_randomuserid_first_user_moved_to' ) );
	}

	/**
	 * A crafted link without the nonce must not dismiss the notice.
	 */
	public function test_dismissal_without_nonce_is_refused() {
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );

		$_GET['dfx_randomuserid_dismiss_notice'] = '1';

		try {
			dfx_random_user_dismissed_activation_msg();
			$this->fail( 'A dismissal without nonce must die.' );
		} catch ( WPDieException $e ) {
			$this->assertTrue( (bool) get_option( 'dfx_randomuserid_activation_show_activation_notice' ) );
		} finally {
			unset( $_GET['dfx_randomuserid_dismiss_notice'] );
		}
	}

	/**
	 * Users who cannot manage the plugin neither see nor dismiss the notice.
	 */
	public function test_other_users_neither_see_nor_dismiss_it() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );

		ob_start();
		dfx_random_user_show_activation_msg();
		$this->assertSame( '', ob_get_clean() );

		$_GET['dfx_randomuserid_dismiss_notice'] = '1';
		dfx_random_user_dismissed_activation_msg();
		unset( $_GET['dfx_randomuserid_dismiss_notice'] );

		$this->assertTrue( (bool) get_option( 'dfx_randomuserid_activation_show_activation_notice' ) );
	}

	/**
	 * The moved user's nickname is printed as text, not as HTML.
	 */
	public function test_nickname_is_escaped() {
		// Stored directly: wp_insert_user() would strip the tags before they reach the notice.
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'nickname', '<script>alert(1)</script>' );
		clean_user_cache( $user_id );
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );
		update_option( 'dfx_randomuserid_first_user_moved_to', $user_id );

		ob_start();
		dfx_random_user_show_activation_msg();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}
}
