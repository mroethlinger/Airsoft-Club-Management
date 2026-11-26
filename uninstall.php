<?php
/**
 * Uninstall handler. Club data is kept unless "Delete all club data" is switched on in the settings.
 *
 * @package AirsoftClub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

define( 'AIRSOFT_CLUB_DIR', plugin_dir_path( __FILE__ ) );
require_once AIRSOFT_CLUB_DIR . 'src/Autoloader.php';
AirsoftClub\Autoloader::register( AIRSOFT_CLUB_DIR . 'src/' );

AirsoftClub\Uninstaller::run();
