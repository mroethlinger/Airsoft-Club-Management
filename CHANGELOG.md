# Changelog

## Unreleased

### Phase 1 – Foundation
- Numbered migrations (`acm_db_version`), core tables: audit log, regions, squads, role assignments.
- WordPress roles: club member, club lead (marker), board, treasurer, cash auditor.
- Rights matrix (ported from the Laravel app) with floors, editable by the board.
- Scoped lead assignments (overall lead, regional lead, squad leader, deputies). Squad leader rights are suspended while a region runs without squads.
- `Access` service as the single source of truth for scoped rights.
- Optional two-factor requirement for leads and board roles (needs the "Two Factor" plugin; on by default, not enforced without the plugin).
- Club settings, branding colours as CSS variables, imprint data, switch for association functions.
- Admin pages: rights, regions & squads, settings, audit log.
- Updates from GitHub releases; opt-in data removal on uninstall.

### Phase 0 – Tooling
- Plugin skeleton, PSR-4 autoloader, PHPCS (WordPress rules), PHPUnit with the WordPress test suite.
- CI: lint, tests (PHP 8.1/WP 6.4 and PHP 8.4/latest), app build, ZIP check, activation on a fresh WordPress.
- Release ZIP on `v*` tags; test VM scripts (`bin/vm-test.sh`, `bin/vm-deploy.sh`).
