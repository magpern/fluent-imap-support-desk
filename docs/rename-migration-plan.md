# Rename migration plan — biopentra-contact-inbox → Fluent IMAP Support Desk

**Status:** Planned — **not executed on Biopentra production** (2026-05-15)

---

## Goal

Move production from:

- Folder: `wp-content/plugins/biopentra-contact-inbox/`
- Main file: `biopentra-contact-inbox.php`
- Product label: Biopentra Support Desk

To:

- Folder: `wp-content/plugins/fluent-imap-support-desk/`
- Main file: `fluent-imap-support-desk.php`
- Product label: Fluent IMAP Support Desk

While minimizing downtime and **without breaking**:

- Database tables (`biopentra_inbox_*`)
- Options (`biopentra_inbox_*`)
- REST clients (`/wp-json/biopentra-support/v1/*`)
- Mail worker env (`API_BASE_URL`, `API_TOKEN`)
- Cron hooks (`biopentra_inbox_*`)
- Capability `manage_biopentra_inbox`

---

## Phase 0 — Current state (safe)

| Item | Action |
|------|--------|
| Production plugin slug | **Keep** `biopentra-contact-inbox` |
| Source of truth | `fluent-imap-support-desk` repo + monorepo copy until retired |
| This standalone ZIP | For new installs / staging only |

---

## Phase 1 — Repo parity (done)

- [x] Standalone git repo with `fluent-imap-support-desk.php` bootstrap
- [x] Unchanged internal APIs (constants, REST, tables)
- [x] ZIP build script
- [x] Documentation

---

## Phase 2 — Staging validation

1. Clone staging with **copy** of production DB.
2. Install `fluent-imap-support-desk-2.0.0.zip` in a **separate** folder — confirm it must **not** run alongside old plugin (deactivate old first in staging test).
3. **Preferred staging test:** replace folder in place:
   ```bash
   ./wp plugin deactivate biopentra-contact-inbox
   mv wp-content/plugins/biopentra-contact-inbox wp-content/plugins/biopentra-contact-inbox.bak
   unzip fluent-imap-support-desk-2.0.0.zip -d wp-content/plugins/
   ./wp plugin activate fluent-imap-support-desk
   ```
4. Verify: tickets, settings, REST health, worker import, outbound reply.
5. Roll back staging from backup if any option/cron regression.

---

## Phase 3 — Production cutover (future window)

**Prerequisites:** DB backup, plugin folder tarball, worker token documented, maintenance notice.

1. `./wp plugin deactivate biopentra-contact-inbox`
2. Backup `wp-content/plugins/biopentra-contact-inbox/`
3. Deploy `fluent-imap-support-desk/` (ZIP or rsync from release tag)
4. `./wp plugin activate fluent-imap-support-desk`
5. `./wp cache flush`
6. Confirm worker still reaches REST (same namespace)
7. Remove old folder after 48h stable

**Do not** run two plugins with both bootstraps active — double cron and double Fluent hooks.

---

## Phase 4 — Deep rename (optional, major release)

Separate project to rename:

| Artifact | Today | Target |
|----------|-------|--------|
| Tables | `biopentra_inbox_*` | e.g. `fisd_*` |
| Options | `biopentra_inbox_*` | `fisd_*` |
| REST | `biopentra-support/v1` | `fluent-imap-support-desk/v1` |
| Capability | `manage_biopentra_inbox` | `manage_fluent_imap_support_desk` |
| Classes | `Biopentra_Contact_Inbox_*` | Namespaced refactor |

Requires migration script, worker image update, and coordinated downtime.

---

## Risks if migration rushed

| Risk | Mitigation |
|------|------------|
| Dual active plugins | Deactivate old before activating new |
| Lost worker token | Export/rotate before cutover |
| Cron duplicates | Flush cron, verify `wp cron event list` |
| Bookmarks to `admin.php?page=biopentra-inbox` | Menu slug unchanged in 2.0.0 — still works |
| Git monorepo drift | Sync or retire `biopentra-custom-plugins/plugins/biopentra-contact-inbox/` |

---

## Production can stay on biopentra-contact-inbox?

**Yes.** Continue production on the existing slug until Phase 3 is scheduled. This repo is the forward-looking source; sync fixes from monorepo → standalone → production folder when ready.
