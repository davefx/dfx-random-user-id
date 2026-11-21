<?php
/**
 * Tests for the activation routine that randomizes the default user (ID 1).
 *
 * @package DFX_Random_User_IDs
 */

/**
 * The activation routine that moves the default user.
 */
class Test_Activation extends WP_UnitTestCase {

	/**
	 * Keep the randomized range small so the rewritten ID is easy to assert on.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'dfx_random_user_id_max_id', static fn() => 100000 );
	}

	/**
	 * First user is moved off ID one.
	 */
	public function test_first_user_is_moved_off_id_one() {
		// Sanity: the test install ships with an admin user at ID 1.
		$this->assertInstanceOf( WP_User::class, get_userdata( 1 ) );

		dfx_random_user_randomize_first_user();
		wp_cache_flush();

		$new_id = (int) get_option( 'dfx_randomuserid_first_user_moved_to' );

		$this->assertGreaterThan( 1, $new_id );
		$this->assertLessThanOrEqual( 100000, $new_id );
		$this->assertFalse( get_userdata( 1 ), 'User ID 1 should no longer exist.' );
		$this->assertInstanceOf( WP_User::class, get_userdata( $new_id ) );
	}

	/**
	 * Post authorship follows the moved user.
	 */
	public function test_post_authorship_follows_the_moved_user() {
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );

		dfx_random_user_randomize_first_user();
		wp_cache_flush();

		$new_id = (int) get_option( 'dfx_randomuserid_first_user_moved_to' );

		$this->assertSame( $new_id, (int) get_post( $post_id )->post_author );
	}

	/**
	 * Change action fires with old and new IDs.
	 */
	public function test_change_action_fires_with_old_and_new_ids() {
		$captured = array();
		add_action(
			'dfx_randomuserid_first_user_id_changed',
			static function ( $old_id, $new_id ) use ( &$captured ) {
				$captured = array( (int) $old_id, (int) $new_id );
			},
			10,
			2
		);

		dfx_random_user_randomize_first_user();

		$new_id = (int) get_option( 'dfx_randomuserid_first_user_moved_to' );

		$this->assertSame( array( 1, $new_id ), $captured );
	}

	/**
	 * Activation notice flag is set.
	 */
	public function test_activation_notice_flag_is_set() {
		dfx_random_user_randomize_first_user();

		$this->assertTrue( (bool) get_option( 'dfx_randomuserid_activation_show_activation_notice' ) );
	}

	/**
	 * No user one only sets the notice flag.
	 */
	public function test_no_user_one_only_sets_the_notice_flag() {
		// Remove the default user so get_userdata( 1 ) returns false. On multisite it is a super
		// admin, and WordPress refuses to delete super admins, so it stops being one first.
		if ( is_multisite() ) {
			update_site_option( 'site_admins', array() );
		}
		$this->assertTrue( self::delete_user( 1 ), 'The default user must be deleted for this test.' );
		wp_cache_flush();

		dfx_random_user_randomize_first_user();

		$this->assertTrue( (bool) get_option( 'dfx_randomuserid_activation_show_activation_notice' ) );
		$this->assertFalse( get_option( 'dfx_randomuserid_first_user_moved_to' ) );
	}
}
