<?php
/**
 * Optional lifecycle notifications for other plugins (e.g. Universal Telegram).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Lifecycle_Hooks {

	const CONTACT_REQUEST_SUBMITTED = 'biopentra_contact_inbox/contact_request_submitted';
	const TICKET_CREATED            = 'biopentra_contact_inbox/ticket_created';
	const MESSAGE_ADDED             = 'biopentra_contact_inbox/message_added';

	/**
	 * Fire a lifecycle action. Listeners are optional consumers: a listener failure must never
	 * break ticket ingestion, so every Throwable is swallowed here.
	 *
	 * @param string               $hook      One of the class constants.
	 * @param int                  $ticket_id Ticket ID.
	 * @param array<string, mixed> $meta      Event metadata.
	 * @return void
	 */
	public static function fire( $hook, $ticket_id, array $meta ) {
		try {
			if ( ! isset( $meta['ticket_number'] ) ) {
				$ticket                = class_exists( 'Biopentra_Contact_Inbox_Ticket_Repository', false ) ? Biopentra_Contact_Inbox_Ticket_Repository::get( (int) $ticket_id ) : null;
				$meta['ticket_number'] = $ticket && isset( $ticket->ticket_number ) && (int) $ticket->ticket_number > 0 ? (int) $ticket->ticket_number : (int) $ticket_id;
			}
			do_action( $hook, (int) $ticket_id, $meta );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	/**
	 * Plain-text excerpt of an email body for notification consumers.
	 *
	 * @param string $body_text Plain body.
	 * @param string $body_html HTML body.
	 * @return string
	 */
	public static function plain_message_text( $body_text, $body_html ) {
		$src = (string) $body_text !== '' ? (string) $body_text : (string) $body_html;
		return trim( wp_strip_all_tags( $src ) );
	}

	/**
	 * Whether an address is this desk's own outbound address (self-copies must not notify).
	 *
	 * @param string $email Address.
	 * @return bool
	 */
	public static function is_own_address( $email ) {
		$own = sanitize_email( (string) get_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) ) );
		return $own !== '' && strtolower( $own ) === strtolower( (string) $email );
	}
}
