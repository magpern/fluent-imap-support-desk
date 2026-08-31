# Changelog

## [Unreleased]

_No pending changes._

## [2.0.5] - 2026-08-31

**Bulk archive fix** — ticket list bulk actions no longer white-screen.

### Fixed

- **`includes/class-plugin.php`** — process ticket bulk POST on `admin_init` (before admin headers) so archive/mark-read redirects succeed instead of calling `exit` after output has started.
- **`includes/class-list-table.php`** — support bulk “select all matching tickets” via `all_tickets` POST flag.
- **`includes/class-ticket-repository.php`** — add `list_ticket_ids()` for bulk actions across the full filtered result set.

### Notes

- No database or REST namespace changes. Upgrade from **2.0.4** is a file replace only.

## [2.0.4] - 2026-07-27

**Bridge SMTP global scope fix** — aligns production with hotfix commit `6d9261d`.

### Fixed

- **`fluent-imap-support-desk.php`** — load Bridge SMTP early when `biopentra_inbox_smtp_scope` is `all_wp_mail`, so WooCommerce checkout, REST, and other `wp_mail()` callers use Bridge SMTP outside admin/cron contexts.
- **`includes/class-bridge-smtp.php`** — apply From/Reply-To overrides for `all_wp_mail` scope (same as plugin-only path).

### Notes

- No database or REST namespace changes. Upgrade from **2.0.3** is a file replace only.

## [2.0.3] - 2026-05-19

**GitHub Release updater** — production installs and updates from GitHub Release ZIP assets.

### Added

- `includes/class-github-updater.php` — WordPress updates from [GitHub Releases](https://github.com/magpern/fluent-imap-support-desk/releases/latest) using asset `fluent-imap-support-desk-X.Y.Z.zip` (not source archives).
- Environment guard: updater enabled on `WP_ENVIRONMENT_TYPE=production` by default; disable on dev with `FISD_DISABLE_GITHUB_UPDATER` or filter `fisd_github_updater_enabled`.
- Prerelease install handling: sites on `-dev`, `-snapshot`, `-alpha`, `-beta`, or `-rc` versions only see an update when the latest **stable** release is newer than the install base version (avoids downgrade noise).

### Notes

- No intentional support-desk behavior changes vs **2.0.2**. Development servers should keep using repo/rsync and disable the updater via `FISD_DISABLE_GITHUB_UPDATER`.

## [2.0.2] - 2026-05-19

**Packaging and release automation** — aligns version numbers with code already on `main`. **No intentional support-desk behavior changes** beyond what landed between `v2.0.0` and today (branding copy).

### Added

- `readme.txt`, `LICENSE` for distribution.
- `scripts/lib/verify-release-zip.py` — production ZIP verification.
- `scripts/release-audit.sh` — repository vs artifact checks.
- `.github/workflows/ci.yml` and `.github/workflows/release.yml` — PHP lint, build ZIP, GitHub Release on `v*` tags.
- `docs/GITHUB_RELEASE_NOTES_2.0.2.md`.

### Changed

- **Production release ZIP** — `scripts/build-zip.sh` uses explicit excludes; ships `includes/`, `assets/`, main plugin file, `uninstall.php`, `readme.txt`, `LICENSE` only.
- **Version** — header and `BIOPENTRA_INBOX_VERSION` set to **2.0.2**.

### Notes

- Git tag **`v2.0.1`** (2026-05-15) pointed at branding/menu-label commits but left the plugin header at **2.0.0**. That tag is historical; **do not use it for deployments**. Use **`v2.0.2`** instead.
- Internal compatibility identifiers (`biopentra_*` options, tables, REST, capability) remain unchanged.

## [2.0.0] - 2026-05-15

### Added

- Initial standalone repository `magpern/fluent-imap-support-desk`.
- Bootstrap file `fluent-imap-support-desk.php` (product name **Fluent IMAP Support Desk**).
- Compatibility: unchanged `BIOPENTRA_INBOX_*` constants, `biopentra_inbox_*` options/tables, `biopentra-support/v1` REST, `manage_biopentra_inbox` capability.
- Operational docs: architecture, security, rename migration, deployment, worker integration, rollback.
- `scripts/build-zip.sh` for private ZIP releases.

### Notes

- No behavior change vs `biopentra-custom-plugins/plugins/biopentra-contact-inbox/` at v2.0.0.
- Production folder cutover to `fluent-imap-support-desk` completed 2026-05-15 (internal IDs unchanged).
