<?php
/**
 * Migration contract.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub\Database;

defined( 'ABSPATH' ) || exit;

/** One numbered schema change. */
interface Migration {

	/** Monotonic integer version: 1, 2, 3, ... */
	public function version(): int;

	/**
	 * Applies the change.
	 *
	 * @param \wpdb $wpdb Database handle.
	 */
	public function up( \wpdb $wpdb ): void;
}
