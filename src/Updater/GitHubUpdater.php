<?php
/**
 * Updates from GitHub releases.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Updater;

defined( 'ABSPATH' ) || exit;

/**
 * Offers updates from GitHub releases through the normal WordPress update screen.
 * The release must contain an asset named airsoft-club-management.zip.
 * Filter airsoft_club_github_repo returns "owner/name"; an empty string switches the updater off
 * (a wordpress.org build will remove this class).
 */
final class GitHubUpdater {

	private const CACHE_KEY = 'acm_github_release';
	private const ASSET     = 'airsoft-club-management.zip';
	private const REPO      = 'mroethlinger/Airsoft-Club-Management';

	/** Registers hooks. */
	public static function hooks(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'inject' ) );
		add_action( 'upgrader_process_complete', array( self::class, 'clear_cache' ) );
	}

	/** Repository or empty string. */
	private static function repo(): string {
		$repo = (string) apply_filters( 'airsoft_club_github_repo', self::REPO );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ? $repo : '';
	}

	/** Clears the cached release. */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Adds our plugin to the update transient.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public static function inject( $transient ) {
		$repo = self::repo();
		if ( '' === $repo || ! is_object( $transient ) ) {
			return $transient;
		}
		$release = self::latest_release( $repo );
		if ( ! $release ) {
			return $transient;
		}
		$basename = plugin_basename( AIRSOFT_CLUB_FILE );
		$item     = (object) array(
			'slug'        => dirname( $basename ),
			'plugin'      => $basename,
			'new_version' => $release['version'],
			'url'         => $release['html_url'],
			'package'     => $release['package'],
		);
		if ( version_compare( $release['version'], AIRSOFT_CLUB_VERSION, '>' ) ) {
			$transient->response[ $basename ] = $item;
		} else {
			$transient->no_update[ $basename ] = $item;
		}
		return $transient;
	}

	/**
	 * Parses a GitHub release API response.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array{version:string,html_url:string,package:string}|null
	 */
	public static function parse_release( $data ): ?array {
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}
		$version = ltrim( (string) $data['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return null;
		}
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			$url = (string) ( $asset['browser_download_url'] ?? '' );
			if ( self::ASSET === ( $asset['name'] ?? '' ) && str_starts_with( $url, 'https://github.com/' ) ) {
				return array(
					'version'  => $version,
					'html_url' => esc_url_raw( (string) ( $data['html_url'] ?? '' ) ),
					'package'  => esc_url_raw( $url ),
				);
			}
		}
		return null;
	}

	/**
	 * Latest release, cached for 12 hours (also failures, to respect the API rate limit).
	 *
	 * @param string $repo owner/name.
	 * @return array{version:string,html_url:string,package:string}|null
	 */
	private static function latest_release( string $repo ): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached ? $cached : null;
		}
		$response = wp_remote_get(
			'https://api.github.com/repos/' . $repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);
		$result   = null;
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$result = self::parse_release( json_decode( wp_remote_retrieve_body( $response ), true ) );
		}
		set_transient( self::CACHE_KEY, $result ? $result : array(), 12 * HOUR_IN_SECONDS );
		return $result;
	}
}
