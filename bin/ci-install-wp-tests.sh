#!/usr/bin/env bash
# CI only: installs WordPress core and the test library against the service MySQL.
# Usage: bin/ci-install-wp-tests.sh <wp-version|latest>
set -euo pipefail
WPV="${1:-latest}"
CORE=/tmp/wordpress
LIB=/tmp/wordpress-tests-lib
if [ "$WPV" = latest ]; then
  WPV=$(curl -fsSL https://api.wordpress.org/core/version-check/1.7/ | jq -r '.offers[0].version')
fi
mkdir -p "$CORE" "$LIB"
curl -fsSL "https://wordpress.org/wordpress-$WPV.tar.gz" | tar -xz -C "$CORE" --strip-components=1
svn export -q --force "https://develop.svn.wordpress.org/tags/$WPV/tests/phpunit/includes" "$LIB/includes"
svn export -q --force "https://develop.svn.wordpress.org/tags/$WPV/tests/phpunit/data" "$LIB/data"
curl -fsSL "https://develop.svn.wordpress.org/tags/$WPV/wp-tests-config-sample.php" \
  | sed -e "s:dirname( __FILE__ ) . '/src/':'$CORE/':" -e "s:__DIR__ . '/src/':'$CORE/':" \
        -e "s/youremptytestdbnamehere/wp_test/" -e "s/yourusernamehere/root/" -e "s/yourpasswordhere/root/" \
        -e "s|localhost|127.0.0.1|" > "$LIB/wp-tests-config.php"
echo "WordPress $WPV ready"
