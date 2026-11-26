<?php
/**
 * Regions, squads and lead assignments.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Admin;

use AirsoftClub\Access\Assignments;
use AirsoftClub\Structure\Structure;

defined( 'ABSPATH' ) || exit;

/** Admin page to set up regions and squads and to appoint leads. */
final class StructurePage {

	private const PAGE = 'acm-structure';

	/**
	 * Action => handler method.
	 *
	 * @var array<string,string>
	 */
	private const ACTIONS = array(
		'acm_region_create' => 'region_create',
		'acm_region_update' => 'region_update',
		'acm_region_delete' => 'region_delete',
		'acm_squad_create'  => 'squad_create',
		'acm_squad_update'  => 'squad_update',
		'acm_squad_delete'  => 'squad_delete',
		'acm_assign'        => 'assign',
		'acm_revoke'        => 'revoke',
	);

	/** Registers the admin-post handlers. */
	public static function hooks(): void {
		foreach ( self::ACTIONS as $action => $method ) {
			add_action( 'admin_post_' . $action, array( self::class, 'handle' ) );
		}
	}

	/** Checks rights and nonce, then dispatches the posted action. */
	public static function handle(): void {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		if ( ! isset( self::ACTIONS[ $action ] ) || ! current_user_can( 'acm_manage_structure' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		check_admin_referer( $action );
		$result = call_user_func( array( self::class, self::ACTIONS[ $action ] ) );
		if ( is_wp_error( $result ) ) {
			Menu::redirect( self::PAGE, 'error', 'error', $result->get_error_message() );
		}
		Menu::redirect( self::PAGE, 'saved' );
	}

	/**
	 * Integer from POST.
	 *
	 * @param string $key Field.
	 */
	private static function int( string $key ): int {
		return isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle().
	}

	/**
	 * String from POST.
	 *
	 * @param string $key Field.
	 */
	private static function str( string $key ): string {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle().
	}

	/** Handler. @return mixed */
	private static function region_create() {
		return Structure::create_region( self::str( 'name' ) );
	}

	/** Handler. @return mixed */
	private static function region_update() {
		return Structure::update_region( self::int( 'id' ), self::str( 'name' ), (bool) self::int( 'squads_enabled' ) );
	}

	/** Handler. @return mixed */
	private static function region_delete() {
		return Structure::delete_region( self::int( 'id' ) );
	}

	/** Handler. @return mixed */
	private static function squad_create() {
		return Structure::create_squad( self::int( 'region_id' ), self::str( 'name' ) );
	}

	/** Handler. @return mixed */
	private static function squad_update() {
		return Structure::rename_squad( self::int( 'id' ), self::str( 'name' ) );
	}

	/** Handler. @return mixed */
	private static function squad_delete() {
		return Structure::delete_squad( self::int( 'id' ) );
	}

	/** Handler. @return mixed */
	private static function assign() {
		return Assignments::assign( self::int( 'user_id' ), sanitize_key( self::str( 'role' ) ), self::int( 'scope_id' ), (bool) self::int( 'is_deputy' ) );
	}

	/** Handler. @return mixed */
	private static function revoke() {
		return Assignments::revoke( self::int( 'id' ) );
	}

	/**
	 * Opens a form for an admin-post action.
	 *
	 * @param string $action Action.
	 * @param string $style  Inline style.
	 */
	private static function form_open( string $action, string $style = 'display:inline' ): void {
		printf( '<form method="post" action="%s" style="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $style ) );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( $action ) );
		wp_nonce_field( $action );
	}

	/**
	 * Prints a delete button with confirmation.
	 *
	 * @param string $action Action.
	 * @param int    $id     Object id.
	 * @param string $label  Button label.
	 */
	private static function delete_button( string $action, int $id, string $label ): void {
		self::form_open( $action );
		printf( '<input type="hidden" name="id" value="%d">', (int) $id );
		printf(
			'<button type="submit" class="button-link button-link-delete" onclick="return confirm(%s)">%s</button></form>',
			esc_attr( wp_json_encode( __( 'Really delete?', 'airsoft-club-management' ) ) ),
			esc_html( $label )
		);
	}

	/**
	 * Lists the holders of a function with revoke buttons.
	 *
	 * @param string $role     Role.
	 * @param int    $scope_id Scope id.
	 */
	private static function holders( string $role, int $scope_id ): void {
		$rows = Assignments::for_scope( $role, $scope_id );
		if ( ! $rows ) {
			echo '<em>' . esc_html__( 'nobody', 'airsoft-club-management' ) . '</em>';
			return;
		}
		echo '<ul style="margin:0">';
		foreach ( $rows as $row ) {
			$user = get_userdata( $row->user_id );
			echo '<li>' . esc_html( $user ? $user->display_name : '#' . $row->user_id );
			if ( $row->is_deputy ) {
				echo ' <span class="description">(' . esc_html__( 'deputy', 'airsoft-club-management' ) . ')</span>';
			}
			if ( Assignments::is_suspended( $row ) ) {
				echo ' <span class="description">(' . esc_html__( 'suspended: squads are off in this region', 'airsoft-club-management' ) . ')</span>';
			}
			echo ' ';
			self::delete_button( 'acm_revoke', $row->id, __( 'remove', 'airsoft-club-management' ) );
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Inline form to appoint a lead.
	 *
	 * @param string $role     Role.
	 * @param int    $scope_id Scope id.
	 */
	private static function assign_form( string $role, int $scope_id ): void {
		self::form_open( 'acm_assign', 'margin-top:4px' );
		printf( '<input type="hidden" name="role" value="%s"><input type="hidden" name="scope_id" value="%d">', esc_attr( $role ), (int) $scope_id );
		wp_dropdown_users(
			array(
				'name'             => 'user_id',
				'show_option_none' => __( '— choose user —', 'airsoft-club-management' ),
				'id'               => 'acm-user-' . $role . '-' . $scope_id,
			)
		);
		echo ' <label><input type="checkbox" name="is_deputy" value="1"> ' . esc_html__( 'deputy', 'airsoft-club-management' ) . '</label> ';
		submit_button( __( 'Appoint', 'airsoft-club-management' ), 'secondary small', '', false );
		echo '</form>';
	}

	/** Renders the page. */
	public static function render(): void {
		if ( ! current_user_can( 'acm_manage_structure' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		$regions = Structure::regions();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Regions & squads', 'airsoft-club-management' ); ?></h1>
			<?php Menu::notice(); ?>

			<h2><?php esc_html_e( 'Overall lead', 'airsoft-club-management' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Has all lead rights in every region.', 'airsoft-club-management' ); ?></p>
			<?php
			self::holders( Assignments::OVERALL_LEAD, 0 );
			self::assign_form( Assignments::OVERALL_LEAD, 0 );
			?>

			<h2><?php esc_html_e( 'Regions', 'airsoft-club-management' ); ?></h2>
			<?php self::form_open( 'acm_region_create', 'margin-bottom:1em' ); ?>
				<label for="acm-new-region" class="screen-reader-text"><?php esc_html_e( 'New region', 'airsoft-club-management' ); ?></label>
				<input type="text" id="acm-new-region" name="name" maxlength="100" required placeholder="<?php esc_attr_e( 'New region', 'airsoft-club-management' ); ?>">
				<?php submit_button( __( 'Add region', 'airsoft-club-management' ), 'primary', '', false ); ?>
			</form>

			<?php if ( ! $regions ) : ?>
				<p><?php esc_html_e( 'No regions yet.', 'airsoft-club-management' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $regions as $region ) : ?>
				<div class="card" style="max-width:none">
					<?php self::form_open( 'acm_region_update' ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $region->id; ?>">
						<input type="text" name="name" value="<?php echo esc_attr( $region->name ); ?>" maxlength="100" required aria-label="<?php esc_attr_e( 'Region name', 'airsoft-club-management' ); ?>" style="font-size:1.2em;font-weight:600">
						<label><input type="checkbox" name="squads_enabled" value="1" <?php checked( $region->squads_enabled ); ?>> <?php esc_html_e( 'Squads on', 'airsoft-club-management' ); ?></label>
						<?php submit_button( __( 'Save', 'airsoft-club-management' ), 'secondary small', '', false ); ?>
					</form>
					<?php self::delete_button( 'acm_region_delete', $region->id, __( 'Delete region', 'airsoft-club-management' ) ); ?>

					<h3><?php esc_html_e( 'Regional lead', 'airsoft-club-management' ); ?></h3>
					<?php
					self::holders( Assignments::REGIONAL_LEAD, $region->id );
					self::assign_form( Assignments::REGIONAL_LEAD, $region->id );
					?>

					<h3><?php esc_html_e( 'Squads', 'airsoft-club-management' ); ?></h3>
					<?php if ( ! $region->squads_enabled ) : ?>
						<p class="description"><?php esc_html_e( 'Squads are switched off for this region. They stay stored and squad leaders keep their appointment, but their rights are suspended.', 'airsoft-club-management' ); ?></p>
					<?php endif; ?>
					<table class="widefat striped">
						<tbody>
						<?php foreach ( Structure::squads( $region->id ) as $squad ) : ?>
							<tr>
								<td style="width:35%">
									<?php self::form_open( 'acm_squad_update' ); ?>
										<input type="hidden" name="id" value="<?php echo (int) $squad->id; ?>">
										<input type="text" name="name" value="<?php echo esc_attr( $squad->name ); ?>" maxlength="100" required aria-label="<?php esc_attr_e( 'Squad name', 'airsoft-club-management' ); ?>">
										<?php submit_button( __( 'Rename', 'airsoft-club-management' ), 'secondary small', '', false ); ?>
									</form>
									<?php self::delete_button( 'acm_squad_delete', $squad->id, __( 'Delete', 'airsoft-club-management' ) ); ?>
								</td>
								<td>
									<strong><?php esc_html_e( 'Squad leader', 'airsoft-club-management' ); ?>:</strong>
									<?php
									self::holders( Assignments::SQUAD_LEAD, $squad->id );
									self::assign_form( Assignments::SQUAD_LEAD, $squad->id );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php self::form_open( 'acm_squad_create', 'margin-top:8px' ); ?>
						<input type="hidden" name="region_id" value="<?php echo (int) $region->id; ?>">
						<input type="text" name="name" maxlength="100" required placeholder="<?php esc_attr_e( 'New squad', 'airsoft-club-management' ); ?>" aria-label="<?php esc_attr_e( 'New squad', 'airsoft-club-management' ); ?>">
						<?php submit_button( __( 'Add squad', 'airsoft-club-management' ), 'secondary', '', false ); ?>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
