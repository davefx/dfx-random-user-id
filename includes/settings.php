<?php
/**
 * The settings page: the ID range, the site's status and the "More plugins" tab.
 *
 * Users are shared by every site of a network, so on multisite the page lives in Network Admin
 * and the range is a network option. get_site_option() is get_option() on a single site, so one
 * code path serves both.
 *
 * @package DFX_Random_User_IDs
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DFX_RANDOM_USER_ID_PAGE' ) ) {
	define( 'DFX_RANDOM_USER_ID_PAGE', 'random-user-ids' );
}

if ( ! function_exists( 'dfx_random_user_id_saved_range' ) ) {

	/**
	 * The range saved in the settings page, or null when none was saved.
	 *
	 * @return array{min: int, max: int}|null
	 */
	function dfx_random_user_id_saved_range() {
		$range = get_site_option( 'dfx_randomuserid_range' );

		if ( ! is_array( $range ) || ! isset( $range['min'], $range['max'] ) ) {
			return null;
		}

		return array(
			'min' => (int) $range['min'],
			'max' => (int) $range['max'],
		);
	}
}

if ( ! function_exists( 'dfx_random_user_id_capability' ) ) {

	/**
	 * The capability that manages the plugin: the network's on multisite.
	 *
	 * @return string
	 */
	function dfx_random_user_id_capability() {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}
}

if ( ! function_exists( 'dfx_random_user_id_page_url' ) ) {

	/**
	 * The settings page's URL.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	function dfx_random_user_id_page_url( $args = array() ) {
		$base = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );

		return add_query_arg( array_merge( array( 'page' => DFX_RANDOM_USER_ID_PAGE ), $args ), $base );
	}
}

if ( ! function_exists( 'dfx_random_user_id_validate_range' ) ) {

	/**
	 * Checks a range typed in the settings page.
	 *
	 * The range must leave at least ten free IDs for every user the site has (and never fewer
	 * than 1,000), so that finding a free random ID stays quick.
	 *
	 * @param mixed $min Minimum, as typed.
	 * @param mixed $max Maximum, as typed.
	 * @return array{min: int, max: int}|WP_Error
	 */
	function dfx_random_user_id_validate_range( $min, $max ) {
		$min = is_scalar( $min ) ? trim( (string) $min ) : '';
		$max = is_scalar( $max ) ? trim( (string) $max ) : '';

		if ( ! ctype_digit( $min ) || ! ctype_digit( $max ) ) {
			return new WP_Error( 'not_numbers', __( 'Both limits must be whole numbers.', 'random-user-ids' ) );
		}

		// Compare as strings first: a number longer than PHP_INT_MAX must not wrap around.
		if ( strlen( ltrim( $max, '0' ) ) > strlen( (string) DFX_RANDOM_USER_ID_DEFAULT_MAX ) ) {
			$max = (string) PHP_INT_MAX;
		}

		$min = (int) $min;
		$max = (int) $max;

		if ( $min < 1 || $max > DFX_RANDOM_USER_ID_DEFAULT_MAX ) {
			/* translators: %s: largest allowed ID. */
			return new WP_Error( 'out_of_bounds', sprintf( __( 'The limits must be between 1 and %s.', 'random-user-ids' ), number_format_i18n( DFX_RANDOM_USER_ID_DEFAULT_MAX ) ) );
		}

		if ( $min >= $max ) {
			return new WP_Error( 'inverted', __( 'The minimum must be lower than the maximum.', 'random-user-ids' ) );
		}

		$needed = max( 1000, 10 * dfx_random_user_id_user_count() );

		if ( $max - $min + 1 < $needed ) {
			/* translators: %s: smallest number of IDs the range must hold. */
			return new WP_Error( 'too_narrow', sprintf( __( 'The range is too narrow for this site: it must hold at least %s IDs, ten for every user.', 'random-user-ids' ), number_format_i18n( $needed ) ) );
		}

		return array(
			'min' => $min,
			'max' => $max,
		);
	}
}

if ( ! function_exists( 'dfx_random_user_id_user_count' ) ) {

	/**
	 * How many users there are, on the whole network on multisite.
	 *
	 * @return int
	 */
	function dfx_random_user_id_user_count() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A count; no API gives the network total.
	}
}

if ( ! function_exists( 'dfx_random_user_id_status' ) ) {

	/**
	 * What the settings page reports about the site's user IDs.
	 *
	 * @return array{users: int, has_id_one: bool, low: int, consecutive: int, moved_to: int}
	 */
	function dfx_random_user_id_status() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Counts over the users table; no API for them.
		$low         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID < 1000" );
		$consecutive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} u INNER JOIN {$wpdb->users} p ON p.ID = u.ID - 1" );
		// phpcs:enable

		return array(
			'users'       => dfx_random_user_id_user_count(),
			'has_id_one'  => false !== get_userdata( 1 ),
			'low'         => $low,
			'consecutive' => $consecutive,
			'moved_to'    => (int) get_option( 'dfx_randomuserid_first_user_moved_to' ),
		);
	}
}

if ( ! function_exists( 'dfx_random_user_id_add_page' ) ) {

	/**
	 * Adds the page under Settings (Network Admin → Settings on multisite).
	 */
	function dfx_random_user_id_add_page() {
		add_submenu_page(
			is_multisite() ? 'settings.php' : 'options-general.php',
			__( 'Random User IDs', 'random-user-ids' ),
			__( 'Random User IDs', 'random-user-ids' ),
			dfx_random_user_id_capability(),
			DFX_RANDOM_USER_ID_PAGE,
			'dfx_random_user_id_render_page'
		);
	}
}

add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', 'dfx_random_user_id_add_page' );

if ( ! function_exists( 'dfx_random_user_id_action_links' ) ) {

	/**
	 * Adds "Settings" to the plugin's links in the Plugins screen.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	function dfx_random_user_id_action_links( $links ) {
		if ( current_user_can( dfx_random_user_id_capability() ) ) {
			array_unshift( $links, '<a href="' . esc_url( dfx_random_user_id_page_url() ) . '">' . esc_html__( 'Settings', 'random-user-ids' ) . '</a>' );
		}

		return $links;
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/dfx-random-user-id.php' ), 'dfx_random_user_id_action_links' );
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/dfx-random-user-id.php' ), 'dfx_random_user_id_action_links' );

if ( ! function_exists( 'dfx_random_user_id_save' ) ) {

	/**
	 * Saves the range (admin-post.php), then goes back to the page with the result.
	 */
	function dfx_random_user_id_save() {
		if ( ! current_user_can( dfx_random_user_id_capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change these settings.', 'random-user-ids' ), 403 );
		}

		check_admin_referer( 'dfx_random_user_id_save' );

		if ( isset( $_POST['reset'] ) ) {
			delete_site_option( 'dfx_randomuserid_range' );
			wp_safe_redirect( dfx_random_user_id_page_url( array( 'saved' => 'reset' ) ) );
			exit;
		}

		$range = dfx_random_user_id_validate_range(
			isset( $_POST['min'] ) ? sanitize_text_field( wp_unslash( $_POST['min'] ) ) : '',
			isset( $_POST['max'] ) ? sanitize_text_field( wp_unslash( $_POST['max'] ) ) : ''
		);

		if ( is_wp_error( $range ) ) {
			wp_safe_redirect( dfx_random_user_id_page_url( array( 'error' => $range->get_error_code() ) ) );
			exit;
		}

		update_site_option( 'dfx_randomuserid_range', $range );
		wp_safe_redirect( dfx_random_user_id_page_url( array( 'saved' => 'range' ) ) );
		exit;
	}
}

add_action( 'admin_post_dfx_random_user_id_save', 'dfx_random_user_id_save' );

if ( ! function_exists( 'dfx_random_user_id_render_page' ) ) {

	/**
	 * Prints the page: the "Settings" tab, or the "More plugins" tab.
	 */
	function dfx_random_user_id_render_page() {
		if ( ! current_user_can( dfx_random_user_id_capability() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$more = 'more-plugins' === $tab;

		echo '<div class="wrap"><h1>' . esc_html__( 'Random User IDs', 'random-user-ids' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';
		echo '<a href="' . esc_url( dfx_random_user_id_page_url() ) . '" class="nav-tab' . ( $more ? '' : ' nav-tab-active' ) . '">' . esc_html__( 'Settings', 'random-user-ids' ) . '</a>';
		echo '<a href="' . esc_url( dfx_random_user_id_page_url( array( 'tab' => 'more-plugins' ) ) ) . '" class="nav-tab' . ( $more ? ' nav-tab-active' : '' ) . '">' . esc_html( dfx_plugin_showcase_label() ) . '</a>';
		echo '</nav>';

		if ( $more ) {
			dfx_plugin_showcase_render( array( 'exclude' => 'random-user-ids' ) );
		} else {
			dfx_random_user_id_render_messages();
			dfx_random_user_id_render_status();
			dfx_random_user_id_render_range_form();
		}

		echo '</div>';
	}
}

if ( ! function_exists( 'dfx_random_user_id_render_messages' ) ) {

	/**
	 * The result of the last save.
	 */
	function dfx_random_user_id_render_messages() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Messages after a redirect; nothing is changed.
		$saved = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable

		$errors = array(
			'not_numbers'   => __( 'Both limits must be whole numbers.', 'random-user-ids' ),
			/* translators: %s: largest allowed ID. */
			'out_of_bounds' => sprintf( __( 'The limits must be between 1 and %s.', 'random-user-ids' ), number_format_i18n( DFX_RANDOM_USER_ID_DEFAULT_MAX ) ),
			'inverted'      => __( 'The minimum must be lower than the maximum.', 'random-user-ids' ),
			'too_narrow'    => __( 'The range is too narrow for this site: it must hold at least ten IDs for every user, and never fewer than 1,000.', 'random-user-ids' ),
		);

		if ( isset( $errors[ $error ] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $errors[ $error ] ) . ' ' . esc_html__( 'The range was not changed.', 'random-user-ids' ) . '</p></div>';
		} elseif ( 'range' === $saved ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Range saved. It applies to the users created from now on; existing IDs do not change.', 'random-user-ids' ) . '</p></div>';
		} elseif ( 'reset' === $saved ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'The default range has been restored.', 'random-user-ids' ) . '</p></div>';
		}
	}
}

if ( ! function_exists( 'dfx_random_user_id_render_status' ) ) {

	/**
	 * The site's status: what an attacker could guess about its user IDs.
	 */
	function dfx_random_user_id_render_status() {
		$status = dfx_random_user_id_status();

		echo '<h2>' . esc_html__( 'Status', 'random-user-ids' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:760px"><tbody>';

		dfx_random_user_id_status_row( __( 'Users', 'random-user-ids' ), number_format_i18n( $status['users'] ), '' );

		dfx_random_user_id_status_row(
			__( 'User with ID 1', 'random-user-ids' ),
			$status['has_id_one'] ? __( 'Exists', 'random-user-ids' ) : __( 'None', 'random-user-ids' ),
			$status['has_id_one'] ? __( 'ID 1 is the first one an attacker tries. The plugin moves it when it is activated; it exists again only if it was created later or the move failed.', 'random-user-ids' ) : ''
		);

		dfx_random_user_id_status_row(
			__( 'Users with IDs below 1,000', 'random-user-ids' ),
			number_format_i18n( $status['low'] ),
			$status['low'] > 0 ? __( 'Low IDs are easy to guess. They usually belong to users created before the plugin was activated, and the plugin does not change them.', 'random-user-ids' ) : ''
		);

		dfx_random_user_id_status_row(
			__( 'Users whose ID follows another user\'s', 'random-user-ids' ),
			number_format_i18n( $status['consecutive'] ),
			$status['consecutive'] > 0 ? __( 'Knowing one of these IDs gives away the next one. They usually come from before the plugin was activated, or from a very narrow range.', 'random-user-ids' ) : ''
		);

		if ( $status['moved_to'] > 0 ) {
			dfx_random_user_id_status_row(
				__( 'First user', 'random-user-ids' ),
				/* translators: %s: the new user ID. */
				sprintf( __( 'Moved from ID 1 to %s on activation', 'random-user-ids' ), number_format_i18n( $status['moved_to'] ) ),
				''
			);
		}

		echo '</tbody></table>';
	}
}

if ( ! function_exists( 'dfx_random_user_id_status_row' ) ) {

	/**
	 * One row of the status table.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $note  Explanation, or ''.
	 */
	function dfx_random_user_id_status_row( $label, $value, $note ) {
		echo '<tr><th scope="row" style="width:40%">' . esc_html( $label ) . '</th><td><strong>' . esc_html( $value ) . '</strong>';

		if ( '' !== $note ) {
			echo '<p class="description">' . esc_html( $note ) . '</p>';
		}

		echo '</td></tr>';
	}
}

if ( ! function_exists( 'dfx_random_user_id_render_range_form' ) ) {

	/**
	 * The form for the ID range.
	 */
	function dfx_random_user_id_render_range_form() {
		$saved     = dfx_random_user_id_saved_range();
		$min       = $saved ? $saved['min'] : 1;
		$max       = $saved ? $saved['max'] : DFX_RANDOM_USER_ID_DEFAULT_MAX;
		$effective = array( dfx_random_user_id_get_min_id(), dfx_random_user_id_get_max_id() );

		echo '<h2>' . esc_html__( 'Range for new user IDs', 'random-user-ids' ) . '</h2>';
		echo '<p>' . esc_html__( 'Every new user gets a random ID between these two numbers. Existing users keep their IDs. The wider the range, the harder the IDs are to guess.', 'random-user-ids' ) . '</p>';

		if ( array( $min, $max ) !== $effective ) {
			/* translators: 1: minimum ID, 2: maximum ID. */
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( 'Code on this site changes the range with the dfx_random_user_id_min_id or dfx_random_user_id_max_id filters, so the range in use is %1$s to %2$s, whatever is saved here.', 'random-user-ids' ), number_format_i18n( $effective[0] ), number_format_i18n( $effective[1] ) ) ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="dfx_random_user_id_save" />';
		wp_nonce_field( 'dfx_random_user_id_save' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="dfx-ruid-min">' . esc_html__( 'Minimum ID', 'random-user-ids' ) . '</label></th>';
		echo '<td><input type="number" id="dfx-ruid-min" name="min" class="regular-text" min="1" max="' . esc_attr( (string) DFX_RANDOM_USER_ID_DEFAULT_MAX ) . '" step="1" value="' . esc_attr( (string) $min ) . '" required /></td></tr>';
		echo '<tr><th scope="row"><label for="dfx-ruid-max">' . esc_html__( 'Maximum ID', 'random-user-ids' ) . '</label></th>';
		echo '<td><input type="number" id="dfx-ruid-max" name="max" class="regular-text" min="1" max="' . esc_attr( (string) DFX_RANDOM_USER_ID_DEFAULT_MAX ) . '" step="1" value="' . esc_attr( (string) $max ) . '" required />';
		/* translators: %s: largest allowed ID. */
		echo '<p class="description">' . esc_html( sprintf( __( 'At most %s, so that the IDs stay exact in JavaScript.', 'random-user-ids' ), number_format_i18n( DFX_RANDOM_USER_ID_DEFAULT_MAX ) ) ) . '</p></td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit">';
		submit_button( __( 'Save range', 'random-user-ids' ), 'primary', 'submit', false );

		if ( $saved ) {
			echo ' ';
			submit_button( __( 'Restore the default range', 'random-user-ids' ), 'secondary', 'reset', false );
		}

		echo '</p></form>';
	}
}
