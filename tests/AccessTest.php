<?php
/**
 * Scoped access tests: every ability for every level and scope.
 *
 * @package AirsoftClub
 */

use AirsoftClub\Access\Abilities;
use AirsoftClub\Access\Access;
use AirsoftClub\Access\Assignments;
use AirsoftClub\Structure\Structure;

/** Access service. */
class AccessTest extends WP_UnitTestCase {

	private int $north;
	private int $south;
	private int $alpha;
	private int $bravo;
	private int $charlie;

	public function set_up(): void {
		parent::set_up();
		Structure::flush();
		Assignments::flush();
		Abilities::flush();
		$this->north   = Structure::create_region( 'North' );
		$this->south   = Structure::create_region( 'South' );
		$this->alpha   = Structure::create_squad( $this->north, 'Alpha' );
		$this->bravo   = Structure::create_squad( $this->north, 'Bravo' );
		$this->charlie = Structure::create_squad( $this->south, 'Charlie' );
	}

	private function member(): int {
		return self::factory()->user->create( array( 'role' => 'acm_member' ) );
	}

	private function lead( string $role, int $scope = 0, bool $deputy = false ): int {
		$user = $this->member();
		$this->assertIsInt( Assignments::assign( $user, $role, $scope, $deputy ) );
		return $user;
	}

	public function test_signed_out_and_subscriber_may_nothing(): void {
		$sub = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		foreach ( array_keys( Abilities::definitions() ) as $ability ) {
			$this->assertFalse( Access::can( 0, $ability, $this->north ), $ability );
			$this->assertFalse( Access::can( $sub, $ability, $this->north ), $ability );
		}
	}

	public function test_member_only_member_level_abilities(): void {
		$user = $this->member();
		foreach ( Abilities::matrix() as $ability => $min ) {
			$this->assertSame( Abilities::MEMBER === $min, Access::can( $user, $ability, $this->north ), $ability );
		}
	}

	public function test_squad_lead_inside_own_squad_and_region_only(): void {
		$user = $this->lead( Assignments::SQUAD_LEAD, $this->alpha );
		foreach ( Abilities::matrix() as $ability => $min ) {
			$expected = $min <= Abilities::SQUAD_LEAD;
			$this->assertSame( $expected, Access::can( $user, $ability, null, $this->alpha ), "$ability own squad" );
			$this->assertSame( $expected, Access::can( $user, $ability, $this->north ), "$ability own region" );
			$this->assertSame( Abilities::MEMBER === $min, Access::can( $user, $ability, null, $this->bravo ), "$ability other squad" );
			$this->assertSame( Abilities::MEMBER === $min, Access::can( $user, $ability, $this->south ), "$ability other region" );
		}
	}

	public function test_regional_lead_in_region_and_its_squads(): void {
		$user = $this->lead( Assignments::REGIONAL_LEAD, $this->north );
		foreach ( Abilities::matrix() as $ability => $min ) {
			$expected = $min <= Abilities::REGIONAL_LEAD;
			$this->assertSame( $expected, Access::can( $user, $ability, $this->north ), $ability );
			$this->assertSame( $expected, Access::can( $user, $ability, null, $this->bravo ), $ability );
			$this->assertSame( Abilities::MEMBER === $min, Access::can( $user, $ability, $this->south ), $ability );
			$this->assertSame( Abilities::MEMBER === $min, Access::can( $user, $ability, null, $this->charlie ), $ability );
		}
	}

	public function test_deputy_has_same_rights(): void {
		$lead   = $this->lead( Assignments::REGIONAL_LEAD, $this->north );
		$deputy = $this->lead( Assignments::REGIONAL_LEAD, $this->north, true );
		foreach ( array_keys( Abilities::definitions() ) as $ability ) {
			$this->assertSame( Access::can( $lead, $ability, $this->north ), Access::can( $deputy, $ability, $this->north ), $ability );
		}
	}

	public function test_overall_lead_and_admin_everywhere(): void {
		$overall = $this->lead( Assignments::OVERALL_LEAD );
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		foreach ( array_keys( Abilities::definitions() ) as $ability ) {
			foreach ( array( $overall, $admin ) as $user ) {
				$this->assertTrue( Access::can( $user, $ability, $this->south ), $ability );
				$this->assertTrue( Access::can( $user, $ability, null, $this->alpha ), $ability );
				$this->assertTrue( Access::can( $user, $ability ), $ability );
			}
		}
		$this->assertNull( Access::regions_for( $overall, 'members.create' ) );
	}

	public function test_mismatched_region_and_squad_is_denied(): void {
		$user = $this->lead( Assignments::REGIONAL_LEAD, $this->south );
		$this->assertFalse( Access::can( $user, 'members.create', $this->south, $this->alpha ) );
	}

	public function test_squad_lead_suspended_when_squads_off(): void {
		$user = $this->lead( Assignments::SQUAD_LEAD, $this->alpha );
		$this->assertTrue( Access::can( $user, 'attendance.record', null, $this->alpha ) );
		Structure::update_region( $this->north, 'North', false );
		Assignments::flush();
		$this->assertFalse( Access::can( $user, 'attendance.record', null, $this->alpha ) );
		$this->assertSame( Abilities::MEMBER, Access::max_level( $user ) );
		$this->assertCount( 1, Assignments::stored_for( $user ), 'assignment is kept' );
		Structure::update_region( $this->north, 'North', true );
		Assignments::flush();
		$this->assertTrue( Access::can( $user, 'attendance.record', null, $this->alpha ) );
	}

	public function test_matrix_change_takes_effect(): void {
		$user = $this->lead( Assignments::SQUAD_LEAD, $this->alpha );
		$this->assertFalse( Access::can( $user, 'members.delete', $this->north ) );
		Abilities::save( array( 'members.delete' => Abilities::SQUAD_LEAD ) );
		$this->assertTrue( Access::can( $user, 'members.delete', $this->north ) );
	}

	public function test_regions_for(): void {
		$user = $this->lead( Assignments::SQUAD_LEAD, $this->charlie );
		Assignments::assign( $user, Assignments::REGIONAL_LEAD, $this->north );
		$this->assertSame( array( $this->north, $this->south ), Access::regions_for( $user, 'attendance.record' ) );
		$this->assertSame( array( $this->north ), Access::regions_for( $user, 'applications.decide' ) );
		$this->assertSame( array(), Access::regions_for( $user, 'events.create_global' ) );
		$this->assertSame( array(), Access::regions_for( $this->member(), 'members.create' ) );
	}

	public function test_abilities_of_and_can_anywhere(): void {
		$user = $this->lead( Assignments::SQUAD_LEAD, $this->alpha );
		$this->assertContains( 'attendance.record', Access::abilities_of( $user ) );
		$this->assertNotContains( 'applications.decide', Access::abilities_of( $user ) );
		$this->assertTrue( Access::can_anywhere( $user, 'events.propose' ) );
		$this->assertFalse( Access::can_anywhere( 0, 'events.propose' ) );
	}

	public function test_unknown_ability_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		Access::can( $this->member(), 'nope.nothing' );
	}
}
