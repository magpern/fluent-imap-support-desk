# Fluent IMAP Support Desk 2.0.3

**GitHub Release updater** — production sites can update from this release’s ZIP via **Dashboard → Updates** (or **Plugins**).

## What changed

- **`includes/class-github-updater.php`** — checks [latest GitHub Release](https://github.com/magpern/fluent-imap-support-desk/releases/latest) and installs asset **`fluent-imap-support-desk-X.Y.Z.zip`** (not source archives).
- **Environment guard** — updater runs on `WP_ENVIRONMENT_TYPE=production` by default; disable on dev/staging with `FISD_DISABLE_GITHUB_UPDATER` in `wp-config.php`.
- **Prerelease installs** — sites on `-dev`, `-snapshot`, `-alpha`, `-beta`, or `-rc` version strings are not prompted to downgrade to an older stable release.

No intentional support-desk behavior changes vs **2.0.2** beyond the updater.

## Upgrade

| From | Action |
|------|--------|
| **2.0.2** (ZIP or manual) | Use **Plugins → Update** on production, or upload this ZIP |
| **2.0.3-dev** (repo checkout) | Replace with this stable ZIP on production only; keep dev on repo/rsync |

Database tables, options, REST namespace (`biopentra-support/v1`), and cron hooks are **unchanged**.

## Install

1. Download **`fluent-imap-support-desk-2.0.3.zip`** from this release.
2. Upload via **Plugins → Add New → Upload** or extract to `wp-content/plugins/fluent-imap-support-desk/`.
3. On production, future updates can use the built-in updater when `WP_ENVIRONMENT_TYPE` is `production`.

## Rollback

Deactivate and restore the **2.0.2** plugin folder from backup (keep DB backup).

Full changelog: [CHANGELOG.md](../CHANGELOG.md)
