<?php
/**
 * Plugin wiring.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub;

use AirsoftClub\Access\Assignments;
use AirsoftClub\Access\Roles;
use AirsoftClub\Access\TwoFactor;
use AirsoftClub\Admin\Menu;
use AirsoftClub\Database\Migrator;
use AirsoftClub\Members\MemberService;
use AirsoftClub\Members\Privacy;
use AirsoftClub\Settings\Branding;
use AirsoftClub\Updater\GitHubUpdater;

defined( 'ABSPATH' ) || exit;

/** Central entry point: activation, deactivation and hook registration. */
final class Plugin {

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/** Runs on plugins_loaded. */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Upgrade on init (not earlier): role labels are translated and translations load on init.
		add_action( 'init', array( self::class, 'load_textdomain' ), 1 );
		add_action( 'init', array( self::class, 'upgrade' ), 2 );
		add_action( 'deleted_user', array( Assignments::class, 'delete_user' ) );
		TwoFactor::hooks();
		Branding::hooks();
		GitHubUpdater::hooks();
		MemberService::hooks();
		Privacy::hooks();
		if ( is_admin() ) {
			Menu::hooks();
		}
	}

	/** Runs pending migrations and re-syncs roles after an update. */
	public static function upgrade(): void {
		if ( Migrator::is_behind() ) {
			Migrator::run();
		}
		Roles::maybe_upgrade();
		if ( get_option( 'acm_version' ) !== AIRSOFT_CLUB_VERSION ) {
			update_option( 'acm_version', AIRSOFT_CLUB_VERSION, false );
		}
	}

	/** Loads translations from languages/. */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'airsoft-club-management', false, dirname( plugin_basename( AIRSOFT_CLUB_FILE ) ) . '/languages' );
	}

	/** Runs on plugin activation. */
	public static function activate(): void {
		Migrator::run();
		Roles::install();
		update_option( 'acm_version', AIRSOFT_CLUB_VERSION, false );
	}

	/** Runs on plugin deactivation. Data and roles are kept. */
	public static function deactivate(): void {
		delete_transient( 'acm_github_release' );
		wp_clear_scheduled_hook( MemberService::PURGE_HOOK );
	}

	/** Whether boot() already ran (for tests). */
	public static function is_booted(): bool {
		return self::$booted;
	}
}
