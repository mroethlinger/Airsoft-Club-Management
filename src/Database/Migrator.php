<?php
/**
 * Versioned migration runner.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Database;

defined( 'ABSPATH' ) || exit;

/** Small versioned migration runner on top of dbDelta. The DB version lives in option acm_db_version. */
final class Migrator {

	public const OPTION = 'acm_db_version';

	/**
	 * All migrations in order.
	 *
	 * @return Migration[]
	 */
	public static function migrations(): array {
		return array(
			new Migrations\M001_Core(),
		);
	}

	/** Highest known version. */
	public static function latest_version(): int {
		return max( array_map( static fn( Migration $m ) => $m->version(), self::migrations() ) );
	}

	/** Whether migrations are pending. */
	public static function is_behind(): bool {
		return (int) get_option( self::OPTION, 0 ) < self::latest_version();
	}

	/** Runs all pending migrations in order. Returns the number applied. */
	public static function run(): int {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$current = (int) get_option( self::OPTION, 0 );
		$applied = 0;
		$list    = self::migrations();
		usort( $list, static fn( Migration $a, Migration $b ) => $a->version() <=> $b->version() );

		foreach ( $list as $migration ) {
			if ( $migration->version() <= $current ) {
				continue;
			}
			$migration->up( $wpdb );
			update_option( self::OPTION, $migration->version(), false );
			++$applied;
		}
		return $applied;
	}

	/**
	 * Full table name with WordPress and plugin prefix.
	 *
	 * @param string $name Short table name, e.g. "regions".
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'acm_' . $name;
	}

	/**
	 * All plugin tables (for uninstall).
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		return array_map( array( self::class, 'table' ), array( 'role_assignments', 'squads', 'regions', 'audit_log' ) );
	}
}
