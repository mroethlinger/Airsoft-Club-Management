#!/usr/bin/env bash
# Sync the working tree to the test VM and run PHPUnit there (real MariaDB, official WP test suite).
# Usage: bash bin/vm-test.sh [phpunit args]   (away from the home network: ACM_SSH_OPTS="-J root@VPS" bash bin/vm-test.sh)
# VM prerequisites (one-time, see docs/TESTING.md): WordPress core in $ACM_WP_PATH, ~/acm-db.env, empty DB acm_wp_test.
set -euo pipefail
VM="${ACM_VM:-maik@192.168.11.12}"
DEST="${ACM_VM_DEST:-/home/maik/acm-dev/airsoft-club-management}"

cd "$(dirname "$0")/.."
tar --exclude=.git --exclude=vendor --exclude=node_modules --exclude=build -cf - . \
  | ssh ${ACM_SSH_OPTS:-} "$VM" "mkdir -p $DEST && tar -xf - -C $DEST"
ssh ${ACM_SSH_OPTS:-} "$VM" "cd $DEST && [ -d vendor ] || composer install --no-interaction -q; bash bin/vm-bootstrap-tests.sh; vendor/bin/phpunit $*"
