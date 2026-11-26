<?php
/**
 * Club settings.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Settings;

use AirsoftClub\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/** Club settings, branding and imprint data: storage, defaults and sanitising. Stored in option acm_settings. */
final class Settings {

	public const OPTION = 'acm_settings';

	/**
	 * Colour fields => default (dark olive/lime look).
	 *
	 * @return array<string,string>
	 */
	public static function colors(): array {
		return array(
			'color_primary'          => '#a4c639',
			'color_primary_contrast' => '#111111',
			'color_background'       => '#121411',
			'color_surface'          => '#1c1f1a',
			'color_text'             => '#e9ecdf',
		);
	}

	/**
	 * Field => default.
	 *
	 * @return array<string,string>
	 */
	public static function defaults(): array {
		return array_merge(
			array(
				'club_name'          => '',
				'club_short_name'    => '',
				'logo_id'            => '',
				'street'             => '',
				'zip'                => '',
				'city'               => '',
				'country'            => 'DE',
				'email'              => '',
				'phone'              => '',
				'responsible'        => '',
				'register_court'     => '',
				'register_number'    => '',
				'module_association' => '1',
				'require_2fa'        => '1',
				'remove_data'        => '',
			),
			self::colors()
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::defaults() ) : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Field.
	 */
	public static function get( string $key ): string {
		return (string) ( self::all()[ $key ] ?? '' );
	}

	/** Whether the association module (fees, cash book, donation receipts) is on. */
	public static function association_enabled(): bool {
		return '1' === self::get( 'module_association' );
	}

	/** Club name with fallback to the site title. */
	public static function club_name(): string {
		$name = self::get( 'club_name' );
		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}

	/** Short name with fallback to the club name. */
	public static function short_name(): string {
		$name = self::get( 'club_short_name' );
		return '' !== $name ? $name : self::club_name();
	}

	/**
	 * Sanitises submitted values (register_setting callback).
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string,string>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$old   = self::all();
		$out   = self::defaults();

		foreach ( array( 'club_name', 'club_short_name', 'street', 'zip', 'city', 'phone', 'responsible', 'register_court', 'register_number' ) as $key ) {
			$out[ $key ] = mb_substr( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ), 0, 200 );
		}
		$out['club_short_name'] = mb_substr( $out['club_short_name'], 0, 20 );

		$country        = strtoupper( sanitize_text_field( (string) ( $input['country'] ?? 'DE' ) ) );
		$out['country'] = preg_match( '/^[A-Z]{2}$/', $country ) ? $country : 'DE';
		$out['email']   = sanitize_email( (string) ( $input['email'] ?? '' ) );

		$logo_id        = absint( $input['logo_id'] ?? 0 );
		$out['logo_id'] = $logo_id && wp_attachment_is_image( $logo_id ) ? (string) $logo_id : '';

		foreach ( self::colors() as $key => $default ) {
			$color       = sanitize_hex_color( (string) ( $input[ $key ] ?? '' ) );
			$out[ $key ] = $color ? strtolower( $color ) : $default;
		}

		foreach ( array( 'module_association', 'require_2fa', 'remove_data' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? '' : '1';
		}

		$changed = array_keys( array_diff_assoc( $out, $old ) );
		if ( $changed ) {
			AuditLog::add( 'settings.updated', 'settings', 0, array( 'fields' => $changed ) );
		}
		return $out;
	}
}
