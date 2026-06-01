<?php
/**
 * Tests for the configurable min/max ID range helpers.
 *
 * @package DFX_Random_User_IDs
 */

class Test_Id_Range extends WP_UnitTestCase {

	public function test_default_max_id_is_half_of_js_max_safe_integer() {
		// MAX_SAFE_INTEGER (9007199254740991) -> ( ( n + 1 ) / 2 ) - 1.
		$this->assertSame( 4503599627370495, dfx_random_user_id_get_max_id() );
	}

	public function test_default_min_id_is_one() {
		$this->assertSame( 1, dfx_random_user_id_get_min_id() );
	}

	public function test_max_id_is_filterable() {
		$callback = static function () {
			return 9999;
		};
		add_filter( 'dfx_random_user_id_max_id', $callback );

		$this->assertSame( 9999, dfx_random_user_id_get_max_id() );

		remove_filter( 'dfx_random_user_id_max_id', $callback );
	}

	public function test_min_id_is_filterable() {
		$callback = static function () {
			return 1000;
		};
		add_filter( 'dfx_random_user_id_min_id', $callback );

		$this->assertSame( 1000, dfx_random_user_id_get_min_id() );

		remove_filter( 'dfx_random_user_id_min_id', $callback );
	}
}
