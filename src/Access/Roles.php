<?php
/**
 * WordPress roles and global capabilities.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Custom WordPress roles and their (unscoped) capabilities. Single source of truth, also feeds the rights page.
 * Scoped lead rights (overall/regional/squad lead) are not WordPress capabilities: see Assignments and Access.
 */
final class Roles {

	public const OPTION  = 'acm_roles_version';
	public const VERSION = '1';

	/**
	 * Capability => description.
	 *
	 * @return array<string,string>
	 */
	public static function capabilities(): array {
		return array(
			'acm_member_area'      => __( 'Open the member area.', 'airsoft-club-management' ),
			'acm_manage_settings'  => __( 'Edit club settings, branding and imprint data.', 'airsoft-club-management' ),
			'acm_manage_structure' => __( 'Create, rename and delete regions and squads; appoint leads.', 'airsoft-club-management' ),
			'acm_manage_rights'    => __( 'Change the rights matrix.', 'airsoft-club-management' ),
			'acm_view_rights'      => __( 'View the rights overview.', 'airsoft-club-management' ),
			'acm_view_audit'       => __( 'Read the audit log.', 'airsoft-club-management' ),
		);
	}

	/**
	 * Role slug => definition.
	 *
	 * @return array<string,array{label:string,description:string,caps:string[]}>
	 */
	public static function roles(): array {
		return array(
			'acm_member'       => array(
				'label'       => __( 'Club member', 'airsoft-club-management' ),
				'description' => __( 'Signed-in member: own profile, events, carpools.', 'airsoft-club-management' ),
				'caps'        => array( 'acm_member_area' ),
			),
			'acm_lead'         => array(
				'label'       => __( 'Club lead', 'airsoft-club-management' ),
				'description' => __( 'Member with at least one lead function. Where the rights apply is set by the lead assignments, not by this role.', 'airsoft-club-management' ),
				'caps'        => array( 'acm_member_area', 'acm_view_rights' ),
			),
			'acm_board'        => array(
				'label'       => __( 'Board', 'airsoft-club-management' ),
				'description' => __( 'Runs the association: settings, structure, rights, audit log.', 'airsoft-club-management' ),
				'caps'        => array( 'acm_member_area', 'acm_manage_settings', 'acm_manage_structure', 'acm_manage_rights', 'acm_view_rights', 'acm_view_audit' ),
			),
			'acm_treasurer'    => array(
				'label'       => __( 'Treasurer', 'airsoft-club-management' ),
				'description' => __( 'Keeps fees and the cash book.', 'airsoft-club-management' ),
				'caps'        => array( 'acm_member_area', 'acm_view_rights' ),
			),
			'acm_cash_auditor' => array(
				'label'       => __( 'Cash auditor', 'airsoft-club-management' ),
				'description' => __( 'Read-only access to the cash book.', 'airsoft-club-management' ),
				'caps'        => array( 'acm_member_area', 'acm_view_rights' ),
			),
		);
	}

	/** Creates or re-syncs roles; administrators get every capability. */
	public static function install(): void {
		foreach ( self::roles() as $slug => $def ) {
			remove_role( $slug );
			add_role( $slug, $def['label'], array_fill_keys( $def['caps'], true ) + array( 'read' => true ) );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_keys( self::capabilities() ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		update_option( self::OPTION, self::VERSION, false );
	}

	/** Re-syncs roles after a plugin update. */
	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION ) !== self::VERSION ) {
			self::install();
		}
	}

	/** Removes roles and capabilities. */
	public static function uninstall(): void {
		foreach ( array_keys( self::roles() ) as $slug ) {
			remove_role( $slug );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_keys( self::capabilities() ) as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
		delete_option( self::OPTION );
	}
}
