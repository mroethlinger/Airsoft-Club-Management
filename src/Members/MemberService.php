<?php
/**
 * Member use cases.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Members;

use AirsoftClub\Access\Abilities;
use AirsoftClub\Access\Access;
use AirsoftClub\Access\Assignments;
use AirsoftClub\Audit\AuditLog;
use AirsoftClub\Structure\Structure;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creating, editing, moving and archiving members, and what other members may see of them.
 * Every method takes the acting user id and checks the rights itself.
 */
final class MemberService {

	public const PURGE_HOOK = 'acm_purge_former_members';

	/** Registers hooks. */
	public static function hooks(): void {
		add_filter( 'airsoft_club_region_in_use', array( self::class, 'region_in_use' ), 10, 2 );
		add_filter( 'airsoft_club_squad_in_use', array( self::class, 'squad_in_use' ), 10, 2 );
		add_filter( 'airsoft_club_regions_for', array( self::class, 'own_region' ), 10, 4 );
		add_action( 'deleted_user', array( MemberRepository::class, 'delete' ) );
		add_action( self::PURGE_HOOK, array( self::class, 'purge_expired' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
	}

	/** Schedules the daily archive clean-up (WP-Cron; Action Scheduler follows in a later phase). */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/** Days former members stay in the archive. */
	public static function archive_days(): int {
		return max( 1, (int) apply_filters( 'airsoft_club_archive_days', 90 ) );
	}

	/**
	 * Filter callback: members block deleting their region.
	 *
	 * @param bool $in_use Current value.
	 * @param int  $id     Region id.
	 */
	public static function region_in_use( $in_use, $id ): bool {
		return $in_use || MemberRepository::count_by( 'region_id', (int) $id ) > 0;
	}

	/**
	 * Filter callback: members block deleting their squad.
	 *
	 * @param bool $in_use Current value.
	 * @param int  $id     Squad id.
	 */
	public static function squad_in_use( $in_use, $id ): bool {
		return $in_use || MemberRepository::count_by( 'squad_id', (int) $id ) > 0;
	}

	/**
	 * Filter callback: member-level abilities apply in the member's own region.
	 *
	 * @param int[]  $regions Regions.
	 * @param int    $user_id User id.
	 * @param string $ability Ability.
	 * @param int    $min     Minimum level.
	 * @return int[]
	 */
	public static function own_region( $regions, $user_id, $ability, $min ): array {
		$regions = (array) $regions;
		if ( Abilities::MEMBER === (int) $min ) {
			$member = MemberRepository::find( (int) $user_id );
			if ( $member && MemberRepository::STATUS_ACTIVE === $member->status ) {
				$regions[] = $member->region_id;
			}
		}
		return $regions;
	}

	/**
	 * Squad of a member as far as it is active (null when the region runs without squads).
	 *
	 * @param object $member Member.
	 */
	public static function active_squad( object $member ): ?int {
		return $member->squad_id && Structure::squads_active( $member->region_id ) ? $member->squad_id : null;
	}

	/**
	 * Whether the actor leads the member: regional lead of the member's region (or higher),
	 * or squad leader of the member's active squad.
	 *
	 * @param int    $actor_id Actor.
	 * @param object $member   Member.
	 */
	public static function manages( int $actor_id, object $member ): bool {
		if ( $actor_id <= 0 ) {
			return false;
		}
		if ( Access::level_in( $actor_id, $member->region_id ) >= Abilities::REGIONAL_LEAD ) {
			return true;
		}
		$squad = self::active_squad( $member );
		return null !== $squad && Access::level_in( $actor_id, null, $squad ) >= Abilities::SQUAD_LEAD;
	}

	/**
	 * Validates a target region/squad pair.
	 *
	 * @param int      $region_id Region.
	 * @param int|null $squad_id  Squad.
	 * @return true|WP_Error
	 */
	private static function check_placement( int $region_id, ?int $squad_id ) {
		if ( ! Structure::region( $region_id ) ) {
			return new WP_Error( 'acm_invalid', __( 'Please choose a region.', 'airsoft-club-management' ) );
		}
		if ( null !== $squad_id ) {
			if ( Structure::region_of_squad( $squad_id ) !== $region_id ) {
				return new WP_Error( 'acm_invalid', __( 'The squad does not belong to this region.', 'airsoft-club-management' ) );
			}
			if ( ! Structure::squads_active( $region_id ) ) {
				return new WP_Error( 'acm_invalid', __( 'This region runs without squads.', 'airsoft-club-management' ) );
			}
		}
		return true;
	}

	/**
	 * Creates a member with a new WordPress account (no password is sent; invitations follow separately).
	 *
	 * @param int                 $actor_id Actor.
	 * @param array<string,mixed> $data     email, first_name, last_name, region_id, squad_id and profile fields.
	 * @return int|WP_Error New user id.
	 */
	public static function create( int $actor_id, array $data ) {
		$region_id = (int) ( $data['region_id'] ?? 0 );
		$squad_id  = empty( $data['squad_id'] ) ? null : (int) $data['squad_id'];
		$placement = self::check_placement( $region_id, $squad_id );
		if ( is_wp_error( $placement ) ) {
			return $placement;
		}
		if ( ! Access::can( $actor_id, 'members.create', $region_id, $squad_id ) ) {
			return new WP_Error( 'acm_forbidden', __( 'You may not add members here.', 'airsoft-club-management' ) );
		}
		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'acm_invalid', __( 'Please enter a valid email address.', 'airsoft-club-management' ) );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'acm_duplicate', __( 'This email address is already in use.', 'airsoft-club-management' ) );
		}
		$profile = Profile::clean(
			array_merge(
				array(
					'first_name' => '',
					'last_name'  => '',
				),
				$data
			)
		);
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		$birth = self::clean_date( $data['birth_date'] ?? null );
		if ( is_wp_error( $birth ) ) {
			return $birth;
		}
		$age_check = self::check_adult( $birth );
		if ( is_wp_error( $age_check ) ) {
			return $age_check;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => self::unique_login( $email ),
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => $profile['first_name'],
				'role'         => 'acm_member',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		MemberRepository::insert(
			array_merge(
				$profile,
				array(
					'user_id'       => $user_id,
					'member_number' => MemberRepository::next_number(),
					'status'        => MemberRepository::STATUS_ACTIVE,
					'region_id'     => $region_id,
					'squad_id'      => $squad_id,
					'birth_date'    => $birth,
					'joined_at'     => current_time( 'Y-m-d' ),
				)
			)
		);
		AuditLog::add(
			'member.created',
			'user',
			$user_id,
			array(
				'region_id' => $region_id,
				'squad_id'  => $squad_id,
			)
		);
		do_action( 'airsoft_club_member_created', $user_id, $actor_id );
		return $user_id;
	}

	/**
	 * Updates profile fields. Members edit their own profile; their leads may edit it too
	 * and additionally set birth date and joining date.
	 *
	 * @param int                 $actor_id  Actor.
	 * @param int                 $target_id Member.
	 * @param array<string,mixed> $input     Fields.
	 * @return string[]|WP_Error Changed fields.
	 */
	public static function update_profile( int $actor_id, int $target_id, array $input ) {
		$member = MemberRepository::find( $target_id );
		if ( ! $member ) {
			return new WP_Error( 'acm_not_found', __( 'Member not found.', 'airsoft-club-management' ) );
		}
		$is_self = $actor_id === $target_id && MemberRepository::STATUS_ACTIVE === $member->status;
		$manages = self::manages( $actor_id, $member );
		if ( ! $is_self && ! $manages ) {
			return new WP_Error( 'acm_forbidden', __( 'You may not edit this member.', 'airsoft-club-management' ) );
		}
		$data = Profile::clean( $input );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( $manages ) {
			foreach ( array( 'birth_date', 'joined_at' ) as $date_field ) {
				if ( array_key_exists( $date_field, $input ) ) {
					$date = self::clean_date( $input[ $date_field ] );
					if ( is_wp_error( $date ) ) {
						return $date;
					}
					if ( 'birth_date' === $date_field ) {
						$adult = self::check_adult( $date );
						if ( is_wp_error( $adult ) ) {
							return $adult;
						}
					}
					$data[ $date_field ] = $date;
				}
			}
		}
		$changed = array();
		foreach ( $data as $key => $value ) {
			if ( $member->$key !== $value ) {
				$changed[] = $key;
			}
		}
		if ( $changed ) {
			MemberRepository::update( $target_id, array_intersect_key( $data, array_flip( $changed ) ) );
			AuditLog::add( 'member.updated', 'user', $target_id, array( 'fields' => $changed ) );
		}
		return $changed;
	}

	/**
	 * Moves a member to another region/squad. Changing the region needs lead rights in both regions;
	 * joining a squad needs leading that squad (or its region).
	 *
	 * @param int      $actor_id  Actor.
	 * @param int      $target_id Member.
	 * @param int      $region_id New region.
	 * @param int|null $squad_id  New squad.
	 * @return true|WP_Error
	 */
	public static function move( int $actor_id, int $target_id, int $region_id, ?int $squad_id ) {
		$member = MemberRepository::find( $target_id );
		if ( ! $member || MemberRepository::STATUS_ACTIVE !== $member->status ) {
			return new WP_Error( 'acm_not_found', __( 'Member not found.', 'airsoft-club-management' ) );
		}
		$placement = self::check_placement( $region_id, $squad_id );
		if ( is_wp_error( $placement ) ) {
			return $placement;
		}
		$allowed = self::manages( $actor_id, $member );
		if ( $allowed && $region_id !== $member->region_id ) {
			$allowed = Access::level_in( $actor_id, $member->region_id ) >= Abilities::REGIONAL_LEAD
				&& Access::level_in( $actor_id, $region_id ) >= Abilities::REGIONAL_LEAD;
		}
		if ( $allowed && null !== $squad_id && $squad_id !== $member->squad_id ) {
			$allowed = Access::level_in( $actor_id, $region_id, $squad_id ) >= Abilities::SQUAD_LEAD;
		}
		if ( ! $allowed ) {
			return new WP_Error( 'acm_forbidden', __( 'You may not move this member there.', 'airsoft-club-management' ) );
		}
		MemberRepository::update(
			$target_id,
			array(
				'region_id' => $region_id,
				'squad_id'  => $squad_id,
			)
		);
		AuditLog::add(
			'member.moved',
			'user',
			$target_id,
			array(
				'from_region' => $member->region_id,
				'to_region'   => $region_id,
				'from_squad'  => $member->squad_id,
				'to_squad'    => $squad_id,
			)
		);
		return true;
	}

	/**
	 * Moves a member to the former members archive: no member area, lead functions removed.
	 *
	 * @param int $actor_id  Actor.
	 * @param int $target_id Member.
	 * @return true|WP_Error
	 */
	public static function leave( int $actor_id, int $target_id ) {
		$member = MemberRepository::find( $target_id );
		if ( ! $member || MemberRepository::STATUS_ACTIVE !== $member->status ) {
			return new WP_Error( 'acm_not_found', __( 'Member not found.', 'airsoft-club-management' ) );
		}
		if ( $actor_id === $target_id ) {
			return new WP_Error( 'acm_forbidden', __( 'You cannot remove yourself.', 'airsoft-club-management' ) );
		}
		if ( ! self::manages( $actor_id, $member ) || ! Access::can( $actor_id, 'members.delete', $member->region_id, self::active_squad( $member ) ) ) {
			return new WP_Error( 'acm_forbidden', __( 'You may not remove this member.', 'airsoft-club-management' ) );
		}
		if ( user_can( $target_id, 'manage_options' ) ) {
			return new WP_Error( 'acm_forbidden', __( 'Administrators cannot be removed here.', 'airsoft-club-management' ) );
		}
		MemberRepository::update(
			$target_id,
			array(
				'status'  => MemberRepository::STATUS_LEFT,
				'left_at' => current_time( 'mysql', true ),
			)
		);
		Assignments::delete_user( $target_id );
		$user = get_userdata( $target_id );
		if ( $user ) {
			foreach ( array( 'acm_member', 'acm_lead' ) as $role ) {
				$user->remove_role( $role );
			}
			\WP_Session_Tokens::get_instance( $target_id )->destroy_all();
		}
		AuditLog::add( 'member.left', 'user', $target_id );
		return true;
	}

	/**
	 * Brings a former member back.
	 *
	 * @param int $actor_id  Actor.
	 * @param int $target_id Former member.
	 * @return true|WP_Error
	 */
	public static function reactivate( int $actor_id, int $target_id ) {
		$member = MemberRepository::find( $target_id );
		if ( ! $member || MemberRepository::STATUS_LEFT !== $member->status ) {
			return new WP_Error( 'acm_not_found', __( 'Former member not found.', 'airsoft-club-management' ) );
		}
		if ( ! self::manages( $actor_id, $member ) || ! Access::can( $actor_id, 'members.create', $member->region_id, self::active_squad( $member ) ) ) {
			return new WP_Error( 'acm_forbidden', __( 'You may not reactivate this member.', 'airsoft-club-management' ) );
		}
		MemberRepository::update(
			$target_id,
			array(
				'status'  => MemberRepository::STATUS_ACTIVE,
				'left_at' => null,
			)
		);
		$user = get_userdata( $target_id );
		if ( $user ) {
			$user->add_role( 'acm_member' );
		}
		AuditLog::add( 'member.reactivated', 'user', $target_id );
		return true;
	}

	/** Deletes former members whose archive period is over (accounts included). Returns the number removed. */
	public static function purge_expired(): int {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::archive_days() * DAY_IN_SECONDS );
		$count  = 0;
		foreach ( MemberRepository::left_before( $cutoff ) as $user_id ) {
			if ( user_can( $user_id, 'manage_options' ) ) {
				continue;
			}
			MemberRepository::delete( $user_id );
			wp_delete_user( $user_id );
			AuditLog::add( 'member.purged', 'user', $user_id );
			++$count;
		}
		return $count;
	}

	/**
	 * Name parts as the viewer may see them. The member and their leads always see the full name;
	 * others get what the member chose: full, last name shortened, or the nickname only.
	 *
	 * @param object $member    Member.
	 * @param int    $viewer_id Viewer.
	 * @return array{first:string,last:string}
	 */
	public static function display_parts( object $member, int $viewer_id ): array {
		$full = array(
			'first' => (string) $member->first_name,
			'last'  => (string) $member->last_name,
		);
		if ( 'full' === $member->name_display || $viewer_id === $member->user_id || self::manages( $viewer_id, $member ) ) {
			return $full;
		}
		if ( 'nickname' === $member->name_display && '' !== (string) $member->nickname ) {
			return array(
				'first' => (string) $member->nickname,
				'last'  => '',
			);
		}
		return array(
			'first' => $full['first'],
			'last'  => '' !== $full['last'] ? mb_substr( $full['last'], 0, 1 ) . '.' : '',
		);
	}

	/**
	 * Display name for a viewer.
	 *
	 * @param object $member    Member.
	 * @param int    $viewer_id Viewer.
	 */
	public static function display_name( object $member, int $viewer_id ): string {
		$p = self::display_parts( $member, $viewer_id );
		return trim( $p['first'] . ' ' . $p['last'] );
	}

	/**
	 * The player card: what the viewer may see of a member.
	 * Contact data only when shared (or for the member and their leads); address, birth date and
	 * mobility only for the member and their leads. Signed-out viewers and non-members see nothing.
	 *
	 * @param object $member    Member.
	 * @param int    $viewer_id Viewer.
	 * @return array<string,mixed>|null
	 */
	public static function card( object $member, int $viewer_id ): ?array {
		if ( $viewer_id <= 0 || ! user_can( $viewer_id, 'acm_member_area' ) ) {
			return null;
		}
		$is_self = $viewer_id === $member->user_id;
		$manages = self::manages( $viewer_id, $member );
		if ( MemberRepository::STATUS_ACTIVE !== $member->status && ! $manages ) {
			return null;
		}
		$region = Structure::region( $member->region_id );
		$squad  = self::active_squad( $member );
		$squad  = $squad ? Structure::squad( $squad ) : null;
		$parts  = self::display_parts( $member, $viewer_id );
		$user   = get_userdata( $member->user_id );
		$card   = array(
			'id'            => $member->user_id,
			'first_name'    => $parts['first'],
			'last_name'     => $parts['last'],
			'nickname'      => $member->nickname,
			'region'        => $region ? $region->name : null,
			'squad'         => $squad ? $squad->name : null,
			'combat_role'   => $member->combat_role,
			'play_style'    => $member->play_style,
			'social'        => $member->social,
			'member_number' => $member->member_number,
			'phone'         => ( $is_self || $manages || $member->share_phone ) ? $member->phone : null,
			'email'         => ( $is_self || $manages || $member->share_email ) && $user ? $user->user_email : null,
		);
		if ( $is_self || $manages ) {
			$card += array(
				'status'              => $member->status,
				'birth_date'          => $member->birth_date,
				'street'              => $member->street,
				'postal_code'         => $member->postal_code,
				'city'                => $member->city,
				'country'             => $member->country,
				'can_drive'           => $member->can_drive,
				'vehicle_seats'       => $member->vehicle_seats,
				'vehicle_description' => $member->vehicle_description,
				'needs_ride'          => $member->needs_ride,
				'mobility_notes'      => $member->mobility_notes,
				'joined_at'           => $member->joined_at,
				'left_at'             => $member->left_at,
			);
		}
		return $card;
	}

	/**
	 * Members the viewer may list: leads see everyone they manage, members see their own region.
	 *
	 * @param int $viewer_id Viewer.
	 * @return object[]
	 */
	public static function visible_members( int $viewer_id ): array {
		if ( $viewer_id <= 0 || ! user_can( $viewer_id, 'acm_member_area' ) ) {
			return array();
		}
		if ( Access::max_level( $viewer_id ) >= Abilities::OVERALL_LEAD ) {
			return MemberRepository::list();
		}
		$regions = array();
		$own     = MemberRepository::find( $viewer_id );
		if ( $own && MemberRepository::STATUS_ACTIVE === $own->status ) {
			$regions[] = $own->region_id;
		}
		foreach ( Assignments::active_for( $viewer_id ) as $a ) {
			if ( Assignments::REGIONAL_LEAD === $a->role ) {
				$regions[] = $a->scope_id;
			} elseif ( Assignments::SQUAD_LEAD === $a->role ) {
				$regions[] = (int) Structure::region_of_squad( $a->scope_id );
			}
		}
		return MemberRepository::list( array_filter( $regions ) );
	}

	/**
	 * Former members in the archive the viewer manages.
	 *
	 * @param int $viewer_id Viewer.
	 * @return object[]
	 */
	public static function archive( int $viewer_id ): array {
		return array_values(
			array_filter(
				MemberRepository::list( null, null, MemberRepository::STATUS_LEFT ),
				static fn( $m ) => self::manages( $viewer_id, $m )
			)
		);
	}

	/**
	 * Validates an optional Y-m-d date.
	 *
	 * @param mixed $value Value.
	 * @return string|null|WP_Error
	 */
	private static function clean_date( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value || $value < '1900-01-01' || $value > gmdate( 'Y-m-d' ) ) {
			return new WP_Error( 'acm_invalid', __( 'Please enter a valid date.', 'airsoft-club-management' ) );
		}
		return $value;
	}

	/**
	 * Members must be adults (no data about minors).
	 *
	 * @param string|null $birth_date Y-m-d.
	 * @return true|WP_Error
	 */
	private static function check_adult( ?string $birth_date ) {
		if ( null !== $birth_date && $birth_date > gmdate( 'Y-m-d', strtotime( '-18 years' ) ) ) {
			return new WP_Error( 'acm_too_young', __( 'Members must be at least 18 years old.', 'airsoft-club-management' ) );
		}
		return true;
	}

	/**
	 * Unique login name derived from the email's local part.
	 *
	 * @param string $email Email.
	 */
	private static function unique_login( string $email ): string {
		$base  = sanitize_user( strtolower( strstr( $email, '@', true ) ), true );
		$base  = '' !== $base ? substr( $base, 0, 50 ) : 'member';
		$login = $base;
		$i     = 2;
		while ( username_exists( $login ) ) {
			$login = $base . $i;
			++$i;
		}
		return $login;
	}
}
