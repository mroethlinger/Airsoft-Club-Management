<?php
/**
 * REST routes for members.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Rest;

use AirsoftClub\Members\Avatars;
use AirsoftClub\Members\MemberRepository;
use AirsoftClub\Members\MemberService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Namespace acm/v1. Every route requires a signed-in member; the services check the scoped rights.
 * Phase 2 ships the routes the member area needs first; more follow with phase 3.
 */
final class MembersController {

	public const NS = 'acm/v1';

	/** Registers hooks. */
	public static function hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/** Registers the routes. */
	public static function routes(): void {
		$member = array( self::class, 'is_member' );
		$id     = array(
			'id' => array(
				'type'     => 'integer',
				'minimum'  => 1,
				'required' => true,
			),
		);
		register_rest_route(
			self::NS,
			'/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'me' ),
					'permission_callback' => $member,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update_me' ),
					'permission_callback' => $member,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/members',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => $member,
			)
		);
		register_rest_route(
			self::NS,
			'/members/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => $member,
				'args'                => $id,
			)
		);
		register_rest_route(
			self::NS,
			'/members/(?P<id>\d+)/avatar',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'avatar' ),
					'permission_callback' => $member,
					'args'                => $id,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'upload_avatar' ),
					'permission_callback' => $member,
					'args'                => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete_avatar' ),
					'permission_callback' => $member,
					'args'                => $id,
				),
			)
		);
	}

	/** Permission callback: signed in with member area access. */
	public static function is_member(): bool {
		return is_user_logged_in() && current_user_can( 'acm_member_area' );
	}

	/**
	 * Maps a WP_Error from a service to a REST error with status.
	 *
	 * @param WP_Error $error Error.
	 */
	private static function error( WP_Error $error ): WP_Error {
		$status = array(
			'acm_forbidden' => 403,
			'acm_not_found' => 404,
			'acm_duplicate' => 409,
		);
		$code   = $error->get_error_code();
		return new WP_Error( $code, $error->get_error_message(), array( 'status' => $status[ $code ] ?? 422 ) );
	}

	/** GET /me. */
	public static function me(): WP_REST_Response|WP_Error {
		$viewer = get_current_user_id();
		$member = MemberRepository::find( $viewer );
		if ( ! $member ) {
			return new WP_Error( 'acm_not_found', __( 'No member record for this account.', 'airsoft-club-management' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'data' => MemberService::card( $member, $viewer ) ) );
	}

	/**
	 * PATCH /me.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function update_me( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$viewer  = get_current_user_id();
		$changed = MemberService::update_profile( $viewer, $viewer, (array) $request->get_json_params() );
		if ( is_wp_error( $changed ) ) {
			return self::error( $changed );
		}
		return new WP_REST_Response(
			array(
				'data'    => MemberService::card( MemberRepository::find( $viewer ), $viewer ),
				'changed' => $changed,
			)
		);
	}

	/** GET /members. */
	public static function index(): WP_REST_Response {
		$viewer = get_current_user_id();
		$cards  = array();
		foreach ( MemberService::visible_members( $viewer ) as $member ) {
			$card = MemberService::card( $member, $viewer );
			if ( $card ) {
				$cards[] = $card;
			}
		}
		return new WP_REST_Response( array( 'data' => $cards ) );
	}

	/**
	 * GET /members/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$viewer = get_current_user_id();
		$member = MemberRepository::find( (int) $request['id'] );
		$card   = $member ? MemberService::card( $member, $viewer ) : null;
		if ( ! $card ) {
			return new WP_Error( 'acm_not_found', __( 'Member not found.', 'airsoft-club-management' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'data' => $card ) );
	}

	/**
	 * GET /members/{id}/avatar: streams the photo after the visibility check.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function avatar( WP_REST_Request $request ): WP_Error {
		$path = Avatars::path_for( get_current_user_id(), (int) $request['id'] );
		if ( ! $path ) {
			return new WP_Error( 'acm_not_found', __( 'No photo.', 'airsoft-club-management' ), array( 'status' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: image/jpeg' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Cache-Control: private, max-age=300' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a private file.
		exit;
	}

	/**
	 * POST /members/{id}/avatar (multipart field "file").
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function upload_avatar( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$files = $request->get_file_params();
		$tmp   = (string) ( $files['file']['tmp_name'] ?? '' );
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'acm_invalid', __( 'Please upload an image.', 'airsoft-club-management' ), array( 'status' => 422 ) );
		}
		$result = Avatars::store( get_current_user_id(), (int) $request['id'], $tmp );
		return is_wp_error( $result ) ? self::error( $result ) : new WP_REST_Response( array( 'ok' => true ), 201 );
	}

	/**
	 * DELETE /members/{id}/avatar.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function delete_avatar( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = Avatars::remove( get_current_user_id(), (int) $request['id'] );
		return is_wp_error( $result ) ? self::error( $result ) : new WP_REST_Response( array( 'ok' => true ) );
	}
}
