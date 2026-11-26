<?php
/**
 * Plugin Name:       Airsoft Club Management
 * Plugin URI:        https://github.com/mroethlinger/Airsoft-Club-Management
 * Description:       Club management for airsoft clubs: members, events, carpools, applicants, gear and association books.
 * Version:           0.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Maik Röthlinger
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       airsoft-club-management
 * Domain Path:       /languages
 *
 * @package AirsoftClub
 */

defined( 'ABSPATH' ) || exit;

define( 'AIRSOFT_CLUB_VERSION', '0.0.1' );
define( 'AIRSOFT_CLUB_FILE', __FILE__ );
define( 'AIRSOFT_CLUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIRSOFT_CLUB_URL', plugin_dir_url( __FILE__ ) );

require_once AIRSOFT_CLUB_DIR . 'src/Autoloader.php';
AirsoftClub\Autoloader::register( AIRSOFT_CLUB_DIR . 'src/' );

register_activation_hook( __FILE__, array( AirsoftClub\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( AirsoftClub\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( AirsoftClub\Plugin::class, 'boot' ) );
