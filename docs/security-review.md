# Security review — Fluent IMAP Support Desk 2.0.0

**Date:** 2026-05-15  
**Scope:** Standalone repo bootstrap + copied includes (no global rename)

---

## Executive summary

No **critical** issues requiring immediate code changes before publishing this repo. Primary risks are **credential storage**, **bearer-token REST import**, and **operator mistakes** (dual plugin install, public `/health`). Production behavior matches the audited `biopentra-contact-inbox` 2.0.0 tree.

---

## Stored secrets

| Secret | Storage | Notes |
|--------|---------|-------|
| IMAP password | Option `biopentra_inbox_imap_pass` or `BIOPENTRA_INBOX_IMAP_PASS` in wp-config | DB backups expose options |
| SMTP password | Option `biopentra_inbox_smtp_pass` or `BIOPENTRA_INBOX_SMTP_PASS` | Same |
| Worker API token | **Hash** in `biopentra_inbox_worker_token_hash`; plaintext shown once on rotate | SHA-256 verify on import |
| Bridge credentials | Also in worker `.env.worker` / Compose | Out of plugin repo |

**Recommendation:** Prefer wp-config constants for passwords on production; restrict DB backup access; rotate worker token after any leak.

---

## REST worker

| Route | `permission_callback` | Risk |
|-------|----------------------|------|
| `/health` | `__return_true` | Information disclosure (version, token configured) — acceptable for internal monitoring; rate-limit at edge if exposed |
| `/messages/import` | Bearer `permission_bearer` | **High value** — forged import if token leaked |
| `/worker/status` | Bearer | Lower impact heartbeat |

Token compared via hash (`Biopentra_Contact_Inbox_Rest_Worker::permission_bearer`). Import can be disabled in settings.

---

## Mail injection

- Outbound: `wp_mail` / PHPMailer via Bridge SMTP; staff reply body uses `wp_kses_post` / template layer in `class-email-reply-template.php`.
- Inbound: import validates payload; subject/body stored and escaped on output in admin.
- **Risk:** HTML replies if template allows rich content — review template settings and staff training.

---

## admin_post / capabilities

- All mutating `admin_post_biopentra_inbox_*` handlers checked in `class-plugin.php` with `current_user_can( BIOPENTRA_INBOX_CAP )` and nonces (`check_admin_referer`).
- Capability `manage_biopentra_inbox` granted to `administrator` on activation.

---

## SQL

- Repositories use `$wpdb->prepare()` for user-derived values in reviewed paths.
- Activator/uninstall use fixed table names with prefix.
- Fluent form lookup uses prepared title query.

---

## Uninstall

`uninstall.php` drops tables and deletes options **only** when `biopentra_inbox_delete_on_uninstall` is `yes`. Default: **preserve data**.

---

## Findings summary

| Severity | Finding |
|----------|---------|
| Medium | Bearer token protects high-value import — protect `.env.worker` and hash option |
| Medium | IMAP/SMTP passwords in DB options |
| Low | Public `/health` endpoint |
| Low | Legacy class/option naming (no security issue; migration complexity) |

**No code changes** in this repo setup task; document-only + compatibility bootstrap.

---

## Hardening (next releases)

- Optional IP allowlist for REST import (worker subnet only).
- Move secrets to environment-only (drop DB password options).
- Rename migration with explicit data migration audit.
