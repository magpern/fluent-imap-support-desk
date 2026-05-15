<?php
/**
 * Cron: delete old archived email tickets (cascade messages), capped per run.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Archived_Email_Cleanup {

	const HOOK = 'biopentra_inbox_archived_cleanup';

	const PER_RUN_CAP = 100;

	/**
	 * Register cron hook callback (called from Cron::init).
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'maybe_reschedule' ), 25 );
	}

	public static function maybe_reschedule() {
		$days = (int) get_option( 'biopentra_inbox_archive_auto_delete_days', 30 );
		if ( $days <= 0 ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Delete archived email tickets older than configured retention.
	 */
	public static function run() {
		$days = (int) get_option( 'biopentra_inbox_archive_auto_delete_days', 30 );
		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		$then    = current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS );
		$cutoff  = wp_date( 'Y-m-d H:i:s', $then );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE source = %s
				AND archived_at IS NOT NULL
				AND archived_at != ''
				AND archived_at < %s
				ORDER BY archived_at ASC
				LIMIT %d",
				'email',
				$cutoff,
				self::PER_RUN_CAP
			)
		);

		if ( ! is_array( $ids ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				Biopentra_Contact_Inbox_Ticket_Repository::delete_ticket_and_messages( $id );
			}
		}
	}
}
