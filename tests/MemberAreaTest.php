<?php
/**
 * Member area: page, session API, nonce and notification feed (phase 3).
 *
 * @package AirsoftClub
 */

use AirsoftClub\Access\Assignments;
use AirsoftClub\Front\AppPage;
use AirsoftClub\Members\MemberService;
use AirsoftClub\Notifications\Notifications;
use AirsoftClub\Structure\Structure;

/** Member area. */
class MemberAreaTest extends WP_UnitTestCase {

	private int $region;
	private int $squad;
	private int $admin;
	private int $member;

	public function set_up(): void {
		parent::set_up();
		Structure::flush();
		Assignments::flush();
		do_action( 'rest_api_init' );
		$this->region = Structure::create_region( 'North' );
		$this->squad  = Structure::create_squad( $this->region, 'Alpha' );
		$this->admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->member = MemberService::create(
			$this->admin,
			array(
				'email'       => 'area@example.org',
				'first_name'  => 'Area',
				'last_name'   => 'Tester',
				'nickname'    => 'Ghost',
				'region_id'   => $this->region,
				'squad_id'    => $this->squad,
				'send_invite' => false,
			)
		);
	}

	private function call( string $method, string $route ): WP_REST_Response {
		return rest_get_server()->dispatch( new WP_REST_Request( $method, '/acm/v1' . $route ) );
	}

	public function test_session_for_member(): void {
		wp_set_current_user( $this->member );
		$res = $this->call( 'GET', '/session' );
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		$u    = $data['data'];
		$this->assertSame( 'Area', $u['first_name'] );
		$this->assertSame( 'Ghost', $u['nickname'] );
		$this->assertSame( 'North', $u['region']['name'] );
		$this->assertSame( 'Alpha', $u['squad']['name'] );
		$this->assertFalse( $u['is_lead'] );
		$this->assertNull( $u['admin_url'] );
		$this->assertContains( 'events.propose', $u['abilities'] );
		$this->assertNotContains( 'members.create', $u['abilities'] );
		$this->assertArrayHasKey( 'color_primary', $data['branding'] );
	}

	public function test_session_for_lead_lists_roles(): void {
		Assignments::assign( $this->member, Assignments::SQUAD_LEAD, $this->squad, true );
		wp_set_current_user( $this->member );
		$u = $this->call( 'GET', '/session' )->get_data()['data'];
		$this->assertTrue( $u['is_lead'] );
		$this->assertSame( 'squad_lead', $u['roles'][0]['role'] );
		$this->assertSame( 'Alpha', $u['roles'][0]['scope_name'] );
		$this->assertTrue( $u['roles'][0]['is_deputy'] );
		$this->assertNotNull( $u['admin_url'], 'leads may view the rights page' );
	}

	public function test_session_for_admin_without_member_record(): void {
		wp_set_current_user( $this->admin );
		$u = $this->call( 'GET', '/session' )->get_data()['data'];
		$this->assertFalse( $u['has_member_record'] );
		$this->assertTrue( $u['is_overall_lead'] );
		$this->assertNull( $u['region'] );
	}

	public function test_session_and_nonce_need_login(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->call( 'GET', '/session' )->get_status() );
		$this->assertSame( 401, $this->call( 'POST', '/nonce' )->get_status() );
		wp_set_current_user( $this->member );
		$nonce = $this->call( 'POST', '/nonce' )->get_data()['nonce'];
		$this->assertSame( 1, wp_verify_nonce( $nonce, 'wp_rest' ) );
	}

	public function test_notifications_feed(): void {
		$other = self::factory()->user->create( array( 'role' => 'acm_member' ) );
		$id    = Notifications::send( $this->member, 'events', 'New event <b>x</b>', 'Body', '/events/1' );
		$this->assertGreaterThan( 0, $id );
		$this->assertSame( 0, Notifications::send( $this->member, 'nonsense', 'x' ) );
		$this->assertGreaterThan( 0, Notifications::send( $this->member, 'system', 'Bad link', '', 'https://evil.example' ) );
		$foreign = Notifications::send( $other, 'events', 'Other' );

		wp_set_current_user( $this->member );
		$data = $this->call( 'GET', '/notifications' )->get_data();
		$this->assertSame( 2, $data['unread'] );
		$this->assertCount( 2, $data['data'] );
		$this->assertNull( $data['data'][0]['url'], 'external links are dropped' );
		$this->assertSame( 'New event x', $data['data'][1]['title'] );

		$this->assertFalse( $this->call( 'POST', "/notifications/{$foreign}/read" )->get_data()['ok'], 'cannot touch others' );
		$this->assertTrue( $this->call( 'POST', "/notifications/{$id}/read" )->get_data()['ok'] );
		$this->assertSame( 1, Notifications::unread( $this->member ) );
		$this->call( 'POST', '/notifications/read-all' );
		$this->assertSame( 0, Notifications::unread( $this->member ) );
		$this->assertSame( 1, Notifications::unread( $other ) );
	}

	public function test_app_page_created_with_shortcode(): void {
		$id = AppPage::ensure_page();
		$this->assertGreaterThan( 0, $id );
		$this->assertSame( $id, AppPage::ensure_page(), 'idempotent' );
		$this->assertStringContainsString( '[acm_app]', get_post( $id )->post_content );
		$this->assertSame( 'mitglieder', AppPage::page_path() );
	}

	public function test_deep_links_rewrite_to_the_page(): void {
		global $wp_rewrite;
		$this->set_permalink_structure( '/%postname%/' );
		$id = AppPage::ensure_page();
		AppPage::rewrite();
		$rules = $wp_rewrite->wp_rewrite_rules();
		$this->assertArrayHasKey( '^mitglieder/(.+?)/?$', $rules );
		$this->assertStringContainsString( 'page_id=' . $id, $rules['^mitglieder/(.+?)/?$'] );
	}

	public function test_template_and_config_for_member(): void {
		$id = AppPage::ensure_page();
		wp_set_current_user( $this->member );
		$this->go_to( get_permalink( $id ) );
		$this->assertSame( AIRSOFT_CLUB_DIR . 'templates/app.php', AppPage::template( 'theme.php' ) );

		$cfg = AppPage::config();
		$this->assertSame( 1, wp_verify_nonce( $cfg['nonce'], 'wp_rest' ) );
		$this->assertStringEndsWith( 'acm/v1', $cfg['api'] );
		$this->assertNotEmpty( $cfg['base'] );

		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'theme.php', AppPage::template( 'theme.php' ), 'other pages keep the theme' );
	}

	public function test_template_renders_message_without_access(): void {
		$id   = AppPage::ensure_page();
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		$this->go_to( get_permalink( $id ) );
		ob_start();
		try {
			include AIRSOFT_CLUB_DIR . 'templates/app.php';
		} finally {
			$html = (string) ob_get_clean();
		}
		$this->assertStringNotContainsString( 'window.acmApp', $html );
		$this->assertStringNotContainsString( 'acm-app', $html );
	}

	public function test_assets_read_from_manifest(): void {
		$dir  = AIRSOFT_CLUB_DIR . 'assets/app/';
		$file = $dir . 'manifest.json';
		$had  = is_readable( $file ) ? file_get_contents( $file ) : null;
		wp_mkdir_p( $dir );
		file_put_contents( $file, wp_json_encode( array( 'src/main.js' => array( 'file' => 'assets/main-abc.js', 'css' => array( 'assets/main-abc.css' ) ) ) ) );
		try {
			$assets = AppPage::assets();
			$this->assertStringEndsWith( 'assets/app/assets/main-abc.js', $assets['js'] );
			$this->assertStringEndsWith( 'assets/app/assets/main-abc.css', $assets['css'][0] );
		} finally {
			null === $had ? unlink( $file ) : file_put_contents( $file, $had );
		}
	}
}
