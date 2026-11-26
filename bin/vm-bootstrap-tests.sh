#!/usr/bin/env bash
# Runs ON the VM. Prepares the WordPress test library once; reads DB_USER/DB_PASS from ~/acm-db.env.
# Uses its own library folder and its own throw-away database (acm_wp_test), separate from other projects.
set -euo pipefail
LIB="${WP_TESTS_DIR:-/tmp/acm-wordpress-tests-lib}"
WP_PATH="${ACM_WP_PATH:-/var/www/kita}"
[ -f "$LIB/wp-tests-config.php" ] && exit 0

# shellcheck disable=SC1090
source <(tr ' ' '\n' < ~/acm-db.env)
WPV=$(wp core version --path="$WP_PATH")
svn export -q --force "https://develop.svn.wordpress.org/tags/$WPV/tests/phpunit/includes" "$LIB/includes"
svn export -q --force "https://develop.svn.wordpress.org/tags/$WPV/tests/phpunit/data" "$LIB/data"
cat > "$LIB/wp-tests-config.php" <<PHP
<?php
define( 'ABSPATH', '$WP_PATH/' );
define( 'WP_DEBUG', true );
define( 'DB_NAME', 'acm_wp_test' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASS' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
\$table_prefix = 'acmtests_';
PHP
