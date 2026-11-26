<?php
/**
 * In-app notifications.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Database\Migrations;

use AirsoftClub\Database\Migration;
use AirsoftClub\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/** Notification feed shown in the bell of the member area (phase 3). */
final class M003_Notifications implements Migration {

	/** Version number. */
	public function version(): int {
		return 3;
	}

	/**
	 * Creates the table.
	 *
	 * @param \wpdb $wpdb Database handle.
	 */
	public function up( \wpdb $wpdb ): void {
		$charset = $wpdb->get_charset_collate();
		$table   = Migrator::table( 'notifications' );
		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				category VARCHAR(20) NOT NULL,
				title VARCHAR(200) NOT NULL,
				body VARCHAR(500) NULL,
				url VARCHAR(255) NULL,
				read_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY user_read (user_id, read_at),
				KEY created_at (created_at)
			) {$charset};"
		);
	}
}
