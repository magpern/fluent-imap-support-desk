<?php
/**
 * Message persistence (per ticket).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Message_Repository {

	/**
	 * @param array<string, mixed> $data Message row.
	 * @return int|false
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_messages';
		$now   = isset( $data['created_at'] ) ? $data['created_at'] : current_time( 'mysql' );

		$mid = isset( $data['message_id'] ) ? Biopentra_Contact_Inbox_Message_Id::normalize( $data['message_id'] ) : null;
		$irt = isset( $data['in_reply_to'] ) ? Biopentra_Contact_Inbox_Message_Id::normalize( $data['in_reply_to'] ) : null;

		$row = array(
			'ticket_id'        => (int) $data['ticket_id'],
			'direction'        => sanitize_key( $data['direction'] ),
			'source'           => sanitize_key( $data['source'] ),
			'message_id'       => $mid,
			'in_reply_to'      => $irt,
			'imap_folder'      => self::null_or_trim( $data['imap_folder'] ?? null, 191 ),
			'imap_uidvalidity' => self::null_or_trim( $data['imap_uidvalidity'] ?? null, 64 ),
			'imap_uid'         => ( isset( $data['imap_uid'] ) && (int) $data['imap_uid'] > 0 ) ? (int) $data['imap_uid'] : null,
			'imap_dedupe_key'  => self::null_or_trim( $data['imap_dedupe_key'] ?? null, 191 ),
			'from_email'       => sanitize_email( $data['from_email'] ),
			'from_name'        => ! empty( $data['from_name'] ) ? sanitize_text_field( $data['from_name'] ) : null,
			'to_email'         => ! empty( $data['to_email'] ) ? sanitize_email( $data['to_email'] ) : null,
			'subject'          => sanitize_text_field( $data['subject'] ),
			'body_text'        => isset( $data['body_text'] ) ? $data['body_text'] : null,
			'body_html'        => isset( $data['body_html'] ) ? $data['body_html'] : null,
			'raw_headers'      => isset( $data['raw_headers'] ) ? $data['raw_headers'] : null,
			'created_at'       => $now,
		);

		$fmt = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$ok = $wpdb->insert( $table, $row, $fmt );
		if ( ! $ok ) {
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param mixed $v Value.
	 * @param int   $max Max length.
	 * @return string|null
	 */
	private static function null_or_trim( $v, $max ) {
		if ( null === $v || $v === '' ) {
			return null;
		}
		$s = sanitize_text_field( (string) $v );
		if ( $s === '' ) {
			return null;
		}
		if ( strlen( $s ) > $max ) {
			$s = substr( $s, 0, $max );
		}
		return $s;
	}

	/**
	 * @param string|null $normalized_id From Message_Id::normalize.
	 * @return bool
	 */
	public static function exists_by_message_id( $normalized_id ) {
		if ( null === $normalized_id || $normalized_id === '' ) {
			return false;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE message_id = %s", $normalized_id ) );
		return (int) $n > 0;
	}

	/**
	 * @param string|null $key SHA1 dedupe key.
	 * @return bool
	 */
	public static function exists_by_imap_dedupe_key( $key ) {
		if ( null === $key || $key === '' ) {
			return false;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE imap_dedupe_key = %s", $key ) );
		return (int) $n > 0;
	}

	/**
	 * @param string|null $normalized_id Message-ID.
	 * @return int|null Ticket ID.
	 */
	public static function find_ticket_id_by_message_id( $normalized_id ) {
		if ( null === $normalized_id || $normalized_id === '' ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tid = $wpdb->get_var( $wpdb->prepare( "SELECT ticket_id FROM {$table} WHERE message_id = %s LIMIT 1", $normalized_id ) );
		return $tid ? (int) $tid : null;
	}

	/**
	 * True if any normalized Message-ID belongs to this ticket (In-Reply-To / References threading proof).
	 *
	 * @param int               $ticket_id              Ticket PK.
	 * @param array<int, string> $normalized_message_ids Message-IDs (normalized, no angle brackets).
	 * @return bool
	 */
	public static function inbound_headers_match_ticket( $ticket_id, array $normalized_message_ids ) {
		$ticket_id = (int) $ticket_id;
		if ( $ticket_id <= 0 || empty( $normalized_message_ids ) ) {
			return false;
		}
		foreach ( $normalized_message_ids as $nid ) {
			if ( ! is_string( $nid ) || $nid === '' ) {
				continue;
			}
			$tid = self::find_ticket_id_by_message_id( $nid );
			if ( $tid && (int) $tid === $ticket_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int $ticket_id Ticket ID.
	 * @return array<int, object>
	 */
	public static function get_by_ticket( $ticket_id ) {
		global $wpdb;
		$ticket_id = (int) $ticket_id;
		if ( $ticket_id <= 0 ) {
			return array();
		}
		$table = $wpdb->prefix . 'biopentra_inbox_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY created_at ASC, id ASC",
				$ticket_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param string      $folder        Folder name.
	 * @param string|int  $uidvalidity UIDVALIDITY.
	 * @param int         $uid         UID.
	 * @return string|null
	 */
	public static function build_imap_dedupe_key( $folder, $uidvalidity, $uid ) {
		if ( $folder === '' || $folder === null || $uidvalidity === '' || $uidvalidity === null ) {
			return null;
		}
		$uid = (int) $uid;
		if ( $uid <= 0 ) {
			return null;
		}
		$f = strtolower( (string) $folder );
		$v = (string) $uidvalidity;
		return sha1( $f . '|' . $v . '|' . $uid );
	}

	/**
	 * @param int $ticket_id Ticket ID.
	 * @param int $limit     Max IDs to collect.
	 * @return array<int, string> Normalized message_ids.
	 */
	public static function get_message_id_chain( $ticket_id, $limit = 15 ) {
		$msgs = self::get_by_ticket( $ticket_id );
		$ids  = array();
		foreach ( $msgs as $m ) {
			if ( ! empty( $m->message_id ) ) {
				$ids[] = $m->message_id;
			}
		}
		$limit = max( 1, (int) $limit );
		return array_slice( $ids, -1 * $limit );
	}

	/**
	 * Latest message direction per ticket (by highest message row id). Used for list “Action” column.
	 *
	 * @param array<int, int> $ticket_ids Ticket primary keys.
	 * @return array<int, string> ticket_id => inbound|outbound
	 */
	public static function get_latest_direction_by_ticket_ids( array $ticket_ids ) {
		$ticket_ids = array_values( array_unique( array_map( 'absint', $ticket_ids ) ) );
		$ticket_ids = array_filter( $ticket_ids );
		if ( empty( $ticket_ids ) ) {
			return array();
		}
		global $wpdb;
		$table       = $wpdb->prefix . 'biopentra_inbox_messages';
		$placeholders = implode( ',', array_fill( 0, count( $ticket_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders match IN list
		$sql = "
			SELECT m.ticket_id, m.direction
			FROM {$table} m
			INNER JOIN (
				SELECT ticket_id, MAX(id) AS max_id
				FROM {$table}
				WHERE ticket_id IN ({$placeholders})
				GROUP BY ticket_id
			) t ON m.ticket_id = t.ticket_id AND m.id = t.max_id
		";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic IN placeholders
		$prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $ticket_ids ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $prepared );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			if ( ! isset( $r->ticket_id ) ) {
				continue;
			}
			$tid = (int) $r->ticket_id;
			$dir = isset( $r->direction ) ? sanitize_key( (string) $r->direction ) : '';
			if ( $tid > 0 && ( 'inbound' === $dir || 'outbound' === $dir ) ) {
				$out[ $tid ] = $dir;
			}
		}
		return $out;
	}
}
