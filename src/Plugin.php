<?php
/**
 * Plugin wiring.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub;

defined( 'ABSPATH' ) || exit;

/** Central entry point: activation, deactivation and hook registration. */
final class Plugin {

	/** Whether boot() already ran. */
	private static bool $booted = false;

	/** Runs on plugins_loaded. */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'init', array( self::class, 'load_textdomain' ) );
	}

	/** Loads translations from languages/. */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'airsoft-club-management', false, dirname( plugin_basename( ACM_FILE ) ) . '/languages' );
	}

	/** Runs on plugin activation. Migrations follow in phase 1. */
	public static function activate(): void {
		update_option( 'acm_version', ACM_VERSION, false );
	}

	/** Runs on plugin deactivation. Data is kept. */
	public static function deactivate(): void {
	}

	/** Whether boot() already ran (for tests). */
	public static function is_booted(): bool {
		return self::$booted;
	}
}
