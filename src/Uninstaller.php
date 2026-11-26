<?php
/**
 * Data removal on uninstall.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub;

use AirsoftClub\Access\Roles;
use AirsoftClub\Database\Migrator;
use AirsoftClub\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Removes plugin data only when the opt-in setting is on. */
final class Uninstaller {

	/**
	 * Options owned by the plugin.
	 *
	 * @var string[]
	 */
	private const OPTIONS = array( Settings::OPTION, Migrator::OPTION, Roles::OPTION, 'acm_version', 'acm_rights_matrix', 'acm_app_page_id', 'acm_app_rewrite' );

	/** Runs the uninstall. Returns whether data was removed. */
	public static function run(): bool {
		delete_transient( 'acm_github_release' );
		wp_clear_scheduled_hook( 'acm_purge_former_members' );
		wp_clear_scheduled_hook( 'acm_prune_notifications' );
		if ( '1' !== Settings::get( 'remove_data' ) ) {
			return false;
		}
		global $wpdb;
		foreach ( Migrator::tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- internal table names.
		}
		Roles::uninstall();
		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
		return true;
	}
}
