<?php
/**
 * Ticket persistence.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Ticket_Repository {

	/**
	 * @param array<string, mixed> $data Row data.
	 * @return int|false Insert ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		$now   = current_time( 'mysql' );

		$ref = isset( $data['source_ref'] ) ? self::null_or_string( $data['source_ref'], 191 ) : null;

		$to = isset( $data['to_email'] ) ? sanitize_email( (string) $data['to_email'] ) : '';
		$to = is_email( $to ) ? $to : null;

		$row = array(
			'source'           => isset( $data['source'] ) ? sanitize_key( $data['source'] ) : 'email',
			'source_ref'       => $ref,
			'subject'          => isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : '',
			'customer_email'   => isset( $data['customer_email'] ) ? sanitize_email( $data['customer_email'] ) : '',
			'customer_name'    => ! empty( $data['customer_name'] ) ? sanitize_text_field( $data['customer_name'] ) : null,
			'to_email'         => $to,
			'status'           => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'open',
			'is_unread'        => ! empty( $data['is_unread'] ) ? 1 : 0,
			'assigned_user_id' => ! empty( $data['assigned_user_id'] ) ? absint( $data['assigned_user_id'] ) : null,
			'last_message_at'  => isset( $data['last_message_at'] ) ? $data['last_message_at'] : $now,
			'created_at'       => isset( $data['created_at'] ) ? $data['created_at'] : $now,
			'updated_at'       => $now,
		);
		$fmt = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );

		$ok = $wpdb->insert( $table, $row, $fmt );
		if ( ! $ok ) {
			return false;
		}
		$id = (int) $wpdb->insert_id;
		self::assign_ticket_number_to_row( $id );
		return $id;
	}

	/**
	 * Immutable public ticket number (defaults to same value as id).
	 *
	 * @param int $id Ticket primary key.
	 * @return void
	 */
	private static function assign_ticket_number_to_row( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'ticket_number' => $id ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * @param int $ticket_number Stored ticket_number column.
	 * @return object|null
	 */
	public static function get_by_ticket_number( $ticket_number ) {
		global $wpdb;
		$n = (int) $ticket_number;
		if ( $n <= 0 ) {
			return null;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ticket_number = %d LIMIT 1", $n ) );
		return $row ? $row : null;
	}

	/**
	 * @param mixed $v Value.
	 * @param int   $max Max length.
	 * @return string|null
	 */
	private static function null_or_string( $v, $max ) {
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
	 * @param string $source Source key.
	 * @param string $ref    Source ref.
	 * @return object|null
	 */
	public static function find_by_source( $source, $ref ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE source = %s AND source_ref = %s LIMIT 1",
				sanitize_key( $source ),
				(string) $ref
			)
		);
		return $row ? $row : null;
	}

	/**
	 * @param int $id Ticket ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
		return $row ? $row : null;
	}

	/**
	 * @param string $email Customer email.
	 * @param string $normalized_subject From Subject_Normalizer.
	 * @return object|null Latest matching ticket (any status; archived allowed — inbound bump clears archive).
	 */
	public static function find_latest_by_customer_and_normalized_subject( $email, $normalized_subject ) {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) || $normalized_subject === '' ) {
			return null;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		$like  = '%' . $wpdb->esc_like( $normalized_subject ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_email = %s AND subject LIKE %s ORDER BY last_message_at DESC, id DESC LIMIT 1",
				$email,
				$like
			)
		);
		return $row ? $row : null;
	}

	/**
	 * @deprecated Use find_latest_by_customer_and_normalized_subject.
	 * @param string $email Customer email.
	 * @param string $normalized_subject From Subject_Normalizer.
	 * @return object|null
	 */
	public static function find_open_by_customer_and_subject( $email, $normalized_subject ) {
		return self::find_latest_by_customer_and_normalized_subject( $email, $normalized_subject );
	}

	/**
	 * Non-archived unread count (menu badge / operational).
	 *
	 * @return int
	 */
	public static function count_operational_unread() {
		return self::count_tickets(
			array(
				'desk_status' => 'unread',
				'search'      => '',
			)
		);
	}

	/**
	 * Same filters as list_tickets; returns total row count.
	 *
	 * @param array<string, mixed> $args desk_status, search.
	 * @return int
	 */
	public static function count_tickets( array $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';

		list( $where_sql, $params ) = self::build_list_where( $args );

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params );
		} else {
			$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Ticket IDs matching list filters (for bulk “select all matching” actions).
	 *
	 * @param array<string, mixed> $args desk_status, search.
	 * @return array<int, int>
	 */
	public static function list_ticket_ids( array $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';

		list( $where_sql, $params ) = self::build_list_where( $args );

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( "SELECT id FROM {$table} WHERE {$where_sql}", $params );
		} else {
			$sql = "SELECT id FROM {$table} WHERE {$where_sql}";
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( $sql );
		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * @param array<string, mixed> $args desk_status, search, paged, per_page.
	 * @return array{where_sql: string, params: array<int, mixed>}
	 */
	private static function build_list_where( array $args ) {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		$desk = isset( $args['desk_status'] ) ? sanitize_key( (string) $args['desk_status'] ) : 'all';
		if ( 'archived' === $desk ) {
			$where[] = 'archived_at IS NOT NULL';
		} else {
			$where[] = '( archived_at IS NULL OR archived_at = %s )';
			$params[] = '0000-00-00 00:00:00';

			if ( in_array( $desk, array( 'open', 'pending', 'closed' ), true ) ) {
				$where[]  = 'status = %s';
				$params[] = $desk;
			} elseif ( 'unread' === $desk ) {
				$where[] = 'is_unread = 1';
			}
		}

		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		if ( $search !== '' ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			if ( ctype_digit( $search ) ) {
				$tid = (int) $search;
				$where[]  = '( id = %d OR ticket_number = %d OR subject LIKE %s OR customer_email LIKE %s OR ( to_email IS NOT NULL AND to_email LIKE %s ) OR ( customer_name IS NOT NULL AND customer_name LIKE %s ) )';
				$params[] = $tid;
				$params[] = $tid;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			} else {
				$tag = Biopentra_Contact_Inbox_Ticket_Ref::TAG_PREFIX;
				$where[]  = "( subject LIKE %s OR customer_email LIKE %s OR ( to_email IS NOT NULL AND to_email LIKE %s ) OR ( customer_name IS NOT NULL AND customer_name LIKE %s ) OR ( ticket_number IS NOT NULL AND CONCAT('[', '{$tag}-', ticket_number, ']') LIKE %s ) )";
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			}
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * @param array<string, mixed> $args desk_status, search, paged, per_page.
	 * @return array{items: array<int, object>, total: int}
	 */
	public static function list_tickets( array $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		$per   = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$page  = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$off   = ( $page - 1 ) * $per;

		list( $where_sql, $params ) = self::build_list_where( $args );

		$orderby = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'last_message_at';
		$col_map = array(
			'ticket_number'   => 'ticket_number',
			'id'              => 'id',
			'customer_name'     => 'customer_name',
			'last_message_at' => 'last_message_at',
		);
		if ( ! isset( $col_map[ $orderby ] ) ) {
			$orderby = 'last_message_at';
		}
		$order_col = $col_map[ $orderby ];
		$order_sql = ( isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params );
		} else {
			$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $count_sql );

		if ( ! empty( $params ) ) {
			$params2  = array_merge( $params, array( $per, $off ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- order_col is whitelisted; order_sql is ASC|DESC only.
			$list_sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_col} {$order_sql} LIMIT %d OFFSET %d",
				$params2
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$list_sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_col} {$order_sql} LIMIT %d OFFSET %d",
				$per,
				$off
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $list_sql );
		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * @param int    $id Ticket ID.
	 * @param string $status open|pending|closed.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'open', 'pending', 'closed' ), true ) ) {
			return false;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param int  $id Ticket ID.
	 * @param bool $unread True = unread.
	 * @return bool
	 */
	public static function set_unread( $id, $unread ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$table,
			array(
				'is_unread'  => $unread ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param int $id Ticket ID.
	 * @return bool
	 */
	public static function mark_read( $id ) {
		return self::set_unread( $id, false );
	}

	/**
	 * @param int         $id            Ticket ID.
	 * @param string|null $last_message_at MySQL datetime.
	 * @param bool|null   $is_unread     Optional override.
	 * @return bool
	 */
	public static function touch( $id, $last_message_at = null, $is_unread = null ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$data = array( 'updated_at' => current_time( 'mysql' ) );
		$fmt  = array( '%s' );
		if ( null !== $last_message_at ) {
			$data['last_message_at'] = $last_message_at;
			$fmt[]                   = '%s';
		}
		if ( null !== $is_unread ) {
			$data['is_unread'] = $is_unread ? 1 : 0;
			$fmt[]             = '%d';
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update( $table, $data, array( 'id' => $id ), $fmt, array( '%d' ) );
	}

	/**
	 * When an existing thread receives inbound mail: leave archive, mark unread, bump activity.
	 *
	 * @param int    $id              Ticket ID.
	 * @param string $last_message_at MySQL datetime.
	 * @return bool
	 */
	public static function bump_inbound_on_existing_thread( $id, $last_message_at ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ok = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET archived_at = NULL, is_unread = 1, last_message_at = %s, updated_at = %s WHERE id = %d",
				$last_message_at,
				current_time( 'mysql' ),
				$id
			)
		);
		return false !== $ok;
	}

	/**
	 * Set ticket-level To address when still empty (first non-empty inbound wins).
	 *
	 * @param int    $id       Ticket ID.
	 * @param string $to_email Recipient email.
	 * @return bool Whether an update ran.
	 */
	public static function maybe_set_ticket_to_email( $id, $to_email ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$to = sanitize_email( (string) $to_email );
		if ( ! is_email( $to ) ) {
			return false;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$q = $wpdb->prepare(
			"UPDATE {$table} SET to_email = %s, updated_at = %s WHERE id = %d AND ( to_email IS NULL OR to_email = '' )",
			$to,
			current_time( 'mysql' ),
			$id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $q );
		return $wpdb->rows_affected > 0;
	}

	/**
	 * @param int $id Ticket ID.
	 * @return bool
	 */
	public static function set_archived( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$table,
			array(
				'archived_at' => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param int $id Ticket ID.
	 * @return bool
	 */
	public static function clear_archived( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ok = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET archived_at = NULL, updated_at = %s WHERE id = %d",
				current_time( 'mysql' ),
				$id
			)
		);
		return false !== $ok;
	}

	/**
	 * Delete one ticket and all its messages (plugin tables only).
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return void
	 */
	public static function delete_ticket_and_messages( $ticket_id ) {
		global $wpdb;
		$ticket_id = (int) $ticket_id;
		if ( $ticket_id <= 0 ) {
			return;
		}
		$messages = $wpdb->prefix . 'biopentra_inbox_messages';
		$tickets  = $wpdb->prefix . 'biopentra_inbox_tickets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $messages, array( 'ticket_id' => $ticket_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $tickets, array( 'id' => $ticket_id ), array( '%d' ) );
	}
}
