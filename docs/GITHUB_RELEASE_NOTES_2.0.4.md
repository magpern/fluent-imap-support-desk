# Fluent IMAP Support Desk 2.0.4

**Bridge SMTP global scope fix** — production hotfix release.

## What changed

- **`fluent-imap-support-desk.php`** — when Support Desk email is enabled and SMTP scope is **`all_wp_mail`**, Bridge SMTP registers on every request that needs `wp_mail()` (WooCommerce checkout, REST previews, storefront transactional mail), not only admin/WP-Cron.
- **`includes/class-bridge-smtp.php`** — From/Reply-To overrides now apply for `all_wp_mail` scope.

No database migration. REST namespace, options, and tables unchanged.

## Upgrade

| From | Action |
|------|--------|
| **2.0.3** (GitHub Release ZIP) | Upload this ZIP or use **Plugins → Update** on production |
| **2.0.3 + manual hotfix** (`6d9261d`) | Replace with this ZIP for a tagged baseline |

## Install

1. Download **`fluent-imap-support-desk-2.0.4.zip`** from this release.
2. Upload via **Plugins → Add New → Upload** or extract to `wp-content/plugins/fluent-imap-support-desk/`.

## Rollback

Restore the **2.0.3** plugin folder from backup.

Full changelog: [CHANGELOG.md](../CHANGELOG.md)
