<?php
/**
 * Reply log CRUD.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Reply_Repository {

	/**
	 * @param int $submission_id Fluent submission id.
	 * @param int $form_id       Form id.
	 * @return bool
	 */
	public static function has_reply( $submission_id, $form_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_replies';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE submission_id = %d AND form_id = %d",
				$submission_id,
				$form_id
			)
		);
		return (int) $n > 0;
	}

	/**
	 * @param int $submission_id Submission id.
	 * @param int $form_id       Form id.
	 * @return array<int, object>
	 */
	public static function get_history( $submission_id, $form_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_replies';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE submission_id = %d AND form_id = %d ORDER BY sent_at ASC, id ASC",
				$submission_id,
				$form_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array $data submission_id, form_id, admin_user_id, recipient_email, subject, body, sent_at.
	 * @return int|false Insert ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_replies';
		$ok    = $wpdb->insert(
			$table,
			array(
				'submission_id'   => (int) $data['submission_id'],
				'form_id'         => (int) $data['form_id'],
				'admin_user_id'   => (int) $data['admin_user_id'],
				'recipient_email' => $data['recipient_email'],
				'subject'         => $data['subject'],
				'body'            => $data['body'],
				'sent_at'         => $data['sent_at'],
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( ! $ok ) {
			return false;
		}
		return (int) $wpdb->insert_id;
	}
}
