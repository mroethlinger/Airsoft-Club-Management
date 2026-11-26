<?php
/**
 * Admin menu.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Admin;

defined( 'ABSPATH' ) || exit;

/** Registers the "Airsoft Club" admin menu and its pages. */
final class Menu {

	public const SLUG = 'acm';

	/** Registers hooks. */
	public static function hooks(): void {
		add_action( 'admin_menu', array( self::class, 'register' ) );
		add_action( 'admin_init', array( SettingsPage::class, 'register_setting' ) );
		StructurePage::hooks();
		RightsPage::hooks();
	}

	/** Adds the menu entries. */
	public static function register(): void {
		add_menu_page(
			__( 'Airsoft Club', 'airsoft-club-management' ),
			__( 'Airsoft Club', 'airsoft-club-management' ),
			'acm_view_rights',
			self::SLUG,
			array( RightsPage::class, 'render' ),
			'dashicons-shield',
			26
		);
		add_submenu_page( self::SLUG, __( 'Rights', 'airsoft-club-management' ), __( 'Rights', 'airsoft-club-management' ), 'acm_view_rights', self::SLUG, array( RightsPage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Structure', 'airsoft-club-management' ), __( 'Regions & squads', 'airsoft-club-management' ), 'acm_manage_structure', 'acm-structure', array( StructurePage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'airsoft-club-management' ), __( 'Settings', 'airsoft-club-management' ), 'acm_manage_settings', 'acm-settings', array( SettingsPage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Audit log', 'airsoft-club-management' ), __( 'Audit log', 'airsoft-club-management' ), 'acm_view_audit', 'acm-audit', array( AuditPage::class, 'render' ) );
	}

	/**
	 * Redirects back to a plugin page with a notice code.
	 *
	 * @param string $page    Page slug.
	 * @param string $notice  Notice code or message key.
	 * @param string $type    "success" or "error".
	 * @param string $message Optional error message.
	 */
	public static function redirect( string $page, string $notice, string $type = 'success', string $message = '' ): void {
		if ( '' !== $message ) {
			set_transient( 'acm_notice_' . get_current_user_id(), $message, 60 );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => $page,
					'acm_notice' => $notice,
					'acm_type'   => 'error' === $type ? 'error' : 'success',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Prints the notice set by redirect(). */
	public static function notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( empty( $_GET['acm_notice'] ) ) {
			return;
		}
		$type = isset( $_GET['acm_type'] ) && 'error' === $_GET['acm_type'] ? 'error' : 'success';
		// phpcs:enable
		$key     = 'acm_notice_' . get_current_user_id();
		$message = (string) get_transient( $key );
		delete_transient( $key );
		if ( '' === $message ) {
			$message = 'error' === $type ? __( 'The action failed.', 'airsoft-club-management' ) : __( 'Saved.', 'airsoft-club-management' );
		}
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}
}
