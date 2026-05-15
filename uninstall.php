<?php
/**
 * Uninstall Biopentra Support Desk (optional full cleanup).
 *
 * When "Delete plugin data on uninstall" is disabled in Settings, this file exits
 * without removing tables, options, cron, or transients.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

if ( ! isset( $wpdb ) || ! ( $wpdb instanceof wpdb ) ) {
	return;
}

$delete = get_option( 'biopentra_inbox_delete_on_uninstall', 'no' );

if ( 'yes' !== $delete ) {
	return;
}

$tables = array(
	$wpdb->prefix . 'biopentra_inbox_replies',
	$wpdb->prefix . 'biopentra_inbox_tickets',
	$wpdb->prefix . 'biopentra_inbox_messages',
);

foreach ( $tables as $table ) {
	if ( ! is_string( $table ) || $table === '' ) {
		continue;
	}
	// Table name: $wpdb->prefix + fixed suffix (no user input). DROP IF EXISTS avoids errors when missing.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

$option_keys = array(
	'biopentra_inbox_display_name',
	'biopentra_inbox_contact_form_id',
	'biopentra_inbox_from_name',
	'biopentra_inbox_from_email',
	'biopentra_inbox_default_reply_subject',
	'biopentra_inbox_bcc_email',
	'biopentra_inbox_store_reply_history',
	'biopentra_inbox_delete_on_uninstall',
	'biopentra_inbox_email_enabled',
	'biopentra_inbox_imap_host',
	'biopentra_inbox_imap_port',
	'biopentra_inbox_imap_user',
	'biopentra_inbox_imap_pass',
	'biopentra_inbox_imap_mailbox',
	'biopentra_inbox_imap_search',
	'biopentra_inbox_imap_mark_seen',
	'biopentra_inbox_smtp_host',
	'biopentra_inbox_smtp_port',
	'biopentra_inbox_smtp_user',
	'biopentra_inbox_smtp_pass',
	'biopentra_inbox_smtp_scope',
	'biopentra_inbox_sync_enabled',
	'biopentra_inbox_sync_interval',
	'biopentra_inbox_sync_message_cap',
	'biopentra_inbox_last_sync_at',
	'biopentra_inbox_last_sync_result',
	'biopentra_inbox_import_driver',
	'biopentra_inbox_worker_import_enabled',
	'biopentra_inbox_worker_token_hash',
	'biopentra_inbox_last_worker_heartbeat',
	'biopentra_inbox_fluent_migrate_cursor',
	'biopentra_inbox_db_version',
	'biopentra_inbox_archive_auto_delete_days',
	'biopentra_inbox_reply_template_enabled',
	'biopentra_inbox_reply_logo_source',
	'biopentra_inbox_reply_logo_custom_url',
	'biopentra_inbox_reply_header',
	'biopentra_inbox_reply_footer',
	'biopentra_inbox_reply_company_source',
	'biopentra_inbox_reply_company_custom',
	'biopentra_inbox_backfill_to_email_done',
	'biopentra_inbox_backfill_to_email_last_id',
);

foreach ( $option_keys as $key ) {
	delete_option( $key );
}

if ( function_exists( 'wp_unschedule_hook' ) ) {
	wp_unschedule_hook( 'biopentra_inbox_imap_sync' );
	wp_unschedule_hook( 'biopentra_inbox_archived_cleanup' );
}

if ( function_exists( 'delete_transient' ) ) {
	delete_transient( 'biopentra_inbox_imap_sync_lock' );
}
