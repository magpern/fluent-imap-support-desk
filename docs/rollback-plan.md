# Rollback plan — Fluent IMAP Support Desk

---

## Plugin-only rollback

1. `./wp plugin deactivate fluent-imap-support-desk`
2. Restore previous folder from backup tarball or reinstall prior ZIP
3. `./wp plugin activate biopentra-contact-inbox` (if rolling back to legacy slug)
4. `./wp cache flush`
5. Verify REST health and tickets list

**Data:** Tables and options are preserved unless uninstall with “delete data” was enabled.

---

## Failed rename migration

If cutover from `biopentra-contact-inbox` to `fluent-imap-support-desk` fails:

1. Deactivate new plugin
2. Restore `biopentra-contact-inbox` folder from `.bak` tarball
3. Reactivate legacy plugin
4. Confirm worker `API_BASE_URL` unchanged (`biopentra-support/v1`)
5. No DB restore needed if tables were not dropped

---

## Database restore

Use only if danger-zone reset or bad migration dropped data:

1. Maintenance mode
2. Restore MariaDB dump from pre-change backup
3. Restore plugin files
4. `./wp cache flush`

---

## Worker / Bridge

- Bridge volumes (`woocommerce_bridge_*`) hold login state — do not delete on plugin rollback
- If worker token rotated during failed deploy, re-enter token in Settings and `.env.worker`

---

## Prevention

- Always backup DB + plugin folder before rename migration
- Never run both plugin folders active simultaneously
