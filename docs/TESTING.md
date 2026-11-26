# Testing

## CI (GitHub Actions)

`.github/workflows/ci.yml` runs on every push and pull request:

- **lint**: `php -l` and PHPCS (WordPress Coding Standards, PHPCompatibilityWP).
- **phpunit**: official WordPress test suite against MariaDB, PHP 8.1 / WP 6.4 and PHP 8.4 / latest WP.
- **build**: Vite build of `app/` into `assets/app/`, then `bin/build-zip.sh`.
- **activate**: installs the built ZIP on a fresh WordPress with WP-CLI, activates it, fails on any PHP notice.

Tags `v*` trigger `release.yml`, which checks the tag against the plugin header version and attaches the ZIP to a GitHub release.

## Test VM

`bash bin/vm-test.sh [phpunit args]` copies the working tree to the test VM and runs PHPUnit there.

One-time setup on the VM (not automated, done by the owner):

1. Create an empty throw-away database `acm_wp_test` and a user with rights on it.
2. Write `~/acm-db.env` with `DB_USER=... DB_PASS=...` (never committed).
3. WordPress core must exist at `ACM_WP_PATH` (default `/var/www/acm`, the test site; tests use their own database and table prefix `acmtests_`).

`bin/vm-bootstrap-tests.sh` then downloads the matching test library into `/tmp/acm-wordpress-tests-lib` on first run.
