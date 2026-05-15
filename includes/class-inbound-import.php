<?php
/**
 * Shared inbound mail import (PHP IMAP + REST worker).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Inbound_Import {

	/**
	 * Import one inbound message from a normalized payload array.
	 *
	 * Dedupe identity: at least one of normalized `message_id` OR non-empty `imap_dedupe_key`
	 * (do not hard-fail when `message_id` is missing if `imap_dedupe_key` is present).
	 *
	 * @param array<string, mixed> $data Payload keys align with Message_Repository::insert + threading fields.
	 * @return array{success: bool, status?: string, reason?: string, error_code?: string, message?: string}
	 */
	public static function import_payload( array $data ) {
		$mid_raw = isset( $data['message_id'] ) ? trim( (string) $data['message_id'] ) : '';
		$mid     = $mid_raw !== '' ? Biopentra_Contact_Inbox_Message_Id::normalize( $mid_raw ) : '';

		$dedupe = isset( $data['imap_dedupe_key'] ) ? trim( (string) $data['imap_dedupe_key'] ) : '';
		if ( strlen( $dedupe ) > 191 ) {
			$dedupe = substr( $dedupe, 0, 191 );
		}

		if ( ( $mid === '' || null === $mid ) && $dedupe === '' ) {
			return array(
				'success'    => false,
				'error_code' => 'validation',
				'message'    => __( 'Either message_id or imap_dedupe_key is required.', 'biopentra-contact-inbox' ),
			);
		}

		if ( $mid && Biopentra_Contact_Inbox_Message_Repository::exists_by_message_id( $mid ) ) {
			return array(
				'success' => true,
				'status'  => 'skipped_duplicate',
				'reason'  => 'message_id',
			);
		}

		if ( $dedupe !== '' && Biopentra_Contact_Inbox_Message_Repository::exists_by_imap_dedupe_key( $dedupe ) ) {
			return array(
				'success' => true,
				'status'  => 'skipped_duplicate',
				'reason'  => 'imap_dedupe_key',
			);
		}

		$from_email = isset( $data['from_email'] ) ? sanitize_email( (string) $data['from_email'] ) : '';
		if ( ! is_email( $from_email ) ) {
			return array(
				'success'    => false,
				'error_code' => 'validation',
				'message'    => __( 'Valid from_email is required.', 'biopentra-contact-inbox' ),
			);
		}

		$date_in = isset( $data['date'] ) ? $data['date'] : '';
		if ( ! self::is_parseable_date( $date_in ) ) {
			return array(
				'success'    => false,
				'error_code' => 'validation',
				'message'    => __( 'Valid date is required.', 'biopentra-contact-inbox' ),
			);
		}

		$imap_uid = isset( $data['imap_uid'] ) ? absint( $data['imap_uid'] ) : 0;

		$in_reply = isset( $data['in_reply_to'] ) ? trim( (string) $data['in_reply_to'] ) : '';
		$in_reply_norm = $in_reply !== '' ? Biopentra_Contact_Inbox_Message_Id::normalize( $in_reply ) : '';

		$refs = self::parse_references( isset( $data['references'] ) ? $data['references'] : null );

		$from_name = isset( $data['from_name'] ) ? sanitize_text_field( (string) $data['from_name'] ) : '';
		$to_email  = isset( $data['to_email'] ) ? sanitize_email( (string) $data['to_email'] ) : '';
		$subject   = isset( $data['subject'] ) ? sanitize_text_field( (string) $data['subject'] ) : '';
		$subject   = wp_specialchars_decode( $subject, ENT_QUOTES );

		$body_text = isset( $data['body_text'] ) ? (string) $data['body_text'] : '';
		$body_html = isset( $data['body_html'] ) ? (string) $data['body_html'] : '';
		$raw       = isset( $data['raw_headers'] ) ? (string) $data['raw_headers'] : '';

		$folder   = isset( $data['imap_folder'] ) ? sanitize_text_field( (string) $data['imap_folder'] ) : '';
		$uidval   = isset( $data['imap_uidvalidity'] ) ? sanitize_text_field( (string) $data['imap_uidvalidity'] ) : '';

		$header_probe = array();
		if ( $in_reply_norm !== '' ) {
			$header_probe[] = $in_reply_norm;
		}
		foreach ( $refs as $r ) {
			if ( is_string( $r ) && $r !== '' && ! in_array( $r, $header_probe, true ) ) {
				$header_probe[] = $r;
			}
		}

		$ticket_id = null;
		$haystack  = $subject . "\n" . wp_strip_all_tags( $body_text ) . "\n" . wp_strip_all_tags( $body_html );
		foreach ( Biopentra_Contact_Inbox_Ticket_Ref::parse_ticket_numbers( $haystack ) as $cand_num ) {
			$row = Biopentra_Contact_Inbox_Ticket_Repository::get_by_ticket_number( $cand_num );
			if ( ! $row ) {
				continue;
			}
			$trow_id = (int) $row->id;
			$cust    = strtolower( (string) $row->customer_email );
			$froml   = strtolower( $from_email );
			$ok      = ( $cust === $froml ) || Biopentra_Contact_Inbox_Message_Repository::inbound_headers_match_ticket( $trow_id, $header_probe );
			if ( $ok ) {
				$ticket_id = $trow_id;
				break;
			}
		}

		if ( ! $ticket_id && $in_reply_norm ) {
			$ticket_id = Biopentra_Contact_Inbox_Message_Repository::find_ticket_id_by_message_id( $in_reply_norm );
		}
		if ( ! $ticket_id && ! empty( $refs ) ) {
			foreach ( $refs as $rid ) {
				$ticket_id = Biopentra_Contact_Inbox_Message_Repository::find_ticket_id_by_message_id( $rid );
				if ( $ticket_id ) {
					break;
				}
			}
		}
		if ( ! $ticket_id ) {
			$norm_sub = Biopentra_Contact_Inbox_Subject_Normalizer::normalize( $subject );
			$ticket   = Biopentra_Contact_Inbox_Ticket_Repository::find_latest_by_customer_and_normalized_subject( $from_email, $norm_sub );
			if ( $ticket ) {
				$ticket_id = (int) $ticket->id;
			}
		}

		global $wpdb;
		$use_tx = method_exists( $wpdb, 'query' );
		$now    = current_time( 'mysql' );
		if ( $use_tx ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'START TRANSACTION' );
		}

		$created_ticket = false;
		$new_ticket_id  = null;

		try {
			if ( ! $ticket_id ) {
				$source_ref = $mid_raw !== '' ? $mid_raw : ( $dedupe !== '' ? $dedupe : null );
				$new_ticket_id = Biopentra_Contact_Inbox_Ticket_Repository::insert(
					array(
						'source'          => 'email',
						'source_ref'      => $source_ref,
						'subject'         => $subject !== '' ? $subject : __( '(no subject)', 'biopentra-contact-inbox' ),
						'customer_email'  => $from_email,
						'customer_name'   => $from_name !== '' ? $from_name : null,
						'to_email'        => $to_email !== '' ? $to_email : null,
						'status'          => 'open',
						'is_unread'       => true,
						'last_message_at' => $now,
						'created_at'      => $now,
					)
				);
				if ( ! $new_ticket_id ) {
					throw new Exception( __( 'Could not create ticket.', 'biopentra-contact-inbox' ) );
				}
				$ticket_id      = (int) $new_ticket_id;
				$created_ticket = true;
			} else {
				Biopentra_Contact_Inbox_Ticket_Repository::bump_inbound_on_existing_thread( $ticket_id, $now );
				Biopentra_Contact_Inbox_Ticket_Repository::maybe_set_ticket_to_email( $ticket_id, $to_email );
			}

			$insert_row = array(
				'ticket_id'        => $ticket_id,
				'direction'        => 'inbound',
				'source'           => 'email',
				'message_id'       => $mid_raw !== '' ? $mid_raw : '',
				'in_reply_to'      => $in_reply_norm,
				'imap_folder'      => $folder !== '' ? $folder : null,
				'imap_uidvalidity' => $uidval !== '' ? $uidval : null,
				'imap_uid'         => $imap_uid > 0 ? $imap_uid : null,
				'imap_dedupe_key'  => $dedupe !== '' ? $dedupe : null,
				'from_email'       => $from_email,
				'from_name'        => $from_name !== '' ? $from_name : null,
				'to_email'         => $to_email !== '' ? $to_email : null,
				'subject'          => $subject !== '' ? $subject : __( '(no subject)', 'biopentra-contact-inbox' ),
				'body_text'        => $body_text,
				'body_html'        => $body_html !== '' ? $body_html : null,
				'raw_headers'      => $raw !== '' ? $raw : null,
				'created_at'       => $now,
			);

			$msg_ins = Biopentra_Contact_Inbox_Message_Repository::insert( $insert_row );
			if ( ! $msg_ins ) {
				$dup = false;
				if ( $mid && Biopentra_Contact_Inbox_Message_Repository::exists_by_message_id( $mid ) ) {
					$dup = true;
				}
				if ( ! $dup && $dedupe !== '' && Biopentra_Contact_Inbox_Message_Repository::exists_by_imap_dedupe_key( $dedupe ) ) {
					$dup = true;
				}
				if ( $dup ) {
					if ( $use_tx ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->query( 'ROLLBACK' );
					}
					if ( $created_ticket && $new_ticket_id ) {
						Biopentra_Contact_Inbox_Ticket_Repository::delete_ticket_and_messages( (int) $new_ticket_id );
					}
					return array(
						'success' => true,
						'status'  => 'skipped_duplicate',
						'reason'  => 'race',
					);
				}
				throw new Exception( __( 'Could not save message.', 'biopentra-contact-inbox' ) );
			}

			if ( $use_tx ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'COMMIT' );
			}

			return array(
				'success' => true,
				'status'  => 'imported',
			);
		} catch ( Exception $e ) {
			if ( $use_tx ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
			}
			if ( $created_ticket && $new_ticket_id ) {
				Biopentra_Contact_Inbox_Ticket_Repository::delete_ticket_and_messages( (int) $new_ticket_id );
			}
			return array(
				'success'    => false,
				'error_code' => 'import',
				'message'    => $e->getMessage(),
			);
		}
	}

	/**
	 * @param mixed $date_in RFC822, ISO8601, unix, or mysql datetime string.
	 * @return bool
	 */
	private static function is_parseable_date( $date_in ) {
		if ( $date_in === null || $date_in === '' ) {
			return false;
		}
		if ( is_numeric( $date_in ) ) {
			return (int) $date_in > 0;
		}
		$s = is_string( $date_in ) ? trim( $date_in ) : '';
		if ( $s === '' ) {
			return false;
		}
		$ts = strtotime( $s );
		return false !== $ts && $ts >= 0;
	}

	/**
	 * @param mixed $refs References header string or list of IDs.
	 * @return array<int, string> Normalized message IDs.
	 */
	private static function parse_references( $refs ) {
		$out = array();
		if ( is_array( $refs ) ) {
			foreach ( $refs as $tok ) {
				$n = Biopentra_Contact_Inbox_Message_Id::normalize( (string) $tok );
				if ( $n ) {
					$out[] = $n;
				}
			}
			return $out;
		}
		if ( is_string( $refs ) && $refs !== '' ) {
			$ref_block = preg_replace( '/\r\n\s+/', ' ', trim( $refs ) );
			foreach ( preg_split( '/\s+/', $ref_block ) as $tok ) {
				$n = Biopentra_Contact_Inbox_Message_Id::normalize( $tok );
				if ( $n ) {
					$out[] = $n;
				}
			}
		}
		return $out;
	}

}
