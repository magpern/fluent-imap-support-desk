# Fluent IMAP Support Desk 2.1.0 — release notes

## Added

- Optional lifecycle actions for other plugins (used by Universal Telegram 0.21.0+):
  `biopentra_contact_inbox/contact_request_submitted`, `biopentra_contact_inbox/ticket_created`
  and `biopentra_contact_inbox/message_added`. They are mutually exclusive, fire after the
  ticket/message is committed, and a listener exception can never break ingestion. See
  `docs/telegram-lifecycle-hooks-freeze.md`.
- **Delete (spam)** button on the ticket detail view: a nonce- and capability-protected
  single-ticket hard delete of the ticket and its messages, with an irreversible-delete
  confirmation.
- `biopentra_inbox_load_reply_runtime()` so non-admin consumers (a Telegram webhook, cron) can read
  tickets and send replies without loading the full admin runtime.

## Changed

- The WP-admin reply and other channels now share one reply operation,
  `Biopentra_Contact_Inbox_Ticket_Reply::send()` (mailer + legacy Fluent reply history). No
  behaviour change for WP-admin replies.

No database, REST namespace, option or table changes.

## Install

Deploy `fluent-imap-support-desk` **2.1.0** / tag **`v2.1.0`**.

Rollback: **2.0.7** / `v2.0.7`.
