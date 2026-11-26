<?php
/**
 * Profile field rules.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Members;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validation of the profile fields a member edits themself (ported from the Laravel ProfileController).
 * Only fields present in the input are validated and returned (partial update).
 */
final class Profile {

	public const NAME_DISPLAYS = array( 'full', 'abbreviated', 'nickname' );
	public const SOCIAL_KEYS   = array( 'discord', 'facebook', 'instagram', 'telegram', 'whatsapp', 'asvz' );
	public const COMBAT_ROLES  = array( 'shooter', 'driver', 'sniper', 'lone_fighter' );
	public const PLAY_STYLES   = array( 'reenactment', 'militia', 'independent' );

	/** Fields a member may change. */
	public const SELF_FIELDS = array(
		'first_name',
		'last_name',
		'nickname',
		'name_display',
		'phone',
		'share_phone',
		'share_email',
		'street',
		'postal_code',
		'city',
		'country',
		'social',
		'can_drive',
		'vehicle_seats',
		'vehicle_description',
		'needs_ride',
		'mobility_notes',
		'combat_role',
		'play_style',
	);

	/**
	 * Validates and normalises profile input.
	 *
	 * @param array<string,mixed> $input  Raw input (unslashed).
	 * @param string[]            $fields Allowed fields.
	 * @return array<string,mixed>|WP_Error Clean values or the first error.
	 */
	public static function clean( array $input, array $fields = self::SELF_FIELDS ) {
		$out    = array();
		$errors = new WP_Error();
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			$value = $input[ $field ];
			switch ( $field ) {
				case 'first_name':
				case 'last_name':
					$value = self::text( $value );
					if ( '' === $value || mb_strlen( $value ) > 100 ) {
						$errors->add( 'acm_invalid', __( 'First and last name are required (max. 100 characters).', 'airsoft-club-management' ), $field );
					}
					break;
				case 'nickname':
					$value = self::nullable_text( $value, 50, $errors, $field );
					break;
				case 'name_display':
					$value = in_array( $value, self::NAME_DISPLAYS, true ) ? $value : self::invalid( $errors, $field );
					break;
				case 'phone':
					$value = self::nullable_text( $value, 40, $errors, $field );
					if ( null !== $value && ! preg_match( '#^[0-9 +\-()/]*$#', $value ) ) {
						$errors->add( 'acm_invalid', __( 'The phone number may only contain digits, spaces and + - ( ) /.', 'airsoft-club-management' ), $field );
					}
					break;
				case 'share_phone':
				case 'share_email':
				case 'can_drive':
				case 'needs_ride':
					$value = self::bool( $value );
					break;
				case 'street':
				case 'vehicle_description':
					$value = self::nullable_text( $value, 150, $errors, $field );
					break;
				case 'postal_code':
					$value = self::nullable_text( $value, 16, $errors, $field );
					break;
				case 'city':
					$value = self::nullable_text( $value, 100, $errors, $field );
					break;
				case 'mobility_notes':
					$value = self::nullable_text( $value, 255, $errors, $field );
					break;
				case 'country':
					$value = strtoupper( self::text( $value ) );
					if ( ! preg_match( '/^[A-Z]{2}$/', $value ) ) {
						self::invalid( $errors, $field );
					}
					break;
				case 'vehicle_seats':
					if ( null === $value || '' === $value ) {
						$value = null;
					} elseif ( is_numeric( $value ) && (int) $value == $value && $value >= 0 && $value <= 8 ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- integer check.
						$value = (int) $value;
					} else {
						$value = self::invalid( $errors, $field );
					}
					break;
				case 'combat_role':
					$value = self::nullable_choice( $value, self::COMBAT_ROLES, $errors, $field );
					break;
				case 'play_style':
					$value = self::nullable_choice( $value, self::PLAY_STYLES, $errors, $field );
					break;
				case 'social':
					$value = self::social( $value, $errors );
					break;
			}
			$out[ $field ] = $value;
		}
		return $errors->has_errors() ? $errors : $out;
	}

	/**
	 * Adds an "invalid value" error.
	 *
	 * @param WP_Error $errors Errors.
	 * @param string   $field  Field.
	 * @return null
	 */
	private static function invalid( WP_Error $errors, string $field ) {
		/* translators: %s: field name */
		$errors->add( 'acm_invalid', sprintf( __( 'Invalid value for %s.', 'airsoft-club-management' ), $field ), $field );
		return null;
	}

	/**
	 * Single-line text.
	 *
	 * @param mixed $value Value.
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
	}

	/**
	 * Optional text with a maximum length.
	 *
	 * @param mixed    $value  Value.
	 * @param int      $max    Max length.
	 * @param WP_Error $errors Errors.
	 * @param string   $field  Field.
	 */
	private static function nullable_text( $value, int $max, WP_Error $errors, string $field ): ?string {
		if ( null === $value ) {
			return null;
		}
		$value = self::text( $value );
		if ( mb_strlen( $value ) > $max ) {
			/* translators: 1: field name, 2: max length */
			$errors->add( 'acm_invalid', sprintf( __( '%1$s is too long (max. %2$d characters).', 'airsoft-club-management' ), $field, $max ), $field );
		}
		return '' === $value ? null : $value;
	}

	/**
	 * Optional choice.
	 *
	 * @param mixed    $value   Value.
	 * @param string[] $choices Allowed values.
	 * @param WP_Error $errors  Errors.
	 * @param string   $field   Field.
	 */
	private static function nullable_choice( $value, array $choices, WP_Error $errors, string $field ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return in_array( $value, $choices, true ) ? $value : self::invalid( $errors, $field );
	}

	/**
	 * Boolean from form or JSON input.
	 *
	 * @param mixed $value Value.
	 */
	private static function bool( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
	}

	/**
	 * Social handles: known keys only, trimmed, empty ones dropped.
	 *
	 * @param mixed    $value  Value.
	 * @param WP_Error $errors Errors.
	 * @return array<string,string>
	 */
	private static function social( $value, WP_Error $errors ): array {
		if ( null === $value || '' === $value ) {
			return array();
		}
		if ( ! is_array( $value ) || array_diff( array_keys( $value ), self::SOCIAL_KEYS ) ) {
			self::invalid( $errors, 'social' );
			return array();
		}
		$out = array();
		foreach ( $value as $key => $handle ) {
			$handle = self::text( $handle );
			if ( mb_strlen( $handle ) > 100 ) {
				self::invalid( $errors, 'social.' . $key );
			} elseif ( '' !== $handle ) {
				$out[ $key ] = $handle;
			}
		}
		return $out;
	}
}
