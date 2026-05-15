<?php
/**
 * Remove all Support Desk tickets, thread messages, and legacy reply rows (plugin tables only).
 *
 * Does not touch plugin settings, worker token hash, Fluent migration cursor, or WooCommerce data.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Support_Desk_Reset {

	/**
	 * Delete all rows in plugin ticket/message/reply tables; optionally clear import bookkeeping.
	 *
	 * @param bool $clear_import_state When true, clear last sync / worker heartbeat options and IMAP sync lock transient (not migration cursor or token).
	 * @return array{tickets_deleted: int, messages_deleted: int, attachments_deleted: int, import_state_cleared: bool, replies_deleted: int}
	 */
	public static function run( $clear_import_state ) {
		global $wpdb;

		$clear_import_state = (bool) $clear_import_state;

		$tickets_table  = $wpdb->prefix . 'biopentra_inbox_tickets';
		$messages_table = $wpdb->prefix . 'biopentra_inbox_messages';
		$replies_table  = $wpdb->prefix . 'biopentra_inbox_replies';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$messages_deleted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$messages_table}`" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tickets_deleted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tickets_table}`" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$replies_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$replies_table}`" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM `{$messages_table}`" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM `{$tickets_table}`" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM `{$replies_table}`" );

		$import_state_cleared = false;
		if ( $clear_import_state ) {
			self::clear_import_state_options_and_lock();
			$import_state_cleared = true;
		}

		return array(
			'tickets_deleted'      => $tickets_deleted,
			'messages_deleted'     => $messages_deleted,
			'attachments_deleted'  => 0,
			'import_state_cleared' => $import_state_cleared,
			'replies_deleted'      => $replies_before,
		);
	}

	/**
	 * Clear WordPress-side sync / worker display state (not settings, token hash, or Fluent migration cursor).
	 */
	public static function clear_import_state_options_and_lock() {
		delete_option( 'biopentra_inbox_last_sync_at' );
		delete_option( 'biopentra_inbox_last_sync_result' );
		delete_option( 'biopentra_inbox_last_worker_heartbeat' );
		delete_option( 'biopentra_inbox_worker_health_cached' );
		if ( class_exists( 'Biopentra_Contact_Inbox_Imap_Sync' ) ) {
			delete_transient( Biopentra_Contact_Inbox_Imap_Sync::LOCK_KEY );
		} else {
			delete_transient( 'biopentra_inbox_imap_sync_lock' );
		}
	}
}
