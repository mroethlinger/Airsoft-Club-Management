#!/usr/bin/env bash
# Builds build/airsoft-club-management.zip from the working tree (expects app already built into assets/app).
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=airsoft-club-management
rm -rf build && mkdir -p "build/$SLUG"
rsync -a \
  --exclude='.git*' --exclude='.github' --exclude='.editorconfig' --exclude='/build' --exclude='/tests' --exclude='/bin' \
  --exclude='/docs' --exclude='/app' --exclude='/theme' --exclude='/node_modules' --exclude='/vendor' \
  --exclude='composer.*' --exclude='phpunit.xml.dist' --exclude='phpcs.xml.dist' --exclude='.phpunit.result.cache' \
  ./ "build/$SLUG/"
(cd build && if command -v zip >/dev/null; then zip -rq "$SLUG.zip" "$SLUG"; else python3 -c "import shutil,sys; shutil.make_archive(sys.argv[1], 'zip', '.', sys.argv[1])" "$SLUG"; fi)
echo "build/$SLUG.zip"
