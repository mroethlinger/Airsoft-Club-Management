<?php
/**
 * Core tables: audit log, regions, squads, scoped role assignments.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Database\Migrations;

use AirsoftClub\Database\Migration;
use AirsoftClub\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/** Foundation schema (phase 1). */
final class M001_Core implements Migration {

	/** Version number. */
	public function version(): int {
		return 1;
	}

	/**
	 * Creates the tables.
	 *
	 * @param \wpdb $wpdb Database handle.
	 */
	public function up( \wpdb $wpdb ): void {
		$charset = $wpdb->get_charset_collate();
		$audit   = Migrator::table( 'audit_log' );
		$regions = Migrator::table( 'regions' );
		$squads  = Migrator::table( 'squads' );
		$assign  = Migrator::table( 'role_assignments' );

		dbDelta(
			"CREATE TABLE {$audit} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				created_at DATETIME NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				action VARCHAR(60) NOT NULL,
				object_type VARCHAR(40) NOT NULL DEFAULT '',
				object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				details LONGTEXT NULL,
				PRIMARY KEY  (id),
				KEY object (object_type, object_id),
				KEY action (action),
				KEY created_at (created_at)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$regions} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(100) NOT NULL,
				squads_enabled TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY name (name)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$squads} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				region_id BIGINT UNSIGNED NOT NULL,
				name VARCHAR(100) NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY region_name (region_id, name)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$assign} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				role VARCHAR(30) NOT NULL,
				scope_type VARCHAR(10) NOT NULL,
				scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				is_deputy TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY assignment (user_id, role, scope_id),
				KEY scope (scope_type, scope_id)
			) {$charset};"
		);
	}
}
