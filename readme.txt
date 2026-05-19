=== Fluent IMAP Support Desk ===
Contributors: magpern
Tags: support, fluent forms, imap, smtp, helpdesk, tickets
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Support desk bridging Fluent Forms tickets with IMAP/SMTP (Proton Bridge and external mail worker compatible).

== Description ==

Fluent IMAP Support Desk turns Fluent Forms submissions into threaded support tickets with IMAP inbound and SMTP outbound mail.

Internal compatibility identifiers (`biopentra_*` options, tables, REST namespace, capabilities) are unchanged from the `biopentra-contact-inbox` lineage.

Production sites on `WP_ENVIRONMENT_TYPE=production` can update from GitHub Release ZIPs via the built-in plugin updater (see README).

== Installation ==

1. Upload the plugin ZIP via **Plugins → Add New → Upload**, or extract to `wp-content/plugins/fluent-imap-support-desk/`.
2. Activate the plugin.
3. Configure IMAP/SMTP and Fluent Forms mapping under **Support Desk** in wp-admin.

== Changelog ==

= 2.0.3 =
* GitHub Release updater for production ZIP installs (see README and docs/GITHUB_RELEASE_NOTES_2.0.3.md).
* No intentional behavior change vs 2.0.2.

= 2.0.2 =
* Production release ZIP hardening and GitHub Actions CI/release automation.
* Version alignment release (supersedes mis-tagged v2.0.1). No intentional behavior change vs 2.0.0 branding updates.
* See docs/GITHUB_RELEASE_NOTES_2.0.2.md.

= 2.0.0 =
* Initial standalone repository and folder cutover from biopentra-contact-inbox.
