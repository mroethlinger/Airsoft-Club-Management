<?php
/**
 * Regions and squads.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Structure;

use AirsoftClub\Access\Assignments;
use AirsoftClub\Audit\AuditLog;
use AirsoftClub\Database\Migrator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Club structure: regions and the squads inside them.
 * A region may switch squads off; squads stay stored and squad leader roles are suspended meanwhile.
 */
final class Structure {

	private const NAME_MAX = 100;

	/**
	 * Per-request cache of regions by id.
	 *
	 * @var array<int,object>|null
	 */
	private static ?array $regions = null;

	/**
	 * Per-request cache of squads by id.
	 *
	 * @var array<int,object>|null
	 */
	private static ?array $squads = null;

	/** Clears the per-request caches. */
	public static function flush(): void {
		self::$regions = null;
		self::$squads  = null;
	}

	/**
	 * All regions ordered by name.
	 *
	 * @return array<int,object> id => row (id, name, squads_enabled)
	 */
	public static function regions(): array {
		if ( null === self::$regions ) {
			global $wpdb;
			$table         = Migrator::table( 'regions' );
			$rows          = (array) $wpdb->get_results( "SELECT id, name, squads_enabled FROM {$table} ORDER BY name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no input.
			self::$regions = array();
			foreach ( $rows as $row ) {
				$row->id                   = (int) $row->id;
				$row->squads_enabled       = (bool) (int) $row->squads_enabled;
				self::$regions[ $row->id ] = $row;
			}
		}
		return self::$regions;
	}

	/**
	 * One region.
	 *
	 * @param int $id Region id.
	 */
	public static function region( int $id ): ?object {
		return self::regions()[ $id ] ?? null;
	}

	/**
	 * Squads, optionally of one region, ordered by name.
	 *
	 * @param int|null $region_id Region filter.
	 * @return array<int,object> id => row (id, region_id, name)
	 */
	public static function squads( ?int $region_id = null ): array {
		if ( null === self::$squads ) {
			global $wpdb;
			$table        = Migrator::table( 'squads' );
			$rows         = (array) $wpdb->get_results( "SELECT id, region_id, name FROM {$table} ORDER BY name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no input.
			self::$squads = array();
			foreach ( $rows as $row ) {
				$row->id                  = (int) $row->id;
				$row->region_id           = (int) $row->region_id;
				self::$squads[ $row->id ] = $row;
			}
		}
		if ( null === $region_id ) {
			return self::$squads;
		}
		return array_filter( self::$squads, static fn( $s ) => $s->region_id === $region_id );
	}

	/**
	 * One squad.
	 *
	 * @param int $id Squad id.
	 */
	public static function squad( int $id ): ?object {
		return self::squads()[ $id ] ?? null;
	}

	/**
	 * Region of a squad.
	 *
	 * @param int $squad_id Squad id.
	 */
	public static function region_of_squad( int $squad_id ): ?int {
		$squad = self::squad( $squad_id );
		return $squad ? $squad->region_id : null;
	}

	/**
	 * Whether squads are switched on for a region.
	 *
	 * @param int $region_id Region id.
	 */
	public static function squads_active( int $region_id ): bool {
		$region = self::region( $region_id );
		return $region && $region->squads_enabled;
	}

	/**
	 * Creates a region.
	 *
	 * @param string $name Name.
	 * @return int|WP_Error New id.
	 */
	public static function create_region( string $name ) {
		$name = self::clean_name( $name );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		if ( self::region_name_taken( $name, 0 ) ) {
			return new WP_Error( 'acm_duplicate', __( 'A region with this name already exists.', 'airsoft-club-management' ) );
		}
		global $wpdb;
		$wpdb->insert(
			Migrator::table( 'regions' ),
			array(
				'name'           => $name,
				'squads_enabled' => 1,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		self::flush();
		AuditLog::add( 'region.created', 'region', $id );
		return $id;
	}

	/**
	 * Renames a region and sets its squad switch.
	 *
	 * @param int    $id             Region id.
	 * @param string $name           New name.
	 * @param bool   $squads_enabled Squad switch.
	 * @return true|WP_Error
	 */
	public static function update_region( int $id, string $name, bool $squads_enabled ) {
		$region = self::region( $id );
		if ( ! $region ) {
			return new WP_Error( 'acm_not_found', __( 'Region not found.', 'airsoft-club-management' ) );
		}
		$name = self::clean_name( $name );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		if ( self::region_name_taken( $name, $id ) ) {
			return new WP_Error( 'acm_duplicate', __( 'A region with this name already exists.', 'airsoft-club-management' ) );
		}
		global $wpdb;
		$wpdb->update(
			Migrator::table( 'regions' ),
			array(
				'name'           => $name,
				'squads_enabled' => $squads_enabled ? 1 : 0,
			),
			array( 'id' => $id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
		self::flush();
		$changed = array();
		if ( $region->name !== $name ) {
			$changed[] = 'name';
		}
		if ( $region->squads_enabled !== $squads_enabled ) {
			$changed[] = 'squads_enabled';
		}
		if ( $changed ) {
			AuditLog::add( 'region.updated', 'region', $id, array( 'fields' => $changed ) );
			do_action( 'airsoft_club_structure_changed' );
		}
		return true;
	}

	/**
	 * Deletes a region without squads. Regional lead assignments of the region are removed.
	 *
	 * @param int $id Region id.
	 * @return true|WP_Error
	 */
	public static function delete_region( int $id ) {
		if ( ! self::region( $id ) ) {
			return new WP_Error( 'acm_not_found', __( 'Region not found.', 'airsoft-club-management' ) );
		}
		if ( self::squads( $id ) ) {
			return new WP_Error( 'acm_in_use', __( 'Delete the squads of this region first.', 'airsoft-club-management' ) );
		}
		/**
		 * Lets later modules (members, events) block deleting a region that is still referenced.
		 *
		 * @param bool $in_use    Whether the region is in use.
		 * @param int  $region_id Region id.
		 */
		if ( apply_filters( 'airsoft_club_region_in_use', false, $id ) ) {
			return new WP_Error( 'acm_in_use', __( 'This region is still in use.', 'airsoft-club-management' ) );
		}
		global $wpdb;
		$wpdb->delete( Migrator::table( 'regions' ), array( 'id' => $id ), array( '%d' ) );
		self::flush();
		Assignments::delete_scope( 'region', $id );
		AuditLog::add( 'region.deleted', 'region', $id );
		do_action( 'airsoft_club_structure_changed' );
		return true;
	}

	/**
	 * Creates a squad in a region.
	 *
	 * @param int    $region_id Region id.
	 * @param string $name      Name.
	 * @return int|WP_Error New id.
	 */
	public static function create_squad( int $region_id, string $name ) {
		if ( ! self::region( $region_id ) ) {
			return new WP_Error( 'acm_not_found', __( 'Region not found.', 'airsoft-club-management' ) );
		}
		$name = self::clean_name( $name );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		if ( self::squad_name_taken( $region_id, $name, 0 ) ) {
			return new WP_Error( 'acm_duplicate', __( 'A squad with this name already exists in this region.', 'airsoft-club-management' ) );
		}
		global $wpdb;
		$wpdb->insert(
			Migrator::table( 'squads' ),
			array(
				'region_id'  => $region_id,
				'name'       => $name,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		self::flush();
		AuditLog::add( 'squad.created', 'squad', $id, array( 'region_id' => $region_id ) );
		return $id;
	}

	/**
	 * Renames a squad.
	 *
	 * @param int    $id   Squad id.
	 * @param string $name New name.
	 * @return true|WP_Error
	 */
	public static function rename_squad( int $id, string $name ) {
		$squad = self::squad( $id );
		if ( ! $squad ) {
			return new WP_Error( 'acm_not_found', __( 'Squad not found.', 'airsoft-club-management' ) );
		}
		$name = self::clean_name( $name );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		if ( self::squad_name_taken( $squad->region_id, $name, $id ) ) {
			return new WP_Error( 'acm_duplicate', __( 'A squad with this name already exists in this region.', 'airsoft-club-management' ) );
		}
		global $wpdb;
		$wpdb->update( Migrator::table( 'squads' ), array( 'name' => $name ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		self::flush();
		AuditLog::add( 'squad.updated', 'squad', $id, array( 'fields' => array( 'name' ) ) );
		return true;
	}

	/**
	 * Deletes a squad and its squad leader assignments.
	 *
	 * @param int $id Squad id.
	 * @return true|WP_Error
	 */
	public static function delete_squad( int $id ) {
		if ( ! self::squad( $id ) ) {
			return new WP_Error( 'acm_not_found', __( 'Squad not found.', 'airsoft-club-management' ) );
		}
		/**
		 * Lets later modules (members) block deleting a squad that still has members.
		 *
		 * @param bool $in_use   Whether the squad is in use.
		 * @param int  $squad_id Squad id.
		 */
		if ( apply_filters( 'airsoft_club_squad_in_use', false, $id ) ) {
			return new WP_Error( 'acm_in_use', __( 'This squad still has members.', 'airsoft-club-management' ) );
		}
		global $wpdb;
		$wpdb->delete( Migrator::table( 'squads' ), array( 'id' => $id ), array( '%d' ) );
		self::flush();
		Assignments::delete_scope( 'squad', $id );
		AuditLog::add( 'squad.deleted', 'squad', $id );
		do_action( 'airsoft_club_structure_changed' );
		return true;
	}

	/**
	 * Whether another region already uses the name.
	 *
	 * @param string $name       Name.
	 * @param int    $except_id  Region id to ignore.
	 */
	private static function region_name_taken( string $name, int $except_id ): bool {
		foreach ( self::regions() as $region ) {
			if ( $region->id !== $except_id && 0 === strcasecmp( $region->name, $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether another squad of the region already uses the name.
	 *
	 * @param int    $region_id Region id.
	 * @param string $name      Name.
	 * @param int    $except_id Squad id to ignore.
	 */
	private static function squad_name_taken( int $region_id, string $name, int $except_id ): bool {
		foreach ( self::squads( $region_id ) as $squad ) {
			if ( $squad->id !== $except_id && 0 === strcasecmp( $squad->name, $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Trims and validates a name.
	 *
	 * @param string $name Raw name.
	 * @return string|WP_Error
	 */
	private static function clean_name( string $name ) {
		$name = trim( sanitize_text_field( $name ) );
		if ( '' === $name ) {
			return new WP_Error( 'acm_invalid', __( 'Please enter a name.', 'airsoft-club-management' ) );
		}
		if ( mb_strlen( $name ) > self::NAME_MAX ) {
			return new WP_Error( 'acm_invalid', __( 'The name is too long (max. 100 characters).', 'airsoft-club-management' ) );
		}
		return $name;
	}
}
