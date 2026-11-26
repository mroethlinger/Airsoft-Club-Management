<?php
/**
 * Member records, rights, privacy filter and archive.
 *
 * @package AirsoftClub
 */

use AirsoftClub\Access\Access;
use AirsoftClub\Access\Assignments;
use AirsoftClub\Members\MemberRepository;
use AirsoftClub\Members\MemberService;
use AirsoftClub\Members\Privacy;
use AirsoftClub\Members\Profile;
use AirsoftClub\Structure\Structure;

/** Members (phase 2). */
class MembersTest extends WP_UnitTestCase {

	private int $north;
	private int $south;
	private int $alpha;
	private int $bravo;
	private int $charlie;
	private int $admin;
	private int $regional;
	private int $squad_lead;

	public function set_up(): void {
		parent::set_up();
		Structure::flush();
		Assignments::flush();
		$this->north      = Structure::create_region( 'North' );
		$this->south      = Structure::create_region( 'South' );
		$this->alpha      = Structure::create_squad( $this->north, 'Alpha' );
		$this->bravo      = Structure::create_squad( $this->north, 'Bravo' );
		$this->charlie    = Structure::create_squad( $this->south, 'Charlie' );
		$this->admin      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->regional   = $this->new_member( $this->north );
		$this->squad_lead = $this->new_member( $this->north, $this->alpha );
		Assignments::assign( $this->regional, Assignments::REGIONAL_LEAD, $this->north );
		Assignments::assign( $this->squad_lead, Assignments::SQUAD_LEAD, $this->alpha );
	}

	private function new_member( int $region, ?int $squad = null, array $extra = array() ): int {
		static $n = 0;
		++$n;
		$id = MemberService::create(
			$this->admin,
			array_merge(
				array(
					'email'      => "member{$n}@example.org",
					'first_name' => 'First' . $n,
					'last_name'  => 'Last' . $n,
					'region_id'  => $region,
					'squad_id'   => $squad,
				),
				$extra
			)
		);
		$this->assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : '' );
		return $id;
	}

	public function test_create_sets_up_account_and_record(): void {
		$id   = $this->new_member( $this->north, $this->bravo, array( 'nickname' => 'Ghost' ) );
		$m    = MemberRepository::find( $id );
		$user = get_userdata( $id );
		$this->assertSame( 'active', $m->status );
		$this->assertSame( $this->bravo, $m->squad_id );
		$this->assertSame( 'Ghost', $m->nickname );
		$this->assertGreaterThan( 0, $m->member_number );
		$this->assertContains( 'acm_member', $user->roles );
		$this->assertStringNotContainsString( 'Last', $user->display_name, 'display name does not expose the last name' );
	}

	public function test_create_rights_follow_scope(): void {
		$data = array(
			'email'      => 'new@example.org',
			'first_name' => 'New',
			'last_name'  => 'Person',
			'region_id'  => $this->north,
			'squad_id'   => $this->bravo,
		);
		$this->assertWPError( MemberService::create( $this->squad_lead, $data ), 'squad lead cannot add to another squad' );
		$this->assertIsInt( MemberService::create( $this->squad_lead, array_merge( $data, array( 'squad_id' => $this->alpha ) ) ) );
		$this->assertWPError( MemberService::create( $this->regional, array_merge( $data, array( 'email' => 'x@example.org', 'region_id' => $this->south, 'squad_id' => null ) ) ) );
		$this->assertWPError( MemberService::create( $this->regional, array_merge( $data, array( 'email' => 'y@example.org', 'squad_id' => $this->charlie ) ) ), 'squad must be in region' );
		$this->assertWPError( MemberService::create( $this->regional, array_merge( $data, array( 'email' => 'new@example.org', 'squad_id' => null ) ) ), 'duplicate email' );
		$this->assertWPError( MemberService::create( $this->regional, array_merge( $data, array( 'email' => 'z@example.org', 'squad_id' => null, 'birth_date' => gmdate( 'Y-m-d', strtotime( '-17 years' ) ) ) ) ), 'adults only' );
	}

	public function test_profile_validation(): void {
		$ok = Profile::clean(
			array(
				'phone'         => '+49 (0) 511/123-45',
				'name_display'  => 'nickname',
				'vehicle_seats' => '3',
				'share_phone'   => '1',
				'social'        => array(
					'discord' => ' ghost#1 ',
					'asvz'    => '',
				),
				'unknown'       => 'ignored',
			)
		);
		$this->assertSame( 3, $ok['vehicle_seats'] );
		$this->assertTrue( $ok['share_phone'] );
		$this->assertSame( array( 'discord' => 'ghost#1' ), $ok['social'] );
		$this->assertArrayNotHasKey( 'unknown', $ok );

		foreach ( array(
			array( 'phone' => 'call me' ),
			array( 'name_display' => 'secret' ),
			array( 'vehicle_seats' => 9 ),
			array( 'combat_role' => 'general' ),
			array( 'country' => 'Germany' ),
			array( 'first_name' => '' ),
			array( 'social' => array( 'myspace' => 'x' ) ),
			array( 'nickname' => str_repeat( 'n', 51 ) ),
		) as $bad ) {
			$this->assertWPError( Profile::clean( $bad ), wp_json_encode( $bad ) );
		}
	}

	public function test_update_profile_self_and_leads(): void {
		$member = $this->new_member( $this->north, $this->alpha );
		$other  = $this->new_member( $this->south );
		$this->assertSame( array( 'city' ), MemberService::update_profile( $member, $member, array( 'city' => 'Hannover' ) ) );
		$this->assertWPError( MemberService::update_profile( $other, $member, array( 'city' => 'X' ) ) );
		$this->assertSame( array( 'phone' ), MemberService::update_profile( $this->squad_lead, $member, array( 'phone' => '123' ) ) );
		$this->assertSame( array(), MemberService::update_profile( $member, $member, array( 'birth_date' => '1990-01-01' ) ), 'members cannot set their birth date' );
		$this->assertSame( array( 'birth_date' ), MemberService::update_profile( $this->regional, $member, array( 'birth_date' => '1990-01-01' ) ) );
		$this->assertWPError( MemberService::update_profile( $this->regional, $member, array( 'birth_date' => '2999-01-01' ) ) );
		$this->assertWPError( MemberService::update_profile( $this->regional, $member, array( 'birth_date' => '1990-02-30' ) ) );
	}

	public function test_squad_lead_loses_management_when_squads_off(): void {
		$member = $this->new_member( $this->north, $this->alpha );
		$this->assertTrue( MemberService::manages( $this->squad_lead, MemberRepository::find( $member ) ) );
		Structure::update_region( $this->north, 'North', false );
		Assignments::flush();
		$m = MemberRepository::find( $member );
		$this->assertFalse( MemberService::manages( $this->squad_lead, $m ) );
		$this->assertTrue( MemberService::manages( $this->regional, $m ) );
		$this->assertNull( MemberService::active_squad( $m ) );
		$this->assertSame( $this->alpha, $m->squad_id, 'squad stays stored' );
	}

	public function test_display_name_rules(): void {
		$member = $this->new_member( $this->north, $this->alpha, array( 'nickname' => 'Ghost' ) );
		$viewer = $this->new_member( $this->north, $this->bravo );
		MemberService::update_profile( $member, $member, array( 'name_display' => 'abbreviated' ) );
		$m = MemberRepository::find( $member );
		$this->assertMatchesRegularExpression( '/^First\d+ L\.$/', MemberService::display_name( $m, $viewer ) );
		$this->assertMatchesRegularExpression( '/^First\d+ Last\d+$/', MemberService::display_name( $m, $member ) );
		$this->assertMatchesRegularExpression( '/^First\d+ Last\d+$/', MemberService::display_name( $m, $this->squad_lead ) );
		MemberService::update_profile( $member, $member, array( 'name_display' => 'nickname' ) );
		$this->assertSame( 'Ghost', MemberService::display_name( MemberRepository::find( $member ), $viewer ) );
	}

	public function test_player_card_privacy(): void {
		$member = $this->new_member( $this->north, $this->alpha );
		MemberService::update_profile(
			$member,
			$member,
			array(
				'phone'       => '0511 1',
				'street'      => 'Main 1',
				'share_phone' => false,
			)
		);
		$viewer = $this->new_member( $this->north, $this->bravo );
		$m      = MemberRepository::find( $member );

		$card = MemberService::card( $m, $viewer );
		$this->assertNull( $card['phone'] );
		$this->assertNull( $card['email'] );
		$this->assertArrayNotHasKey( 'street', $card );
		$this->assertArrayNotHasKey( 'birth_date', $card );

		MemberService::update_profile( $member, $member, array( 'share_phone' => true, 'share_email' => true ) );
		$card = MemberService::card( MemberRepository::find( $member ), $viewer );
		$this->assertSame( '0511 1', $card['phone'] );
		$this->assertStringEndsWith( '@example.org', $card['email'] );

		$lead_card = MemberService::card( $m, $this->squad_lead );
		$this->assertSame( 'Main 1', $lead_card['street'] );
		$this->assertNull( MemberService::card( $m, 0 ) );
		$this->assertNull( MemberService::card( $m, self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) );
	}

	public function test_visible_members(): void {
		$north_member = $this->new_member( $this->north );
		$south_member = $this->new_member( $this->south );
		$ids          = static fn( $list ) => array_map( static fn( $m ) => $m->user_id, $list );
		$this->assertContains( $north_member, $ids( MemberService::visible_members( $north_member ) ) );
		$this->assertNotContains( $south_member, $ids( MemberService::visible_members( $north_member ) ) );
		$this->assertContains( $south_member, $ids( MemberService::visible_members( $this->admin ) ) );
		$this->assertSame( array(), MemberService::visible_members( 0 ) );
	}

	public function test_move_rules(): void {
		$member = $this->new_member( $this->north, $this->alpha );
		$this->assertWPError( MemberService::move( $this->squad_lead, $member, $this->north, $this->bravo ), 'squad lead cannot move into a foreign squad' );
		$this->assertTrue( MemberService::move( $this->squad_lead, $member, $this->north, null ), 'squad lead can take someone out of their squad' );
		$this->assertTrue( MemberService::move( $this->regional, $member, $this->north, $this->bravo ) );
		$this->assertWPError( MemberService::move( $this->regional, $member, $this->south, null ), 'needs both regions' );
		Assignments::assign( $this->regional, Assignments::REGIONAL_LEAD, $this->south );
		$this->assertTrue( MemberService::move( $this->regional, $member, $this->south, $this->charlie ) );
		$this->assertSame( $this->charlie, MemberRepository::find( $member )->squad_id );
	}

	public function test_leave_archive_reactivate_and_purge(): void {
		$member = $this->new_member( $this->north, $this->alpha );
		Assignments::assign( $member, Assignments::SQUAD_LEAD, $this->alpha );
		$this->assertWPError( MemberService::leave( $this->squad_lead, $member ), 'squad lead may not delete by default' );
		$this->assertWPError( MemberService::leave( $this->regional, $this->regional ), 'not oneself' );
		$this->assertTrue( MemberService::leave( $this->regional, $member ) );

		$m = MemberRepository::find( $member );
		$this->assertSame( 'left', $m->status );
		$this->assertFalse( user_can( $member, 'acm_member_area' ) );
		$this->assertSame( array(), Assignments::stored_for( $member ) );
		$this->assertNull( MemberService::card( $m, $this->new_member( $this->north ) ), 'former members are hidden from members' );
		$this->assertCount( 1, MemberService::archive( $this->regional ) );
		$this->assertCount( 1, MemberService::archive( $this->squad_lead ), 'own squad' );
		$this->assertCount( 0, MemberService::archive( $this->new_member( $this->north, $this->bravo ) ) );

		$this->assertTrue( MemberService::reactivate( $this->regional, $member ) );
		$this->assertTrue( user_can( $member, 'acm_member_area' ) );

		MemberService::leave( $this->regional, $member );
		$this->assertSame( 0, MemberService::purge_expired(), 'inside the retention period' );
		MemberRepository::update( $member, array( 'left_at' => gmdate( 'Y-m-d H:i:s', time() - 91 * DAY_IN_SECONDS ) ) );
		$this->assertSame( 1, MemberService::purge_expired() );
		$this->assertNull( MemberRepository::find( $member ) );
		$this->assertFalse( get_userdata( $member ) );
	}

	public function test_members_block_structure_deletion(): void {
		$this->new_member( $this->south, $this->charlie );
		$this->assertWPError( Structure::delete_squad( $this->charlie ) );
		$this->assertWPError( Structure::delete_region( $this->south ) );
	}

	public function test_member_level_ability_in_own_region(): void {
		$member = $this->new_member( $this->south );
		$this->assertSame( array( $this->south ), Access::regions_for( $member, 'events.propose' ) );
	}

	public function test_privacy_export_and_erase(): void {
		$member = $this->new_member( $this->north, null, array( 'nickname' => 'Ghost' ) );
		$email  = get_userdata( $member )->user_email;
		$export = Privacy::export( $email );
		$values = wp_list_pluck( $export['data'][0]['data'], 'value' );
		$this->assertContains( 'Ghost', $values );
		$this->assertContains( 'North', $values );

		$erase = Privacy::erase( $email );
		$this->assertTrue( $erase['items_retained'] );
		$this->assertNotNull( MemberRepository::find( $member ) );

		MemberService::leave( $this->regional, $member );
		$erase = Privacy::erase( $email );
		$this->assertTrue( $erase['items_removed'] );
		$this->assertNull( MemberRepository::find( $member ) );
	}
}
