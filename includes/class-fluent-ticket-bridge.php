<?php
/**
 * Fluent Forms: ticket creation on submit and optional email subject tagging.
 *
 * Fluent hooks used (present in bundled fluentform):
 * - {@see fluentform/submission_inserted} — core registers GlobalNotificationHandler at priority 10;
 *   we run {@see Biopentra_Contact_Inbox_Fluent_Migration::ensure_ticket_for_submission} at priority 5.
 * - {@see fluentform/submission_message_parse} — confirmation message parsing (FormHandler / SubmissionHandlerService).
 * - {@see fluentform/integration_notify_notifications} — sync email feeds; we capture entry id for the subject filter.
 * - {@see fluentform/email_subject} — prepend {@see Biopentra_Contact_Inbox_Ticket_Ref::format_subject} when capture context matches.
 *
 * Limitations:
 * - Async / queued Fluent notifications are not handled here; subject tag applies to synchronous notification sends.
 * - If Fluent changes hook signatures, handlers no-op via try/catch without breaking submission.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Fluent_Ticket_Bridge {

	/**
	 * @var array{entry_id: int, form_id: int}
	 */
	private static $mail_context = array(
		'entry_id' => 0,
		'form_id'  => 0,
	);

	/**
	 * Register only when Fluent Forms is loaded (constant from fluentform.php).
	 */
	public static function init() {
		if ( ! defined( 'FLUENTFORM_VERSION' ) ) {
			return;
		}

		add_action( 'fluentform/submission_inserted', array( __CLASS__, 'on_submission_inserted_early' ), 5, 3 );
		add_filter( 'fluentform/submission_message_parse', array( __CLASS__, 'on_submission_message_parse' ), 5, 4 );
		add_action( 'fluentform/integration_notify_notifications', array( __CLASS__, 'capture_mail_context' ), 1, 4 );
		add_action( 'fluentform/integration_notify_notifications', array( __CLASS__, 'clear_mail_context' ), 999, 4 );
		add_filter( 'fluentform/email_subject', array( __CLASS__, 'filter_email_subject_with_context' ), 20, 4 );
	}

	/**
	 * @return int
	 */
	private static function configured_form_id() {
		return (int) get_option( 'biopentra_inbox_contact_form_id', 0 );
	}

	/**
	 * @param int         $insert_id  Submission id.
	 * @param array       $form_data  Submitted data.
	 * @param object|null $form       Form object.
	 * @return void
	 */
	public static function on_submission_inserted_early( $insert_id, $form_data, $form ) {
		try {
			$insert_id = (int) $insert_id;
			if ( $insert_id <= 0 || ! is_object( $form ) || empty( $form->id ) ) {
				return;
			}
			if ( (int) $form->id !== self::configured_form_id() ) {
				return;
			}
			Biopentra_Contact_Inbox_Fluent_Migration::ensure_ticket_for_submission( $insert_id, (int) $form->id );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	/**
	 * @param string      $message    HTML or text fragment.
	 * @param int|false   $insert_id  Submission id.
	 * @param array       $form_data  Data.
	 * @param object|null $form       Form.
	 * @return string
	 */
	public static function on_submission_message_parse( $message, $insert_id, $form_data, $form ) {
		try {
			$insert_id = (int) $insert_id;
			if ( $insert_id <= 0 || ! is_object( $form ) || empty( $form->id ) ) {
				return is_string( $message ) ? $message : '';
			}
			if ( (int) $form->id !== self::configured_form_id() ) {
				return is_string( $message ) ? $message : '';
			}
			$tid = Biopentra_Contact_Inbox_Fluent_Migration::ensure_ticket_for_submission( $insert_id, (int) $form->id );
			if ( ! $tid ) {
				return is_string( $message ) ? $message : '';
			}
			$row = Biopentra_Contact_Inbox_Ticket_Repository::get( (int) $tid );
			if ( ! $row ) {
				return is_string( $message ) ? $message : '';
			}
			$tn = isset( $row->ticket_number ) && (int) $row->ticket_number > 0 ? (int) $row->ticket_number : (int) $tid;
			$tag = Biopentra_Contact_Inbox_Ticket_Ref::bracket_tag( $tn );
			if ( $tag === '' ) {
				return is_string( $message ) ? $message : '';
			}
			$line = "\n\n" . sprintf(
				/* translators: %s: ticket reference like [BP-123] */
				__( 'Your request reference: %s', 'biopentra-contact-inbox' ),
				$tag
			);
			return ( is_string( $message ) ? $message : '' ) . esc_html( $line );
		} catch ( \Throwable $e ) {
			return is_string( $message ) ? $message : '';
		}
	}

	/**
	 * @param array       $feed      Feed definition.
	 * @param array       $form_data Submission data.
	 * @param object      $entry     Entry row.
	 * @param object|null $form      Form.
	 * @return void
	 */
	public static function capture_mail_context( $feed, $form_data, $entry, $form ) {
		self::$mail_context = array(
			'entry_id' => 0,
			'form_id'  => 0,
		);
		try {
			if ( ! is_array( $feed ) || empty( $feed['meta_key'] ) || 'notifications' !== $feed['meta_key'] ) {
				return;
			}
			if ( ! is_object( $form ) || empty( $form->id ) ) {
				return;
			}
			if ( (int) $form->id !== self::configured_form_id() ) {
				return;
			}
			if ( ! is_object( $entry ) || empty( $entry->id ) ) {
				return;
			}
			self::$mail_context = array(
				'entry_id' => (int) $entry->id,
				'form_id'  => (int) $form->id,
			);
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	/**
	 * @return void
	 */
	public static function clear_mail_context() {
		self::$mail_context = array(
			'entry_id' => 0,
			'form_id'  => 0,
		);
	}

	/**
	 * @param string $subject       Subject.
	 * @param array  $notification  Notification payload.
	 * @param array  $submitted_data Data.
	 * @param object $form           Form.
	 * @return string
	 */
	public static function filter_email_subject_with_context( $subject, $notification, $submitted_data, $form ) {
		try {
			$subject = is_string( $subject ) ? $subject : '';
			$eid     = (int) self::$mail_context['entry_id'];
			$fid     = (int) self::$mail_context['form_id'];
			if ( $eid <= 0 || $fid <= 0 || $fid !== self::configured_form_id() ) {
				return $subject;
			}
			$tid = Biopentra_Contact_Inbox_Fluent_Migration::ensure_ticket_for_submission( $eid, $fid );
			if ( ! $tid ) {
				return $subject;
			}
			$row = Biopentra_Contact_Inbox_Ticket_Repository::get( (int) $tid );
			if ( ! $row ) {
				return $subject;
			}
			$tn = isset( $row->ticket_number ) && (int) $row->ticket_number > 0 ? (int) $row->ticket_number : (int) $tid;
			return Biopentra_Contact_Inbox_Ticket_Ref::format_subject( $subject, $tn );
		} catch ( \Throwable $e ) {
			return is_string( $subject ) ? $subject : '';
		}
	}
}
