<?php
/**
 * Admin ticket list table.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Biopentra_Contact_Inbox_List_Table extends WP_List_Table {

	/**
	 * Screen ID for the Support Desk admin page (must match top-level menu slug {@code biopentra-inbox}).
	 */
	const SCREEN_LIST_HOOK = 'toplevel_page_biopentra-inbox';

	/**
	 * Column headers for WP_List_Table and for the {@see manage_{$screen->id}_columns} filter.
	 *
	 * @return array<string, string>
	 */
	public static function column_definitions() {
		return array(
			'cb'              => '<input type="checkbox" />',
			'ticket_num'      => __( 'Ticket #', 'biopentra-contact-inbox' ),
			'last_message_at' => __( 'Last activity', 'biopentra-contact-inbox' ),
			'subject'         => __( 'Subject', 'biopentra-contact-inbox' ),
			'customer'        => __( 'Customer', 'biopentra-contact-inbox' ),
			'to'              => __( 'To', 'biopentra-contact-inbox' ),
			'source'          => __( 'Source', 'biopentra-contact-inbox' ),
			'status'          => __( 'Status', 'biopentra-contact-inbox' ),
			'action'          => __( 'Action', 'biopentra-contact-inbox' ),
			'unread'          => __( 'Read', 'biopentra-contact-inbox' ),
		);
	}

	/**
	 * @param array<string, string> $columns Passed by WordPress (often empty before filter).
	 * @return array<string, string>
	 */
	public static function filter_screen_columns( $columns ) {
		return self::column_definitions();
	}

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ticket',
				'plural'   => 'tickets',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return self::column_definitions();
	}

	/**
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'ticket_num'      => array( 'ticket_number', true ),
			'last_message_at' => array( 'last_message_at', true ),
			'customer'        => array( 'customer_name', false ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'bulk_archive'     => __( 'Archive', 'biopentra-contact-inbox' ),
			'bulk_mark_read'   => __( 'Mark read', 'biopentra-contact-inbox' ),
			'bulk_mark_unread' => __( 'Mark unread', 'biopentra-contact-inbox' ),
		);
	}

	/**
	 * Process bulk POST before items are loaded.
	 */
	public static function process_bulk_request() {
		if ( empty( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( empty( $_POST['page'] ) || 'biopentra-inbox' !== $_POST['page'] ) {
			return;
		}
		$action = isset( $_POST['action'] ) && $_POST['action'] !== '-1'
			? sanitize_key( wp_unslash( $_POST['action'] ) )
			: ( isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '' );
		if ( ! in_array( $action, array( 'bulk_archive', 'bulk_mark_read', 'bulk_mark_unread' ), true ) ) {
			return;
		}
		check_admin_referer( 'bulk-tickets' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			return;
		}
		$ids = isset( $_POST['ticket'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ticket'] ) ) : array();
		$ids = array_values( array_filter( $ids ) );
		if ( empty( $ids ) && ! empty( $_POST['all_tickets'] ) ) {
			$desk = isset( $_POST['desk_status'] ) ? sanitize_key( wp_unslash( $_POST['desk_status'] ) ) : 'all';
			$s    = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
			$ids  = Biopentra_Contact_Inbox_Ticket_Repository::list_ticket_ids(
				array(
					'desk_status' => $desk,
					'search'      => $s,
				)
			);
		}
		if ( empty( $ids ) ) {
			return;
		}
		foreach ( $ids as $id ) {
			if ( 'bulk_archive' === $action ) {
				Biopentra_Contact_Inbox_Ticket_Repository::set_archived( $id );
			} elseif ( 'bulk_mark_read' === $action ) {
				Biopentra_Contact_Inbox_Ticket_Repository::mark_read( $id );
			} elseif ( 'bulk_mark_unread' === $action ) {
				Biopentra_Contact_Inbox_Ticket_Repository::set_unread( $id, true );
			}
		}
		$desk = isset( $_POST['desk_status'] ) ? sanitize_key( wp_unslash( $_POST['desk_status'] ) ) : 'all';
		$s    = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$args = array(
			'page'         => 'biopentra-inbox',
			'desk_status'  => $desk,
			's'            => $s,
			'bsd_bulk'     => '1',
		);
		$ob = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : '';
		$or = isset( $_POST['order'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['order'] ) ) ) : '';
		if ( in_array( $ob, array( 'ticket_number', 'id', 'customer_name', 'last_message_at' ), true ) ) {
			$args['orderby'] = $ob;
		}
		if ( in_array( $or, array( 'asc', 'desc' ), true ) ) {
			$args['order'] = $or;
		}
		$url = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	protected function column_ticket_num( $item ) {
		$tn = isset( $item->ticket_number ) && (int) $item->ticket_number > 0 ? (int) $item->ticket_number : (int) $item->id;
		$tag = Biopentra_Contact_Inbox_Ticket_Ref::bracket_tag( $tn );
		return $tag !== '' ? '<code>' . esc_html( $tag ) . '</code>' : '—';
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="ticket[]" value="%d" />',
			(int) $item->id
		);
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item->{$column_name} ) ? esc_html( (string) $item->{$column_name} ) : '—';
	}

	protected function column_last_message_at( $item ) {
		$t = isset( $item->last_message_at ) ? $item->last_message_at : '';
		if ( ! $t ) {
			return '—';
		}
		$url = add_query_arg(
			array(
				'page'      => 'biopentra-inbox',
				'ticket_id' => (int) $item->id,
			),
			admin_url( 'admin.php' )
		);
		return '<a href="' . esc_url( $url ) . '">' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t ) ) . '</a>';
	}

	/**
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_subject( $item ) {
		$sub = isset( $item->subject ) ? (string) $item->subject : '';
		$url = add_query_arg(
			array(
				'page'      => 'biopentra-inbox',
				'ticket_id' => (int) $item->id,
			),
			admin_url( 'admin.php' )
		);
		$title = '<a href="' . esc_url( $url ) . '">' . esc_html( $sub !== '' ? $sub : __( '(no subject)', 'biopentra-contact-inbox' ) ) . '</a>';

		$actions   = array();
		$actions['reply'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Reply', 'biopentra-contact-inbox' ) . '</a>';

		$tid      = (int) $item->id;
		$archived = ! empty( $item->archived_at );

		if ( $archived ) {
			$actions['unarchive'] = self::inline_detached_submit(
				self::detached_row_form_id( 'unarchive', $tid ),
				__( 'Unarchive', 'biopentra-contact-inbox' ),
				'visibility'
			);
		} else {
			$actions['archive'] = self::inline_detached_submit(
				self::detached_row_form_id( 'archive', $tid ),
				__( 'Archive', 'biopentra-contact-inbox' ),
				'archive'
			);
		}

		$unread = ! empty( $item->is_unread );
		if ( $unread ) {
			$actions['mark_read'] = self::inline_detached_submit(
				self::detached_row_form_id( 'mark_read', $tid ),
				__( 'Mark read', 'biopentra-contact-inbox' ),
				'yes'
			);
		} else {
			$actions['mark_unread'] = self::inline_detached_submit(
				self::detached_row_form_id( 'mark_unread', $tid ),
				__( 'Mark unread', 'biopentra-contact-inbox' ),
				'marker'
			);
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Stable DOM id for a detached POST form (must match buttons’ form="…" attribute).
	 *
	 * @param string $op        archive|unarchive|mark_read|mark_unread.
	 * @param int    $ticket_id Ticket PK.
	 * @return string
	 */
	public static function detached_row_form_id( $op, $ticket_id ) {
		$tid = (int) $ticket_id;
		switch ( sanitize_key( $op ) ) {
			case 'archive':
				return 'bsd-inbox-row-arch-' . $tid;
			case 'unarchive':
				return 'bsd-inbox-row-unarch-' . $tid;
			case 'mark_read':
				return 'bsd-inbox-row-mread-' . $tid;
			case 'mark_unread':
				return 'bsd-inbox-row-munread-' . $tid;
			default:
				return 'bsd-inbox-row-x-' . $tid;
		}
	}

	/**
	 * Submit button that POSTs a detached form (outside the bulk list form) via HTML5 form attribute.
	 *
	 * @param string $form_dom_id Detached form id.
	 * @param string $label       Accessible label.
	 * @param string $dashicon    dashicons-* suffix.
	 * @return string
	 */
	private static function inline_detached_submit( $form_dom_id, $label, $dashicon ) {
		$icon = sanitize_key( $dashicon );
		$html  = '<button type="submit" class="button-link" form="' . esc_attr( $form_dom_id ) . '" title="' . esc_attr( $label ) . '">';
		$html .= '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" style="font-size:16px;width:16px;height:16px;vertical-align:text-top;" aria-hidden="true"></span>';
		$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		$html .= '</button>';
		return $html;
	}

	/**
	 * Detached POST forms for row actions (must appear before the bulk list &lt;form&gt; — never nest forms).
	 *
	 * @param array<int, object> $items Current page rows.
	 */
	public static function render_detached_row_action_forms( array $items ) {
		if ( empty( $items ) ) {
			return;
		}
		$desk = isset( $_REQUEST['desk_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['desk_status'] ) ) : 'all';
		if ( ! in_array( $desk, array( 'all', 'open', 'pending', 'closed', 'unread', 'archived' ), true ) ) {
			$desk = 'all';
		}
		$s     = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$paged = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;
		list( $sob, $sor ) = self::list_request_sort();

		echo '<div class="bsd-inbox-detached-row-forms" style="display:none;" aria-hidden="true">';
		foreach ( $items as $item ) {
			$tid = (int) $item->id;
			if ( $tid <= 0 ) {
				continue;
			}
			$archived = ! empty( $item->archived_at );
			if ( ! $archived ) {
				self::print_detached_row_form(
					self::detached_row_form_id( 'archive', $tid ),
					'biopentra_inbox_ticket_archive',
					'biopentra_inbox_ticket_archive_' . $tid,
					$tid,
					$desk,
					$s,
					$sob,
					$sor,
					$paged
				);
			} else {
				self::print_detached_row_form(
					self::detached_row_form_id( 'unarchive', $tid ),
					'biopentra_inbox_ticket_unarchive',
					'biopentra_inbox_ticket_unarchive_' . $tid,
					$tid,
					$desk,
					$s,
					$sob,
					$sor,
					$paged
				);
			}
			$unread = ! empty( $item->is_unread );
			if ( $unread ) {
				self::print_detached_row_form(
					self::detached_row_form_id( 'mark_read', $tid ),
					'biopentra_inbox_ticket_mark_read',
					'biopentra_inbox_ticket_mark_read_' . $tid,
					$tid,
					$desk,
					$s,
					$sob,
					$sor,
					$paged
				);
			} else {
				self::print_detached_row_form(
					self::detached_row_form_id( 'mark_unread', $tid ),
					'biopentra_inbox_ticket_mark_unread',
					'biopentra_inbox_ticket_mark_unread_' . $tid,
					$tid,
					$desk,
					$s,
					$sob,
					$sor,
					$paged
				);
			}
		}
		echo '</div>';
	}

	/**
	 * @param string $html_id            Unique form id (DOM).
	 * @param string $admin_post_action  admin_post_{$action} hook name.
	 * @param string $nonce_action       wp_nonce_field action string.
	 * @param int    $ticket_id          Ticket PK.
	 * @param string $desk               desk_status.
	 * @param string $search             Search string.
	 * @param string $orderby            orderby key or ''.
	 * @param string $order              asc|desc or ''.
	 * @param int    $paged              Current list page.
	 */
	private static function print_detached_row_form( $html_id, $admin_post_action, $nonce_action, $ticket_id, $desk, $search, $orderby, $order, $paged ) {
		echo '<form id="' . esc_attr( $html_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bsd-inbox-row-action-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $admin_post_action ) . '" />';
		wp_nonce_field( $nonce_action, '_wpnonce', false );
		echo '<input type="hidden" name="ticket_id" value="' . esc_attr( (string) (int) $ticket_id ) . '" />';
		echo '<input type="hidden" name="desk_status" value="' . esc_attr( $desk ) . '" />';
		echo '<input type="hidden" name="s" value="' . esc_attr( $search ) . '" />';
		if ( $orderby !== '' ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $orderby ) . '" />';
		}
		if ( $order !== '' ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $order ) . '" />';
		}
		if ( $paged > 1 ) {
			echo '<input type="hidden" name="paged" value="' . esc_attr( (string) (int) $paged ) . '" />';
		}
		echo '</form>';
	}

	protected function column_customer( $item ) {
		$email = isset( $item->customer_email ) ? (string) $item->customer_email : '';
		$name  = isset( $item->customer_name ) && $item->customer_name !== null ? (string) $item->customer_name : '';
		$out   = '';
		if ( $name !== '' ) {
			$out .= esc_html( $name ) . '<br />';
		}
		$out .= $email !== '' ? esc_html( $email ) : '—';
		return $out;
	}

	protected function column_to( $item ) {
		$to = isset( $item->to_email ) && $item->to_email !== null ? (string) $item->to_email : '';
		if ( $to === '' || ! is_email( $to ) ) {
			return '—';
		}
		return '<a href="' . esc_url( 'mailto:' . $to ) . '">' . esc_html( $to ) . '</a>';
	}

	protected function column_source( $item ) {
		$src = isset( $item->source ) ? sanitize_key( (string) $item->source ) : '';
		$ref = isset( $item->source_ref ) && $item->source_ref !== null ? (string) $item->source_ref : '';
		$lbl = $src === 'fluent' ? __( 'Fluent', 'biopentra-contact-inbox' ) : ( $src === 'email' ? __( 'Email', 'biopentra-contact-inbox' ) : $src );
		if ( $ref !== '' && $src === 'fluent' ) {
			return esc_html( $lbl ) . ' <span class="description">#' . esc_html( $ref ) . '</span>';
		}
		return esc_html( $lbl !== '' ? $lbl : '—' );
	}

	protected function column_status( $item ) {
		$st = isset( $item->status ) ? sanitize_key( (string) $item->status ) : 'open';
		return '<span class="biopentra-inbox-ticket-status status-' . esc_attr( $st ) . '">' . esc_html( ucfirst( $st ) ) . '</span>';
	}

	/**
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_action( $item ) {
		$status = isset( $item->status ) ? sanitize_key( (string) $item->status ) : 'open';
		if ( 'closed' === $status ) {
			return '<span class="biopentra-inbox-action-badge closed">' . esc_html__( 'Closed', 'biopentra-contact-inbox' ) . '</span>';
		}
		$dir = isset( $item->bsd_last_direction ) ? sanitize_key( (string) $item->bsd_last_direction ) : '';
		if ( 'outbound' === $dir ) {
			return '<span class="biopentra-inbox-action-badge replied">' . esc_html__( 'Replied', 'biopentra-contact-inbox' ) . '</span>';
		}
		return '<span class="biopentra-inbox-action-badge needs-reply">' . esc_html__( 'Needs reply', 'biopentra-contact-inbox' ) . '</span>';
	}

	protected function column_unread( $item ) {
		$unread = ! empty( $item->is_unread );
		return $unread
			? '<span class="biopentra-inbox-status unreplied">' . esc_html__( 'Unread', 'biopentra-contact-inbox' ) . '</span>'
			: '<span class="biopentra-inbox-status replied">' . esc_html__( 'Read', 'biopentra-contact-inbox' ) . '</span>';
	}

	public function prepare_items() {
		$per_page = 20;
		$paged    = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;
		$desk     = isset( $_REQUEST['desk_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['desk_status'] ) ) : 'all';
		if ( ! in_array( $desk, array( 'all', 'open', 'pending', 'closed', 'unread', 'archived' ), true ) ) {
			$desk = 'all';
		}
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'last_message_at';
		if ( ! in_array( $orderby, array( 'ticket_number', 'id', 'customer_name', 'last_message_at' ), true ) ) {
			$orderby = 'last_message_at';
		}
		$order = isset( $_REQUEST['order'] ) ? strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) : 'desc';
		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

		$result = Biopentra_Contact_Inbox_Ticket_Repository::list_tickets(
			array(
				'per_page'    => $per_page,
				'paged'       => $paged,
				'desk_status' => $desk,
				'search'      => $search,
				'orderby'     => $orderby,
				'order'       => $order,
			)
		);

		$this->items = $result['items'];

		$ticket_ids = array();
		foreach ( $this->items as $row ) {
			$ticket_ids[] = (int) $row->id;
		}
		$directions = Biopentra_Contact_Inbox_Message_Repository::get_latest_direction_by_ticket_ids( $ticket_ids );
		foreach ( $this->items as $k => $row ) {
			$tid = (int) $row->id;
			$this->items[ $k ]->bsd_last_direction = isset( $directions[ $tid ] ) ? $directions[ $tid ] : '';
		}

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => $per_page > 0 ? (int) ceil( $result['total'] / $per_page ) : 1,
			)
		);
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$base = admin_url( 'admin.php' );
		$s    = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$desk = isset( $_REQUEST['desk_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['desk_status'] ) ) : 'all';

		$links = array(
			'all'      => __( 'All', 'biopentra-contact-inbox' ),
			'open'     => __( 'Open', 'biopentra-contact-inbox' ),
			'pending'  => __( 'Pending', 'biopentra-contact-inbox' ),
			'closed'   => __( 'Closed', 'biopentra-contact-inbox' ),
			'unread'   => __( 'Unread', 'biopentra-contact-inbox' ),
			'archived' => __( 'Archived', 'biopentra-contact-inbox' ),
		);

		echo '<div class="alignleft actions biopentra-inbox-filters">';
		list( $sob, $sor ) = self::list_request_sort();
		foreach ( $links as $key => $label ) {
			$q = array(
				'page'        => 'biopentra-inbox',
				'desk_status' => $key,
				's'           => $s,
			);
			if ( $sob !== '' ) {
				$q['orderby'] = $sob;
			}
			if ( $sor !== '' ) {
				$q['order'] = $sor;
			}
			$url   = add_query_arg( $q, $base );
			$class = ( $desk === $key ) ? 'button button-primary' : 'button';
			$n     = Biopentra_Contact_Inbox_Ticket_Repository::count_tickets(
				array(
					'desk_status' => $key,
					'search'      => '',
				)
			);
			$label_out = esc_html( $label ) . ' <span class="count">(' . esc_html( (string) (int) $n ) . ')</span>';
			echo '<a class="' . esc_attr( $class ) . '" style="margin-right:6px;" href="' . esc_url( $url ) . '">' . $label_out . '</a>';
		}
		echo '</div>';
	}

	/**
	 * Wrap list in POST form so bulk actions are not GET-based.
	 */
	public function display() {
		$this->prepare_items();

		$desk = isset( $_REQUEST['desk_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['desk_status'] ) ) : 'all';
		if ( ! in_array( $desk, array( 'all', 'open', 'pending', 'closed', 'unread', 'archived' ), true ) ) {
			$desk = 'all';
		}

		self::render_detached_row_action_forms( $this->items );

		echo '<form id="bsd-inbox-tickets" method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		wp_nonce_field( 'bulk-tickets' );
		echo '<input type="hidden" name="page" value="biopentra-inbox" />';
		echo '<input type="hidden" name="desk_status" value="' . esc_attr( $desk ) . '" />';
		list( $sob, $sor ) = self::list_request_sort();
		if ( $sob !== '' ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $sob ) . '" />';
		}
		if ( $sor !== '' ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $sor ) . '" />';
		}

		$this->search_box( __( 'Search tickets', 'biopentra-contact-inbox' ), 'ticket' );
		$this->display_tablenav( 'top' );

		echo '<table class="wp-list-table widefat fixed striped table-view-list tickets">';
		echo '<thead><tr>';
		$this->print_column_headers();
		echo '</tr></thead><tbody id="the-list" data-wp-lists="list:ticket">';
		if ( empty( $this->items ) ) {
			echo '<tr class="no-items"><td class="colspanchange" colspan="' . (int) count( $this->get_columns() ) . '">';
			$this->no_items();
			echo '</td></tr>';
		} else {
			foreach ( $this->items as $item ) {
				$this->single_row( $item );
			}
		}
		echo '</tbody>';
		echo '<tfoot><tr>';
		$this->print_column_headers( false );
		echo '</tr></tfoot>';
		echo '</table>';

		$this->display_tablenav( 'bottom' );
		echo '</form>';
	}

	/**
	 * Current list sort from the request (GET/POST), validated.
	 *
	 * @return array{0: string, 1: string} orderby key, asc|desc.
	 */
	private static function list_request_sort() {
		$ob = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : '';
		$or = isset( $_REQUEST['order'] ) ? strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) : '';
		if ( ! in_array( $ob, array( 'ticket_number', 'id', 'customer_name', 'last_message_at' ), true ) ) {
			$ob = '';
		}
		if ( ! in_array( $or, array( 'asc', 'desc' ), true ) ) {
			$or = '';
		}
		return array( $ob, $or );
	}

	/**
	 * Highlight rows where the latest message is inbound and the ticket is still unread (subtle; see admin CSS).
	 *
	 * @param object $item Row.
	 */
	public function single_row( $item ) {
		$classes  = array();
		$status   = isset( $item->status ) ? sanitize_key( (string) $item->status ) : 'open';
		$dir      = isset( $item->bsd_last_direction ) ? sanitize_key( (string) $item->bsd_last_direction ) : '';
		$unread   = ! empty( $item->is_unread );
		$archived = ! empty( $item->archived_at );
		if ( ! $archived && 'closed' !== $status && 'inbound' === $dir && $unread ) {
			$classes[] = 'biopentra-inbox-needs-reply-row';
		}
		$class_attr = $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '';
		echo '<tr' . $class_attr . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}
}
