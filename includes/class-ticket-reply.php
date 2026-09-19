<?php
/**
 * Canonical staff reply on a ticket: customer email + ticket state + legacy reply history.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Ticket_Reply {

	/**
	 * Send a staff reply. Shared by the WP-admin reply form and other channels (e.g. Telegram)
	 * so every channel produces identical customer email, ticket history and state.
	 *
	 * @param int    $ticket_id     Ticket ID.
	 * @param string $to            Recipient.
	 * @param string $subject       Subject (a ticket reference tag is added by the mailer).
	 * @param string $body          HTML body (wp_kses_post applied by the mailer).
	 * @param int    $admin_user_id WordPress user credited with the reply.
	 * @return bool|\WP_Error `true` on success, `WP_Error` on failure.
	 */
	public static function send( $ticket_id, $to, $subject, $body, $admin_user_id = 0 ) {
		$ticket_id = (int) $ticket_id;

		$result = Biopentra_Contact_Inbox_Mailer::send_ticket_reply( $ticket_id, $to, $subject, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$ticket  = Biopentra_Contact_Inbox_Ticket_Repository::get( $ticket_id );
		$form_id = Biopentra_Contact_Inbox_Form_Resolver::get_form_id();
		if ( $ticket && get_option( 'biopentra_inbox_store_reply_history', 'yes' ) === 'yes' && $form_id > 0 && isset( $ticket->source ) && 'fluent' === $ticket->source && ! empty( $ticket->source_ref ) ) {
			$sid = (int) $ticket->source_ref;
			if ( $sid > 0 ) {
				$tn            = isset( $ticket->ticket_number ) && (int) $ticket->ticket_number > 0 ? (int) $ticket->ticket_number : $ticket_id;
				$base_sub      = $subject !== '' ? $subject : fisd_get_default_reply_subject();
				$final_subject = Biopentra_Contact_Inbox_Ticket_Ref::format_subject( $base_sub, $tn );
				Biopentra_Contact_Inbox_Reply_Repository::insert(
					array(
						'submission_id'   => $sid,
						'form_id'         => $form_id,
						'admin_user_id'   => (int) $admin_user_id,
						'recipient_email' => $to,
						'subject'         => $final_subject,
						'body'            => $body,
						'sent_at'         => current_time( 'mysql' ),
					)
				);
			}
		}

		return true;
	}
}
