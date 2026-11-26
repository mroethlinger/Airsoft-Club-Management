<?php
/**
 * Two-factor requirement for leads and board roles.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Access;

use AirsoftClub\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Leads and board roles must use two-factor authentication when the setting is on.
 * 2FA itself comes from the "Two Factor" plugin; without it the requirement cannot be checked
 * and is not enforced (the admin sees a notice instead), so a fresh site does not lock itself out.
 * Administrators are never restricted: they control the site anyway.
 */
final class TwoFactor {

	/**
	 * Roles whose plugin capabilities need 2FA.
	 *
	 * @var string[]
	 */
	private const PROTECTED_ROLES = array( 'acm_lead', 'acm_board', 'acm_treasurer', 'acm_cash_auditor' );

	/** Registers hooks. */
	public static function hooks(): void {
		add_filter( 'user_has_cap', array( self::class, 'filter_caps' ), 20, 4 );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	/** Whether the "Two Factor" plugin is active. */
	public static function provider_available(): bool {
		/**
		 * Whether a 2FA provider is available (default: the "Two Factor" plugin is active).
		 *
		 * @param bool $available Availability.
		 */
		return (bool) apply_filters( 'airsoft_club_2fa_provider_available', class_exists( 'Two_Factor_Core' ) );
	}

	/** Whether the requirement is switched on and can be enforced. */
	public static function enforced(): bool {
		return '1' === Settings::get( 'require_2fa' ) && self::provider_available();
	}

	/**
	 * Whether the user has 2FA configured.
	 *
	 * @param int $user_id User id.
	 */
	public static function user_has_2fa( int $user_id ): bool {
		$using = class_exists( 'Two_Factor_Core' ) && \Two_Factor_Core::is_user_using_two_factor( $user_id );
		/**
		 * Whether the user has two-factor authentication configured.
		 *
		 * @param bool $using   Result from the provider.
		 * @param int  $user_id User id.
		 */
		return (bool) apply_filters( 'airsoft_club_user_has_2fa', $using, $user_id );
	}

	/**
	 * Whether lead/board functions are blocked for a user because 2FA is missing.
	 *
	 * @param int $user_id User id.
	 */
	public static function blocks( int $user_id ): bool {
		if ( ! self::enforced() || user_can( $user_id, 'manage_options' ) ) {
			return false;
		}
		return ! self::user_has_2fa( $user_id );
	}

	/**
	 * Removes plugin capabilities (except the member area) from protected roles without 2FA.
	 *
	 * @param array<string,bool> $allcaps All capabilities of the user.
	 * @param string[]           $caps    Required primitive capabilities.
	 * @param array<int,mixed>   $args    Arguments; [1] is the user id.
	 * @param \WP_User           $user    User.
	 * @return array<string,bool>
	 */
	public static function filter_caps( $allcaps, $caps, $args, $user ) {
		if ( ! $user instanceof \WP_User || ! array_intersect( self::PROTECTED_ROLES, (array) $user->roles ) ) {
			return $allcaps;
		}
		$wants_plugin_cap = false;
		foreach ( (array) $caps as $cap ) {
			if ( str_starts_with( (string) $cap, 'acm_' ) && 'acm_member_area' !== $cap ) {
				$wants_plugin_cap = true;
			}
		}
		if ( ! $wants_plugin_cap || ! empty( $allcaps['manage_options'] ) || ! self::blocks( (int) $user->ID ) ) {
			return $allcaps;
		}
		foreach ( array_keys( $allcaps ) as $cap ) {
			if ( str_starts_with( (string) $cap, 'acm_' ) && 'acm_member_area' !== $cap ) {
				$allcaps[ $cap ] = false;
			}
		}
		return $allcaps;
	}

	/** Shows a notice when 2FA is required but the provider plugin is missing. */
	public static function notice(): void {
		if ( '1' !== Settings::get( 'require_2fa' ) || self::provider_available() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Airsoft Club Management: two-factor authentication is required for leads and board roles, but the "Two Factor" plugin is not active. The requirement is not enforced until it is installed and activated.', 'airsoft-club-management' )
		);
	}
}
