<?php
/**
 * Batched backfill of ticket-level to_email from earliest message row (no heavy SQL subqueries).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Ticket_Backfill {

	const CHUNK = 40;

	/**
	 * Run at most one small batch per request until done.
	 */
	public static function maybe_run_to_email_chunk() {
		if ( 'yes' === get_option( 'biopentra_inbox_backfill_to_email_done', '' ) ) {
			return;
		}

		global $wpdb;
		$tickets  = $wpdb->prefix . 'biopentra_inbox_tickets';
		$messages = $wpdb->prefix . 'biopentra_inbox_messages';

		$last_id = (int) get_option( 'biopentra_inbox_backfill_to_email_last_id', 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM `{$tickets}` WHERE source = %s AND id > %d
				AND ( to_email IS NULL OR to_email = '' )
				ORDER BY id ASC
				LIMIT %d",
				'email',
				$last_id,
				self::CHUNK
			)
		);

		if ( ! is_array( $ids ) || empty( $ids ) ) {
			update_option( 'biopentra_inbox_backfill_to_email_done', 'yes', false );
			return;
		}

		$max_id = $last_id;
		foreach ( $ids as $tid ) {
			$tid = (int) $tid;
			if ( $tid <= 0 ) {
				continue;
			}
			$max_id = max( $max_id, $tid );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$to = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT to_email FROM `{$messages}` WHERE ticket_id = %d AND to_email IS NOT NULL AND to_email != '' ORDER BY id ASC LIMIT 1",
					$tid
				)
			);
			if ( ! is_string( $to ) || $to === '' ) {
				continue;
			}
			$to = sanitize_email( $to );
			if ( ! is_email( $to ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$tickets,
				array( 'to_email' => $to ),
				array( 'id' => $tid ),
				array( '%s' ),
				array( '%d' )
			);
		}

		update_option( 'biopentra_inbox_backfill_to_email_last_id', $max_id, false );
	}

}
