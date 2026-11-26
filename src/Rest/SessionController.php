<?php
/**
 * Session, nonce and notification routes for the member area.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Rest;

use AirsoftClub\Access\Abilities;
use AirsoftClub\Access\Access;
use AirsoftClub\Access\Assignments;
use AirsoftClub\Access\TwoFactor;
use AirsoftClub\Members\MemberRepository;
use AirsoftClub\Members\MemberService;
use AirsoftClub\Notifications\Notifications;
use AirsoftClub\Settings\Branding;
use AirsoftClub\Settings\Settings;
use AirsoftClub\Structure\Structure;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/** GET /session, POST /nonce, notification feed. */
final class SessionController {

	/** Registers hooks. */
	public static function hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/** Registers the routes. */
	public static function routes(): void {
		$member = array( MembersController::class, 'is_member' );
		register_rest_route(
			MembersController::NS,
			'/session',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'session' ),
				'permission_callback' => $member,
			)
		);
		register_rest_route(
			MembersController::NS,
			'/nonce',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'nonce' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
		register_rest_route(
			MembersController::NS,
			'/notifications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'notifications' ),
				'permission_callback' => $member,
			)
		);
		register_rest_route(
			MembersController::NS,
			'/notifications/(?P<id>\d+)/read',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'read' ),
				'permission_callback' => $member,
			)
		);
		register_rest_route(
			MembersController::NS,
			'/notifications/read-all',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'read_all' ),
				'permission_callback' => $member,
			)
		);
	}

	/** Branding for the app. */
	public static function branding(): array {
		$s = Settings::all();
		return array(
			'org_name'               => Settings::club_name(),
			'org_short_name'         => Settings::short_name(),
			'logo_url'               => Branding::logo_url( 'thumbnail' ),
			'color_primary'          => $s['color_primary'],
			'color_primary_contrast' => $s['color_primary_contrast'],
			'color_background'       => $s['color_background'],
			'color_surface'          => $s['color_surface'],
			'color_text'             => $s['color_text'],
			'association'            => Settings::association_enabled(),
		);
	}

	/**
	 * Everything the app shell needs about the signed-in user.
	 *
	 * @param int $user_id User.
	 * @return array<string,mixed>
	 */
	public static function user_summary( int $user_id ): array {
		$user   = get_userdata( $user_id );
		$member = MemberRepository::find( $user_id );
		$card   = $member ? MemberService::card( $member, $user_id ) : null;
		$roles  = array();
		$labels = array(
			Assignments::OVERALL_LEAD  => 'overall_lead',
			Assignments::REGIONAL_LEAD => 'regional_lead',
			Assignments::SQUAD_LEAD    => 'squad_lead',
		);
		foreach ( Assignments::active_for( $user_id ) as $a ) {
			$scope   = Assignments::REGIONAL_LEAD === $a->role ? Structure::region( $a->scope_id ) : ( Assignments::SQUAD_LEAD === $a->role ? Structure::squad( $a->scope_id ) : null );
			$roles[] = array(
				'role'       => $labels[ $a->role ] ?? $a->role,
				'scope_id'   => $a->scope_id,
				'scope_name' => $scope ? $scope->name : null,
				'is_deputy'  => $a->is_deputy,
			);
		}
		$level  = Access::max_level( $user_id );
		$region = $member ? Structure::region( $member->region_id ) : null;
		$squad  = $member ? MemberService::active_squad( $member ) : null;
		$squad  = $squad ? Structure::squad( $squad ) : null;
		$first  = $member ? $member->first_name : (string) $user->first_name;
		$last   = $member ? $member->last_name : (string) $user->last_name;

		$card = (array) $card;
		if ( isset( $card['social'] ) ) {
			$card['social'] = (object) $card['social'];
		}
		return array_merge(
			$card,
			array(
				'id'                => $user_id,
				'first_name'        => $first,
				'last_name'         => $last,
				'name'              => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : $user->display_name,
				'email'             => $user->user_email,
				'has_member_record' => (bool) $member,
				'name_display'      => $member ? $member->name_display : 'full',
				'share_phone'       => $member ? $member->share_phone : false,
				'share_email'       => $member ? $member->share_email : false,
				'avatar_url'        => $member && $member->avatar_path ? self::avatar_url( $user_id, (string) $member->avatar_path ) : null,
				'region'            => $region ? array(
					'id'             => $region->id,
					'name'           => $region->name,
					'squads_enabled' => $region->squads_enabled,
				) : null,
				'squad'             => $squad ? array(
					'id'   => $squad->id,
					'name' => $squad->name,
				) : null,
				'roles'             => $roles,
				'is_lead'           => $level >= Abilities::SQUAD_LEAD,
				'is_overall_lead'   => $level >= Abilities::OVERALL_LEAD,
				'abilities'         => Access::abilities_of( $user_id ),
				'needs_2fa'         => TwoFactor::enforced() && ! empty( array_intersect( array( 'acm_lead', 'acm_board', 'acm_treasurer', 'acm_cash_auditor' ), (array) $user->roles ) ) && TwoFactor::blocks( $user_id ),
				'admin_url'         => user_can( $user_id, 'acm_view_rights' ) ? admin_url( 'admin.php?page=acm' ) : null,
				'profile_url'       => admin_url( 'profile.php' ),
			)
		);
	}

	/**
	 * Avatar URL with REST nonce (images cannot send the X-WP-Nonce header) and a cache buster.
	 *
	 * @param int    $user_id User.
	 * @param string $file    Stored file name.
	 */
	public static function avatar_url( int $user_id, string $file ): string {
		return add_query_arg(
			array(
				'v'        => substr( md5( $file ), 0, 8 ),
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			),
			rest_url( MembersController::NS . '/members/' . $user_id . '/avatar' )
		);
	}

	/** GET /session. */
	public static function session(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'data'     => self::user_summary( get_current_user_id() ),
				'branding' => self::branding(),
			)
		);
	}

	/** POST /nonce: a fresh nonce after the old one expired. */
	public static function nonce(): WP_REST_Response {
		return new WP_REST_Response( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
	}

	/** GET /notifications. */
	public static function notifications(): WP_REST_Response {
		$user = get_current_user_id();
		return new WP_REST_Response(
			array(
				'data'   => Notifications::latest( $user ),
				'unread' => Notifications::unread( $user ),
			)
		);
	}

	/**
	 * POST /notifications/{id}/read.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function read( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'ok' => Notifications::mark_read( get_current_user_id(), (int) $request['id'] ) ) );
	}

	/** POST /notifications/read-all. */
	public static function read_all(): WP_REST_Response {
		return new WP_REST_Response( array( 'count' => Notifications::read_all( get_current_user_id() ) ) );
	}
}
