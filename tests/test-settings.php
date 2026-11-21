<?php
/**
 * Tests for the settings page: the saved range, its validation, the status and the tabs.
 *
 * @package DFX_Random_User_IDs
 */

/**
 * The settings page.
 */
class Test_Settings extends WP_UnitTestCase {

	/**
	 * HTTP requests made during the test.
	 *
	 * @var string[]
	 */
	private $requests = array();

	/**
	 * Resets the state each test relies on.
	 */
	public function set_up() {
		parent::set_up();
		delete_site_option( 'dfx_randomuserid_range' );
		delete_transient( 'dfx_plugin_showcase_catalog' );
		$this->requests = array();

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->requests[] = $url;
				return new WP_Error( 'offline', 'No network in tests.' );
			},
			10,
			3
		);
	}

	/**
	 * Saved range is used.
	 */
	public function test_saved_range_is_used() {
		update_site_option(
			'dfx_randomuserid_range',
			array(
				'min' => 5000,
				'max' => 900000,
			)
		);

		$this->assertSame( 5000, dfx_random_user_id_get_min_id() );
		$this->assertSame( 900000, dfx_random_user_id_get_max_id() );
	}

	/**
	 * Filters override the saved range.
	 */
	public function test_filters_override_the_saved_range() {
		update_site_option(
			'dfx_randomuserid_range',
			array(
				'min' => 5000,
				'max' => 900000,
			)
		);
		add_filter( 'dfx_random_user_id_max_id', static fn() => 777777 );

		$this->assertSame( 777777, dfx_random_user_id_get_max_id() );
	}

	/**
	 * New users get IDs inside the saved range.
	 */
	public function test_new_users_get_ids_inside_the_saved_range() {
		update_site_option(
			'dfx_randomuserid_range',
			array(
				'min' => 200000,
				'max' => 300000,
			)
		);

		$user_id = self::factory()->user->create();

		$this->assertGreaterThanOrEqual( 200000, $user_id );
		$this->assertLessThanOrEqual( 300000, $user_id );
	}

	/**
	 * A valid range is accepted.
	 */
	public function test_a_valid_range_is_accepted() {
		$this->assertSame(
			array(
				'min' => 1000,
				'max' => 9999,
			),
			dfx_random_user_id_validate_range( '1000', ' 9999 ' )
		);
	}

	/**
	 * Invalid ranges are rejected with the right error.
	 *
	 * @dataProvider invalid_ranges
	 *
	 * @param string $min  Minimum, as typed.
	 * @param string $max  Maximum, as typed.
	 * @param string $code Expected error code.
	 */
	public function test_invalid_ranges_are_rejected( $min, $max, $code ) {
		$result = dfx_random_user_id_validate_range( $min, $max );

		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code() );
	}

	/**
	 * Ranges the settings page must reject, with the error code each one gets.
	 *
	 * @return array
	 */
	public function invalid_ranges() {
		return array(
			'letters'           => array( 'abc', '9999', 'not_numbers' ),
			'negative'          => array( '-5', '9999', 'not_numbers' ),
			'decimal'           => array( '1.5', '9999', 'not_numbers' ),
			'zero'              => array( '0', '9999', 'out_of_bounds' ),
			'beyond javascript' => array( '1', '4503599627370496', 'out_of_bounds' ),
			'overflowing int'   => array( '1', '99999999999999999999999', 'out_of_bounds' ),
			'inverted'          => array( '9999', '1000', 'inverted' ),
			'too narrow'        => array( '1000', '1500', 'too_narrow' ),
		);
	}

	/**
	 * Narrow limit grows with the users.
	 */
	public function test_narrow_limit_grows_with_the_users() {
		self::factory()->user->create_many( 150 );

		// 151 users or more need at least 1,510 IDs: 1,000 is no longer enough.
		$this->assertWPError( dfx_random_user_id_validate_range( '1', '1200' ) );
	}

	/**
	 * Status reports ID 1 and low IDs.
	 */
	public function test_status_reports_id_one_and_low_ids() {
		$status = dfx_random_user_id_status();

		// The test install's admin is ID 1.
		$this->assertTrue( $status['has_id_one'] );
		$this->assertGreaterThanOrEqual( 1, $status['low'] );

		dfx_random_user_randomize_first_user();
		wp_cache_flush();

		$this->assertFalse( dfx_random_user_id_status()['has_id_one'] );
	}

	/**
	 * Status counts consecutive IDs.
	 */
	public function test_status_counts_consecutive_ids() {
		global $wpdb;

		$a = self::factory()->user->create();
		$b = self::factory()->user->create();
		$wpdb->update( $wpdb->users, array( 'ID' => 3000001 ), array( 'ID' => $a ) );
		$wpdb->update( $wpdb->users, array( 'ID' => 3000002 ), array( 'ID' => $b ) );

		$this->assertGreaterThanOrEqual( 1, dfx_random_user_id_status()['consecutive'] );
	}

	/**
	 * Settings tab shows status and form without network.
	 */
	public function test_settings_tab_shows_status_and_form_without_network() {
		$html = $this->render_page( '' );

		$this->assertStringContainsString( 'nav-tab-wrapper', $html );
		$this->assertStringContainsString( 'name="min"', $html );
		$this->assertStringContainsString( 'User with ID 1', $html );
		$this->assertSame( array(), $this->requests, 'The settings tab must not call davefx.com.' );
	}

	/**
	 * More plugins tab lists the catalog without this plugin.
	 */
	public function test_more_plugins_tab_lists_the_catalog_without_this_plugin() {
		$html = $this->render_page( 'more-plugins' );

		$this->assertStringContainsString( 'dfx-showcase', $html );
		$this->assertStringContainsString( 'Coupon Audit', $html );
		$this->assertStringNotContainsString( 'DFX Random User IDs</h3>', $html );
		$this->assertStringNotContainsString( 'name="min"', $html );
	}

	/**
	 * Page is hidden from users without the capability.
	 */
	public function test_page_is_hidden_from_users_without_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		ob_start();
		dfx_random_user_id_render_page();

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Settings link is added for administrators.
	 */
	public function test_settings_link_is_added_for_administrators() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}

		$links = dfx_random_user_id_action_links( array() );

		$this->assertStringContainsString( 'page=random-user-ids', $links[0] );
	}

	/**
	 * Save rejects requests without a valid nonce.
	 */
	public function test_save_rejects_requests_without_a_valid_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
		$_POST    = array(
			'min' => '1000',
			'max' => '999999',
		);
		$_REQUEST = $_POST;

		$this->expectException( WPDieException::class );

		try {
			dfx_random_user_id_save();
		} finally {
			$this->assertFalse( get_site_option( 'dfx_randomuserid_range' ) );
		}
	}

	/**
	 * Save stores a valid range and rejects an invalid one.
	 */
	public function test_save_stores_a_valid_range_and_rejects_an_invalid_one() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}

		$this->assertStringContainsString( 'saved=range', $this->post_save( '1000', '999999' ) );
		$this->assertSame(
			array(
				'min' => 1000,
				'max' => 999999,
			),
			get_site_option( 'dfx_randomuserid_range' )
		);

		$this->assertStringContainsString( 'error=inverted', $this->post_save( '999999', '1000' ) );
		$this->assertSame( 999999, get_site_option( 'dfx_randomuserid_range' )['max'], 'An invalid range must not replace the saved one.' );
	}

	/**
	 * Renders the settings page as an administrator.
	 *
	 * @param string $tab Tab, or ''.
	 * @return string
	 */
	private function render_page( $tab ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
		$_GET = '' === $tab ? array() : array( 'tab' => $tab );

		ob_start();
		dfx_random_user_id_render_page();
		$html = (string) ob_get_clean();
		$_GET = array();

		return $html;
	}

	/**
	 * Posts the form with a valid nonce and returns where it redirects.
	 *
	 * @param string $min Minimum.
	 * @param string $max Maximum.
	 * @return string
	 */
	private function post_save( $min, $max ) {
		$_POST    = array(
			'min'      => $min,
			'max'      => $max,
			'_wpnonce' => wp_create_nonce( 'dfx_random_user_id_save' ),
		);
		$_REQUEST = $_POST;
		$redirect = '';

		$catch = static function ( $location ) {
			throw new RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $catch );

		try {
			dfx_random_user_id_save();
		} catch ( RuntimeException $e ) {
			$redirect = $e->getMessage();
		}

		remove_filter( 'wp_redirect', $catch );
		$_POST    = array();
		$_REQUEST = array();

		return $redirect;
	}
}
