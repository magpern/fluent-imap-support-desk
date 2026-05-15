<?php
/**
 * Fluent submission reads via $wpdb.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Submission_Repository {

	const REASON_LABELS = array(
		'general_question'  => 'General question',
		'shipping_question' => 'Shipping question',
		'order_issue'       => 'Order issue',
		'payment_question'  => 'Payment question',
		'product_request'   => 'Product request',
		'other'             => 'Other',
	);

	/**
	 * @param array $args form_id, per_page, paged, status (all|unreplied|replied), search.
	 * @return array{items: array<int, object>, total: int}
	 */
	public static function query_submissions( array $args ) {
		global $wpdb;

		$form_id  = (int) $args['form_id'];
		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;
		$status   = isset( $args['status'] ) ? $args['status'] : 'all';
		$search   = isset( $args['search'] ) ? sanitize_text_field( wp_unslash( $args['search'] ) ) : '';

		$sub_table = $wpdb->prefix . 'fluentform_submissions';
		$rep_table = $wpdb->prefix . 'biopentra_inbox_replies';

		$where_parts   = array();
		$where_parts[] = $wpdb->prepare( 's.form_id = %d', $form_id );
		// NULL status must be allowed (SQL: NULL != 'trashed' is not TRUE).
		$where_parts[] = $wpdb->prepare( '( s.status IS NULL OR s.status != %s )', 'trashed' );

		if ( $search !== '' ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts[] = $wpdb->prepare( 's.response LIKE %s', $like );
		}

		$where = implode( ' AND ', $where_parts );

		if ( 'unreplied' === $status ) {
			$join = " LEFT JOIN {$rep_table} r ON r.submission_id = s.id AND r.form_id = s.form_id ";
			$where .= ' AND r.id IS NULL ';
		} elseif ( 'replied' === $status ) {
			$join = " INNER JOIN {$rep_table} r ON r.submission_id = s.id AND r.form_id = s.form_id ";
		} else {
			$join = '';
		}

		$count_sql = "SELECT COUNT(DISTINCT s.id) FROM {$sub_table} s {$join} WHERE {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fully built with wpdb->prepare parts
		$total = (int) $wpdb->get_var( $count_sql );

		$sql = "SELECT DISTINCT s.* FROM {$sub_table} s {$join} WHERE {$where} ORDER BY s.created_at DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql_full = $wpdb->prepare( $sql, $per_page, $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $sql_full );

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Non-trashed submission count for one form (ignores inbox replied/unreplied filters).
	 *
	 * @param int $form_id Fluent form ID.
	 * @return int
	 */
	public static function count_for_form( $form_id ) {
		global $wpdb;
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return 0;
		}
		$table = $wpdb->prefix . 'fluentform_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE form_id = %d AND ( status IS NULL OR status != %s )",
				$form_id,
				'trashed'
			)
		);
		return (int) $n;
	}

	/**
	 * Non-trashed submissions across all Fluent forms.
	 *
	 * @return int
	 */
	public static function count_all_non_trashed() {
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ( status IS NULL OR status != %s )",
				'trashed'
			)
		);
		return (int) $n;
	}

	/**
	 * @param int $submission_id Submission PK.
	 * @param int $form_id       Scoped form.
	 * @return object|null
	 */
	public static function get_submission( $submission_id, $form_id ) {
		global $wpdb;
		$submission_id = (int) $submission_id;
		$form_id       = (int) $form_id;
		if ( $submission_id <= 0 || $form_id <= 0 ) {
			return null;
		}
		$table = $wpdb->prefix . 'fluentform_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND form_id = %d AND ( status IS NULL OR status != %s ) LIMIT 1",
				$submission_id,
				$form_id,
				'trashed'
			)
		);
		return $row ? $row : null;
	}

	/**
	 * @param string|null $json Response JSON.
	 * @return array<string, mixed>
	 */
	public static function decode_response( $json ) {
		if ( ! is_string( $json ) || $json === '' ) {
			return array();
		}
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * @param string $key Stored value.
	 * @return string
	 */
	public static function reason_label( $key ) {
		$key = is_string( $key ) ? $key : '';
		return isset( self::REASON_LABELS[ $key ] ) ? self::REASON_LABELS[ $key ] : $key;
	}

	/**
	 * @param array $response Decoded response.
	 * @return string
	 */
	/**
	 * Best-effort name for list/detail (Fluent field keys vary).
	 *
	 * @param array<string, mixed> $data Decoded response.
	 * @return string
	 */
	public static function extract_submitter_name( array $data ) {
		if ( ! empty( $data['full_name'] ) && is_string( $data['full_name'] ) ) {
			return trim( $data['full_name'] );
		}
		foreach ( array( 'your_name', 'name', 'customer_name', 'fullName' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$val = trim( $data[ $key ] );
				if ( $val !== '' ) {
					return $val;
				}
			}
		}
		if ( empty( $data['names'] ) ) {
			return '';
		}
		$names = $data['names'];
		if ( is_string( $names ) ) {
			$decoded = json_decode( $names, true );
			$names   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $names ) ) {
			return '';
		}
		$first = isset( $names['first_name'] ) ? (string) $names['first_name'] : '';
		$last  = isset( $names['last_name'] ) ? (string) $names['last_name'] : '';
		$both  = trim( $first . ' ' . $last );
		if ( $both !== '' ) {
			return $both;
		}
		if ( ! empty( $names['value'] ) && is_string( $names['value'] ) ) {
			return trim( $names['value'] );
		}
		return '';
	}

	/**
	 * Best-effort email (Fluent field keys vary).
	 *
	 * @param array<string, mixed> $data Decoded response.
	 * @return string Sanitized email or empty.
	 */
	public static function extract_submitter_email( array $data ) {
		foreach ( array( 'email', 'email_address', 'e_mail', 'contact_email', 'your-email', 'your_email' ) as $key ) {
			if ( empty( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$candidate = sanitize_email( $data[ $key ] );
			if ( is_email( $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	public static function context_summary( array $response ) {
		$parts = array();
		if ( ! empty( $response['wc_related_order'] ) ) {
			$parts[] = sprintf(
				/* translators: %s order id */
				__( 'Order: %s', 'biopentra-contact-inbox' ),
				$response['wc_related_order']
			);
		}
		if ( ! empty( $response['requested_product'] ) ) {
			$parts[] = sprintf(
				/* translators: %s product text */
				__( 'Product: %s', 'biopentra-contact-inbox' ),
				$response['requested_product']
			);
		}
		return $parts ? implode( ' · ', $parts ) : '—';
	}

	/**
	 * Human-readable lines from a decoded Fluent response (no raw JSON dumps).
	 *
	 * @param array<string, mixed> $data Decoded response.
	 * @return array<int, string> Lines suitable for email quotes or admin summaries.
	 */
	public static function human_summary_lines( array $data ) {
		$lines = array();
		$name  = self::extract_submitter_name( $data );
		if ( $name !== '' ) {
			$lines[] = __( 'Name:', 'biopentra-contact-inbox' ) . ' ' . $name;
		}
		$email = self::extract_submitter_email( $data );
		if ( $email !== '' ) {
			$lines[] = __( 'Email:', 'biopentra-contact-inbox' ) . ' ' . $email;
		}
		if ( ! empty( $data['reason'] ) && is_string( $data['reason'] ) ) {
			$lines[] = __( 'Reason:', 'biopentra-contact-inbox' ) . ' ' . self::reason_label( $data['reason'] );
		}
		if ( ! empty( $data['message'] ) && is_string( $data['message'] ) ) {
			$msg = trim( wp_strip_all_tags( $data['message'] ) );
			if ( $msg !== '' ) {
				$lines[] = __( 'Message:', 'biopentra-contact-inbox' ) . "\n" . $msg;
			}
		}
		$ctx = self::context_summary( $data );
		if ( $ctx !== '' && $ctx !== '—' ) {
			$lines[] = $ctx;
		}
		return $lines;
	}
}
