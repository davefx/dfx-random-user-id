<?php
/**
 * Tests for the wp_pre_insert_user_data filter that assigns random IDs
 * to newly created users.
 *
 * @package DFX_Random_User_IDs
 */

class Test_User_Register extends WP_UnitTestCase {

	public function test_updates_are_left_untouched() {
		$data   = array( 'user_login' => 'existing' );
		$result = dfx_random_user_id_user_register( $data, true );

		$this->assertArrayNotHasKey( 'ID', $result );
		$this->assertSame( $data, $result );
	}

	public function test_new_user_data_gets_id_within_range() {
		add_filter( 'dfx_random_user_id_min_id', static fn() => 1000 );
		add_filter( 'dfx_random_user_id_max_id', static fn() => 9999 );

		$result = dfx_random_user_id_user_register( array( 'user_login' => 'new' ), false );

		$this->assertArrayHasKey( 'ID', $result );
		$this->assertGreaterThanOrEqual( 1000, $result['ID'] );
		$this->assertLessThanOrEqual( 9999, $result['ID'] );
	}

	public function test_real_user_creation_lands_in_range() {
		add_filter( 'dfx_random_user_id_min_id', static fn() => 500000 );
		add_filter( 'dfx_random_user_id_max_id', static fn() => 600000 );

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertGreaterThanOrEqual( 500000, $user_id );
		$this->assertLessThanOrEqual( 600000, $user_id );
		$this->assertInstanceOf( WP_User::class, get_userdata( $user_id ) );
	}

	public function test_collision_with_existing_id_is_avoided() {
		// Create a user with a known, fixed ID by disabling our filter first.
		remove_filter( 'wp_pre_insert_user_data', 'dfx_random_user_id_user_register', 10 );
		$taken = self::factory()->user->create();
		add_filter( 'wp_pre_insert_user_data', 'dfx_random_user_id_user_register', 10, 2 );

		// Constrain the range to exactly two IDs, one of which is already used,
		// so the only valid choice is the unused one.
		add_filter( 'dfx_random_user_id_min_id', static fn() => $taken );
		add_filter( 'dfx_random_user_id_max_id', static fn() => $taken + 1 );

		$new = self::factory()->user->create();

		$this->assertSame( $taken + 1, $new );
	}
}
