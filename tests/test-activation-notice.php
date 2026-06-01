<?php
/**
 * Tests for the admin activation notice and its dismissal handler.
 *
 * @package DFX_Random_User_IDs
 */

class Test_Activation_Notice extends WP_UnitTestCase {

	public function test_no_output_when_flag_is_absent() {
		delete_option( 'dfx_randomuserid_activation_show_activation_notice' );

		ob_start();
		dfx_random_user_show_activation_msg();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_notice_reports_when_no_user_was_moved() {
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );
		delete_option( 'dfx_randomuserid_first_user_moved_to' );

		ob_start();
		dfx_random_user_show_activation_msg();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice is-dismissible', $output );
		$this->assertStringContainsString( 'no user ID has been randomized', $output );
	}

	public function test_notice_reports_the_new_id_when_a_user_was_moved() {
		$user_id = self::factory()->user->create();
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );
		update_option( 'dfx_randomuserid_first_user_moved_to', $user_id );

		ob_start();
		dfx_random_user_show_activation_msg();
		$output = ob_get_clean();

		$this->assertStringContainsString( (string) $user_id, $output );
	}

	public function test_dismissal_clears_both_options() {
		update_option( 'dfx_randomuserid_activation_show_activation_notice', true );
		update_option( 'dfx_randomuserid_first_user_moved_to', 12345 );

		$_GET['dfx_randomuserid_dismiss_notice'] = '1';
		dfx_random_user_dismissed_activation_msg();
		unset( $_GET['dfx_randomuserid_dismiss_notice'] );

		$this->assertFalse( get_option( 'dfx_randomuserid_activation_show_activation_notice' ) );
		$this->assertFalse( get_option( 'dfx_randomuserid_first_user_moved_to' ) );
	}
}
