# Changelog

## Unreleased

### Phase 3 – Member area shell
- Vue 3 member area (vue-router, vue-i18n, Tailwind 4) mounted on the page "mitglieder" with its own minimal template; deep links via a rewrite rule.
- REST client with WordPress cookie auth, `X-WP-Nonce` and one automatic nonce refresh (`POST /acm/v1/nonce`).
- `GET /acm/v1/session` (user, functions, abilities, branding); in-app notification feed with bell (`/notifications`, read, read-all), pruned after 90 days.
- Navigation as in the Laravel app: desktop top bar with "Verwaltung" and avatar menu, phone tab bar with menu sheet.
- Dashboard (events top, tasks for leads, region/squad/functions bottom), member list and player card, account pages (profile with name display and photo, contact and sharing, mobility, security).
- Events, rides and feedback show a placeholder until their phases.

### Phase 2 – Members (core)
- Member records linked to WordPress users, sequential membership numbers, adults only.
- Profile rules ported from the Laravel app (name display, contact sharing, address, social/ASVZ, mobility, combat role, play style).
- Leads manage members in their scope; move between regions/squads; end membership and reactivate.
- Player card and member list filtered by viewer; former members archive with daily purge after 90 days.
- Invitation by WordPress set-password mail; admin page "Members"; privacy exporter and eraser.
- Avatars in a private folder (re-encoded JPEG, max. 512 px, random names), delivered only after the visibility check.
- REST `acm/v1`: `/me` (read, update), `/members`, `/members/{id}`, `/members/{id}/avatar` (get, upload, delete).
- Not yet: consent documents, fee tiers (phase 9), member area UI (phase 3).

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
