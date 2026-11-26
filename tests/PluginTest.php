<?php
/**
 * Smoke tests for the plugin skeleton.
 *
 * @package AirsoftClub
 */

use AirsoftClub\Plugin;

/** Plugin bootstrap, autoloader and activation. */
class PluginTest extends WP_UnitTestCase {

	public function test_constants_are_defined(): void {
		$this->assertTrue( defined( 'ACM_VERSION' ) );
		$this->assertFileExists( ACM_FILE );
	}

	public function test_autoloader_loads_plugin_class(): void {
		$this->assertTrue( class_exists( Plugin::class ) );
		$this->assertFalse( class_exists( 'AirsoftClub\DoesNotExist' ) );
	}

	public function test_boot_runs_on_plugins_loaded(): void {
		$this->assertTrue( Plugin::is_booted() );
		$this->assertNotFalse( has_action( 'init', array( Plugin::class, 'load_textdomain' ) ) );
	}

	public function test_activation_stores_version(): void {
		delete_option( 'acm_version' );
		Plugin::activate();
		$this->assertSame( ACM_VERSION, get_option( 'acm_version' ) );
	}

	public function test_header_version_matches_constant(): void {
		$data = get_file_data( ACM_FILE, array( 'Version' => 'Version' ) );
		$this->assertSame( ACM_VERSION, $data['Version'] );
	}
}
