<?php
/**
 * Scoped lead assignments.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Access;

use AirsoftClub\Audit\AuditLog;
use AirsoftClub\Database\Migrator;
use AirsoftClub\Structure\Structure;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Lead functions bound to a scope: overall lead (club), regional lead (region), squad leader (squad).
 * Deputies are assignments flagged is_deputy with the same rights.
 * Users with at least one assignment get the marker role acm_lead.
 */
final class Assignments {

	public const OVERALL_LEAD  = 'overall_lead';
	public const REGIONAL_LEAD = 'regional_lead';
	public const SQUAD_LEAD    = 'squad_lead';

	/**
	 * Per-request cache: user id => rows.
	 *
	 * @var array<int,array<int,object>>
	 */
	private static array $cache = array();

	/**
	 * Role => scope type.
	 *
	 * @return array<string,string>
	 */
	public static function scope_types(): array {
		return array(
			self::OVERALL_LEAD  => 'club',
			self::REGIONAL_LEAD => 'region',
			self::SQUAD_LEAD    => 'squad',
		);
	}

	/**
	 * Role => label.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			self::OVERALL_LEAD  => __( 'Overall lead', 'airsoft-club-management' ),
			self::REGIONAL_LEAD => __( 'Regional lead', 'airsoft-club-management' ),
			self::SQUAD_LEAD    => __( 'Squad leader', 'airsoft-club-management' ),
		);
	}

	/** Clears the per-request cache. */
	public static function flush(): void {
		self::$cache = array();
	}

	/**
	 * All stored assignments of a user, including suspended ones.
	 *
	 * @param int $user_id User id.
	 * @return array<int,object> rows (id, user_id, role, scope_type, scope_id, is_deputy)
	 */
	public static function stored_for( int $user_id ): array {
		if ( ! isset( self::$cache[ $user_id ] ) ) {
			global $wpdb;
			$table = Migrator::table( 'role_assignments' );
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, role, scope_type, scope_id, is_deputy FROM {$table} WHERE user_id = %d ORDER BY id", $user_id ) );
			foreach ( $rows as $row ) {
				$row->id        = (int) $row->id;
				$row->user_id   = (int) $row->user_id;
				$row->scope_id  = (int) $row->scope_id;
				$row->is_deputy = (bool) (int) $row->is_deputy;
			}
			self::$cache[ $user_id ] = $rows;
		}
		return self::$cache[ $user_id ];
	}

	/**
	 * Effective assignments: squad leader roles in regions with squads switched off are suspended.
	 *
	 * @param int $user_id User id.
	 * @return array<int,object>
	 */
	public static function active_for( int $user_id ): array {
		return array_values(
			array_filter(
				self::stored_for( $user_id ),
				static function ( $a ) {
					if ( self::SQUAD_LEAD !== $a->role ) {
						return true;
					}
					$region = Structure::region_of_squad( $a->scope_id );
					return null !== $region && Structure::squads_active( $region );
				}
			)
		);
	}

	/**
	 * Whether a stored assignment is suspended.
	 *
	 * @param object $assignment Row.
	 */
	public static function is_suspended( object $assignment ): bool {
		if ( self::SQUAD_LEAD !== $assignment->role ) {
			return false;
		}
		$region = Structure::region_of_squad( (int) $assignment->scope_id );
		return null === $region || ! Structure::squads_active( $region );
	}

	/**
	 * All assignments for a scope.
	 *
	 * @param string $role     Role.
	 * @param int    $scope_id Scope id (0 for club).
	 * @return array<int,object>
	 */
	public static function for_scope( string $role, int $scope_id ): array {
		global $wpdb;
		$table = Migrator::table( 'role_assignments' );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, role, scope_type, scope_id, is_deputy FROM {$table} WHERE role = %s AND scope_id = %d ORDER BY is_deputy, id", $role, $scope_id ) );
		foreach ( $rows as $row ) {
			$row->id        = (int) $row->id;
			$row->user_id   = (int) $row->user_id;
			$row->scope_id  = (int) $row->scope_id;
			$row->is_deputy = (bool) (int) $row->is_deputy;
		}
		return $rows;
	}

	/**
	 * Appoints a user.
	 *
	 * @param int    $user_id   User id.
	 * @param string $role      One of the role constants.
	 * @param int    $scope_id  Region or squad id (0 for overall lead).
	 * @param bool   $is_deputy Deputy flag.
	 * @return int|WP_Error Assignment id.
	 */
	public static function assign( int $user_id, string $role, int $scope_id = 0, bool $is_deputy = false ) {
		$types = self::scope_types();
		if ( ! isset( $types[ $role ] ) ) {
			return new WP_Error( 'acm_invalid', __( 'Unknown lead function.', 'airsoft-club-management' ) );
		}
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'acm_not_found', __( 'User not found.', 'airsoft-club-management' ) );
		}
		if ( self::OVERALL_LEAD === $role ) {
			$scope_id = 0;
		} elseif ( self::REGIONAL_LEAD === $role && ! Structure::region( $scope_id ) ) {
			return new WP_Error( 'acm_not_found', __( 'Region not found.', 'airsoft-club-management' ) );
		} elseif ( self::SQUAD_LEAD === $role && ! Structure::squad( $scope_id ) ) {
			return new WP_Error( 'acm_not_found', __( 'Squad not found.', 'airsoft-club-management' ) );
		}
		foreach ( self::stored_for( $user_id ) as $existing ) {
			if ( $existing->role === $role && $existing->scope_id === $scope_id ) {
				return new WP_Error( 'acm_duplicate', __( 'This user already holds this function.', 'airsoft-club-management' ) );
			}
		}
		global $wpdb;
		$wpdb->insert(
			Migrator::table( 'role_assignments' ),
			array(
				'user_id'    => $user_id,
				'role'       => $role,
				'scope_type' => $types[ $role ],
				'scope_id'   => $scope_id,
				'is_deputy'  => $is_deputy ? 1 : 0,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		self::flush();
		self::sync_marker_role( $user_id );
		AuditLog::add(
			'assignment.created',
			'user',
			$user_id,
			array(
				'role'      => $role,
				'scope_id'  => $scope_id,
				'is_deputy' => $is_deputy,
			)
		);
		return $id;
	}

	/**
	 * Removes an assignment.
	 *
	 * @param int $assignment_id Assignment id.
	 * @return true|WP_Error
	 */
	public static function revoke( int $assignment_id ) {
		global $wpdb;
		$table = Migrator::table( 'role_assignments' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT user_id, role, scope_id FROM {$table} WHERE id = %d", $assignment_id ) );
		if ( ! $row ) {
			return new WP_Error( 'acm_not_found', __( 'Assignment not found.', 'airsoft-club-management' ) );
		}
		$wpdb->delete( $table, array( 'id' => $assignment_id ), array( '%d' ) );
		self::flush();
		self::sync_marker_role( (int) $row->user_id );
		AuditLog::add(
			'assignment.deleted',
			'user',
			(int) $row->user_id,
			array(
				'role'     => $row->role,
				'scope_id' => (int) $row->scope_id,
			)
		);
		return true;
	}

	/**
	 * Removes all assignments of a deleted user.
	 *
	 * @param int $user_id User id.
	 */
	public static function delete_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( Migrator::table( 'role_assignments' ), array( 'user_id' => $user_id ), array( '%d' ) );
		self::flush();
	}

	/**
	 * Adds or removes the marker role acm_lead so it matches the stored assignments.
	 *
	 * @param int $user_id User id.
	 */
	public static function sync_marker_role( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$has = in_array( 'acm_lead', (array) $user->roles, true );
		if ( self::stored_for( $user_id ) && ! $has ) {
			$user->add_role( 'acm_lead' );
		} elseif ( ! self::stored_for( $user_id ) && $has ) {
			$user->remove_role( 'acm_lead' );
		}
	}
}
