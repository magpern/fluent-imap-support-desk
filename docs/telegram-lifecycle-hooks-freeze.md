# Telegram lifecycle hooks and ticket delete — implementation freeze

**Status: Accepted / Frozen — 2026-09-19.** DEV only. Counterpart of Universal Telegram
ADR-0046 (`universal-telegram` repo, `docs/adr/0046-…`). This plugin only *emits* optional
hooks and gains one admin action; all Telegram behaviour lives in the other plugin.

## Contract

Three mutually exclusive `do_action( $hook, int $ticket_id, array $meta )` hooks, fired through
`Biopentra_Contact_Inbox_Lifecycle_Hooks::fire()` which swallows every `\Throwable` so a
listener can never break ingestion:

| Hook | Fired from | Condition |
|---|---|---|
| `biopentra_contact_inbox/contact_request_submitted` | `Fluent_Migration::ensure_ticket_for_submission()` | this call created the ticket **and** the caller passed `$fire_hooks = true` |
| `biopentra_contact_inbox/ticket_created` | `Inbound_Import::import_payload()` | new ticket created, after `COMMIT` |
| `biopentra_contact_inbox/message_added` | `Inbound_Import::import_payload()` | inbound message joined an existing ticket, after `COMMIT`; skipped when the sender is the desk's own address |

`$meta` keys: `source` (`email`|`fluent`), `subject`, `customer_email`, `customer_name`,
`message_text` (plain text), `message_row_id` (inserted message id), plus
`direction = 'inbound'` for `message_added`.

A new imported ticket emits only `ticket_created`; a later inbound email emits only
`message_added`; a contact submission emits only `contact_request_submitted`. Hooks fire after
the transaction commits and never on duplicate/rolled-back imports.

Contact-form payload: `message_text` is the submission's `message` field (tags stripped) —
verified against live form 5 (`full_name`, `email`, `reason_for_contact`, `message`,
`requested_product`, `wc_related_order`); if absent, `Submission_Repository::human_summary_lines()`;
otherwise empty. The raw decoded submission is never emitted.

Delete: `admin_post_biopentra_inbox_ticket_delete` — capability `BIOPENTRA_INBOX_CAP`, nonce
`biopentra_inbox_ticket_delete_{id}`, calls `Ticket_Repository::delete_ticket_and_messages()`
(cascade to messages), safe redirect to the ticket list with a notice. "Delete (spam)" button
with an irreversible-delete `confirm()` on the ticket detail view. Single-ticket hard delete.

## Deviations from the reviewed plan (found during code inspection)

1. `$fire_hooks` **defaults to `false`** (opt-in), not `true`. `ensure_ticket_for_submission()`
   is also called lazily from admin/legacy-URL paths, the mail-context filters and message
   parsing; only the live-submission handler `on_submission_inserted_early()` opts in. This is
   the smaller-risk direction of the same intent (backfill/legacy never notify).
   `migrate_batch()` inserts tickets itself and never fires hooks.
2. `message_added` is suppressed for the desk's own From address (self-copies via Bcc or
   loopback must not notify managers).
3. `message_row_id` is added to `$meta` so consumers can build collision-free idempotency keys.

## Out of scope

PHPUnit scaffolding (existing CI is `php -l` + package build; verification is manual on DEV);
removing the underlying Fluent Forms submission row on delete; a spam status; bulk delete;
production deployment.

## Version impact

Plugin 2.0.7 → 2.1.0 (additive hooks + admin action). No schema change.

## Verification

Manual on DEV: contact form → one `contact_request_submitted`; fresh email → one
`ticket_created` only; reply email on existing thread → one `message_added` only; listener
that throws does not break import/submission; Delete removes ticket + messages, rejects
missing nonce / unprivileged user.
