<?php
/**
 * Central policy service.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Access;

use AirsoftClub\Structure\Structure;

defined( 'ABSPATH' ) || exit;

/**
 * Answers "may user X do Y in region/squad Z". The only source of truth for scoped rights:
 * every REST route and admin action asks here.
 *
 * Levels: administrators and overall leads 3 everywhere; regional leads 2 in their region;
 * squad leaders 1 in their squad (and in its region when no squad is given); everyone else 0.
 * Leads without required 2FA count as members (see TwoFactor).
 */
final class Access {

	/**
	 * Level of a user inside a region and/or squad.
	 *
	 * @param int      $user_id   User id.
	 * @param int|null $region_id Region id or null.
	 * @param int|null $squad_id  Squad id or null.
	 */
	public static function level_in( int $user_id, ?int $region_id = null, ?int $squad_id = null ): int {
		if ( $user_id <= 0 ) {
			return -1;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return Abilities::OVERALL_LEAD;
		}
		if ( TwoFactor::blocks( $user_id ) ) {
			return Abilities::MEMBER;
		}
		$assignments = Assignments::active_for( $user_id );
		foreach ( $assignments as $a ) {
			if ( Assignments::OVERALL_LEAD === $a->role ) {
				return Abilities::OVERALL_LEAD;
			}
		}
		if ( null !== $squad_id ) {
			$squad_region = Structure::region_of_squad( $squad_id );
			if ( null === $squad_region || ( null !== $region_id && $region_id !== $squad_region ) ) {
				return Abilities::MEMBER;
			}
			$region_id = $squad_region;
		}
		if ( null === $region_id ) {
			return self::max_level( $user_id );
		}
		foreach ( $assignments as $a ) {
			if ( Assignments::REGIONAL_LEAD === $a->role && $a->scope_id === $region_id ) {
				return Abilities::REGIONAL_LEAD;
			}
		}
		foreach ( $assignments as $a ) {
			if ( Assignments::SQUAD_LEAD !== $a->role ) {
				continue;
			}
			if ( null !== $squad_id ? $a->scope_id === $squad_id : Structure::region_of_squad( $a->scope_id ) === $region_id ) {
				return Abilities::SQUAD_LEAD;
			}
		}
		return Abilities::MEMBER;
	}

	/**
	 * Highest level the user holds anywhere.
	 *
	 * @param int $user_id User id.
	 */
	public static function max_level( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return -1;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return Abilities::OVERALL_LEAD;
		}
		if ( TwoFactor::blocks( $user_id ) ) {
			return Abilities::MEMBER;
		}
		$max = Abilities::MEMBER;
		$map = array(
			Assignments::OVERALL_LEAD  => Abilities::OVERALL_LEAD,
			Assignments::REGIONAL_LEAD => Abilities::REGIONAL_LEAD,
			Assignments::SQUAD_LEAD    => Abilities::SQUAD_LEAD,
		);
		foreach ( Assignments::active_for( $user_id ) as $a ) {
			$max = max( $max, $map[ $a->role ] ?? Abilities::MEMBER );
		}
		return $max;
	}

	/**
	 * May the user use the ability in the given region/squad?
	 * Signed-out users may do nothing; signed-in users need the member area capability.
	 *
	 * @param int      $user_id   User id.
	 * @param string   $ability   Ability key from Abilities::definitions().
	 * @param int|null $region_id Region id.
	 * @param int|null $squad_id  Squad id.
	 */
	public static function can( int $user_id, string $ability, ?int $region_id = null, ?int $squad_id = null ): bool {
		if ( $user_id <= 0 || ! user_can( $user_id, 'acm_member_area' ) ) {
			return false;
		}
		return self::level_in( $user_id, $region_id, $squad_id ) >= Abilities::min_level( $ability );
	}

	/**
	 * Does the user qualify for the ability somewhere (to show or hide menu entries)?
	 *
	 * @param int    $user_id User id.
	 * @param string $ability Ability key.
	 */
	public static function can_anywhere( int $user_id, string $ability ): bool {
		if ( $user_id <= 0 || ! user_can( $user_id, 'acm_member_area' ) ) {
			return false;
		}
		return self::max_level( $user_id ) >= Abilities::min_level( $ability );
	}

	/**
	 * Abilities the user holds somewhere.
	 *
	 * @param int $user_id User id.
	 * @return string[]
	 */
	public static function abilities_of( int $user_id ): array {
		return array_values( array_filter( array_keys( Abilities::definitions() ), static fn( $a ) => self::can_anywhere( $user_id, $a ) ) );
	}

	/**
	 * Regions in which the user reaches the level for an ability. Null means all regions.
	 * Member-level abilities in the own region are added by the members module (filter).
	 *
	 * @param int    $user_id User id.
	 * @param string $ability Ability key.
	 * @return int[]|null
	 */
	public static function regions_for( int $user_id, string $ability ): ?array {
		if ( $user_id <= 0 || ! user_can( $user_id, 'acm_member_area' ) ) {
			return array();
		}
		$min = Abilities::min_level( $ability );
		if ( self::max_level( $user_id ) >= Abilities::OVERALL_LEAD ) {
			return null;
		}
		if ( $min >= Abilities::OVERALL_LEAD ) {
			return array();
		}
		$regions = array();
		if ( ! TwoFactor::blocks( $user_id ) ) {
			foreach ( Assignments::active_for( $user_id ) as $a ) {
				if ( Assignments::REGIONAL_LEAD === $a->role && $min <= Abilities::REGIONAL_LEAD ) {
					$regions[] = $a->scope_id;
				} elseif ( Assignments::SQUAD_LEAD === $a->role && $min <= Abilities::SQUAD_LEAD ) {
					$regions[] = (int) Structure::region_of_squad( $a->scope_id );
				}
			}
		}
		/**
		 * Adds regions for member-level abilities (e.g. the member's own region).
		 *
		 * @param int[]  $regions Region ids.
		 * @param int    $user_id User id.
		 * @param string $ability Ability.
		 * @param int    $min     Minimum level.
		 */
		$regions = (array) apply_filters( 'airsoft_club_regions_for', $regions, $user_id, $ability, $min );
		$regions = array_values( array_unique( array_filter( array_map( 'intval', $regions ) ) ) );
		sort( $regions );
		return $regions;
	}
}
