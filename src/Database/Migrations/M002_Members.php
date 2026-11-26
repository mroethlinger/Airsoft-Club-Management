<?php
/**
 * Member records.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Database\Migrations;

use AirsoftClub\Database\Migration;
use AirsoftClub\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/** Club member data linked 1:1 to a WordPress user (phase 2). */
final class M002_Members implements Migration {

	/** Version number. */
	public function version(): int {
		return 2;
	}

	/**
	 * Creates the table.
	 *
	 * @param \wpdb $wpdb Database handle.
	 */
	public function up( \wpdb $wpdb ): void {
		$charset = $wpdb->get_charset_collate();
		$table   = Migrator::table( 'members' );
		dbDelta(
			"CREATE TABLE {$table} (
				user_id BIGINT UNSIGNED NOT NULL,
				member_number INT UNSIGNED NULL,
				status VARCHAR(10) NOT NULL DEFAULT 'active',
				first_name VARCHAR(100) NOT NULL DEFAULT '',
				last_name VARCHAR(100) NOT NULL DEFAULT '',
				nickname VARCHAR(50) NULL,
				name_display VARCHAR(12) NOT NULL DEFAULT 'full',
				birth_date DATE NULL,
				phone VARCHAR(40) NULL,
				share_phone TINYINT(1) NOT NULL DEFAULT 0,
				share_email TINYINT(1) NOT NULL DEFAULT 0,
				street VARCHAR(150) NULL,
				postal_code VARCHAR(16) NULL,
				city VARCHAR(100) NULL,
				country CHAR(2) NOT NULL DEFAULT 'DE',
				social LONGTEXT NULL,
				can_drive TINYINT(1) NOT NULL DEFAULT 0,
				vehicle_seats TINYINT UNSIGNED NULL,
				vehicle_description VARCHAR(150) NULL,
				needs_ride TINYINT(1) NOT NULL DEFAULT 0,
				mobility_notes VARCHAR(255) NULL,
				combat_role VARCHAR(20) NULL,
				play_style VARCHAR(20) NULL,
				region_id BIGINT UNSIGNED NOT NULL,
				squad_id BIGINT UNSIGNED NULL,
				joined_at DATE NULL,
				left_at DATETIME NULL,
				avatar_path VARCHAR(255) NULL,
				notification_prefs LONGTEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (user_id),
				UNIQUE KEY member_number (member_number),
				KEY region (region_id, status),
				KEY squad (squad_id),
				KEY status_left (status, left_at)
			) {$charset};"
		);
	}
}
