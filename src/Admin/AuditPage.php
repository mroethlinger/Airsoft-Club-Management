<?php
/**
 * Audit log viewer.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Admin;

use AirsoftClub\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/** Read-only list of the latest audit entries. */
final class AuditPage {

	/** Renders the page. */
	public static function render(): void {
		if ( ! current_user_can( 'acm_view_audit' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		$rows = AuditLog::latest( 200 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Audit log', 'airsoft-club-management' ); ?></h1>
			<p class="description"><?php esc_html_e( 'The latest 200 entries. Entries cannot be changed.', 'airsoft-club-management' ); ?></p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Time', 'airsoft-club-management' ); ?></th>
					<th><?php esc_html_e( 'User', 'airsoft-club-management' ); ?></th>
					<th><?php esc_html_e( 'Action', 'airsoft-club-management' ); ?></th>
					<th><?php esc_html_e( 'Object', 'airsoft-club-management' ); ?></th>
					<th><?php esc_html_e( 'Details', 'airsoft-club-management' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php $user = $row->user_id ? get_userdata( (int) $row->user_id ) : false; ?>
					<tr>
						<td><?php echo esc_html( get_date_from_gmt( $row->created_at, 'Y-m-d H:i' ) ); ?></td>
						<td><?php echo esc_html( $user ? $user->display_name : ( $row->user_id ? '#' . $row->user_id : '–' ) ); ?></td>
						<td><code><?php echo esc_html( $row->action ); ?></code></td>
						<td><?php echo esc_html( $row->object_type ? $row->object_type . ' #' . $row->object_id : '–' ); ?></td>
						<td><code><?php echo esc_html( (string) $row->details ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
