<?php
/**
 * Rights overview and matrix.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Admin;

use AirsoftClub\Access\Abilities;
use AirsoftClub\Access\Roles;

defined( 'ABSPATH' ) || exit;

/** Shows WordPress roles with their rights and the scoped rights matrix; the board can change the matrix. */
final class RightsPage {

	/** Registers the save handler. */
	public static function hooks(): void {
		add_action( 'admin_post_acm_rights_save', array( self::class, 'save' ) );
	}

	/** Saves the matrix. */
	public static function save(): void {
		if ( ! current_user_can( 'acm_manage_rights' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		check_admin_referer( 'acm_rights_save' );
		$raw   = isset( $_POST['levels'] ) && is_array( $_POST['levels'] ) ? wp_unslash( $_POST['levels'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to int below.
		$input = array();
		foreach ( $raw as $key => $level ) {
			$input[ sanitize_text_field( (string) $key ) ] = (int) $level;
		}
		Abilities::save( $input );
		Menu::redirect( Menu::SLUG, 'saved' );
	}

	/** Renders the page. */
	public static function render(): void {
		if ( ! current_user_can( 'acm_view_rights' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		$can_edit = current_user_can( 'acm_manage_rights' );
		$levels   = Abilities::levels();
		$matrix   = Abilities::matrix();
		$caps     = Roles::capabilities();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rights', 'airsoft-club-management' ); ?></h1>
			<?php Menu::notice(); ?>

			<h2><?php esc_html_e( 'Lead functions', 'airsoft-club-management' ); ?></h2>
			<p><?php esc_html_e( 'Each action can be used from the level shown upwards, always inside the area of the function: squad leaders in their squad, regional leads (and deputies) in their region, the overall lead everywhere.', 'airsoft-club-management' ); ?></p>
			<?php if ( $can_edit ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="acm_rights_save">
				<?php wp_nonce_field( 'acm_rights_save' ); ?>
			<?php endif; ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Action', 'airsoft-club-management' ); ?></th>
					<th><?php esc_html_e( 'Description', 'airsoft-club-management' ); ?></th>
					<th><?php esc_html_e( 'From level', 'airsoft-club-management' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( Abilities::groups() as $group => $group_label ) : ?>
					<tr><th colspan="3"><strong><?php echo esc_html( $group_label ); ?></strong></th></tr>
					<?php foreach ( Abilities::definitions() as $key => $def ) : ?>
						<?php
						if ( $def['group'] !== $group ) {
							continue;
						}
						?>
						<tr>
							<td><?php echo esc_html( $def['label'] ); ?></td>
							<td><?php echo esc_html( $def['description'] ); ?></td>
							<td>
								<?php if ( $can_edit ) : ?>
									<select name="levels[<?php echo esc_attr( $key ); ?>]" aria-label="<?php echo esc_attr( $def['label'] ); ?>">
										<?php foreach ( $levels as $num => $label ) : ?>
											<?php
											if ( $num < $def['floor'] ) {
												continue;
											}
											?>
											<option value="<?php echo (int) $num; ?>" <?php selected( $matrix[ $key ], $num ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<?php echo esc_html( $levels[ $matrix[ $key ] ] ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $can_edit ) : ?>
				<?php submit_button( __( 'Save rights', 'airsoft-club-management' ) ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'WordPress roles', 'airsoft-club-management' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Role', 'airsoft-club-management' ); ?></th>
					<th><?php esc_html_e( 'Description', 'airsoft-club-management' ); ?></th>
					<th><?php esc_html_e( 'Rights', 'airsoft-club-management' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( Roles::roles() as $def ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $def['label'] ); ?></strong></td>
						<td><?php echo esc_html( $def['description'] ); ?></td>
						<td><ul style="margin:0">
							<?php foreach ( $def['caps'] as $cap ) : ?>
								<li><?php echo esc_html( $caps[ $cap ] ?? $cap ); ?></li>
							<?php endforeach; ?>
						</ul></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
