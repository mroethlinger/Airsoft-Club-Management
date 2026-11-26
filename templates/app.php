<?php
/**
 * Standalone document for the member area. Only the app's own CSS and JS are loaded.
 *
 * @package AirsoftClub
 */

use AirsoftClub\Front\AppPage;

defined( 'ABSPATH' ) || exit;

$airsoft_club_assets = AppPage::assets();
$airsoft_club_config = AppPage::config();
$airsoft_club_brand  = $airsoft_club_config['branding'];
$airsoft_club_allow  = current_user_can( 'acm_member_area' );

if ( ! headers_sent() ) {
	nocache_headers();
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: same-origin' );
	header( 'X-Content-Type-Options: nosniff' );
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="<?php echo esc_attr( $airsoft_club_brand['color_surface'] ); ?>">
	<title><?php echo esc_html( $airsoft_club_brand['org_name'] ); ?></title>
	<style>
		:root{--c-primary:<?php echo esc_html( $airsoft_club_brand['color_primary'] ); ?>;--c-primary-contrast:<?php echo esc_html( $airsoft_club_brand['color_primary_contrast'] ); ?>;--c-background:<?php echo esc_html( $airsoft_club_brand['color_background'] ); ?>;--c-surface:<?php echo esc_html( $airsoft_club_brand['color_surface'] ); ?>;--c-text:<?php echo esc_html( $airsoft_club_brand['color_text'] ); ?>}
		html,body{margin:0;background:var(--c-background);color:var(--c-text);font-family:system-ui,sans-serif}
		.acm-msg{max-width:32rem;margin:15vh auto;padding:2rem;background:var(--c-surface);border-radius:.75rem}
		.acm-msg a{color:var(--c-primary)}
	</style>
	<?php if ( $airsoft_club_allow && $airsoft_club_assets ) : ?>
		<?php foreach ( $airsoft_club_assets['css'] as $airsoft_club_css ) : ?>
			<link rel="stylesheet" href="<?php echo esc_url( $airsoft_club_css ); ?>"><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone app document. ?>
		<?php endforeach; ?>
		<script>window.acmApp = <?php echo wp_json_encode( $airsoft_club_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ); ?>;</script>
		<script type="module" src="<?php echo esc_url( $airsoft_club_assets['js'] ); ?>"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone app document. ?>
	<?php endif; ?>
</head>
<body>
<?php if ( ! $airsoft_club_allow ) : ?>
	<div class="acm-msg">
		<h1><?php echo esc_html( $airsoft_club_brand['org_name'] ); ?></h1>
		<p><?php esc_html_e( 'Your account has no access to the member area (any more). Please contact your club lead.', 'airsoft-club-management' ); ?></p>
		<p><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'airsoft-club-management' ); ?></a></p>
	</div>
<?php elseif ( ! $airsoft_club_assets ) : ?>
	<div class="acm-msg"><p><?php esc_html_e( 'The member area is not built. Please install the release ZIP of the plugin.', 'airsoft-club-management' ); ?></p></div>
<?php else : ?>
	<div id="acm-app"></div>
	<noscript><div class="acm-msg"><?php esc_html_e( 'The member area needs JavaScript.', 'airsoft-club-management' ); ?></div></noscript>
<?php endif; ?>
</body>
</html>
