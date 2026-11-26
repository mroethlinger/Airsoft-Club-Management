<?php
/**
 * Settings page.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Admin;

use AirsoftClub\Access\TwoFactor;
use AirsoftClub\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Club settings: name, branding, imprint data, modules, security, data removal. */
final class SettingsPage {

	/** Registers the option with the Settings API. */
	public static function register_setting(): void {
		register_setting(
			'acm_settings',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);
		add_filter( 'option_page_capability_acm_settings', static fn() => 'acm_manage_settings' );
	}

	/** Renders the page. */
	public static function render(): void {
		if ( ! current_user_can( 'acm_manage_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'airsoft-club-management' ), 403 );
		}
		wp_enqueue_media();
		$s    = Settings::all();
		$name = Settings::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Club settings', 'airsoft-club-management' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'acm_settings' ); ?>
				<h2><?php esc_html_e( 'Club', 'airsoft-club-management' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text( $name, 'club_name', __( 'Club name', 'airsoft-club-management' ), $s );
					self::text( $name, 'club_short_name', __( 'Short name', 'airsoft-club-management' ), $s, __( 'Max. 20 characters, used in the app header and on the home screen icon.', 'airsoft-club-management' ) );
					?>
					<tr>
						<th scope="row"><label for="acm-logo-id"><?php esc_html_e( 'Logo', 'airsoft-club-management' ); ?></label></th>
						<td>
							<input type="number" min="0" id="acm-logo-id" name="<?php echo esc_attr( $name ); ?>[logo_id]" value="<?php echo esc_attr( $s['logo_id'] ); ?>" class="small-text">
							<button type="button" class="button" id="acm-logo-pick"><?php esc_html_e( 'Choose from media library', 'airsoft-club-management' ); ?></button>
							<p class="description"><?php esc_html_e( 'Attachment ID of an image. The logo is uploaded per installation and is not part of the plugin.', 'airsoft-club-management' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Colours', 'airsoft-club-management' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$labels = array(
						'color_primary'          => __( 'Primary colour', 'airsoft-club-management' ),
						'color_primary_contrast' => __( 'Text on primary colour', 'airsoft-club-management' ),
						'color_background'       => __( 'Background', 'airsoft-club-management' ),
						'color_surface'          => __( 'Surface (cards)', 'airsoft-club-management' ),
						'color_text'             => __( 'Text', 'airsoft-club-management' ),
					);
					foreach ( $labels as $key => $label ) {
						?>
						<tr>
							<th scope="row"><label for="acm-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="color" id="acm-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
						</tr>
						<?php
					}
					?>
				</table>

				<h2><?php esc_html_e( 'Imprint data', 'airsoft-club-management' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text( $name, 'street', __( 'Street', 'airsoft-club-management' ), $s );
					self::text( $name, 'zip', __( 'Postcode', 'airsoft-club-management' ), $s );
					self::text( $name, 'city', __( 'City', 'airsoft-club-management' ), $s );
					self::text( $name, 'country', __( 'Country (ISO code)', 'airsoft-club-management' ), $s );
					self::text( $name, 'email', __( 'Email', 'airsoft-club-management' ), $s, '', 'email' );
					self::text( $name, 'phone', __( 'Phone', 'airsoft-club-management' ), $s );
					self::text( $name, 'responsible', __( 'Responsible for content', 'airsoft-club-management' ), $s );
					self::text( $name, 'register_court', __( 'Register court', 'airsoft-club-management' ), $s );
					self::text( $name, 'register_number', __( 'Register number', 'airsoft-club-management' ), $s );
					?>
				</table>

				<h2><?php esc_html_e( 'Modules and security', 'airsoft-club-management' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox( $name, 'module_association', __( 'Association functions', 'airsoft-club-management' ), $s, __( 'Fees, cash book and donation receipts. Switch off for clubs without registered association (e.V.) status.', 'airsoft-club-management' ) );
					$twofa_hint = TwoFactor::provider_available()
						? __( 'Leads, board, treasurer and cash auditors lose their extra rights until they set up two-factor authentication.', 'airsoft-club-management' )
						: __( 'Needs the "Two Factor" plugin. Until it is active, the requirement is not enforced.', 'airsoft-club-management' );
					self::checkbox( $name, 'require_2fa', __( 'Require two-factor authentication', 'airsoft-club-management' ), $s, $twofa_hint );
					self::checkbox( $name, 'remove_data', __( 'Delete all club data when the plugin is deleted', 'airsoft-club-management' ), $s, __( 'Off by default. When on, deleting the plugin removes all tables, settings and roles. This cannot be undone.', 'airsoft-club-management' ) );
					?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		document.getElementById('acm-logo-pick')?.addEventListener('click', function () {
			const frame = wp.media({ library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				document.getElementById('acm-logo-id').value = frame.state().get('selection').first().id;
			});
			frame.open();
		});
		</script>
		<?php
	}

	/**
	 * Prints a text row.
	 *
	 * @param string               $name  Option name.
	 * @param string               $key   Field.
	 * @param string               $label Label.
	 * @param array<string,string> $s     Values.
	 * @param string               $hint  Description.
	 * @param string               $type  Input type.
	 */
	private static function text( string $name, string $key, string $label, array $s, string $hint = '', string $type = 'text' ): void {
		?>
		<tr>
			<th scope="row"><label for="acm-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>" id="acm-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="regular-text">
				<?php if ( '' !== $hint ) : ?>
					<p class="description"><?php echo esc_html( $hint ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Prints a checkbox row.
	 *
	 * @param string               $name  Option name.
	 * @param string               $key   Field.
	 * @param string               $label Label.
	 * @param array<string,string> $s     Values.
	 * @param string               $hint  Description.
	 */
	private static function checkbox( string $name, string $key, string $label, array $s, string $hint ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="1" <?php checked( '1', $s[ $key ] ); ?>> <?php esc_html_e( 'On', 'airsoft-club-management' ); ?></label>
				<p class="description"><?php echo esc_html( $hint ); ?></p>
			</td>
		</tr>
		<?php
	}
}
