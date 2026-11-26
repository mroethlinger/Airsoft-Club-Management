<?php
/**
 * REST routes acm/v1 for members and avatars.
 *
 * @package AirsoftClub
 */

use AirsoftClub\Access\Assignments;
use AirsoftClub\Members\Avatars;
use AirsoftClub\Members\MemberRepository;
use AirsoftClub\Members\MemberService;
use AirsoftClub\Structure\Structure;

/** Members REST API. */
class MembersRestTest extends WP_UnitTestCase {

	private int $north;
	private int $south;
	private int $admin;
	private int $a;
	private int $b;
	private int $far;

	public function set_up(): void {
		parent::set_up();
		Structure::flush();
		Assignments::flush();
		do_action( 'rest_api_init' );
		$this->north = Structure::create_region( 'North' );
		$this->south = Structure::create_region( 'South' );
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->a     = $this->member( 'a', $this->north );
		$this->b     = $this->member( 'b', $this->north );
		$this->far   = $this->member( 'far', $this->south );
	}

	private function member( string $key, int $region ): int {
		$id = MemberService::create(
			$this->admin,
			array(
				'email'       => "{$key}@example.org",
				'first_name'  => ucfirst( $key ),
				'last_name'   => 'Tester',
				'region_id'   => $region,
				'send_invite' => false,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function call( string $method, string $route, ?array $json = null ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/acm/v1' . $route );
		if ( null !== $json ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $json ) );
		}
		return rest_get_server()->dispatch( $request );
	}

	public function test_routes_need_a_member(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->call( 'GET', '/me' )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->call( 'GET', '/members' )->get_status() );
	}

	public function test_me_and_update(): void {
		wp_set_current_user( $this->a );
		$res = $this->call( 'GET', '/me' );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'A', $res->get_data()['data']['first_name'] );

		$res = $this->call( 'PATCH', '/me', array( 'city' => 'Hannover', 'phone' => 'nope!' ) );
		$this->assertSame( 422, $res->get_status() );
		$res = $this->call( 'PATCH', '/me', array( 'city' => 'Hannover', 'status' => 'left', 'region_id' => $this->south ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( array( 'city' ), $res->get_data()['changed'] );
		$m = MemberRepository::find( $this->a );
		$this->assertSame( 'active', $m->status, 'status cannot be set by the member' );
		$this->assertSame( $this->north, $m->region_id, 'region cannot be set by the member' );
	}

	public function test_member_list_limited_to_own_region(): void {
		wp_set_current_user( $this->a );
		$ids = wp_list_pluck( $this->call( 'GET', '/members' )->get_data()['data'], 'id' );
		$this->assertContains( $this->b, $ids );
		$this->assertNotContains( $this->far, $ids );
		$this->assertSame( 200, $this->call( 'GET', '/members/' . $this->b )->get_status() );
		$this->assertSame( 404, $this->call( 'GET', '/members/' . $this->far )->get_status() );
		$this->assertSame( 404, $this->call( 'GET', '/members/999999' )->get_status() );
	}

	public function test_card_hides_unshared_contact_data(): void {
		MemberService::update_profile( $this->b, $this->b, array( 'phone' => '0511', 'street' => 'Main 1' ) );
		wp_set_current_user( $this->a );
		$card = $this->call( 'GET', '/members/' . $this->b )->get_data()['data'];
		$this->assertNull( $card['phone'] );
		$this->assertNull( $card['email'] );
		$this->assertArrayNotHasKey( 'street', $card );
	}

	private function image( int $w, int $h ): string {
		$path = wp_tempnam( 'acm-test.png' );
		$img  = imagecreatetruecolor( $w, $h );
		imagepng( $img, $path );
		imagedestroy( $img );
		return $path;
	}

	public function test_avatar_store_permissions_and_cleanup(): void {
		$tmp = $this->image( 1200, 800 );
		$this->assertWPError( Avatars::store( $this->b, $this->a, $tmp ), 'others may not change the photo' );
		$this->assertTrue( Avatars::store( $this->a, $this->a, $tmp ) );

		$name = MemberRepository::find( $this->a )->avatar_path;
		$path = Avatars::dir() . $name;
		$this->assertFileExists( $path );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{24}\.jpg$/', $name );
		$size = getimagesize( $path );
		$this->assertSame( 'image/jpeg', $size['mime'] );
		$this->assertLessThanOrEqual( 512, max( $size[0], $size[1] ) );
		$this->assertFileExists( dirname( Avatars::dir() ) . '/.htaccess' );

		$this->assertSame( $path, Avatars::path_for( $this->b, $this->a ), 'same region may see it' );
		$this->assertNull( Avatars::path_for( $this->far, $this->a ), 'other region may not' );
		$this->assertNull( Avatars::path_for( 0, $this->a ) );

		$text = wp_tempnam( 'acm-test.txt' );
		file_put_contents( $text, '<?php echo 1;' );
		$this->assertWPError( Avatars::store( $this->a, $this->a, $text ) );

		$this->assertTrue( Avatars::store( $this->a, $this->a, $this->image( 50, 50 ) ) );
		$this->assertFileDoesNotExist( $path, 'old photo replaced' );
		$new = Avatars::dir() . MemberRepository::find( $this->a )->avatar_path;
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $this->a );
		$this->assertFileDoesNotExist( $new, 'deleted with the user' );
	}

	public function test_avatar_routes(): void {
		wp_set_current_user( $this->a );
		$this->assertSame( 404, $this->call( 'GET', '/members/' . $this->a . '/avatar' )->get_status() );
		$this->assertSame( 422, $this->call( 'POST', '/members/' . $this->a . '/avatar' )->get_status() );
		$this->assertSame( 403, $this->call( 'DELETE', '/members/' . $this->b . '/avatar' )->get_status() );
		$this->assertSame( 200, $this->call( 'DELETE', '/members/' . $this->a . '/avatar' )->get_status() );
	}
}
