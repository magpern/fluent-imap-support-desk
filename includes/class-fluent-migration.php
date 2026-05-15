<?php
/**
 * Batch migrate Fluent submissions into tickets/messages.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Fluent_Migration {

	/**
	 * @param int $form_id Fluent form ID.
	 * @param int $limit   Max rows per batch.
	 * @return array{migrated: int, done: bool}
	 */
	public static function migrate_batch( $form_id, $limit = 25 ) {
		global $wpdb;
		$form_id = (int) $form_id;
		$limit    = max( 1, min( 100, (int) $limit ) );
		if ( $form_id <= 0 ) {
			return array( 'migrated' => 0, 'done' => true );
		}

		$cursor = (int) get_option( 'biopentra_inbox_fluent_migrate_cursor', 0 );
		$sub    = $wpdb->prefix . 'fluentform_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.* FROM {$sub} s
				WHERE s.form_id = %d AND s.id > %d AND ( s.status IS NULL OR s.status != %s )
				ORDER BY s.id ASC
				LIMIT %d",
				$form_id,
				$cursor,
				'trashed',
				$limit
			)
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			update_option( 'biopentra_inbox_fluent_migrate_cursor', 0 );
			return array( 'migrated' => 0, 'done' => true );
		}

		$migrated = 0;
		$max_id   = $cursor;
		foreach ( $rows as $row ) {
			$max_id = max( $max_id, (int) $row->id );
			$sid    = (int) $row->id;
			$exists = Biopentra_Contact_Inbox_Ticket_Repository::find_by_source( 'fluent', (string) $sid );
			if ( $exists ) {
				continue;
			}

			$data = Biopentra_Contact_Inbox_Submission_Repository::decode_response( $row->response );
			$email = Biopentra_Contact_Inbox_Submission_Repository::extract_submitter_email( $data );
			if ( ! is_email( $email ) ) {
				continue;
			}
			$name    = Biopentra_Contact_Inbox_Submission_Repository::extract_submitter_name( $data );
			$subject = __( 'Fluent form submission', 'biopentra-contact-inbox' );
			if ( ! empty( $data['reason_for_contact'] ) ) {
				$subject = Biopentra_Contact_Inbox_Submission_Repository::reason_label( (string) $data['reason_for_contact'] );
			}

			$created = isset( $row->created_at ) ? $row->created_at : current_time( 'mysql' );
			$tid     = Biopentra_Contact_Inbox_Ticket_Repository::insert(
				array(
					'source'          => 'fluent',
					'source_ref'      => (string) $sid,
					'subject'         => $subject,
					'customer_email'  => $email,
					'customer_name'   => $name !== '' ? $name : null,
					'status'          => 'open',
					'is_unread'       => true,
					'last_message_at' => $created,
					'created_at'      => $created,
				)
			);
			if ( ! $tid ) {
				continue;
			}

			$body_text = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			Biopentra_Contact_Inbox_Message_Repository::insert(
				array(
					'ticket_id'   => $tid,
					'direction'   => 'inbound',
					'source'      => 'fluent',
					'from_email'  => $email,
					'from_name'   => $name !== '' ? $name : null,
					'to_email'    => null,
					'subject'     => $subject,
					'body_text'   => $body_text,
					'body_html'   => null,
					'raw_headers' => null,
					'created_at'  => $created,
				)
			);
			++$migrated;
		}

		update_option( 'biopentra_inbox_fluent_migrate_cursor', $max_id );
		$done = count( $rows ) < $limit;
		if ( $done ) {
			update_option( 'biopentra_inbox_fluent_migrate_cursor', 0 );
		}

		return array(
			'migrated' => $migrated,
			'done'     => $done,
		);
	}

	/**
	 * Ensure a single Fluent submission has a ticket (for legacy URLs).
	 *
	 * @param int $submission_id Submission ID.
	 * @param int $form_id       Form ID.
	 * @return int|false Ticket ID.
	 */
	public static function ensure_ticket_for_submission( $submission_id, $form_id ) {
		$submission_id = (int) $submission_id;
		$form_id       = (int) $form_id;
		if ( $submission_id <= 0 || $form_id <= 0 ) {
			return false;
		}
		$existing = Biopentra_Contact_Inbox_Ticket_Repository::find_by_source( 'fluent', (string) $submission_id );
		if ( $existing ) {
			return (int) $existing->id;
		}
		$row = Biopentra_Contact_Inbox_Submission_Repository::get_submission( $submission_id, $form_id );
		if ( ! $row ) {
			return false;
		}
		$data  = Biopentra_Contact_Inbox_Submission_Repository::decode_response( $row->response );
		$email = Biopentra_Contact_Inbox_Submission_Repository::extract_submitter_email( $data );
		if ( ! is_email( $email ) ) {
			return false;
		}
		$name    = Biopentra_Contact_Inbox_Submission_Repository::extract_submitter_name( $data );
		$subject = __( 'Fluent form submission', 'biopentra-contact-inbox' );
		if ( ! empty( $data['reason_for_contact'] ) ) {
			$subject = Biopentra_Contact_Inbox_Submission_Repository::reason_label( (string) $data['reason_for_contact'] );
		}
		$created = isset( $row->created_at ) ? $row->created_at : current_time( 'mysql' );
		$tid     = Biopentra_Contact_Inbox_Ticket_Repository::insert(
			array(
				'source'          => 'fluent',
				'source_ref'      => (string) $submission_id,
				'subject'         => $subject,
				'customer_email'  => $email,
				'customer_name'   => $name !== '' ? $name : null,
				'status'          => 'open',
				'is_unread'       => true,
				'last_message_at' => $created,
				'created_at'      => $created,
			)
		);
		if ( ! $tid ) {
			return false;
		}
		$body_text = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		Biopentra_Contact_Inbox_Message_Repository::insert(
			array(
				'ticket_id'   => $tid,
				'direction'   => 'inbound',
				'source'      => 'fluent',
				'from_email'  => $email,
				'from_name'   => $name !== '' ? $name : null,
				'to_email'    => null,
				'subject'     => $subject,
				'body_text'   => $body_text,
				'body_html'   => null,
				'raw_headers' => null,
				'created_at'  => $created,
			)
		);
		return $tid;
	}
}
