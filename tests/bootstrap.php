<?php
/**
 * PHPUnit bootstrap using the official WordPress test suite.
 * Needs WP_TESTS_DIR (default /tmp/wordpress-tests-lib) with a wp-tests-config.php pointing to a throw-away database.
 *
 * @package AirsoftClub
 */

$acm_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $acm_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/airsoft-club-management.php';
	}
);

require $acm_tests_dir . '/includes/bootstrap.php';
