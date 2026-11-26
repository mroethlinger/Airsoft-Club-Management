<?php
/**
 * Admin pages render without notices and enforce capabilities.
 *
 * @package AirsoftClub
 */

use AirsoftClub\Access\Assignments;
use AirsoftClub\Admin\AuditPage;
use AirsoftClub\Admin\RightsPage;
use AirsoftClub\Admin\SettingsPage;
use AirsoftClub\Admin\StructurePage;
use AirsoftClub\Structure\Structure;

/** Admin screens. */
class AdminPagesTest extends WP_UnitTestCase {

	private function render( callable $page ): string {
		ob_start();
		try {
			$page();
		} finally {
			$out = (string) ob_get_clean();
		}
		return $out;
	}

	public function test_pages_render_for_admin(): void {
		Structure::flush();
		$region = Structure::create_region( 'North <b>' );
		$squad  = Structure::create_squad( $region, 'Alpha' );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		Assignments::assign( $admin, Assignments::SQUAD_LEAD, $squad );
		wp_set_current_user( $admin );
		set_current_screen( 'dashboard' );

		$html = $this->render( array( StructurePage::class, 'render' ) );
		$this->assertStringContainsString( 'North', $html );
		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
		$this->assertStringNotContainsString( '<b>', $html );

		$html = $this->render( array( RightsPage::class, 'render' ) );
		$this->assertStringContainsString( 'levels[members.create]', $html );

		$html = $this->render( array( SettingsPage::class, 'render' ) );
		$this->assertStringContainsString( 'acm_settings[club_name]', $html );

		$html = $this->render( array( AuditPage::class, 'render' ) );
		$this->assertStringContainsString( 'region.created', $html );
	}

	public function test_lead_sees_rights_read_only(): void {
		$lead = self::factory()->user->create( array( 'role' => 'acm_lead' ) );
		wp_set_current_user( $lead );
		$html = $this->render( array( RightsPage::class, 'render' ) );
		$this->assertStringNotContainsString( '<select', $html );
	}

	public function test_member_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'acm_member' ) ) );
		$this->expectException( WPDieException::class );
		$this->render( array( SettingsPage::class, 'render' ) );
	}

	public function test_structure_handler_refuses_member(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'acm_member' ) ) );
		$_POST['action'] = 'acm_region_create';
		$this->expectException( WPDieException::class );
		try {
			StructurePage::handle();
		} finally {
			unset( $_POST['action'] );
		}
	}

	public function test_structure_handler_checks_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['action']    = 'acm_region_create';
		$_REQUEST['_wpnonce'] = 'invalid';
		$this->expectException( WPDieException::class );
		try {
			StructurePage::handle();
		} finally {
			unset( $_POST['action'], $_REQUEST['_wpnonce'] );
		}
	}
}
