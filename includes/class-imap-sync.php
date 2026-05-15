<?php
/**
 * IMAP import from Proton Bridge (PHP ext-imap).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Imap_Sync {

	const LOCK_KEY = 'biopentra_inbox_imap_sync_lock';

	/**
	 * @var self|null
	 */
	private static $instance;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Cron entry: lock, sync, persist summary.
	 */
	public function run_and_record() {
		if ( get_option( 'biopentra_inbox_email_enabled', 'no' ) !== 'yes' ) {
			return;
		}
		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}
		set_transient( self::LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );
		try {
			$result = $this->sync_once();
			update_option( 'biopentra_inbox_last_sync_at', current_time( 'mysql' ) );
			update_option( 'biopentra_inbox_last_sync_result', wp_json_encode( $result ) );
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * @return array{seen: int, imported: int, skipped: int, errors: array<int, string>}
	 */
	public function sync_once() {
		$out = array(
			'seen'     => 0,
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		if ( ! extension_loaded( 'imap' ) ) {
			$out['errors'][] = __( 'PHP IMAP extension is not loaded.', 'biopentra-contact-inbox' );
			return $out;
		}

		$host     = (string) get_option( 'biopentra_inbox_imap_host', '' );
		$port     = absint( get_option( 'biopentra_inbox_imap_port', 2143 ) );
		$user     = (string) get_option( 'biopentra_inbox_imap_user', '' );
		$pass     = self::get_imap_password();
		$mailbox  = (string) get_option( 'biopentra_inbox_imap_mailbox', 'INBOX' );
		$search   = trim( (string) get_option( 'biopentra_inbox_imap_search', 'UNSEEN' ) );
		$cap      = max( 1, (int) get_option( 'biopentra_inbox_sync_message_cap', 50 ) );
		$markseen = get_option( 'biopentra_inbox_imap_mark_seen', 'yes' ) === 'yes';

		if ( $host === '' || $port <= 0 || $user === '' ) {
			$out['errors'][] = __( 'IMAP host, port, and username are required.', 'biopentra-contact-inbox' );
			return $out;
		}

		$mb = '{' . $host . ':' . (string) $port . '/imap/tls/novalidate-cert}' . $mailbox;
		$conn = @imap_open( $mb, $user, $pass ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $conn ) {
			$out['errors'][] = '[biopentra-inbox-imap] ' . (string) imap_last_error();
			return $out;
		}

		$status = imap_status( $conn, $mb, SA_UIDVALIDITY );
		$uidval = ( $status && isset( $status->uidvalidity ) ) ? (string) $status->uidvalidity : '';

		$uids = imap_search( $conn, $search === '' ? 'ALL' : $search, SE_UID );
		if ( false === $uids || ! is_array( $uids ) ) {
			imap_close( $conn );
			return $out;
		}

		$uids = array_slice( array_map( 'intval', $uids ), 0, $cap );
		$out['seen'] = count( $uids );

		foreach ( $uids as $uid ) {
			try {
				$res = $this->import_one( $conn, $mb, $mailbox, $uidval, $uid, $markseen );
				if ( 'imported' === $res ) {
					++$out['imported'];
				} else {
					++$out['skipped'];
				}
			} catch ( Exception $e ) {
				$out['errors'][] = '[biopentra-inbox-imap] ' . $e->getMessage();
			}
		}

		imap_close( $conn );
		return $out;
	}

	/**
	 * @param resource $conn     IMAP stream.
	 * @param string   $mb       Full mailbox string for imap_status.
	 * @param string   $folder   Logical folder label.
	 * @param string   $uidval   UIDVALIDITY.
	 * @param int      $uid      Message UID.
	 * @param bool     $markseen Set Seen flag.
	 * @return string imported|skipped
	 */
	private function import_one( $conn, $mb, $folder, $uidval, $uid, $markseen ) {
		$ov = imap_fetch_overview( $conn, (string) $uid, FT_UID );
		if ( ! is_array( $ov ) || empty( $ov[0] ) ) {
			return 'skipped';
		}
		$o = $ov[0];

		$raw = imap_fetchheader( $conn, (string) $uid, FT_UID );

		$mid_raw = isset( $o->message_id ) ? (string) $o->message_id : '';

		$dedupe = '';
		if ( $folder !== '' && $uidval !== '' && $uid > 0 ) {
			$dedupe = Biopentra_Contact_Inbox_Message_Repository::build_imap_dedupe_key( $folder, $uidval, $uid );
		}

		$in_reply = '';
		if ( isset( $o->in_reply_to ) ) {
			$in_reply = (string) $o->in_reply_to;
		}
		if ( $in_reply === '' && $raw ) {
			if ( preg_match( '/^In-Reply-To:\s*(.+)$/mi', $raw, $m ) ) {
				$in_reply = trim( $m[1] );
			}
		}

		$refs = array();
		if ( $raw && preg_match( '/^References:\s*((?:.|\r\n\s)+)$/mi', $raw, $rm ) ) {
			$ref_block = preg_replace( '/\r\n\s+/', ' ', trim( $rm[1] ) );
			foreach ( preg_split( '/\s+/', $ref_block ) as $tok ) {
				$n = Biopentra_Contact_Inbox_Message_Id::normalize( $tok );
				if ( $n ) {
					$refs[] = $n;
				}
			}
		}

		$from_email = '';
		$from_name  = '';
		if ( ! empty( $o->from ) ) {
			$parsed = imap_rfc822_parse_adrlist( $o->from, '' );
			if ( is_array( $parsed ) && ! empty( $parsed[0] ) ) {
				$a = $parsed[0];
				if ( ! empty( $a->mailbox ) && ! empty( $a->host ) ) {
					$from_email = sanitize_email( $a->mailbox . '@' . $a->host );
				}
				if ( ! empty( $a->personal ) ) {
					$from_name = imap_utf8( (string) $a->personal );
				}
			}
		}

		$to_email = '';
		if ( ! empty( $o->to ) ) {
			$pto = imap_rfc822_parse_adrlist( $o->to, '' );
			if ( is_array( $pto ) && ! empty( $pto[0] ) && ! empty( $pto[0]->mailbox ) && ! empty( $pto[0]->host ) ) {
				$to_email = sanitize_email( $pto[0]->mailbox . '@' . $pto[0]->host );
			}
		}

		$subject = isset( $o->subject ) ? imap_utf8( (string) $o->subject ) : '';
		$subject = wp_specialchars_decode( $subject, ENT_QUOTES );

		$body_full = imap_body( $conn, (string) $uid, FT_UID );
		if ( false === $body_full ) {
			$body_full = '';
		}
		$body_html = '';
		$body_text = '';
		if ( stripos( $body_full, '<html' ) !== false || stripos( $body_full, '<body' ) !== false ) {
			$body_html = $body_full;
			$body_text = wp_strip_all_tags( $body_full );
		} else {
			$body_text = $body_full;
		}

		$date_raw = '';
		if ( isset( $o->date ) ) {
			$date_raw = (string) $o->date;
		}

		$payload = array(
			'message_id'       => $mid_raw,
			'in_reply_to'      => $in_reply,
			'references'       => $refs,
			'imap_folder'      => $folder,
			'imap_uidvalidity' => $uidval,
			'imap_uid'         => (int) $uid,
			'imap_dedupe_key'  => $dedupe,
			'from_email'       => $from_email,
			'from_name'        => $from_name,
			'to_email'         => $to_email,
			'subject'          => $subject,
			'body_text'        => $body_text,
			'body_html'        => $body_html,
			'raw_headers'      => $raw,
			'date'             => $date_raw !== '' ? $date_raw : gmdate( 'r' ),
		);

		$res = Biopentra_Contact_Inbox_Inbound_Import::import_payload( $payload );
		if ( empty( $res['success'] ) ) {
			$msg = isset( $res['message'] ) ? (string) $res['message'] : __( 'Import failed.', 'biopentra-contact-inbox' );
			throw new Exception( $msg );
		}
		if ( isset( $res['status'] ) && 'skipped_duplicate' === $res['status'] ) {
			return 'skipped';
		}

		if ( $markseen ) {
			@imap_setflag_full( $conn, (string) $uid, '\\Seen', ST_UID ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return 'imported';
	}

	/**
	 * @return string
	 */
	public static function get_imap_password() {
		if ( defined( 'BIOPENTRA_INBOX_IMAP_PASS' ) && BIOPENTRA_INBOX_IMAP_PASS !== '' ) {
			return (string) BIOPENTRA_INBOX_IMAP_PASS;
		}
		return (string) get_option( 'biopentra_inbox_imap_pass', '' );
	}
}
