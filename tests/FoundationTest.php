<?php
/**
 * Settings, roles, rights matrix, 2FA, migrations, updater, uninstall, admin handlers.
 *
 * @package AirsoftClub
 */

use AirsoftClub\Access\Abilities;
use AirsoftClub\Access\Access;
use AirsoftClub\Access\Assignments;
use AirsoftClub\Access\Roles;
use AirsoftClub\Access\TwoFactor;
use AirsoftClub\Database\Migrator;
use AirsoftClub\Settings\Branding;
use AirsoftClub\Settings\Settings;
use AirsoftClub\Structure\Structure;
use AirsoftClub\Uninstaller;
use AirsoftClub\Updater\GitHubUpdater;

/** Phase 1 foundation. */
class FoundationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Structure::flush();
		Assignments::flush();
		Abilities::flush();
	}

	public function test_migrations_create_tables(): void {
		global $wpdb;
		$this->assertSame( Migrator::latest_version(), (int) get_option( Migrator::OPTION ) );
		$this->assertFalse( Migrator::is_behind() );
		foreach ( Migrator::tables() as $table ) {
			$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
		}
		$this->assertSame( 0, Migrator::run(), 'second run applies nothing' );
	}

	public function test_roles_installed_and_admin_has_all_caps(): void {
		foreach ( Roles::roles() as $slug => $def ) {
			$role = get_role( $slug );
			$this->assertNotNull( $role, $slug );
			foreach ( $def['caps'] as $cap ) {
				$this->assertTrue( $role->has_cap( $cap ), "$slug $cap" );
			}
		}
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		foreach ( array_keys( Roles::capabilities() ) as $cap ) {
			$this->assertTrue( user_can( $admin, $cap ), $cap );
		}
		$member = self::factory()->user->create( array( 'role' => 'acm_member' ) );
		$this->assertTrue( user_can( $member, 'acm_member_area' ) );
		$this->assertFalse( user_can( $member, 'acm_manage_settings' ) );
		$this->assertFalse( user_can( $member, 'edit_posts' ) );
	}

	public function test_settings_sanitise(): void {
		$out = Settings::sanitize(
			array(
				'club_name'       => '<b>CIS</b> Airsoft',
				'club_short_name' => str_repeat( 'A', 40 ),
				'email'           => 'not an email',
				'country'         => 'germany',
				'color_primary'   => 'red; }body{display:none',
				'color_text'      => '#ABCDEF',
				'logo_id'         => '12345',
				'require_2fa'     => '',
				'unknown'         => 'x',
			)
		);
		$this->assertSame( 'CIS Airsoft', $out['club_name'] );
		$this->assertSame( 20, mb_strlen( $out['club_short_name'] ) );
		$this->assertSame( '', $out['email'] );
		$this->assertSame( 'DE', $out['country'] );
		$this->assertSame( '#a4c639', $out['color_primary'] );
		$this->assertSame( '#abcdef', $out['color_text'] );
		$this->assertSame( '', $out['logo_id'] );
		$this->assertSame( '', $out['require_2fa'] );
		$this->assertArrayNotHasKey( 'unknown', $out );
	}

	public function test_defaults_and_fallbacks(): void {
		delete_option( Settings::OPTION );
		$this->assertTrue( Settings::association_enabled() );
		$this->assertSame( '1', Settings::get( 'require_2fa' ) );
		$this->assertSame( '', Settings::get( 'remove_data' ) );
		$this->assertSame( get_bloginfo( 'name' ), Settings::club_name() );
		update_option( Settings::OPTION, array( 'module_association' => '' ) );
		$this->assertFalse( Settings::association_enabled() );
	}

	public function test_branding_css_only_contains_valid_colours(): void {
		update_option( Settings::OPTION, array( 'color_primary' => '#112233', 'color_text' => 'x;}</style><script>' ) );
		$css = Branding::css();
		$this->assertStringContainsString( '--acm-primary:#112233;', $css );
		$this->assertStringNotContainsString( 'script', $css );
		$this->assertStringContainsString( '--acm-primary-contrast:', $css );
	}

	public function test_matrix_rejects_levels_below_floor_and_unknown(): void {
		Abilities::save(
			array(
				'members.create' => Abilities::MEMBER,
				'events.propose' => Abilities::OVERALL_LEAD,
				'nope'           => 1,
				'members.delete' => 9,
			)
		);
		$m = Abilities::matrix();
		$this->assertSame( Abilities::SQUAD_LEAD, $m['members.create'] );
		$this->assertSame( Abilities::OVERALL_LEAD, $m['events.propose'] );
		$this->assertSame( Abilities::REGIONAL_LEAD, $m['members.delete'] );
		$this->assertArrayNotHasKey( 'nope', $m );
		update_option( Abilities::OPTION, array( 'applications.decide' => 0 ) );
		Abilities::flush();
		$this->assertSame( Abilities::REGIONAL_LEAD, Abilities::matrix()['applications.decide'], 'stored invalid value falls back' );
	}

	public function test_two_factor_not_enforced_without_provider(): void {
		$this->assertFalse( TwoFactor::provider_available() );
		$this->assertFalse( TwoFactor::enforced() );
		$board = self::factory()->user->create( array( 'role' => 'acm_board' ) );
		$this->assertTrue( user_can( $board, 'acm_manage_settings' ) );
	}

	public function test_two_factor_block_removes_lead_and_board_rights(): void {
		$region = Structure::create_region( 'North' );
		$lead   = self::factory()->user->create( array( 'role' => 'acm_member' ) );
		Assignments::assign( $lead, Assignments::REGIONAL_LEAD, $region );
		$board = self::factory()->user->create( array( 'role' => 'acm_board' ) );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );

		add_filter( 'airsoft_club_2fa_provider_available', '__return_true' );
		add_filter( 'airsoft_club_user_has_2fa', '__return_false' );
		$this->assertTrue( TwoFactor::enforced() );
		$this->assertFalse( Access::can( $lead, 'members.create', $region ) );
		$this->assertTrue( Access::can( $lead, 'events.propose', $region ), 'member rights stay' );
		$this->assertFalse( user_can( $board, 'acm_manage_settings' ) );
		$this->assertTrue( user_can( $board, 'acm_member_area' ) );
		$this->assertTrue( user_can( $admin, 'acm_manage_settings' ) );
		$this->assertTrue( Access::can( $admin, 'members.create', $region ) );

		remove_filter( 'airsoft_club_user_has_2fa', '__return_false' );
		add_filter( 'airsoft_club_user_has_2fa', '__return_true' );
		$this->assertTrue( Access::can( $lead, 'members.create', $region ) );
		$this->assertTrue( user_can( $board, 'acm_manage_settings' ) );

		update_option( Settings::OPTION, array( 'require_2fa' => '' ) );
		remove_filter( 'airsoft_club_user_has_2fa', '__return_true' );
		$this->assertTrue( Access::can( $lead, 'members.create', $region ), 'setting off' );
	}

	public function test_updater_parses_only_valid_releases(): void {
		$ok = array(
			'tag_name' => 'v1.2.3',
			'html_url' => 'https://github.com/x/y/releases/v1.2.3',
			'assets'   => array(
				array(
					'name'                 => 'airsoft-club-management.zip',
					'browser_download_url' => 'https://github.com/x/y/releases/download/v1.2.3/airsoft-club-management.zip',
				),
			),
		);
		$this->assertSame( '1.2.3', GitHubUpdater::parse_release( $ok )['version'] );
		$this->assertNull( GitHubUpdater::parse_release( array_merge( $ok, array( 'prerelease' => true ) ) ) );
		$this->assertNull( GitHubUpdater::parse_release( array_merge( $ok, array( 'tag_name' => 'latest' ) ) ) );
		$bad                                     = $ok;
		$bad['assets'][0]['browser_download_url'] = 'https://evil.example/x.zip';
		$this->assertNull( GitHubUpdater::parse_release( $bad ) );
		$this->assertNull( GitHubUpdater::parse_release( 'nope' ) );
	}

	public function test_uninstall_keeps_data_by_default(): void {
		delete_option( Settings::OPTION );
		$this->assertFalse( Uninstaller::run() );
		$this->assertNotNull( get_role( 'acm_member' ) );
	}

	public function test_uninstall_removes_everything_when_opted_in(): void {
		// The test suite wraps everything in a transaction; record DROP statements instead of running them.
		$dropped = array();
		$catch   = static function ( $query ) use ( &$dropped ) {
			if ( preg_match( '/^DROP TABLE IF EXISTS (\S+)$/', $query, $m ) ) {
				$dropped[] = $m[1];
				return 'SELECT 1';
			}
			return $query;
		};
		add_filter( 'query', $catch, 1 );
		update_option( Settings::OPTION, array( 'remove_data' => '1' ) );
		$this->assertTrue( Uninstaller::run() );
		remove_filter( 'query', $catch, 1 );

		$this->assertEqualsCanonicalizing( Migrator::tables(), $dropped );
		$this->assertNull( get_role( 'acm_member' ) );
		$this->assertFalse( get_option( Settings::OPTION ) );
		$this->assertFalse( get_option( Migrator::OPTION ) );
		Roles::install();
		update_option( Migrator::OPTION, Migrator::latest_version() );
	}
}
