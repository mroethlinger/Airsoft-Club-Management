<?php
/**
 * Minimal PSR-4 autoloader so no Composer is needed on the server.
 *
 * @package AirsoftClub
 */

namespace AirsoftClub;

defined( 'ABSPATH' ) || exit;

/** Maps AirsoftClub\Foo\Bar to src/Foo/Bar.php. */
final class Autoloader {

	/**
	 * Registers the autoloader.
	 *
	 * @param string $base_dir Absolute path of the src/ folder with trailing slash.
	 */
	public static function register( string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $base_dir ): void {
				$prefix = __NAMESPACE__ . '\\';
				if ( ! str_starts_with( $class_name, $prefix ) ) {
					return;
				}
				$file = $base_dir . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
