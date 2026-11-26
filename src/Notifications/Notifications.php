<?php
/**
 * In-app notification feed.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Notifications;

use AirsoftClub\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * The feed behind the bell in the member area. Mail and push delivery follow in phase 10;
 * every notification always lands in the feed. Entries older than 90 days are pruned daily.
 */
final class Notifications {

	public const CATEGORIES = array( 'events', 'rides', 'votes', 'applications', 'cases', 'system' );
	public const PRUNE_HOOK = 'acm_prune_notifications';

	/** Registers hooks. */
	public static function hooks(): void {
		add_action( self::PRUNE_HOOK, array( self::class, 'prune' ) );
		add_action( 'deleted_user', array( self::class, 'delete_user' ) );
		add_action(
			'init',
			static function () {
				if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
					wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
				}
			}
		);
	}

	/** Table name. */
	private static function table(): string {
		return Migrator::table( 'notifications' );
	}

	/**
	 * Adds a notification for a user.
	 *
	 * @param int    $user_id  Recipient.
	 * @param string $category One of CATEGORIES.
	 * @param string $title    Short title.
	 * @param string $body     Optional text.
	 * @param string $url      App route, e.g. "/events/12".
	 * @return int Notification id (0 when refused).
	 */
	public static function send( int $user_id, string $category, string $title, string $body = '', string $url = '' ): int {
		if ( $user_id <= 0 || ! in_array( $category, self::CATEGORIES, true ) || ! get_userdata( $user_id ) ) {
			return 0;
		}
		$url = '' !== $url && preg_match( '#^/[A-Za-z0-9/_\-?=&%.]*$#', $url ) ? $url : null;
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'user_id'    => $user_id,
				'category'   => $category,
				'title'      => mb_substr( sanitize_text_field( $title ), 0, 200 ),
				'body'       => '' === $body ? null : mb_substr( sanitize_textarea_field( $body ), 0, 500 ),
				'url'        => $url,
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$id = (int) $wpdb->insert_id;
		do_action( 'airsoft_club_notification_sent', $id, $user_id, $category );
		return $id;
	}

	/**
	 * Latest notifications of a user.
	 *
	 * @param int $user_id User.
	 * @param int $limit   Max entries.
	 * @return array<int,array<string,mixed>>
	 */
	public static function latest( int $user_id, int $limit = 30 ): array {
		global $wpdb;
		$table = self::table();
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, category, title, body, url, read_at, created_at FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, max( 1, min( 100, $limit ) ) ) );
		return array_map(
			static fn( $r ) => array(
				'id'         => (int) $r->id,
				'category'   => $r->category,
				'title'      => $r->title,
				'body'       => $r->body,
				'url'        => $r->url,
				'read'       => null !== $r->read_at,
				'created_at' => mysql_to_rfc3339( $r->created_at ),
			),
			$rows
		);
	}

	/**
	 * Number of unread notifications.
	 *
	 * @param int $user_id User.
	 */
	public static function unread( int $user_id ): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND read_at IS NULL", $user_id ) );
	}

	/**
	 * Marks one notification as read (only the owner's).
	 *
	 * @param int $user_id User.
	 * @param int $id      Notification id.
	 */
	public static function mark_read( int $user_id, int $id ): bool {
		global $wpdb;
		$table = self::table();
		return (bool) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET read_at = %s WHERE id = %d AND user_id = %d AND read_at IS NULL", current_time( 'mysql', true ), $id, $user_id ) );
	}

	/**
	 * Marks all notifications of a user as read.
	 *
	 * @param int $user_id User.
	 */
	public static function read_all( int $user_id ): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET read_at = %s WHERE user_id = %d AND read_at IS NULL", current_time( 'mysql', true ), $user_id ) );
	}

	/** Deletes entries older than 90 days. */
	public static function prune(): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Removes the feed of a deleted user.
	 *
	 * @param int $user_id User.
	 */
	public static function delete_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'user_id' => $user_id ), array( '%d' ) );
	}
}
