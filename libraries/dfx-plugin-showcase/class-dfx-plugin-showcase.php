<?php
/**
 * The catalog of the author's other plugins, shown in a tab of each plugin's settings page.
 *
 * Only the newest bundled copy of this file is loaded (see load.php).
 *
 * @package DFX_Plugin_Showcase
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches the catalog from the author's site, caches it, and prints it as plugin cards.
 *
 * Network: one GET to CATALOG_URL, made only when an administrator opens the tab, and then at
 * most once a week (once every 12 hours while the site is unreachable). Nothing about the
 * site is sent: the request uses a neutral user agent, not WordPress's, which has the site URL. If the request fails, the catalog bundled in catalog.json is shown instead.
 */
final class DFX_Plugin_Showcase {

	public const CATALOG_URL = 'https://davefx.com/plugins.json';

	private const SCHEMA      = 1;
	private const TRANSIENT   = 'dfx_plugin_showcase_catalog';
	private const CACHE_OK    = WEEK_IN_SECONDS;
	private const CACHE_ERROR = 12 * HOUR_IN_SECONDS;

	/**
	 * English texts, used when the catalog has none for the user's language.
	 */
	private const STRINGS = array(
		'label'     => 'More plugins',
		'intro'     => 'Other plugins by the same author that may be useful to you.',
		'install'   => 'Install',
		'details'   => 'More details',
		'active'    => 'Active',
		'installed' => 'Installed',
		'empty'     => 'There are no other plugins to show right now.',
	);

	/**
	 * The single instance.
	 *
	 * @var DFX_Plugin_Showcase|null
	 */
	private static $instance = null;

	/**
	 * The catalog for this request.
	 *
	 * @var array|null
	 */
	private $catalog = null;

	/**
	 * Returns the single instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The tab's label. It is read from the cache only, never fetched, because it is printed on
	 * every view of the settings page, not just the tab's.
	 */
	public function label(): string {
		$cached = get_transient( self::TRANSIENT );

		return $this->text( 'label', is_array( $cached ) ? $cached : $this->bundled() );
	}

	/**
	 * Prints the catalog.
	 *
	 * @param array $args See dfx_plugin_showcase_render().
	 */
	public function render( array $args ): void {
		$catalog = $this->catalog();
		$exclude = array_map( 'strval', (array) ( $args['exclude'] ?? array() ) );
		$plugins = array();

		foreach ( $catalog['plugins'] as $plugin ) {
			if ( ! in_array( $plugin['id'], $exclude, true ) ) {
				$plugins[] = $plugin;
			}
		}

		$can_install = current_user_can( 'install_plugins' );

		if ( $can_install ) {
			add_thickbox();
		}

		$this->print_styles();

		echo '<div class="dfx-showcase">';
		echo '<p class="dfx-showcase-intro">' . esc_html( $this->text( 'intro', $catalog ) ) . '</p>';

		if ( array() === $plugins ) {
			echo '<p>' . esc_html( $this->text( 'empty', $catalog ) ) . '</p></div>';
			return;
		}

		echo '<div class="dfx-showcase-grid">';

		foreach ( $plugins as $plugin ) {
			$this->render_card( $plugin, $catalog, $can_install );
		}

		echo '</div></div>';
	}

	/**
	 * Prints one plugin's card.
	 *
	 * @param array $plugin      Catalog entry.
	 * @param array $catalog     The catalog, for its texts.
	 * @param bool  $can_install Whether the user may install plugins.
	 */
	private function render_card( array $plugin, array $catalog, bool $can_install ): void {
		$status      = $this->status( $plugin );
		$description = $this->localized( $plugin['description'] );

		echo '<div class="dfx-showcase-card">';

		if ( '' !== $plugin['icon'] ) {
			echo '<img class="dfx-showcase-icon" src="' . esc_url( $plugin['icon'] ) . '" alt="" width="64" height="64" loading="lazy" />';
		}

		echo '<div class="dfx-showcase-body">';
		echo '<h3>' . esc_html( $plugin['name'] ) . '</h3>';

		if ( '' !== $description ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}

		echo '<p class="dfx-showcase-actions">';

		if ( '' !== $status ) {
			echo '<span class="dfx-showcase-status">' . esc_html( $this->text( $status, $catalog ) ) . '</span> ';
		} elseif ( $can_install && '' !== $plugin['wporg'] ) {
			$url = add_query_arg(
				array(
					'tab'       => 'plugin-information',
					'plugin'    => $plugin['wporg'],
					'TB_iframe' => 'true',
					'width'     => 772,
					'height'    => 600,
				),
				self_admin_url( 'plugin-install.php' )
			);

			echo '<a class="button thickbox open-plugin-details-modal" href="' . esc_url( $url ) . '">' . esc_html( $this->text( 'install', $catalog ) ) . '</a> ';
		}

		echo '<a href="' . esc_url( $plugin['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $this->text( 'details', $catalog ) ) . '</a>';
		echo '</p></div></div>';
	}

	/**
	 * Whether the plugin is active or installed here: '' when it is neither.
	 *
	 * @param array $plugin Catalog entry.
	 * @return string 'active', 'installed' or ''.
	 */
	private function status( array $plugin ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$status = '';

		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( ! in_array( dirname( $file ), $plugin['dirs'], true ) ) {
				continue;
			}

			if ( is_plugin_active( $file ) ) {
				return 'active';
			}

			$status = 'installed';
		}

		return $status;
	}

	/**
	 * The catalog: from the cache, else from the author's site, else the bundled copy.
	 *
	 * @return array{strings: array, plugins: array}
	 */
	private function catalog(): array {
		if ( null !== $this->catalog ) {
			return $this->catalog;
		}

		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['plugins'] ) ) {
			$this->catalog = $cached;
			return $this->catalog;
		}

		$response = wp_safe_remote_get(
			self::CATALOG_URL,
			array(
				'timeout'     => 5,
				'redirection' => 2,
				// WordPress's default user agent carries the site's URL; this one says nothing about the site.
				'user-agent'  => 'DFX-Plugin-Showcase/' . ( $GLOBALS['dfx_plugin_showcase']['version'] ?? '1' ),
			)
		);
		$remote   = null;

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$remote = $this->parse( wp_remote_retrieve_body( $response ) );
		}

		$this->catalog = $remote ?? $this->bundled();
		set_transient( self::TRANSIENT, $this->catalog, null !== $remote ? self::CACHE_OK : self::CACHE_ERROR );

		return $this->catalog;
	}

	/**
	 * The catalog bundled with this copy.
	 *
	 * @return array{strings: array, plugins: array}
	 */
	private function bundled(): array {
		$json = is_readable( __DIR__ . '/catalog.json' ) ? (string) file_get_contents( __DIR__ . '/catalog.json' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

		return $this->parse( $json ) ?? array(
			'strings' => array(),
			'plugins' => array(),
		);
	}

	/**
	 * Parses and validates a catalog. Anything unexpected in an entry drops the entry, and
	 * only https URLs are kept: the catalog comes from the network.
	 *
	 * @param string $json Catalog JSON.
	 * @return array{strings: array, plugins: array}|null Null when it is not a catalog.
	 */
	private function parse( string $json ): ?array {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || self::SCHEMA !== ( $data['schema'] ?? null ) || ! isset( $data['plugins'] ) || ! is_array( $data['plugins'] ) ) {
			return null;
		}

		$plugins = array();

		foreach ( $data['plugins'] as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) || empty( $entry['name'] ) || ! $this->is_https( $entry['url'] ?? '' ) ) {
				continue;
			}

			$id    = sanitize_key( (string) $entry['id'] );
			$wporg = isset( $entry['wporg'] ) ? sanitize_key( (string) $entry['wporg'] ) : '';
			$dirs  = isset( $entry['dirs'] ) && is_array( $entry['dirs'] ) ? $entry['dirs'] : array_filter( array( $wporg, '' !== $wporg ? $wporg . '-premium' : '' ) );

			$plugins[] = array(
				'id'          => $id,
				'name'        => sanitize_text_field( (string) $entry['name'] ),
				'description' => $this->texts( $entry['description'] ?? array() ),
				'url'         => (string) $entry['url'],
				'icon'        => $this->is_https( $entry['icon'] ?? '' ) ? (string) $entry['icon'] : '',
				'wporg'       => $wporg,
				'dirs'        => array_values( array_map( 'sanitize_file_name', array_map( 'strval', $dirs ) ) ),
			);
		}

		$strings = array();

		foreach ( ( isset( $data['strings'] ) && is_array( $data['strings'] ) ? $data['strings'] : array() ) as $lang => $texts ) {
			$strings[ sanitize_key( (string) $lang ) ] = $this->texts( $texts );
		}

		return array(
			'strings' => $strings,
			'plugins' => $plugins,
		);
	}

	/**
	 * Keeps the string values of a map, as plain text.
	 *
	 * @param mixed $texts Language or key => text.
	 * @return array<string, string>
	 */
	private function texts( $texts ): array {
		$clean = array();

		foreach ( is_array( $texts ) ? $texts : array() as $key => $text ) {
			if ( is_string( $text ) ) {
				$clean[ sanitize_key( (string) $key ) ] = sanitize_text_field( $text );
			}
		}

		return $clean;
	}

	/**
	 * Whether a value is an https URL.
	 *
	 * @param mixed $url Value.
	 */
	private function is_https( $url ): bool {
		return is_string( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
	}

	/**
	 * One of the tab's texts, in the user's language when the catalog has it.
	 *
	 * @param string $key     Key of self::STRINGS.
	 * @param array  $catalog The catalog.
	 */
	private function text( string $key, array $catalog ): string {
		foreach ( $this->languages() as $lang ) {
			if ( ! empty( $catalog['strings'][ $lang ][ $key ] ) ) {
				return $catalog['strings'][ $lang ][ $key ];
			}
		}

		return self::STRINGS[ $key ] ?? '';
	}

	/**
	 * A text from a language => text map, in the user's language, else English, else any.
	 *
	 * @param array<string, string> $texts Language => text.
	 */
	private function localized( array $texts ): string {
		foreach ( array_merge( $this->languages(), array( 'en' ) ) as $lang ) {
			if ( ! empty( $texts[ $lang ] ) ) {
				return $texts[ $lang ];
			}
		}

		return (string) reset( $texts );
	}

	/**
	 * The user's language keys, most specific first: es_es, es.
	 *
	 * @return string[]
	 */
	private function languages(): array {
		$locale = strtolower( function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale() );

		return array_unique( array( sanitize_key( $locale ), substr( $locale, 0, 2 ) ) );
	}

	/**
	 * Prints the tab's styles, once.
	 */
	private function print_styles(): void {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;

		echo '<style>'
			. '.dfx-showcase-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;margin-top:12px}'
			. '.dfx-showcase-card{display:flex;gap:14px;align-items:flex-start;background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:16px}'
			. '.dfx-showcase-icon{flex:none;width:64px;height:64px;border-radius:4px}'
			. '.dfx-showcase-body h3{margin:0 0 6px;font-size:14px}'
			. '.dfx-showcase-body p{margin:0 0 10px}'
			. '.dfx-showcase-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}'
			. '.dfx-showcase-status{color:#00a32a;font-weight:600}'
			. '</style>';
	}
}
