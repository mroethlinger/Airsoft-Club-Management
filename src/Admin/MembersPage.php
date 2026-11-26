<?php
/**
 * Member list for the board.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Admin;

use AirsoftClub\Members\MemberRepository;
use AirsoftClub\Members\MemberService;
use AirsoftClub\Structure\Structure;

defined( 'ABSPATH' ) || exit;

/**
 * Admin list of members and former members with "add member", "end membership" and "reactivate".
 * Leads do this in the member area; in wp-admin the scoped rules of MemberService apply as well.
 */
final class MembersPage {

	private const PAGE = 'acm-members';

	/** Registers the admin-post handlers. */
	public static function hooks(): void {
		add_action( 'admin_post_acm_member_create', array( self::class, 'handle' ) );
		add_action( 'admin_post_acm_member_leave', array( self::class, 'handle' ) );
		add_action( 'admin_post_acm_member_reactivate', array( self::class, 'handle' ) );
	}

	/** Dispatches a posted action. */
	public static function handle(): void {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		if ( ! current_user_can( 'acm_manage_structure' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		check_admin_referer( $action );
		$actor = get_current_user_id();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		switch ( $action ) {
			case 'acm_member_create':
				$fields = array( 'email', 'first_name', 'last_name', 'region_id', 'squad_id' );
				$data   = array();
				foreach ( $fields as $field ) {
					$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
				}
				$data['region_id']   = (int) $data['region_id'];
				$data['squad_id']    = (int) $data['squad_id'] ? (int) $data['squad_id'] : null;
				$data['send_invite'] = ! empty( $_POST['send_invite'] );
				$result              = MemberService::create( $actor, $data );
				break;
			case 'acm_member_leave':
				$result = MemberService::leave( $actor, $id );
				break;
			case 'acm_member_reactivate':
				$result = MemberService::reactivate( $actor, $id );
				break;
			default:
				wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		// phpcs:enable
		if ( is_wp_error( $result ) ) {
			Menu::redirect( self::PAGE, 'error', 'error', $result->get_error_message() );
		}
		Menu::redirect( self::PAGE, 'saved' );
	}

	/**
	 * Small inline action form.
	 *
	 * @param string $action Action.
	 * @param int    $id     Member id.
	 * @param string $label  Button label.
	 * @param bool   $danger Ask for confirmation.
	 */
	private static function action_button( string $action, int $id, string $label, bool $danger ): void {
		printf( '<form method="post" action="%s" style="display:inline">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="id" value="%d">', esc_attr( $action ), (int) $id );
		wp_nonce_field( $action );
		printf(
			'<button type="submit" class="%s"%s>%s</button></form>',
			$danger ? 'button-link button-link-delete' : 'button-link',
			$danger ? ' onclick="return confirm(' . esc_attr( wp_json_encode( __( 'End this membership? The member loses access immediately.', 'airsoft-club-management' ) ) ) . ')"' : '',
			esc_html( $label )
		);
	}

	/** Renders the page. */
	public static function render(): void {
		if ( ! current_user_can( 'acm_manage_structure' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		$viewer  = get_current_user_id();
		$members = MemberService::visible_members( $viewer );
		$archive = MemberService::archive( $viewer );
		$regions = Structure::regions();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Members', 'airsoft-club-management' ); ?></h1>
			<?php Menu::notice(); ?>

			<?php if ( $regions ) : ?>
			<h2><?php esc_html_e( 'Add member', 'airsoft-club-management' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="acm_member_create">
				<?php wp_nonce_field( 'acm_member_create' ); ?>
				<input type="text" name="first_name" required maxlength="100" placeholder="<?php esc_attr_e( 'First name', 'airsoft-club-management' ); ?>" aria-label="<?php esc_attr_e( 'First name', 'airsoft-club-management' ); ?>">
				<input type="text" name="last_name" required maxlength="100" placeholder="<?php esc_attr_e( 'Last name', 'airsoft-club-management' ); ?>" aria-label="<?php esc_attr_e( 'Last name', 'airsoft-club-management' ); ?>">
				<input type="email" name="email" required placeholder="<?php esc_attr_e( 'Email', 'airsoft-club-management' ); ?>" aria-label="<?php esc_attr_e( 'Email', 'airsoft-club-management' ); ?>">
				<select name="region_id" required aria-label="<?php esc_attr_e( 'Region', 'airsoft-club-management' ); ?>">
					<?php foreach ( $regions as $region ) : ?>
						<option value="<?php echo (int) $region->id; ?>"><?php echo esc_html( $region->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="squad_id" aria-label="<?php esc_attr_e( 'Squad', 'airsoft-club-management' ); ?>">
					<option value="0"><?php esc_html_e( '— no squad —', 'airsoft-club-management' ); ?></option>
					<?php foreach ( Structure::squads() as $squad ) : ?>
						<option value="<?php echo (int) $squad->id; ?>"><?php echo esc_html( $regions[ $squad->region_id ]->name . ' / ' . $squad->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><input type="checkbox" name="send_invite" value="1" checked> <?php esc_html_e( 'Send invitation email', 'airsoft-club-management' ); ?></label>
				<?php submit_button( __( 'Add', 'airsoft-club-management' ), 'primary', '', false ); ?>
			</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Create a region first.', 'airsoft-club-management' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Active members', 'airsoft-club-management' ); ?> (<?php echo count( $members ); ?>)</h2>
			<?php self::table( $members, $viewer, false ); ?>

			<h2><?php esc_html_e( 'Former members', 'airsoft-club-management' ); ?> (<?php echo count( $archive ); ?>)</h2>
			<p class="description">
				<?php
				/* translators: %d: number of days */
				echo esc_html( sprintf( __( 'Former members are deleted automatically %d days after leaving.', 'airsoft-club-management' ), MemberService::archive_days() ) );
				?>
			</p>
			<?php self::table( $archive, $viewer, true ); ?>
		</div>
		<?php
	}

	/**
	 * Member table.
	 *
	 * @param object[] $rows    Members.
	 * @param int      $viewer  Viewer.
	 * @param bool     $archive Archive mode.
	 */
	private static function table( array $rows, int $viewer, bool $archive ): void {
		if ( ! $rows ) {
			echo '<p><em>' . esc_html__( 'None.', 'airsoft-club-management' ) . '</em></p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'No.', 'airsoft-club-management' ); ?></th>
				<th><?php esc_html_e( 'Name', 'airsoft-club-management' ); ?></th>
				<th><?php esc_html_e( 'Region', 'airsoft-club-management' ); ?></th>
				<th><?php esc_html_e( 'Squad', 'airsoft-club-management' ); ?></th>
				<th><?php echo esc_html( $archive ? __( 'Left', 'airsoft-club-management' ) : __( 'Joined', 'airsoft-club-management' ) ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rows as $m ) : ?>
				<?php
				$region = Structure::region( $m->region_id );
				$squad  = MemberService::active_squad( $m );
				$squad  = $squad ? Structure::squad( $squad ) : null;
				?>
				<tr>
					<td><?php echo esc_html( (string) $m->member_number ); ?></td>
					<td><?php echo esc_html( MemberService::display_name( $m, $viewer ) ); ?></td>
					<td><?php echo esc_html( $region ? $region->name : '' ); ?></td>
					<td><?php echo esc_html( $squad ? $squad->name : '–' ); ?></td>
					<td><?php echo esc_html( $archive ? get_date_from_gmt( (string) $m->left_at, 'Y-m-d' ) : (string) $m->joined_at ); ?></td>
					<td>
						<?php
						if ( $archive ) {
							self::action_button( 'acm_member_reactivate', $m->user_id, __( 'Reactivate', 'airsoft-club-management' ), false );
						} elseif ( $m->user_id !== $viewer ) {
							self::action_button( 'acm_member_leave', $m->user_id, __( 'End membership', 'airsoft-club-management' ), true );
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
