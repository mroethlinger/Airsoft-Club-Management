# Airsoft Club Management – project plan

Status: draft 1 (2026-11-26). Owner: Maik Röthlinger.

This plan describes how to build **Airsoft Club Management**, a standalone WordPress plugin that combines

- the club tooling of the existing **Airsoft-Verwaltung** web app (Laravel 13 + Vue 3, private repo `mroethlinger/Airsoft-Verwaltung`, live as a test at `cis-verwaltung.xabasul.de`), and
- the association tooling of the **Kita-Verein** WordPress plugin (members, fees, cash book, donation receipts, forms, public pages).

The new plugin is **independent**: it does not require Kita-Verein or the Laravel app at runtime. Code from both projects may be copied and adapted (both belong to the same author; Kita-Verein code is GPL-2.0-or-later, which this plugin will use as well), but there is no shared package and no dependency between the repositories.

---

## 1. Goals

1. One WordPress installation runs the complete club: public website, member area, club management and the association's books.
2. Installable by any airsoft club on ordinary shared hosting (Plesk, cPanel): no SSH, no Composer, no Node.js, no cron required on the server.
3. Updates through the normal WordPress plugin update mechanism.
4. German user interface first (formal translations prepared for English), English source code, comments, docs and commit messages.
5. Data protection by design (GDPR / DSGVO): adults only, minimal personal data, member-controlled visibility.

## 2. Non-goals (for version 1.0)

- Online payments (PayPal, Stripe, SEPA direct debit). Fees and donations are managed and booked only.
- Multi-tenancy (several clubs in one installation). One installation = one club.
- Native Android/iOS apps. The member area is a PWA; a Capacitor wrapper can follow later.
- DATEV export and a full GoBD procedural documentation (same limits as Kita-Verein).
- Minors as members or any data about children.

## 3. Basic decisions

| # | Decision |
|---|---|
| D1 | Plugin slug `airsoft-club-management`, namespace `AirsoftClub`, text domain `airsoft-club-management`, table prefix `{wp}acm_`, capability prefix `acm_`, option prefix `acm_`, REST namespace `acm/v1`. |
| D2 | PHP ≥ 8.1, WordPress ≥ 6.4, MySQL ≥ 8.0 or MariaDB ≥ 10.6. Developed on PHP 8.4 / WP 6.x / MariaDB 11.8. |
| D3 | Repository root = plugin folder. Release ZIP built by GitHub Actions on `v*` tags; tests, `bin/`, `docs/` and front-end sources are excluded from the ZIP, built assets are included. |
| D4 | No Composer on the server. Own PSR-4 autoloader. Third-party PHP libraries that must ship (e.g. Web Push) are bundled under a prefixed namespace (PHP-Scoper) to avoid conflicts with other plugins. |
| D5 | Own migration runner (integer DB version in option `acm_db_version`, `dbDelta` for schema), runs on activation and on `plugins_loaded` when behind. |
| D6 | Background work via **Action Scheduler** (bundled, as WooCommerce does) with WP-Cron as the trigger. Same model as the Laravel app's web-triggered scheduler: no system cron needed, optional real cron documented. |
| D7 | Public website = WordPress itself (block editor, block theme). SEO either by the plugin's built-in basics or by Yoast / Rank Math (plugin switches its own output off when one is active). |
| D8 | Member area = the existing **Vue 3 single-page app**, ported to talk to the WordPress REST API (`acm/v1`) with cookie authentication and `X-WP-Nonce`. It is mounted on one WordPress page via a shortcode/block (`[acm_app]`), with its own client-side routes below that page. |
| D9 | Administration for the board and treasurer (cash book, fees, receipts, forms, settings) stays in classic WordPress admin screens (as in Kita-Verein). Club leads (regional/squad leads) work in the member area, not in wp-admin. |
| D10 | Authentication = WordPress users. Two-factor authentication via an established plugin (recommended: "Two Factor"); the plugin can require 2FA for leads and board roles and refuses lead functions without it. |
| D11 | Rights = WordPress roles + custom capabilities **plus scoped role assignments** (club / region / squad) in an own table, because WordPress capabilities have no scope. One central `Access` service answers "may user X do Y on object Z". |
| D12 | Repository is **public**: no secrets, no real member data, no demo passwords in the repo. Demo data uses invented names and invented addresses that cannot sign in. |
| D13 | License: code GPL-2.0-or-later. Club logos and artwork are not part of the repo (uploaded per installation). |

## 4. Feature map: where each function comes from

Legend: **L** = port from Airsoft-Verwaltung (Laravel/Vue), **K** = adapt from Kita-Verein, **W** = WordPress core or a recommended plugin, **N** = new.

| Area | Function | Source |
|---|---|---|
| Foundation | Settings, branding (name, short name, logo, colours), imprint data | L + K |
| | Roles, rights matrix with readable names and descriptions | L + K |
| | Scoped assignments (overall lead, regional lead, squad leader, deputies) | L |
| | Structure: regions, squads, per-region squad switch, function switch | L |
| | Audit log | L + K |
| | Updates | K |
| Members | Member records, mobility/address, contact sharing, social/Discord, ASVZ name, combat role, play style | L |
| | Name display (full / abbreviated / nickname) | L |
| | Avatars (private storage, re-encoded) | L |
| | Player card with privacy filter | L |
| | Invitations, consent documents (DSGVO PDFs) | L |
| | Former members archive (90 days, reactivation) | L |
| | Membership numbers, fee tiers, fee lists, reminders | K |
| | Data export, deletion request | K |
| Applicants | Public application form (honeypot, min-fill time, throttle, min age 18, Discord hint) | L |
| | Reviews (stars + one comment per member), handling by squad leader | L |
| | Interview invitation (in person / WhatsApp / phone / Discord), mail with accept/decline/other date, 6-digit PIN | L |
| | Secret vote, regional lead decides ties, retention 6 months | L |
| | Convert to member with invitation | L |
| Events | Events as custom post type, categories/types, internal/external, locations with GPS | L + K |
| | Event proposals with approval, multi-region events | L |
| | Registration with capacity, waiting list, deadline, transport choice | L + K |
| | Public calendar, iCal feed, schema.org, reminders | K |
| | QR check-in, offline queue, attendance statistics | L |
| Carpools | Rides per event, driver accepts only, seekers by distance, detour km, meeting point vs pickup, printable pickup plan | L |
| | Routing (Nominatim + OSRM, cache, request budget) | L |
| Gear | Equipment, replicas (manufacturer serial, internal id, energy classes), inspections, camouflage catalog | L |
| Leadership | Tasks page, Kummerkasten (anonymous, case handling, report on close), archive | L |
| Notifications | In-app feed, mail, Web Push, per-category preferences | L |
| Association | Cash book (append-only, hash chain, reversals, period locks, receipts, reports, CSV/XLSX/PDF) | K |
| | Donation info page, donation receipts (Zuwendungsbestätigung), donation barometer | K |
| | Printable forms and manual PDF | K |
| Website | Block theme in club look, one-page home, imprint/privacy templates, search engine visibility modes, meta defaults | K + L |
| | Pages, menus, media, editor for beginners and HTML for pros | W |
| | SEO | K + W |
| Operations | Demo data seeder (WP-CLI command and admin button) | L + K |
| | Data import from the Laravel app | N |

## 5. Architecture

### 5.1 Folder layout

```
airsoft-club-management.php      plugin header, bootstrap
uninstall.php                    data kept unless opt-in removal is set
src/
  Plugin.php                     wiring, hooks
  Autoloader.php
  Database/                      Migrator, migrations/NNN_*.php
  Access/                        Roles, Capabilities, ScopedAssignments, Access (policy service)
  Settings/ Branding/ Structure/ Audit/
  Members/ Applicants/ Events/ Attendance/ Carpools/ Routing/
  Gear/ Feedback/ Tasks/ Notifications/ Push/
  Cashbook/ Fees/ Donations/ Forms/ Pages/ Privacy/ Seo/
  Rest/                          one controller per area, namespace acm/v1
  Admin/                         wp-admin screens (board, treasurer, settings)
  Front/                         shortcodes, blocks, [acm_app] mount, PWA routes
  Cli/                           WP-CLI commands (demo seed, import, maintenance)
  Updater/
app/                             Vue 3 source of the member area (built with Vite in CI)
assets/                          built JS/CSS (committed only in release ZIP), icons, fonts
languages/                       .pot, de_DE .po/.mo/.l10n.php
theme/airsoft-club-theme/        optional block theme, released as its own ZIP
lib/                             bundled, namespace-prefixed third-party code
tests/                           PHPUnit (WP test suite), JS unit tests
bin/ docs/ .github/workflows/
```

### 5.2 Data model (custom tables, prefix `acm_`)

Users stay in `wp_users`; club data is linked by `user_id`.

- `members` (user_id, member number, status, name display, birth date, address, postcode, city, country, phone, share flags, social JSON, combat role, play style, region_id, squad_id, joined_at, left_at, avatar path, notification prefs JSON)
- `regions` (name, squads_enabled), `squads` (region_id, name)
- `role_assignments` (user_id, role, scope_type, scope_id, is_deputy, suspended)
- `audit_log`
- `consents` / `consent_documents`, `invitations`
- `applications`, `application_reviews`, `application_votes`, `application_interviews`
- Events: custom post type `acm_event` + taxonomy `acm_event_type`; meta for dates, kind, external link, regions, location, capacity, deadline, proposal status
- `locations` (name, address, GPS, website, region)
- `event_registrations` (status, waitlist position, transport, pickup mode, cancel token)
- `attendance` (event, user, recorded_at, client_uuid, recorded_by)
- `rides`, `ride_requests`, `routing_cache` (or transients)
- `equipment`, `replicas`, `replica_inspections`, `gear_options`
- `feedback`, `feedback_comments`
- `notifications` (in-app feed), `push_subscriptions`
- From Kita-Verein: `fee_tiers`, `fees`, `cashbook_entries`, `cashbook_periods`, `cashbook_projects`, `receipts`, `donation_receipts`, `downloads`

All tables get created/changed only through numbered migrations. Money in cents (integer). Dates in UTC, shown in the site timezone.

### 5.3 Access control

- WordPress roles: `acm_member`, `acm_applicant` (optional), `acm_lead` (marker role for leads), `acm_board`, `acm_treasurer`, `acm_cash_auditor`; administrators get everything.
- Scoped assignments decide where a lead's rights apply: overall lead (club), regional lead (+ deputy) per region, squad leader (+ deputy) per squad. Squad-leader rights are suspended while squads are disabled for the region.
- The rights matrix from the Laravel app (`config/permissions.php`) becomes a PHP array in `Access/Capabilities.php`; the rights page renders names and short descriptions from it.
- Every REST route declares a `permission_callback` that calls `Access::can($user, $ability, $object)`. Tests cover each ability for each role.

### 5.4 Member area (PWA)

- Vue 3 + vue-i18n + Tailwind 4, built by Vite in CI. The API client switches from `/api/...` to `wp-json/acm/v1/...` and sends `X-WP-Nonce`.
- Nonce refresh: a small endpoint returns a fresh nonce; the app retries once on `rest_cookie_invalid_nonce`.
- Mounted by `[acm_app]` on one page (default slug `/mitglieder/`); WordPress rewrite rule sends `/mitglieder/*` to that page so deep links work.
- PWA: manifest and service worker served from the site root via rewrite rules (`/acm-sw.js`, scope `/`), so installation and push work. Offline check-in keeps the IndexedDB queue and sync endpoint from the Laravel app (idempotent by `client_uuid`).
- Login page: WordPress login styled in the club look, with a clear "Mitgliederbereich" heading and a prominent "Jetzt bewerben" link (same as today).

### 5.5 Background jobs

Action Scheduler queues: mails, push, reminders, application vote closing, retention clean-up (applications after 6 months, former members after 90 days), audit pruning, routing look-ups. WP-Cron triggers it on page loads; documentation explains how to add a real cron call when available.

### 5.6 External services

- Routing: Nominatim (geocoding, max 1 request/s, budget per request, 30-day cache) and OSRM (distance table). Configurable base URLs so a club can run its own instances. Opt-out setting; fallback to straight-line distance × 1.3.
- Web Push: VAPID keys generated in the admin, contact address mandatory.
- No external fonts, CDNs or analytics.

## 6. Phases

Each phase ends with: tests green, deployed to the test site, short changelog entry, tag `v0.x.0`. Effort = working days of one developer (estimate).

### Phase 0 – Repository and tooling (2–3 days)
- Plugin skeleton, autoloader, coding standard (PHPCS with WordPress rules), `.editorconfig`.
- Test environment: WordPress test suite on a Linux test VM (same approach as Kita-Verein, own throw-away database), `bin/vm-test.sh`.
- Vite build for `app/`, GitHub Actions: lint, PHPUnit, JS build, release ZIP on tag.
- Test site on the existing test VM (new Apache vhost, own database), later reachable as a subdomain behind the VPS proxy.
- **Done when:** empty plugin activates, CI green, release ZIP installs on a clean WordPress.

### Phase 1 – Foundation (6–9 days)
- Settings and branding (name, short name, logo, colours → CSS variables for theme and app), imprint data.
- Migrator, roles and capabilities, scoped assignments, `Access` service, rights page.
- Structure: regions, squads, squad switch per region, overall function switch.
- Audit log, updater, uninstall with opt-in removal.
- **Done when:** an admin can set up the club, create regions/squads and appoint leads; all abilities have tests.

### Phase 2 – Members (8–12 days)
- Member records, profile editing, contact sharing, social/ASVZ, name display, avatars (private storage, authorised delivery).
- Invitations (set password link), consent documents, member numbers.
- Player card with privacy filter; member list per region/squad.
- Former members archive with 90-day retention and reactivation; data export and deletion request.
- **Done when:** behaviour matches the Laravel app's member tests (port the test cases).

### Phase 3 – Member area shell (6–9 days)
- Port the Vue app: router base, API client, auth state, nonce handling, navigation (desktop flyout "Verwaltung", mobile tab bar and sheet), dashboard (events top, tasks middle, region/squad/roles bottom), profile menu.
- Notification bell with the in-app feed.
- **Done when:** a member can sign in, see the dashboard and edit the profile on desktop and phone.

### Phase 4 – Events and attendance (8–12 days)
- `acm_event` post type, types, locations, internal/external events, multi-region, proposals with approval.
- Registration with capacity, waiting list, deadline (prefilled: opens now, deadline 3 days before at 23:00), transport choice changeable until the deadline.
- Public calendar, iCal, schema.org, member-only events, reminders, "new event in your region" notification.
- QR check-in with offline queue, attendance statistics for leads.
- **Done when:** the full event flow incl. offline check-in works on a phone.

### Phase 5 – Carpools (6–8 days)
- Rides per event (auto-created when a member offers a ride at registration), seats, start point (default: member address), meeting point or individual pickup, detour km.
- Seekers see rides with free seats sorted by distance; only the driver accepts; seekers list by distance for the driver; printable pickup plan with suggested order.
- Routing service with cache and budget.
- "Fahrgemeinschaften" in the profile menu.
- **Done when:** the carpool tests from the Laravel app pass in their ported form.

### Phase 6 – Applicants (6–9 days)
- Public application form (anti-spam, throttle, age check, Discord field), handling by squad leaders, stars and one comment per region member.
- Interview invitation with mail, accept/decline/other date, 6-digit PIN, inviter notified.
- Secret vote, decision rules, conversion to member, 6-month retention.
- **Done when:** an applicant can go from form to member without wp-admin.

### Phase 7 – Gear and replicas (4–6 days)
- Equipment, replicas (manufacturer serial unique per maker, internal id, drive, modes, energy class), inspections (12 months, no self-inspection), transfers by leads.
- Catalogs incl. the worldwide camouflage list.
- **Done when:** the player card shows replica data without serial numbers and clearance state.

### Phase 8 – Leadership tools (4–6 days)
- Tasks page (open applicants, proposals, inspections, cases), Kummerkasten (anonymous option, visibility rules for complaints about a regional lead, comments, report required on close), archive (closed cases, former members).
- **Done when:** visibility rules have tests for every role combination.

### Phase 9 – Association and finance (6–10 days, mostly adaptation of Kita-Verein)
- Fee tiers and fee lists, reminders.
- Cash book (append-only, hash chain, reversals, period locks, receipts, reports, exports), donation receipts, donation info page, barometer, printable forms, PDF manual.
- Wording and defaults adapted to sports/airsoft clubs (spheres, categories).
- **Done when:** Kita-Verein's cash book tests pass in the new namespace; reviewed by a tax adviser before real use (not tax advice).

### Phase 10 – Notifications and push (4–6 days)
- Mail templates in club look, per-category preferences (events, rides, votes, applications, cases), Web Push with bundled library, service worker handlers.
- **Done when:** a member receives a push for a new event on an installed PWA (Android and iOS home-screen app).

### Phase 11 – Website, theme and SEO (5–8 days)
- Block theme "Airsoft Club" (dark olive/lime look of the current app, configurable colours), one-page home, header/footer patterns, login page styling.
- Search engine visibility modes (hidden / application form visible / login visible) and editable meta defaults; imprint and privacy templates (clearly marked as templates).
- **Done when:** a fresh installation has a usable public site in under 15 minutes.

### Phase 12 – Migration, hardening, release 1.0 (8–12 days)
- Importer from the Laravel app: export command on the Laravel side (JSON, incl. files) + import in WP-CLI and in the admin (members become WordPress users with password-reset mails, IDs mapped, audit kept).
- Security review (capabilities, nonces, SQL, escaping, uploads, rate limits), accessibility basics, performance with 500 members / 200 events.
- Demo data command (invented members, no sign-in possible), English README and German admin guide.
- **Done when:** the CIS data from the test system is imported into a staging site and checked by the user; tag `v1.0.0`.

**Total: about 75–110 working days (15–22 weeks of one developer).** Phases 7, 8 and 10 can be moved after 1.0 if an earlier release is wanted.

## 7. Testing

- PHPUnit with the WordPress test suite on the test VM; every REST route gets permission and validation tests.
- The Laravel app's 284 feature tests are the reference: each phase ports the relevant test cases.
- JS unit tests for the offline queue and API client; manual checks on desktop and phone per phase; screenshots in the PR/commit notes.

## 8. Security and privacy

- Capability + scope check on every action, nonces, prepared statements, output escaping, strict input validation.
- Private files (avatars, receipts, consent PDFs) outside public access (protected uploads folder + authorised endpoint).
- Rate limits for login (WordPress + proxy), application form, PIN entry and API.
- 2FA mandatory for leads, board and treasurer (configurable, on by default for new installs).
- Member-controlled visibility of phone and email; adults only; retention jobs for applicants and former members.
- Security headers set by the plugin where WordPress does not (CSP for the app page).

## 9. Risks

| Risk | Mitigation |
|---|---|
| Porting effort larger than estimated (Laravel conveniences such as Eloquent, policies, queues, validation are missing) | Small own helpers (repository classes, validator, Action Scheduler); port test cases first |
| Conflicts with other plugins (scripts, REST, libraries) | Prefixed bundled libraries, app isolated on its own page, no global JS |
| WP-Cron does not run on quiet sites | Same as today; document real cron; critical jobs also triggered on member-area requests |
| Scoped rights become inconsistent with WordPress roles | `Access` service is the only source of truth; role re-sync on every version change |
| Shared hosting limits (memory, execution time, upload size) | Batch jobs, chunked imports, checks on the system status page |
| Legal requirements for cash book and receipts | Clearly marked as needing review by a tax adviser |
| Two systems in parallel during the transition | Laravel app stays live until the import is accepted; then read-only |

## 10. Open questions for the owner

1. Final product name and whether the plugin should be published on wordpress.org later.
2. Should the Laravel app be frozen (only fixes) from now on, or keep getting features until 1.0?
3. Release order: full 1.0 at once, or an early 0.x with members/events/applicants for CIS first?
4. Which hosting will CIS use in production (PHP/WordPress versions, cron available?)?
5. Should "Verein" functions (fees, cash book, receipts) be switchable off for clubs without e.V. status?
