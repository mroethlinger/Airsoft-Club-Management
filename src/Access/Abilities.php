<?php
/**
 * Rights matrix for scoped abilities.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Access;

use AirsoftClub\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * The rights matrix: the lowest lead level that may use each ability.
 * Levels, from lowest to highest: member (0) < squad lead (1) < regional lead (2) < overall lead (3).
 * A level applies inside the scope of the assignment. "floor" is the lowest level the matrix may assign.
 * Defaults are code; changes by the board are stored in option acm_rights_matrix.
 */
final class Abilities {

	public const OPTION = 'acm_rights_matrix';

	public const MEMBER        = 0;
	public const SQUAD_LEAD    = 1;
	public const REGIONAL_LEAD = 2;
	public const OVERALL_LEAD  = 3;

	/**
	 * Per-request cache.
	 *
	 * @var array<string,int>|null
	 */
	private static ?array $matrix = null;

	/**
	 * Level number => label.
	 *
	 * @return array<int,string>
	 */
	public static function levels(): array {
		return array(
			self::MEMBER        => __( 'Member', 'airsoft-club-management' ),
			self::SQUAD_LEAD    => __( 'Squad leader', 'airsoft-club-management' ),
			self::REGIONAL_LEAD => __( 'Regional lead', 'airsoft-club-management' ),
			self::OVERALL_LEAD  => __( 'Overall lead', 'airsoft-club-management' ),
		);
	}

	/**
	 * Group key => label.
	 *
	 * @return array<string,string>
	 */
	public static function groups(): array {
		return array(
			'events'       => __( 'Events', 'airsoft-club-management' ),
			'members'      => __( 'Members', 'airsoft-club-management' ),
			'equipment'    => __( 'Equipment', 'airsoft-club-management' ),
			'applications' => __( 'Applicants', 'airsoft-club-management' ),
		);
	}

	/**
	 * Ability => definition.
	 *
	 * @return array<string,array{group:string,label:string,description:string,default:int,floor:int}>
	 */
	public static function definitions(): array {
		return array(
			'events.propose'         => array(
				'group'       => 'events',
				'label'       => __( 'Propose events', 'airsoft-club-management' ),
				'description' => __( 'Suggest an event that a lead approves before it is published.', 'airsoft-club-management' ),
				'default'     => self::MEMBER,
				'floor'       => self::MEMBER,
			),
			'events.approve'         => array(
				'group'       => 'events',
				'label'       => __( 'Approve event proposals', 'airsoft-club-management' ),
				'description' => __( 'Accept or reject proposed events in the own area.', 'airsoft-club-management' ),
				'default'     => self::SQUAD_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'events.publish_global'  => array(
				'group'       => 'events',
				'label'       => __( 'Publish events for all regions', 'airsoft-club-management' ),
				'description' => __( 'Make an approved event visible to every region.', 'airsoft-club-management' ),
				'default'     => self::SQUAD_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'events.create_regional' => array(
				'group'       => 'events',
				'label'       => __( 'Create regional events', 'airsoft-club-management' ),
				'description' => __( 'Create events directly (without approval) for the own region.', 'airsoft-club-management' ),
				'default'     => self::REGIONAL_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'events.create_global'   => array(
				'group'       => 'events',
				'label'       => __( 'Create club-wide events', 'airsoft-club-management' ),
				'description' => __( 'Create events for the whole club.', 'airsoft-club-management' ),
				'default'     => self::OVERALL_LEAD,
				'floor'       => self::REGIONAL_LEAD,
			),
			'locations.manage'       => array(
				'group'       => 'events',
				'label'       => __( 'Manage locations', 'airsoft-club-management' ),
				'description' => __( 'Create and edit playing fields and meeting places.', 'airsoft-club-management' ),
				'default'     => self::REGIONAL_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'attendance.record'      => array(
				'group'       => 'events',
				'label'       => __( 'Record attendance', 'airsoft-club-management' ),
				'description' => __( 'Check members in at events (QR scan).', 'airsoft-club-management' ),
				'default'     => self::SQUAD_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'members.create'         => array(
				'group'       => 'members',
				'label'       => __( 'Add members', 'airsoft-club-management' ),
				'description' => __( 'Invite new members into the own area.', 'airsoft-club-management' ),
				'default'     => self::SQUAD_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'members.delete'         => array(
				'group'       => 'members',
				'label'       => __( 'Remove members', 'airsoft-club-management' ),
				'description' => __( 'Move members to the former members archive.', 'airsoft-club-management' ),
				'default'     => self::REGIONAL_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'catalogs.manage'        => array(
				'group'       => 'members',
				'label'       => __( 'Manage catalogs', 'airsoft-club-management' ),
				'description' => __( 'Edit selection lists such as camouflage patterns.', 'airsoft-club-management' ),
				'default'     => self::SQUAD_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'replicas.inspect'       => array(
				'group'       => 'equipment',
				'label'       => __( 'Inspect replicas', 'airsoft-club-management' ),
				'description' => __( 'Record replica inspections (never for own replicas).', 'airsoft-club-management' ),
				'default'     => self::SQUAD_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'applications.claim'     => array(
				'group'       => 'applications',
				'label'       => __( 'Handle applications', 'airsoft-club-management' ),
				'description' => __( 'Take over an application and invite the applicant to an interview.', 'airsoft-club-management' ),
				'default'     => self::SQUAD_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
			'applications.decide'    => array(
				'group'       => 'applications',
				'label'       => __( 'Decide on applications', 'airsoft-club-management' ),
				'description' => __( 'Accept or reject applicants after the vote, break ties.', 'airsoft-club-management' ),
				'default'     => self::REGIONAL_LEAD,
				'floor'       => self::SQUAD_LEAD,
			),
		);
	}

	/**
	 * Current matrix: ability => minimum level (defaults overlaid with valid saved values).
	 *
	 * @return array<string,int>
	 */
	public static function matrix(): array {
		if ( null !== self::$matrix ) {
			return self::$matrix;
		}
		$saved  = get_option( self::OPTION, array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$matrix = array();
		foreach ( self::definitions() as $key => $def ) {
			$level          = isset( $saved[ $key ] ) ? (int) $saved[ $key ] : $def['default'];
			$matrix[ $key ] = self::valid_level( $level, $def['floor'] ) ? $level : $def['default'];
		}
		self::$matrix = $matrix;
		return $matrix;
	}

	/**
	 * Minimum level for an ability.
	 *
	 * @param string $ability Ability key.
	 * @throws \InvalidArgumentException For unknown abilities (programming error).
	 */
	public static function min_level( string $ability ): int {
		$matrix = self::matrix();
		if ( ! isset( $matrix[ $ability ] ) ) {
			throw new \InvalidArgumentException( esc_html( "Unknown ability {$ability}" ) );
		}
		return $matrix[ $ability ];
	}

	/**
	 * Saves changed levels. Unknown abilities and levels below the floor are ignored.
	 *
	 * @param array<string,mixed> $input Ability => level.
	 * @return string[] Abilities that changed.
	 */
	public static function save( array $input ): array {
		$current = self::matrix();
		$defs    = self::definitions();
		$store   = array();
		$changed = array();
		foreach ( $defs as $key => $def ) {
			$level = array_key_exists( $key, $input ) ? (int) $input[ $key ] : $current[ $key ];
			if ( ! self::valid_level( $level, $def['floor'] ) ) {
				$level = $current[ $key ];
			}
			if ( $level !== $current[ $key ] ) {
				$changed[] = $key;
			}
			if ( $level !== $def['default'] ) {
				$store[ $key ] = $level;
			}
		}
		update_option( self::OPTION, $store, false );
		self::flush();
		if ( $changed ) {
			AuditLog::add( 'rights.updated', 'rights', 0, array( 'abilities' => $changed ) );
		}
		return $changed;
	}

	/** Clears the per-request cache. */
	public static function flush(): void {
		self::$matrix = null;
	}

	/**
	 * Whether a level is known and not below the floor.
	 *
	 * @param int $level Level.
	 * @param int $floor Floor.
	 */
	private static function valid_level( int $level, int $floor ): bool {
		return $level >= $floor && $level <= self::OVERALL_LEAD;
	}
}
