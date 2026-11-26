<?php
/**
 * Member area page.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Front;

use AirsoftClub\Rest\SessionController;

defined( 'ABSPATH' ) || exit;

/**
 * Mounts the Vue member area on one WordPress page (default slug "mitglieder").
 * Deep links below that page are routed to it by a rewrite rule; the page is rendered by the
 * plugin's own minimal template so theme styles and scripts cannot interfere with the app.
 */
final class AppPage {

	public const OPTION          = 'acm_app_page_id';
	private const REWRITE_OPTION = 'acm_app_rewrite';

	/** Registers hooks. */
	public static function hooks(): void {
		add_shortcode( 'acm_app', array( self::class, 'shortcode' ) );
		add_action( 'init', array( self::class, 'rewrite' ), 20 );
		add_filter( 'template_include', array( self::class, 'template' ), 99 );
		add_filter( 'display_post_states', array( self::class, 'post_state' ), 10, 2 );
	}

	/** Creates the page on activation unless one exists. */
	public static function ensure_page(): int {
		$id = (int) get_option( self::OPTION );
		if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Member area', 'airsoft-club-management' ),
				'post_name'    => 'mitglieder',
				'post_content' => '<!-- wp:shortcode -->[acm_app]<!-- /wp:shortcode -->',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( self::OPTION, (int) $id, false );
			delete_option( self::REWRITE_OPTION );
			return (int) $id;
		}
		return 0;
	}

	/** The page id or 0. */
	public static function page_id(): int {
		$id = (int) get_option( self::OPTION );
		return $id && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/** Path of the page relative to the site, e.g. "mitglieder". */
	public static function page_path(): string {
		$id = self::page_id();
		return $id ? trim( (string) get_page_uri( $id ), '/' ) : '';
	}

	/** Sends every URL below the page to the page itself; flushes once when the path changes. */
	public static function rewrite(): void {
		$path = self::page_path();
		if ( '' === $path ) {
			return;
		}
		add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/(.+?)/?$', 'index.php?page_id=' . self::page_id() . '&acm_route=$matches[1]', 'top' );
		add_rewrite_tag( '%acm_route%', '(.+)' );
		if ( get_option( self::REWRITE_OPTION ) !== $path ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_OPTION, $path, false );
		}
	}

	/**
	 * Marks the page in the page list.
	 *
	 * @param string[] $states States.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public static function post_state( $states, $post ) {
		if ( self::page_id() === (int) $post->ID ) {
			$states['acm_app'] = __( 'Member area', 'airsoft-club-management' );
		}
		return $states;
	}

	/**
	 * Uses the plugin template for the app page.
	 *
	 * @param string $template Template chosen by WordPress.
	 */
	public static function template( $template ) {
		$id = self::page_id();
		if ( ! $id || ! is_page( $id ) ) {
			return $template;
		}
		if ( ! is_user_logged_in() ) {
			// Not auth_redirect(): it checks the wp-admin cookie, which browsers only send below /wp-admin.
			wp_safe_redirect( wp_login_url( (string) get_permalink( $id ) ) );
			exit;
		}
		return AIRSOFT_CLUB_DIR . 'templates/app.php';
	}

	/** Shortcode fallback (e.g. when the page is shown inside another template). */
	public static function shortcode(): string {
		$id  = self::page_id();
		$url = $id ? get_permalink( $id ) : '';
		return $url ? '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the member area', 'airsoft-club-management' ) . '</a></p>' : '';
	}

	/**
	 * Built entry from the Vite manifest.
	 *
	 * @return array{js:string,css:string[]}|null
	 */
	public static function assets(): ?array {
		$file = AIRSOFT_CLUB_DIR . 'assets/app/manifest.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$manifest = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$entry    = is_array( $manifest ) ? ( $manifest['src/main.js'] ?? null ) : null;
		if ( ! is_array( $entry ) || empty( $entry['file'] ) ) {
			return null;
		}
		$base = AIRSOFT_CLUB_URL . 'assets/app/';
		return array(
			'js'  => $base . $entry['file'],
			'css' => array_map( static fn( $c ) => $base . $c, (array) ( $entry['css'] ?? array() ) ),
		);
	}

	/**
	 * Boot configuration for the app (printed as JSON).
	 *
	 * @return array<string,mixed>
	 */
	public static function config(): array {
		$id = self::page_id();
		return array(
			'base'      => (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH ),
			'api'       => esc_url_raw( rest_url( 'acm/v1' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'logoutUrl' => wp_logout_url( home_url( '/' ) ),
			'homeUrl'   => home_url( '/' ),
			'loginUrl'  => wp_login_url(),
			'locale'    => determine_locale(),
			'branding'  => SessionController::branding(),
			'version'   => AIRSOFT_CLUB_VERSION,
		);
	}
}
