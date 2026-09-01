# Fluent IMAP Support Desk 2.0.6 — release notes

## Changed

- Automatic updates now come from a private update server via the bundled
  [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) v5
  library (`lib/plugin-update-checker/`). The bespoke GitHub-release updater
  (`includes/class-github-updater.php`, `FISD_Github_Updater`) has been removed.
- The update check runs only when the `PRIVATE_UPDATE_SERVER` constant is defined
  in `wp-config.php`; otherwise the plugin does not check for updates.
- Added a CI workflow that uploads the release ZIP to the update server on each
  `v*` tag.

## Install

Deploy `fluent-imap-support-desk` **2.0.6** / tag **`v2.0.6`**. Define
`PRIVATE_UPDATE_SERVER` in `wp-config.php` on the target site to receive updates.

Rollback: **2.0.5** / `v2.0.5`.
