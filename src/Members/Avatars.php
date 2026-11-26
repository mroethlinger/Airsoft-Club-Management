<?php
/**
 * Member photos.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Members;

use AirsoftClub\Audit\AuditLog;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Member photos in a private folder: uploads are re-encoded (drops EXIF/GPS and anything hidden in the file),
 * scaled to at most 512 px and stored under a random name. They are delivered only through the REST
 * route acm/v1/members/{id}/avatar after the same visibility check as the player card.
 */
final class Avatars {

	private const MAX_BYTES = 5 * MB_IN_BYTES;
	private const SIZE      = 512;
	private const MIME      = array( 'image/jpeg', 'image/png', 'image/webp' );

	/** Private folder (created with deny rules on first use). */
	public static function dir(): string {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . 'acm-private/avatars/';
	}

	/** Creates the folder with web server deny rules. */
	private static function ensure_dir(): bool {
		$dir = self::dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$root = dirname( $dir ) . '/';
		if ( ! file_exists( $root . '.htaccess' ) ) {
			file_put_contents( $root . '.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny static file.
		}
		foreach ( array( $root, $dir ) as $folder ) {
			if ( ! file_exists( $folder . 'index.php' ) ) {
				file_put_contents( $folder . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny static file.
			}
		}
		return true;
	}

	/**
	 * Stores a new photo for a member (the member themself or a lead who manages them).
	 *
	 * @param int    $actor_id  Actor.
	 * @param int    $target_id Member.
	 * @param string $tmp_path  Path of the uploaded temporary file.
	 * @return true|WP_Error
	 */
	public static function store( int $actor_id, int $target_id, string $tmp_path ) {
		$member = MemberRepository::find( $target_id );
		if ( ! $member ) {
			return new WP_Error( 'acm_not_found', __( 'Member not found.', 'airsoft-club-management' ) );
		}
		if ( $actor_id !== $target_id && ! MemberService::manages( $actor_id, $member ) ) {
			return new WP_Error( 'acm_forbidden', __( 'You may not change this photo.', 'airsoft-club-management' ) );
		}
		if ( ! is_file( $tmp_path ) || filesize( $tmp_path ) > self::MAX_BYTES ) {
			return new WP_Error( 'acm_invalid', __( 'Please upload an image of at most 5 MB.', 'airsoft-club-management' ) );
		}
		$info = wp_getimagesize( $tmp_path );
		if ( ! $info || ! in_array( $info['mime'] ?? '', self::MIME, true ) ) {
			return new WP_Error( 'acm_invalid', __( 'Please upload a JPEG, PNG or WebP image.', 'airsoft-club-management' ) );
		}
		$editor = wp_get_image_editor( $tmp_path );
		if ( is_wp_error( $editor ) ) {
			return new WP_Error( 'acm_invalid', __( 'The image could not be read.', 'airsoft-club-management' ) );
		}
		$editor->resize( self::SIZE, self::SIZE, true );
		$editor->set_quality( 85 );
		if ( ! self::ensure_dir() ) {
			return new WP_Error( 'acm_storage', __( 'The photo could not be saved.', 'airsoft-club-management' ) );
		}
		$name  = wp_generate_password( 24, false ) . '.jpg';
		$saved = $editor->save( self::dir() . $name, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			return new WP_Error( 'acm_storage', __( 'The photo could not be saved.', 'airsoft-club-management' ) );
		}
		self::delete_file( $member->avatar_path );
		MemberRepository::update( $target_id, array( 'avatar_path' => $name ) );
		AuditLog::add( 'member.avatar_changed', 'user', $target_id );
		return true;
	}

	/**
	 * Removes the photo.
	 *
	 * @param int $actor_id  Actor.
	 * @param int $target_id Member.
	 * @return true|WP_Error
	 */
	public static function remove( int $actor_id, int $target_id ) {
		$member = MemberRepository::find( $target_id );
		if ( ! $member ) {
			return new WP_Error( 'acm_not_found', __( 'Member not found.', 'airsoft-club-management' ) );
		}
		if ( $actor_id !== $target_id && ! MemberService::manages( $actor_id, $member ) ) {
			return new WP_Error( 'acm_forbidden', __( 'You may not change this photo.', 'airsoft-club-management' ) );
		}
		self::delete_file( $member->avatar_path );
		MemberRepository::update( $target_id, array( 'avatar_path' => null ) );
		return true;
	}

	/**
	 * Absolute path of a member's photo if the viewer may see it.
	 *
	 * @param int $viewer_id Viewer.
	 * @param int $target_id Member.
	 */
	public static function path_for( int $viewer_id, int $target_id ): ?string {
		$member = MemberRepository::find( $target_id );
		if ( ! $member || ! $member->avatar_path || null === MemberService::card( $member, $viewer_id ) ) {
			return null;
		}
		$path = self::dir() . basename( (string) $member->avatar_path );
		return is_file( $path ) ? $path : null;
	}

	/**
	 * Deletes a stored file by name.
	 *
	 * @param string|null $name File name.
	 */
	public static function delete_file( ?string $name ): void {
		if ( $name ) {
			$path = self::dir() . basename( $name );
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * Deletes the photo of a deleted user.
	 *
	 * @param int $user_id User id.
	 */
	public static function on_delete_user( int $user_id ): void {
		$member = MemberRepository::find( $user_id );
		if ( $member ) {
			self::delete_file( $member->avatar_path );
		}
	}
}
