<?php
/**
 * Member persistence.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Members;

use AirsoftClub\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/** Reads and writes rows of the members table. Rows are returned as typed stdClass objects. */
final class MemberRepository {

	public const STATUS_ACTIVE = 'active';
	public const STATUS_LEFT   = 'left';

	/** Columns stored as integers. */
	private const INT_COLUMNS = array( 'user_id', 'member_number', 'vehicle_seats', 'region_id', 'squad_id' );

	/** Columns stored as booleans. */
	private const BOOL_COLUMNS = array( 'share_phone', 'share_email', 'can_drive', 'needs_ride' );

	/** Columns stored as JSON. */
	private const JSON_COLUMNS = array( 'social', 'notification_prefs' );

	/** Table name. */
	private static function table(): string {
		return Migrator::table( 'members' );
	}

	/**
	 * Converts a raw row.
	 *
	 * @param object|null $row Raw row.
	 */
	private static function hydrate( ?object $row ): ?object {
		if ( ! $row ) {
			return null;
		}
		foreach ( self::INT_COLUMNS as $col ) {
			$row->$col = null === $row->$col ? null : (int) $row->$col;
		}
		foreach ( self::BOOL_COLUMNS as $col ) {
			$row->$col = (bool) (int) $row->$col;
		}
		foreach ( self::JSON_COLUMNS as $col ) {
			$decoded   = null === $row->$col ? null : json_decode( (string) $row->$col, true );
			$row->$col = is_array( $decoded ) ? $decoded : array();
		}
		return $row;
	}

	/**
	 * Prepares values for the database.
	 *
	 * @param array<string,mixed> $data Values.
	 * @return array<string,mixed>
	 */
	private static function dehydrate( array $data ): array {
		foreach ( self::BOOL_COLUMNS as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$data[ $col ] = $data[ $col ] ? 1 : 0;
			}
		}
		foreach ( self::JSON_COLUMNS as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$data[ $col ] = $data[ $col ] ? wp_json_encode( $data[ $col ] ) : null;
			}
		}
		return $data;
	}

	/**
	 * One member.
	 *
	 * @param int $user_id User id.
	 */
	public static function find( int $user_id ): ?object {
		global $wpdb;
		$table = self::table();
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) ) );
	}

	/**
	 * Inserts a member row.
	 *
	 * @param array<string,mixed> $data Column values incl. user_id and region_id.
	 */
	public static function insert( array $data ): bool {
		global $wpdb;
		$now                = current_time( 'mysql', true );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		return (bool) $wpdb->insert( self::table(), self::dehydrate( $data ) );
	}

	/**
	 * Updates a member row.
	 *
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $data    Column values.
	 */
	public static function update( int $user_id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( self::table(), self::dehydrate( $data ), array( 'user_id' => $user_id ) );
	}

	/**
	 * Deletes a member row.
	 *
	 * @param int $user_id User id.
	 */
	public static function delete( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'user_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * Members filtered by region, squad and status, ordered by name.
	 *
	 * @param int[]|null $region_ids Region filter (null = all).
	 * @param int|null   $squad_id   Squad filter.
	 * @param string     $status     Status.
	 * @return object[]
	 */
	public static function list( ?array $region_ids = null, ?int $squad_id = null, string $status = self::STATUS_ACTIVE ): array {
		global $wpdb;
		$table  = self::table();
		$where  = array( 'status = %s' );
		$params = array( $status );
		if ( null !== $region_ids ) {
			$region_ids = array_values( array_unique( array_map( 'intval', $region_ids ) ) );
			if ( ! $region_ids ) {
				return array();
			}
			$where[] = 'region_id IN (' . implode( ',', array_fill( 0, count( $region_ids ), '%d' ) ) . ')';
			$params  = array_merge( $params, $region_ids );
		}
		if ( null !== $squad_id ) {
			$where[]  = 'squad_id = %d';
			$params[] = $squad_id;
		}
		$sql  = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY last_name, first_name';
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Number of members (any status) referencing a region or squad.
	 *
	 * @param string $column "region_id" or "squad_id".
	 * @param int    $id     Id.
	 */
	public static function count_by( string $column, int $id ): int {
		global $wpdb;
		$table  = self::table();
		$column = 'squad_id' === $column ? 'squad_id' : 'region_id';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- column is whitelisted.
	}

	/**
	 * Clears the squad of all members of a region's squad (e.g. after a squad was moved).
	 *
	 * @param int $squad_id Squad id.
	 */
	public static function clear_squad( int $squad_id ): void {
		global $wpdb;
		$wpdb->update( self::table(), array( 'squad_id' => null ), array( 'squad_id' => $squad_id ) );
	}

	/**
	 * Former members who left before the given UTC time.
	 *
	 * @param string $before MySQL datetime (UTC).
	 * @return int[] User ids.
	 */
	public static function left_before( string $before ): array {
		global $wpdb;
		$table = self::table();
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE status = %s AND left_at IS NOT NULL AND left_at < %s", self::STATUS_LEFT, $before ) ) );
	}

	/** Next free member number. */
	public static function next_number(): int {
		global $wpdb;
		$table = self::table();
		return 1 + (int) $wpdb->get_var( "SELECT MAX(member_number) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no input.
	}

	/**
	 * Whether a member number is taken by someone else.
	 *
	 * @param int $number  Number.
	 * @param int $user_id User to ignore.
	 */
	public static function number_taken( int $number, int $user_id ): bool {
		global $wpdb;
		$table = self::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE member_number = %d AND user_id <> %d", $number, $user_id ) );
	}
}
