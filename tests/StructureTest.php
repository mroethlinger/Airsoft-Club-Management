<?php
/**
 * Regions, squads and assignments.
 *
 * @package AirsoftClub
 */

use AirsoftClub\Access\Assignments;
use AirsoftClub\Audit\AuditLog;
use AirsoftClub\Structure\Structure;

/** Structure and Assignments. */
class StructureTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Structure::flush();
		Assignments::flush();
	}

	public function test_region_crud_and_validation(): void {
		$id = Structure::create_region( '  North  ' );
		$this->assertIsInt( $id );
		$this->assertSame( 'North', Structure::region( $id )->name );
		$this->assertWPError( Structure::create_region( 'north' ) );
		$this->assertWPError( Structure::create_region( '' ) );
		$this->assertWPError( Structure::create_region( str_repeat( 'x', 101 ) ) );
		$this->assertTrue( Structure::update_region( $id, 'Nord', false ) );
		$this->assertFalse( Structure::region( $id )->squads_enabled );
		$this->assertTrue( Structure::delete_region( $id ) );
		$this->assertNull( Structure::region( $id ) );
		$this->assertWPError( Structure::delete_region( $id ) );
	}

	public function test_region_with_squads_cannot_be_deleted(): void {
		$region = Structure::create_region( 'North' );
		$squad  = Structure::create_squad( $region, 'Alpha' );
		$this->assertWPError( Structure::delete_region( $region ) );
		$this->assertTrue( Structure::delete_squad( $squad ) );
		$this->assertTrue( Structure::delete_region( $region ) );
	}

	public function test_in_use_filter_blocks_delete(): void {
		$region = Structure::create_region( 'North' );
		add_filter( 'airsoft_club_region_in_use', '__return_true' );
		$this->assertWPError( Structure::delete_region( $region ) );
		remove_filter( 'airsoft_club_region_in_use', '__return_true' );
	}

	public function test_squad_names_unique_per_region(): void {
		$north = Structure::create_region( 'North' );
		$south = Structure::create_region( 'South' );
		$this->assertIsInt( Structure::create_squad( $north, 'Alpha' ) );
		$this->assertWPError( Structure::create_squad( $north, 'ALPHA' ) );
		$this->assertIsInt( Structure::create_squad( $south, 'Alpha' ) );
		$this->assertWPError( Structure::create_squad( 99999, 'X' ) );
		$bravo = Structure::create_squad( $north, 'Bravo' );
		$this->assertWPError( Structure::rename_squad( $bravo, 'alpha' ) );
		$this->assertTrue( Structure::rename_squad( $bravo, 'Bravo 2' ) );
		$this->assertCount( 2, Structure::squads( $north ) );
	}

	public function test_names_are_sanitised(): void {
		$id = Structure::create_region( '<script>x</script>West' );
		$this->assertSame( 'West', Structure::region( $id )->name );
	}

	public function test_assign_validates_and_syncs_marker_role(): void {
		$region = Structure::create_region( 'North' );
		$user   = self::factory()->user->create( array( 'role' => 'acm_member' ) );
		$this->assertWPError( Assignments::assign( $user, 'king', 0 ) );
		$this->assertWPError( Assignments::assign( $user, Assignments::REGIONAL_LEAD, 99999 ) );
		$this->assertWPError( Assignments::assign( $user, Assignments::SQUAD_LEAD, 99999 ) );
		$this->assertWPError( Assignments::assign( 99999, Assignments::REGIONAL_LEAD, $region ) );

		$id = Assignments::assign( $user, Assignments::REGIONAL_LEAD, $region );
		$this->assertIsInt( $id );
		$this->assertWPError( Assignments::assign( $user, Assignments::REGIONAL_LEAD, $region ) );
		$this->assertContains( 'acm_lead', get_userdata( $user )->roles );
		$this->assertContains( 'acm_member', get_userdata( $user )->roles );

		$this->assertTrue( Assignments::revoke( $id ) );
		$this->assertNotContains( 'acm_lead', get_userdata( $user )->roles );
		$this->assertWPError( Assignments::revoke( $id ) );
	}

	public function test_deleting_scope_removes_assignments(): void {
		$region = Structure::create_region( 'North' );
		$squad  = Structure::create_squad( $region, 'Alpha' );
		$user   = self::factory()->user->create( array( 'role' => 'acm_member' ) );
		Assignments::assign( $user, Assignments::SQUAD_LEAD, $squad );
		Assignments::assign( $user, Assignments::REGIONAL_LEAD, $region );
		Structure::delete_squad( $squad );
		Structure::delete_region( $region );
		Assignments::flush();
		$this->assertSame( array(), Assignments::stored_for( $user ) );
		$this->assertNotContains( 'acm_lead', get_userdata( $user )->roles, 'marker role removed' );
	}

	public function test_deleted_user_loses_assignments(): void {
		$region = Structure::create_region( 'North' );
		$user   = self::factory()->user->create( array( 'role' => 'acm_member' ) );
		Assignments::assign( $user, Assignments::REGIONAL_LEAD, $region );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user );
		$this->assertSame( array(), Assignments::for_scope( Assignments::REGIONAL_LEAD, $region ) );
	}

	public function test_changes_are_audited_without_names(): void {
		$region = Structure::create_region( 'Secret Name' );
		$rows   = AuditLog::latest( 5 );
		$this->assertSame( 'region.created', $rows[0]->action );
		$this->assertSame( $region, (int) $rows[0]->object_id );
		foreach ( $rows as $row ) {
			$this->assertStringNotContainsString( 'Secret Name', (string) $row->details );
		}
	}
}
