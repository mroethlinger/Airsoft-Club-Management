#!/usr/bin/env bash
# Builds the plugin on the test VM (app + ZIP) and installs it on the test site (http://acm.test.lan).
# Usage: bash bin/vm-deploy.sh
set -euo pipefail
VM="${ACM_VM:-maik@192.168.11.12}"
SITE="${ACM_SITE_PATH:-/var/www/acm}"
BUILD="${ACM_VM_BUILD:-/home/maik/acm-dev/build-src}"
cd "$(dirname "$0")/.."
git ls-files -z -c -o --exclude-standard | tar --null -T - -cf - | ssh ${ACM_SSH_OPTS:-} "$VM" "rm -rf $BUILD && mkdir -p $BUILD && tar -xf - -C $BUILD"
ssh ${ACM_SSH_OPTS:-} "$VM" "set -e; cd $BUILD/app && npm install --no-audit --no-fund --silent && npm run build --silent; cd $BUILD && ZIP=\$(bash bin/build-zip.sh | tail -1) && wp plugin install \"\$ZIP\" --force --activate --path=$SITE"
