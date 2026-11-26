<?php
/**
 * Append-only audit log.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Audit;

use AirsoftClub\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/** Append-only audit log. Never store personal data in the details, only ids and field names. */
final class AuditLog {

	/**
	 * Adds an entry.
	 *
	 * @param string              $action      Dotted action name, e.g. "region.created".
	 * @param string              $object_type Object type, e.g. "region".
	 * @param int                 $object_id   Object id.
	 * @param array<string,mixed> $details     Ids and field names only.
	 */
	public static function add( string $action, string $object_type = '', int $object_id = 0, array $details = array() ): void {
		global $wpdb;
		$wpdb->insert(
			Migrator::table( 'audit_log' ),
			array(
				'created_at'  => current_time( 'mysql', true ),
				'user_id'     => get_current_user_id(),
				'action'      => substr( $action, 0, 60 ),
				'object_type' => substr( $object_type, 0, 40 ),
				'object_id'   => $object_id,
				'details'     => $details ? wp_json_encode( $details ) : null,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Latest entries, newest first.
	 *
	 * @param int $limit Max rows.
	 * @return array<int,object>
	 */
	public static function latest( int $limit = 100 ): array {
		global $wpdb;
		$table = Migrator::table( 'audit_log' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max( 1, $limit ) ) );
	}
}
