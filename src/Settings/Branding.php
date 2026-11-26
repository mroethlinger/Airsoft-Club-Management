<?php
/**
 * Branding output.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Settings;

defined( 'ABSPATH' ) || exit;

/** Turns the branding settings into CSS custom properties for the theme, the member area and the login page. */
final class Branding {

	/** Registers hooks. */
	public static function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'login_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * CSS variable name => value.
	 *
	 * @return array<string,string>
	 */
	public static function variables(): array {
		$s   = Settings::all();
		$out = array();
		foreach ( array_keys( Settings::colors() ) as $key ) {
			$color = sanitize_hex_color( $s[ $key ] );
			if ( $color ) {
				$out[ '--acm-' . str_replace( '_', '-', substr( $key, 6 ) ) ] = $color;
			}
		}
		return $out;
	}

	/** Inline CSS with the variables on :root. */
	public static function css(): string {
		$lines = array();
		foreach ( self::variables() as $name => $value ) {
			$lines[] = $name . ':' . $value . ';';
		}
		return ':root{' . implode( '', $lines ) . '}';
	}

	/**
	 * Logo URL or empty string.
	 *
	 * @param string $size Image size.
	 */
	public static function logo_url( string $size = 'medium' ): string {
		$id = (int) Settings::get( 'logo_id' );
		if ( ! $id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, $size );
		return $url ? $url : '';
	}

	/** Adds the variables to the page. */
	public static function enqueue(): void {
		wp_register_style( 'acm-branding', false, array(), AIRSOFT_CLUB_VERSION );
		wp_enqueue_style( 'acm-branding' );
		wp_add_inline_style( 'acm-branding', self::css() );
	}
}
