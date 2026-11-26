<?php
/**
 * GDPR export and erasure through the WordPress privacy tools.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Members;

use AirsoftClub\Structure\Structure;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the member data into Tools > Export/Erase Personal Data.
 * Active members are not erased automatically (membership is a contract); the board ends the
 * membership first, then the erasure removes the record.
 */
final class Privacy {

	/** Registers hooks. */
	public static function hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	/**
	 * Adds the exporter.
	 *
	 * @param array<string,array<string,mixed>> $exporters Exporters.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_exporter( $exporters ) {
		$exporters['airsoft-club-management'] = array(
			'exporter_friendly_name' => __( 'Airsoft club membership', 'airsoft-club-management' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Adds the eraser.
	 *
	 * @param array<string,array<string,mixed>> $erasers Erasers.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_eraser( $erasers ) {
		$erasers['airsoft-club-management'] = array(
			'eraser_friendly_name' => __( 'Airsoft club membership', 'airsoft-club-management' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exports the member record of the email's user.
	 *
	 * @param string $email Email.
	 * @return array{data:array<int,mixed>,done:bool}
	 */
	public static function export( $email ): array {
		$user   = get_user_by( 'email', (string) $email );
		$member = $user ? MemberRepository::find( (int) $user->ID ) : null;
		if ( ! $member ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$region = Structure::region( $member->region_id );
		$squad  = $member->squad_id ? Structure::squad( $member->squad_id ) : null;
		$fields = array(
			'member_number'       => __( 'Membership number', 'airsoft-club-management' ),
			'status'              => __( 'Status', 'airsoft-club-management' ),
			'first_name'          => __( 'First name', 'airsoft-club-management' ),
			'last_name'           => __( 'Last name', 'airsoft-club-management' ),
			'nickname'            => __( 'Nickname', 'airsoft-club-management' ),
			'name_display'        => __( 'Name display', 'airsoft-club-management' ),
			'birth_date'          => __( 'Birth date', 'airsoft-club-management' ),
			'phone'               => __( 'Phone', 'airsoft-club-management' ),
			'share_phone'         => __( 'Phone shared with members', 'airsoft-club-management' ),
			'share_email'         => __( 'Email shared with members', 'airsoft-club-management' ),
			'street'              => __( 'Street', 'airsoft-club-management' ),
			'postal_code'         => __( 'Postcode', 'airsoft-club-management' ),
			'city'                => __( 'City', 'airsoft-club-management' ),
			'country'             => __( 'Country', 'airsoft-club-management' ),
			'can_drive'           => __( 'Can drive', 'airsoft-club-management' ),
			'vehicle_seats'       => __( 'Free seats', 'airsoft-club-management' ),
			'vehicle_description' => __( 'Vehicle', 'airsoft-club-management' ),
			'needs_ride'          => __( 'Needs a ride', 'airsoft-club-management' ),
			'mobility_notes'      => __( 'Mobility notes', 'airsoft-club-management' ),
			'combat_role'         => __( 'Combat role', 'airsoft-club-management' ),
			'play_style'          => __( 'Play style', 'airsoft-club-management' ),
			'joined_at'           => __( 'Joined', 'airsoft-club-management' ),
			'left_at'             => __( 'Left', 'airsoft-club-management' ),
		);
		$data   = array();
		foreach ( $fields as $key => $label ) {
			$value = $member->$key;
			if ( is_bool( $value ) ) {
				$value = $value ? __( 'yes', 'airsoft-club-management' ) : __( 'no', 'airsoft-club-management' );
			}
			if ( null !== $value && '' !== $value ) {
				$data[] = array(
					'name'  => $label,
					'value' => (string) $value,
				);
			}
		}
		foreach ( $member->social as $network => $handle ) {
			$data[] = array(
				'name'  => ucfirst( (string) $network ),
				'value' => (string) $handle,
			);
		}
		$data[] = array(
			'name'  => __( 'Region', 'airsoft-club-management' ),
			'value' => $region ? $region->name : '',
		);
		if ( $squad ) {
			$data[] = array(
				'name'  => __( 'Squad', 'airsoft-club-management' ),
				'value' => $squad->name,
			);
		}
		return array(
			'data' => array(
				array(
					'group_id'    => 'airsoft-club-member',
					'group_label' => __( 'Club membership', 'airsoft-club-management' ),
					'item_id'     => 'member-' . $member->user_id,
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	/**
	 * Erases the member record of a former member; active memberships are retained.
	 *
	 * @param string $email Email.
	 * @return array{items_removed:bool,items_retained:bool,messages:string[],done:bool}
	 */
	public static function erase( $email ): array {
		$user   = get_user_by( 'email', (string) $email );
		$member = $user ? MemberRepository::find( (int) $user->ID ) : null;
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
		if ( ! $member ) {
			return $result;
		}
		if ( MemberRepository::STATUS_ACTIVE === $member->status ) {
			$result['items_retained'] = true;
			$result['messages'][]     = __( 'The membership is still active. End the membership first, then run the erasure again.', 'airsoft-club-management' );
			return $result;
		}
		MemberRepository::delete( $member->user_id );
		$result['items_removed'] = true;
		return $result;
	}
}
