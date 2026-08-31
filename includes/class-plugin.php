<?php
/**
 * Admin menu, Support Desk screens, reply and maintenance handlers.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Plugin {

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

	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_process_ticket_bulk_action' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_biopentra_inbox_reply', array( $this, 'handle_reply_post' ) );
		add_action( 'admin_post_biopentra_inbox_run_imap_sync', array( $this, 'handle_run_imap_sync' ) );
		add_action( 'admin_post_biopentra_inbox_migrate_fluent', array( $this, 'handle_migrate_fluent' ) );
		add_action( 'admin_post_biopentra_inbox_ticket_status', array( $this, 'handle_ticket_status' ) );
		add_action( 'admin_post_biopentra_inbox_rotate_worker_token', array( $this, 'handle_rotate_worker_token' ) );
		add_action( 'admin_post_biopentra_inbox_check_worker_http_health', array( $this, 'handle_check_worker_http_health' ) );
		add_action( 'admin_post_biopentra_inbox_trigger_worker_mailbox_check', array( $this, 'handle_trigger_worker_mailbox_check' ) );
		add_action( 'admin_post_biopentra_inbox_reset_support_desk', array( $this, 'handle_reset_support_desk' ) );
		add_action( 'admin_post_biopentra_inbox_save_archive_retention', array( $this, 'handle_save_archive_retention' ) );
		add_action( 'admin_post_biopentra_inbox_ticket_archive', array( $this, 'handle_ticket_archive' ) );
		add_action( 'admin_post_biopentra_inbox_ticket_unarchive', array( $this, 'handle_ticket_unarchive' ) );
		add_action( 'admin_post_biopentra_inbox_ticket_mark_read', array( $this, 'handle_ticket_mark_read' ) );
		add_action( 'admin_post_biopentra_inbox_ticket_mark_unread', array( $this, 'handle_ticket_mark_unread' ) );
		add_action( 'admin_post_biopentra_inbox_test_smtp', array( $this, 'handle_test_smtp' ) );
		add_action( 'admin_post_biopentra_inbox_preview_reply_template', array( $this, 'handle_preview_reply_template' ) );

		add_action( 'admin_menu', array( $this, 'add_inbox_menu_badge' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter(
			'manage_' . Biopentra_Contact_Inbox_List_Table::SCREEN_LIST_HOOK . '_columns',
			array( 'Biopentra_Contact_Inbox_List_Table', 'filter_screen_columns' )
		);
	}

	public function register_settings() {
		Biopentra_Contact_Inbox_Settings::register();
	}

	/**
	 * Handle ticket list bulk POST before admin headers (redirect must run early).
	 */
	public function maybe_process_ticket_bulk_action() {
		Biopentra_Contact_Inbox_List_Table::process_bulk_request();
	}

	public function register_menu() {
		$title = get_option( 'biopentra_inbox_display_name', __( 'Fluent IMAP Support Desk', 'biopentra-contact-inbox' ) );
		if ( ! is_string( $title ) || $title === '' ) {
			$title = __( 'Fluent IMAP Support Desk', 'biopentra-contact-inbox' );
		}

		add_menu_page(
			$title,
			$title,
			BIOPENTRA_INBOX_CAP,
			'biopentra-inbox',
			array( $this, 'render_entries_page' ),
			'dashicons-email-alt',
			58
		);

		add_submenu_page(
			'biopentra-inbox',
			__( 'Tickets', 'biopentra-contact-inbox' ),
			__( 'Tickets', 'biopentra-contact-inbox' ),
			BIOPENTRA_INBOX_CAP,
			'biopentra-inbox',
			array( $this, 'render_entries_page' )
		);

		add_submenu_page(
			'biopentra-inbox',
			__( 'Settings', 'biopentra-contact-inbox' ),
			__( 'Settings', 'biopentra-contact-inbox' ),
			BIOPENTRA_INBOX_CAP,
			'biopentra-inbox-settings',
			array( 'Biopentra_Contact_Inbox_Settings', 'render_page' )
		);
	}

	/**
	 * List screen styles (Action column badges, subtle needs-reply row).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_biopentra-inbox' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'biopentra-inbox-list',
			BIOPENTRA_INBOX_URL . 'assets/admin-inbox-list.css',
			array(),
			BIOPENTRA_INBOX_VERSION
		);
	}

	public function render_entries_page() {
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'biopentra-contact-inbox' ) );
		}

		$this->maybe_flash_notices();

		if ( ! Biopentra_Contact_Inbox_Form_Resolver::fluent_tables_exist() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Fluent Forms does not appear to be installed or its database tables are missing. You can still use email-based tickets after IMAP sync.', 'biopentra-contact-inbox' ) . '</p></div>';
		}

		$form_id = Biopentra_Contact_Inbox_Form_Resolver::get_form_id();
		if ( $form_id <= 0 ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'No Fluent Forms form is selected. Open Settings → General to choose a form for migration and legacy submission links.', 'biopentra-contact-inbox' );
			echo '</p></div>';
		}

		$ticket_id = isset( $_GET['ticket_id'] ) ? (int) $_GET['ticket_id'] : 0;
		if ( $ticket_id > 0 ) {
			$this->render_ticket_detail( $ticket_id );
			return;
		}

		$submission_id = isset( $_GET['submission_id'] ) ? (int) $_GET['submission_id'] : 0;
		if ( $submission_id > 0 ) {
			if ( $form_id <= 0 ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Configure a Fluent Forms form in Settings before opening legacy submissions.', 'biopentra-contact-inbox' ) . '</p></div></div>';
				return;
			}
			$ticket_from_fluent = Biopentra_Contact_Inbox_Fluent_Migration::ensure_ticket_for_submission( $submission_id, $form_id );
			if ( $ticket_from_fluent ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'      => 'biopentra-inbox',
							'ticket_id' => $ticket_from_fluent,
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
			$this->render_legacy_submission( $submission_id, $form_id );
			return;
		}

		$this->render_ticket_list( $form_id );
	}

	/**
	 * @param int $ticket_id Ticket primary key.
	 */
	private function render_ticket_detail( $ticket_id ) {
		$ticket = Biopentra_Contact_Inbox_Ticket_Repository::get( $ticket_id );
		echo '<div class="wrap">';
		if ( ! $ticket ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Ticket not found.', 'biopentra-contact-inbox' ) . '</p></div>';
			$list = add_query_arg( array( 'page' => 'biopentra-inbox' ), admin_url( 'admin.php' ) );
			echo '<p><a href="' . esc_url( $list ) . '">' . esc_html__( 'Back to tickets', 'biopentra-contact-inbox' ) . '</a></p>';
			echo '</div>';
			return;
		}

		Biopentra_Contact_Inbox_Ticket_Repository::mark_read( $ticket_id );
		$ticket = Biopentra_Contact_Inbox_Ticket_Repository::get( $ticket_id );

		$messages = Biopentra_Contact_Inbox_Message_Repository::get_by_ticket( $ticket_id );
		Biopentra_Contact_Inbox_Admin_Detail::render_ticket( $ticket, $messages );
		echo '</div>';
	}

	/**
	 * @param int $form_id Resolved form ID (may be 0).
	 */
	private function render_ticket_list( $form_id ) {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Tickets', 'biopentra-contact-inbox' ) . '</h1>';

		if ( ! empty( $_GET['bsd_bulk'] ) && '1' === $_GET['bsd_bulk'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Bulk action completed.', 'biopentra-contact-inbox' ) . '</p></div>';
		}

		if ( $form_id > 0 ) {
			$this->maybe_wrong_form_notice( $form_id );
		}

		$list_table = new Biopentra_Contact_Inbox_List_Table();
		$list_table->display();
		echo '</div>';
	}

	/**
	 * @param int $submission_id Submission PK.
	 * @param int $form_id       Scoped form.
	 */
	private function render_legacy_submission( $submission_id, $form_id ) {
		echo '<div class="wrap">';

		$row = Biopentra_Contact_Inbox_Submission_Repository::get_submission( $submission_id, $form_id );
		if ( ! $row ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Submission not found.', 'biopentra-contact-inbox' ) . '</p></div>';
			$list = add_query_arg( array( 'page' => 'biopentra-inbox' ), admin_url( 'admin.php' ) );
			echo '<p><a href="' . esc_url( $list ) . '">' . esc_html__( 'Back to tickets', 'biopentra-contact-inbox' ) . '</a></p>';
			echo '</div>';
			return;
		}

		$history = Biopentra_Contact_Inbox_Reply_Repository::get_history( $submission_id, $form_id );
		Biopentra_Contact_Inbox_Admin_Detail::render( $row, $form_id, $history );
		echo '</div>';
	}

	/**
	 * Warn when the configured form has no rows but Fluent has other submissions (often wrong form ID in settings).
	 *
	 * @param int $form_id Resolved Fluent form ID.
	 */
	private function maybe_wrong_form_notice( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return;
		}

		$for_this = Biopentra_Contact_Inbox_Submission_Repository::count_for_form( $form_id );
		if ( $for_this > 0 ) {
			return;
		}

		$total = Biopentra_Contact_Inbox_Submission_Repository::count_all_non_trashed();
		if ( $total <= 0 ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=biopentra-inbox-settings' );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: Fluent form ID */
				__( 'No submissions were found for the Fluent Forms form currently selected for this inbox (form ID %d), but other Fluent Forms submissions exist in the database. The selected form may be wrong.', 'biopentra-contact-inbox' ),
				$form_id
			)
		);
		echo ' ';
		echo '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Review Support Desk → Settings', 'biopentra-contact-inbox' ) . '</a>';
		echo '</p></div>';
	}

	private function maybe_flash_notices() {
		if ( ! empty( $_GET['bsd_notice'] ) ) {
			$n = sanitize_key( wp_unslash( $_GET['bsd_notice'] ) );
			$msg = '';
			switch ( $n ) {
				case 'archived':
					$msg = __( 'Ticket archived.', 'biopentra-contact-inbox' );
					break;
				case 'unarchived':
					$msg = __( 'Ticket unarchived.', 'biopentra-contact-inbox' );
					break;
				case 'marked_read':
					$msg = __( 'Ticket marked read.', 'biopentra-contact-inbox' );
					break;
				case 'marked_unread':
					$msg = __( 'Ticket marked unread.', 'biopentra-contact-inbox' );
					break;
			}
			if ( $msg !== '' ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}

		if ( ! empty( $_GET['bsd_err'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['bsd_err'] ) );
			$emsg = __( 'Something went wrong.', 'biopentra-contact-inbox' );
			switch ( $code ) {
				case 'cap':
					$emsg = __( 'You do not have permission to do that.', 'biopentra-contact-inbox' );
					break;
				case 'nonce':
					$emsg = __( 'Security check failed. Please try again.', 'biopentra-contact-inbox' );
					break;
				case 'bad_ticket':
					$emsg = __( 'Missing or invalid ticket.', 'biopentra-contact-inbox' );
					break;
				case 'unknown':
					$emsg = __( 'Unknown action.', 'biopentra-contact-inbox' );
					break;
			}
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $emsg ) . '</p></div>';
		}

		if ( ! empty( $_GET['reply_sent'] ) && '1' === $_GET['reply_sent'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reply sent.', 'biopentra-contact-inbox' ) . '</p></div>';
		}

		if ( ! empty( $_GET['bsd_status'] ) && '1' === $_GET['bsd_status'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ticket status updated.', 'biopentra-contact-inbox' ) . '</p></div>';
		}

		if ( ! empty( $_GET['reply_err'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['reply_err'] ) );
			$msg  = __( 'Could not send reply.', 'biopentra-contact-inbox' );

			switch ( $code ) {
				case 'no_form':
					$msg = __( 'No form is configured for this inbox.', 'biopentra-contact-inbox' );
					break;
				case 'not_found':
					$msg = __( 'That submission could not be loaded.', 'biopentra-contact-inbox' );
					break;
				case 'no_ticket':
					$msg = __( 'That ticket could not be loaded.', 'biopentra-contact-inbox' );
					break;
				case 'bad_email':
					$msg = __( 'The recipient email is invalid.', 'biopentra-contact-inbox' );
					break;
				case 'empty_body':
					$msg = __( 'Message body is required.', 'biopentra-contact-inbox' );
					break;
				case 'send_failed':
					$msg = __( 'wp_mail() failed to send. Check your mail configuration or SMTP plugin.', 'biopentra-contact-inbox' );
					break;
			}

			echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	public function handle_reply_post() {
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to send replies.', 'biopentra-contact-inbox' ) );
		}

		$back = admin_url( 'admin.php' );

		$ticket_id = isset( $_POST['ticket_id'] ) ? (int) $_POST['ticket_id'] : 0;
		if ( $ticket_id > 0 ) {
			check_admin_referer( 'biopentra_inbox_reply_ticket_' . $ticket_id );

			$ticket = Biopentra_Contact_Inbox_Ticket_Repository::get( $ticket_id );
			if ( ! $ticket ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'reply_err' => 'no_ticket' ), $back ) );
				exit;
			}

			$to = isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '';
			if ( ! is_email( $to ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'ticket_id' => $ticket_id, 'reply_err' => 'bad_email' ), $back ) );
				exit;
			}

			$subject = isset( $_POST['reply_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['reply_subject'] ) ) : '';
			$body    = isset( $_POST['reply_body'] ) ? wp_unslash( $_POST['reply_body'] ) : '';
			$body    = wp_kses_post( $body );

			if ( trim( wp_strip_all_tags( $body ) ) === '' ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'ticket_id' => $ticket_id, 'reply_err' => 'empty_body' ), $back ) );
				exit;
			}

			$result = Biopentra_Contact_Inbox_Mailer::send_ticket_reply( $ticket_id, $to, $subject, $body );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'ticket_id' => $ticket_id, 'reply_err' => 'send_failed' ), $back ) );
				exit;
			}

			$form_id = Biopentra_Contact_Inbox_Form_Resolver::get_form_id();
			if ( get_option( 'biopentra_inbox_store_reply_history', 'yes' ) === 'yes' && $form_id > 0 && isset( $ticket->source ) && 'fluent' === $ticket->source && ! empty( $ticket->source_ref ) ) {
				$sid = (int) $ticket->source_ref;
				if ( $sid > 0 ) {
					$tn            = isset( $ticket->ticket_number ) && (int) $ticket->ticket_number > 0 ? (int) $ticket->ticket_number : $ticket_id;
					$base_sub      = $subject !== '' ? $subject : fisd_get_default_reply_subject();
					$final_subject = Biopentra_Contact_Inbox_Ticket_Ref::format_subject( $base_sub, $tn );
					Biopentra_Contact_Inbox_Reply_Repository::insert(
						array(
							'submission_id'   => $sid,
							'form_id'         => $form_id,
							'admin_user_id'   => get_current_user_id(),
							'recipient_email' => $to,
							'subject'         => $final_subject,
							'body'            => $body,
							'sent_at'         => current_time( 'mysql' ),
						)
					);
				}
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'ticket_id' => $ticket_id, 'reply_sent' => '1' ), $back ) );
			exit;
		}

		if ( empty( $_POST['submission_id'] ) ) {
			wp_die( esc_html__( 'Missing submission or ticket.', 'biopentra-contact-inbox' ) );
		}

		$sid = (int) $_POST['submission_id'];
		check_admin_referer( 'biopentra_inbox_reply_' . $sid );

		$form_id = Biopentra_Contact_Inbox_Form_Resolver::get_form_id();

		if ( $form_id <= 0 ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'submission_id' => $sid, 'reply_err' => 'no_form' ), $back ) );
			exit;
		}

		$row = Biopentra_Contact_Inbox_Submission_Repository::get_submission( $sid, $form_id );
		if ( ! $row ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'reply_err' => 'not_found' ), $back ) );
			exit;
		}

		$to = isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '';
		if ( ! is_email( $to ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'submission_id' => $sid, 'reply_err' => 'bad_email' ), $back ) );
			exit;
		}

		$subject = isset( $_POST['reply_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['reply_subject'] ) ) : '';
		$body    = isset( $_POST['reply_body'] ) ? wp_unslash( $_POST['reply_body'] ) : '';
		$body    = wp_kses_post( $body );

		if ( trim( wp_strip_all_tags( $body ) ) === '' ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'submission_id' => $sid, 'reply_err' => 'empty_body' ), $back ) );
			exit;
		}

		$tid_form = Biopentra_Contact_Inbox_Fluent_Migration::ensure_ticket_for_submission( $sid, $form_id );
		if ( $tid_form ) {
			$trow = Biopentra_Contact_Inbox_Ticket_Repository::get( (int) $tid_form );
			if ( $trow ) {
				$tn = isset( $trow->ticket_number ) && (int) $trow->ticket_number > 0 ? (int) $trow->ticket_number : (int) $tid_form;
				$subject = Biopentra_Contact_Inbox_Ticket_Ref::format_subject( $subject, $tn );
			}
		}

		$result = Biopentra_Contact_Inbox_Mailer::send_reply( $to, $subject, $body );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'submission_id' => $sid, 'reply_err' => 'send_failed' ), $back ) );
			exit;
		}

		if ( get_option( 'biopentra_inbox_store_reply_history', 'yes' ) === 'yes' ) {
			$final_subject = $subject !== '' ? $subject : fisd_get_default_reply_subject();
			Biopentra_Contact_Inbox_Reply_Repository::insert(
				array(
					'submission_id'   => $sid,
					'form_id'         => $form_id,
					'admin_user_id'   => get_current_user_id(),
					'recipient_email' => $to,
					'subject'         => $final_subject,
					'body'            => $body,
					'sent_at'         => current_time( 'mysql' ),
				)
			);
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'biopentra-inbox', 'submission_id' => $sid, 'reply_sent' => '1' ), $back ) );
		exit;
	}

	public function handle_run_imap_sync() {
		check_admin_referer( 'biopentra_inbox_run_imap_sync' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to run sync.', 'biopentra-contact-inbox' ) );
		}

		$redirect = add_query_arg(
			array(
				'page'     => 'biopentra-inbox-settings',
				'tab'      => 'sync',
				'bsd_sync' => '1',
			),
			admin_url( 'admin.php' )
		);

		if ( Biopentra_Contact_Inbox_Cron::import_driver() !== 'php_imap' ) {
			$redirect = add_query_arg( 'bsd_sync', 'worker_driver', $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( get_transient( Biopentra_Contact_Inbox_Imap_Sync::LOCK_KEY ) ) {
			$redirect = add_query_arg( 'bsd_sync', 'busy', $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}

		Biopentra_Contact_Inbox_Imap_Sync::instance()->run_and_record();
		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_rotate_worker_token() {
		check_admin_referer( 'biopentra_inbox_rotate_worker_token' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to rotate the worker token.', 'biopentra-contact-inbox' ) );
		}
		require_once BIOPENTRA_INBOX_PATH . 'includes/class-rest-worker.php';
		$plain = Biopentra_Contact_Inbox_Rest_Worker::generate_worker_token();
		set_transient( 'biopentra_inbox_worker_token_show_' . get_current_user_id(), $plain, 120 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'biopentra-inbox-settings',
					'tab'  => 'sync',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_check_worker_http_health() {
		check_admin_referer( 'biopentra_inbox_check_worker_http_health' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to run this check.', 'biopentra-contact-inbox' ) );
		}

		require_once BIOPENTRA_INBOX_PATH . 'includes/class-rest-worker.php';
		$result = Biopentra_Contact_Inbox_Rest_Worker::probe_worker_container_http_health();

		update_option(
			'biopentra_inbox_worker_health_cached',
			wp_json_encode(
				array(
					'checked_at_gmt' => gmdate( 'c' ),
					'healthy'        => $result['healthy'],
				)
			)
		);

		$message = $result['healthy']
			? __( 'Mail worker responded and reports ready.', 'biopentra-contact-inbox' )
			: __( 'Mail worker did not respond as expected. Confirm the stack is running and this site can reach the mail worker (see Advanced / Developer details on this tab).', 'biopentra-contact-inbox' );

		set_transient(
			'biopentra_inbox_worker_http_health_notice_' . get_current_user_id(),
			array(
				'healthy' => $result['healthy'],
				'message' => $message,
			),
			120
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'biopentra-inbox-settings',
					'tab'  => 'sync',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_trigger_worker_mailbox_check() {
		check_admin_referer( 'biopentra_inbox_trigger_worker_mailbox_check' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.', 'biopentra-contact-inbox' ) );
		}

		if ( Biopentra_Contact_Inbox_Cron::import_driver() !== 'worker' ) {
			set_transient(
				'biopentra_inbox_worker_mailbox_notice_' . get_current_user_id(),
				array(
					'level'   => 'warning',
					'message' => __( 'Immediate mailbox check is only available when the import driver is set to the mail worker.', 'biopentra-contact-inbox' ),
				),
				120
			);
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'biopentra-inbox-settings',
						'tab'  => 'sync',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		require_once BIOPENTRA_INBOX_PATH . 'includes/class-rest-worker.php';
		$result = Biopentra_Contact_Inbox_Rest_Worker::trigger_worker_mailbox_check();

		if ( $result['ok'] ) {
			set_transient(
				'biopentra_inbox_worker_mailbox_notice_' . get_current_user_id(),
				array(
					'level'   => 'success',
					'message' => __( 'Immediate mailbox check was sent. New messages should appear in tickets shortly if the mailbox has mail.', 'biopentra-contact-inbox' ),
				),
				120
			);
		} else {
			set_transient(
				'biopentra_inbox_worker_mailbox_notice_' . get_current_user_id(),
				array(
					'level'   => 'error',
					'message' => $result['message'],
				),
				120
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'biopentra-inbox-settings',
					'tab'  => 'sync',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_test_imap() {
		check_admin_referer( 'biopentra_inbox_test_imap' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to run this test.', 'biopentra-contact-inbox' ) );
		}

		if ( Biopentra_Contact_Inbox_Cron::import_driver() !== 'php_imap' || ! extension_loaded( 'imap' ) ) {
			$args = array(
				'page'          => 'biopentra-inbox-settings',
				'tab'           => 'email',
				'bsd_imap_test' => 'skipped',
			);
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$r = Biopentra_Contact_Inbox_Bridge_Diagnostics::run_imap_test();

		$args = array(
			'page'           => 'biopentra-inbox-settings',
			'tab'            => 'email',
			'bsd_imap_test'  => $r['code'],
		);
		if ( $r['detail'] !== '' ) {
			$args['bsd_imap_msg'] = rawurlencode( $r['detail'] );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_test_smtp() {
		check_admin_referer( 'biopentra_inbox_test_smtp' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to run this test.', 'biopentra-contact-inbox' ) );
		}

		$r = Biopentra_Contact_Inbox_Bridge_Diagnostics::run_smtp_test();

		$args = array(
			'page'           => 'biopentra-inbox-settings',
			'tab'            => 'email',
			'bsd_smtp_test'  => $r['code'],
		);
		if ( $r['detail'] !== '' ) {
			$args['bsd_smtp_msg'] = rawurlencode( $r['detail'] );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_preview_reply_template() {
		Biopentra_Contact_Inbox_Email_Reply_Template::output_preview_document();
	}

	public function handle_migrate_fluent() {
		check_admin_referer( 'biopentra_inbox_migrate_fluent' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to migrate.', 'biopentra-contact-inbox' ) );
		}

		$form_id = Biopentra_Contact_Inbox_Form_Resolver::get_form_id();
		$base     = add_query_arg(
			array(
				'page' => 'biopentra-inbox-settings',
				'tab'  => 'sync',
			),
			admin_url( 'admin.php' )
		);

		if ( $form_id <= 0 ) {
			wp_safe_redirect( add_query_arg( 'bsd_migrate', 'no_form', $base ) );
			exit;
		}

		$r = Biopentra_Contact_Inbox_Fluent_Migration::migrate_batch( $form_id, 25 );
		$redirect = add_query_arg(
			array(
				'bsd_migrate' => '1',
				'migrated'    => (int) $r['migrated'],
				'done'        => ! empty( $r['done'] ) ? '1' : '0',
			),
			$base
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_ticket_status() {
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to change ticket status.', 'biopentra-contact-inbox' ) );
		}

		$ticket_id = isset( $_POST['ticket_id'] ) ? (int) $_POST['ticket_id'] : 0;
		if ( $ticket_id <= 0 ) {
			wp_die( esc_html__( 'Missing ticket.', 'biopentra-contact-inbox' ) );
		}

		check_admin_referer( 'biopentra_inbox_ticket_status_' . $ticket_id );

		$new = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
		if ( ! in_array( $new, array( 'open', 'pending', 'closed' ), true ) ) {
			$new = 'open';
		}

		Biopentra_Contact_Inbox_Ticket_Repository::update_status( $ticket_id, $new );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'biopentra-inbox',
					'ticket_id'   => $ticket_id,
					'bsd_status'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_ticket_archive() {
		$this->handle_ticket_row_action( 'archive' );
	}

	public function handle_ticket_unarchive() {
		$this->handle_ticket_row_action( 'unarchive' );
	}

	public function handle_ticket_mark_read() {
		$this->handle_ticket_row_action( 'mark_read' );
	}

	public function handle_ticket_mark_unread() {
		$this->handle_ticket_row_action( 'mark_unread' );
	}

	/**
	 * Row actions: POST to admin-post.php (detached forms on list avoid nested-form browser bugs).
	 *
	 * @param string $op archive|unarchive|mark_read|mark_unread.
	 */
	private function handle_ticket_row_action( $op ) {
		$op = sanitize_key( $op );
		if ( ! in_array( $op, array( 'archive', 'unarchive', 'mark_read', 'mark_unread' ), true ) ) {
			$this->safe_redirect_inbox_list( array( 'bsd_err' => 'unknown' ) );
		}

		$ticket_id = isset( $_POST['ticket_id'] ) ? (int) $_POST['ticket_id'] : 0;
		if ( $ticket_id <= 0 ) {
			$this->safe_redirect_inbox_list( array( 'bsd_err' => 'bad_ticket' ) );
		}

		$nonce_action = 'biopentra_inbox_ticket_' . $op . '_' . $ticket_id;
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $nonce_action ) ) {
			$this->safe_redirect_inbox_list(
				array_merge( $this->inbox_list_query_args_from_post(), array( 'bsd_err' => 'nonce' ) )
			);
		}

		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			$this->safe_redirect_inbox_list(
				array_merge( $this->inbox_list_query_args_from_post(), array( 'bsd_err' => 'cap' ) )
			);
		}

		$notice = '';
		switch ( $op ) {
			case 'archive':
				Biopentra_Contact_Inbox_Ticket_Repository::set_archived( $ticket_id );
				$notice = 'archived';
				break;
			case 'unarchive':
				Biopentra_Contact_Inbox_Ticket_Repository::clear_archived( $ticket_id );
				$notice = 'unarchived';
				break;
			case 'mark_read':
				Biopentra_Contact_Inbox_Ticket_Repository::mark_read( $ticket_id );
				$notice = 'marked_read';
				break;
			case 'mark_unread':
				Biopentra_Contact_Inbox_Ticket_Repository::set_unread( $ticket_id, true );
				$notice = 'marked_unread';
				break;
			default:
				$this->safe_redirect_inbox_list(
					array_merge( $this->inbox_list_query_args_from_post(), array( 'bsd_err' => 'unknown' ) )
				);
		}

		if ( ! empty( $_POST['redirect_to'] ) && 'ticket' === sanitize_key( wp_unslash( $_POST['redirect_to'] ) ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'biopentra-inbox',
						'ticket_id'  => $ticket_id,
						'bsd_notice' => $notice,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$this->safe_redirect_inbox_list(
			array_merge(
				$this->inbox_list_query_args_from_post(),
				array( 'bsd_notice' => $notice )
			)
		);
	}

	/**
	 * @param array<string, string|int> $query_args Query args for admin.php inbox screen.
	 */
	private function safe_redirect_inbox_list( array $query_args ) {
		if ( ! isset( $query_args['page'] ) ) {
			$query_args['page'] = 'biopentra-inbox';
		}
		wp_safe_redirect( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * List context from POST (hidden fields on detached row forms).
	 *
	 * @return array<string, string|int>
	 */
	private function inbox_list_query_args_from_post() {
		$desk = isset( $_POST['desk_status'] ) ? sanitize_key( wp_unslash( $_POST['desk_status'] ) ) : 'all';
		if ( ! in_array( $desk, array( 'all', 'open', 'pending', 'closed', 'unread', 'archived' ), true ) ) {
			$desk = 'all';
		}
		$args = array(
			'page'        => 'biopentra-inbox',
			'desk_status' => $desk,
		);
		$s = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		if ( $s !== '' ) {
			$args['s'] = $s;
		}
		$ob = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : '';
		$or = isset( $_POST['order'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['order'] ) ) ) : '';
		if ( in_array( $ob, array( 'ticket_number', 'id', 'customer_name', 'last_message_at' ), true ) ) {
			$args['orderby'] = $ob;
		}
		if ( in_array( $or, array( 'asc', 'desc' ), true ) ) {
			$args['order'] = $or;
		}
		$paged = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;
		if ( $paged > 1 ) {
			$args['paged'] = $paged;
		}
		return $args;
	}

	/**
	 * Append operational unread count to main menu title.
	 */
	public function add_inbox_menu_badge() {
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			return;
		}
		$n = Biopentra_Contact_Inbox_Ticket_Repository::count_operational_unread();
		if ( $n < 1 ) {
			return;
		}
		global $menu;
		if ( ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( isset( $item[2] ) && 'biopentra-inbox' === $item[2] ) {
				$show = (int) min( 99, $n );
				$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( (string) $show ) . '</span></span>';
				break;
			}
		}
	}

	/**
	 * Save Advanced tab “auto-delete archived … days” (dedicated POST so it always persists with manage_biopentra_inbox).
	 */
	public function handle_save_archive_retention() {
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to change this setting.', 'biopentra-contact-inbox' ) );
		}
		check_admin_referer( 'biopentra_inbox_save_archive_retention' );

		$raw = isset( $_POST['biopentra_inbox_archive_auto_delete_days'] ) ? wp_unslash( $_POST['biopentra_inbox_archive_auto_delete_days'] ) : null;
		$val = Biopentra_Contact_Inbox_Settings::sanitize_archive_delete_days( $raw );
		update_option( 'biopentra_inbox_archive_auto_delete_days', $val, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'biopentra-inbox-settings',
					'tab'                => 'advanced',
					'bsd_archive_saved'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete all Support Desk tickets, thread messages, and legacy reply log rows (plugin tables only).
	 */
	public function handle_reset_support_desk() {
		check_admin_referer( 'biopentra_inbox_reset_support_desk' );
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.', 'biopentra-contact-inbox' ) );
		}

		$clear_state = false;
		if ( isset( $_POST['biopentra_inbox_clear_import_state'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['biopentra_inbox_clear_import_state'] ) ) ) {
			$clear_state = true;
		}

		$result = Biopentra_Contact_Inbox_Support_Desk_Reset::run( $clear_state );

		set_transient(
			'biopentra_inbox_desk_reset_notice_' . get_current_user_id(),
			$result,
			120
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'biopentra-inbox-settings',
					'tab'  => 'advanced',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
