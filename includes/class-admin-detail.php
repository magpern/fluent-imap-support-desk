<?php
/**
 * Submission detail + reply UI (legacy Fluent) and ticket thread UI.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Admin_Detail {

	/**
	 * @param object $row       Submission row.
	 * @param int    $form_id   Form scope.
	 * @param array  $reply_log Reply rows.
	 */
	public static function render( $row, $form_id, array $reply_log ) {
		$data = Biopentra_Contact_Inbox_Submission_Repository::decode_response( $row->response );
		$sid  = (int) $row->id;

		$list_url = add_query_arg( array( 'page' => 'biopentra-inbox' ), admin_url( 'admin.php' ) );
		echo '<p><a href="' . esc_url( $list_url ) . '">&larr; ' . esc_html__( 'Back to tickets', 'biopentra-contact-inbox' ) . '</a></p>';

		echo '<h2>' . esc_html__( 'Submission', 'biopentra-contact-inbox' ) . ' #' . esc_html( (string) $sid ) . '</h2>';

		echo '<h3>' . esc_html__( 'Meta', 'biopentra-contact-inbox' ) . '</h3>';
		echo '<table class="widefat striped"><tbody>';
		echo '<tr><th>' . esc_html__( 'Date', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( isset( $row->created_at ) ? $row->created_at : '—' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'IP', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( isset( $row->ip ) ? $row->ip : '—' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Source URL', 'biopentra-contact-inbox' ) . '</th><td>';
		$src = isset( $row->source_url ) ? $row->source_url : '';
		echo $src ? '<a href="' . esc_url( $src ) . '">' . esc_html( $src ) . '</a>' : '—';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Fluent user ID', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( isset( $row->user_id ) ? (string) $row->user_id : '—' ) . '</td></tr>';
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Submitted data', 'biopentra-contact-inbox' ) . '</h3>';
		echo '<table class="widefat striped"><tbody>';
		if ( empty( $data ) ) {
			echo '<tr><td>' . esc_html__( '(empty)', 'biopentra-contact-inbox' ) . '</td></tr>';
		} else {
			foreach ( $data as $k => $v ) {
				$disp = is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
				echo '<tr><th scope="row">' . esc_html( (string) $k ) . '</th><td>' . esc_html( $disp ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Reply history', 'biopentra-contact-inbox' ) . '</h3>';
		if ( empty( $reply_log ) ) {
			echo '<p>' . esc_html__( 'No replies logged yet.', 'biopentra-contact-inbox' ) . '</p>';
		} else {
			foreach ( $reply_log as $r ) {
				$user = get_userdata( (int) $r->admin_user_id );
				$who  = $user ? $user->user_login : '#' . $r->admin_user_id;
				echo '<div style="border:1px solid #ccd0d4;padding:12px;margin-bottom:12px;background:#fff;">';
				echo '<p><strong>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $r->sent_at ) ) . '</strong> — ';
				echo esc_html( sprintf( /* translators: %s wp username */ __( 'by %s', 'biopentra-contact-inbox' ), $who ) );
				echo '<br /><em>' . esc_html__( 'To:', 'biopentra-contact-inbox' ) . '</em> ' . esc_html( $r->recipient_email );
				echo '<br /><em>' . esc_html__( 'Subject:', 'biopentra-contact-inbox' ) . '</em> ' . esc_html( $r->subject ) . '</p>';
				echo '<div class="biopentra-inbox-reply-body">' . wp_kses_post( wpautop( $r->body ) ) . '</div>';
				echo '</div>';
			}
		}

		$recipient = Biopentra_Contact_Inbox_Submission_Repository::extract_submitter_email( $data );
		if ( ! is_email( $recipient ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No valid email on this submission; replies are disabled.', 'biopentra-contact-inbox' ) . '</p></div>';
			return;
		}

		$default_subject = get_option( 'biopentra_inbox_default_reply_subject', 'Re: Your Biopentra inquiry' );
		$action          = admin_url( 'admin-post.php' );

		echo '<h3>' . esc_html__( 'Send reply', 'biopentra-contact-inbox' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( $action ) . '" class="biopentra-inbox-reply-form">';
		echo '<input type="hidden" name="action" value="biopentra_inbox_reply" />';
		wp_nonce_field( 'biopentra_inbox_reply_' . $sid );
		echo '<input type="hidden" name="submission_id" value="' . esc_attr( (string) $sid ) . '" />';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="biopentra_reply_to">' . esc_html__( 'To', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<input type="email" class="regular-text" id="biopentra_reply_to" name="reply_to" value="' . esc_attr( $recipient ) . '" />';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="biopentra_reply_subject">' . esc_html__( 'Subject', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<input type="text" class="large-text" id="biopentra_reply_subject" name="reply_subject" value="' . esc_attr( $default_subject ) . '" />';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="biopentra_reply_body">' . esc_html__( 'Message', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<textarea name="reply_body" id="biopentra_reply_body" rows="8" class="large-text" required></textarea>';
		echo '<p class="description">' . esc_html__( 'Sent through the Support Desk mail settings using your configured From name and address.', 'biopentra-contact-inbox' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Send reply', 'biopentra-contact-inbox' ) );
		echo '</form>';
	}

	/**
	 * @param object               $ticket   Row from tickets table.
	 * @param array<int, object> $messages Message rows.
	 */
	public static function render_ticket( $ticket, array $messages ) {
		$tid = (int) $ticket->id;

		$list_url = add_query_arg( array( 'page' => 'biopentra-inbox' ), admin_url( 'admin.php' ) );
		echo '<p><a href="' . esc_url( $list_url ) . '">&larr; ' . esc_html__( 'Back to tickets', 'biopentra-contact-inbox' ) . '</a></p>';

		echo '<h2>' . esc_html__( 'Ticket', 'biopentra-contact-inbox' ) . ' #' . esc_html( (string) $tid );
		$tn_disp = isset( $ticket->ticket_number ) && (int) $ticket->ticket_number > 0 ? (int) $ticket->ticket_number : $tid;
		$tag     = Biopentra_Contact_Inbox_Ticket_Ref::bracket_tag( $tn_disp );
		if ( $tag !== '' ) {
			echo ' <span class="description">' . esc_html( $tag ) . '</span>';
		}
		echo '</h2>';

		$archived = ! empty( $ticket->archived_at );
		if ( $archived ) {
			echo '<p><span class="biopentra-inbox-archived-badge" style="display:inline-block;padding:2px 8px;border-radius:3px;background:#646970;color:#fff;font-size:12px;">' . esc_html__( 'Archived', 'biopentra-contact-inbox' ) . '</span></p>';
			$action = admin_url( 'admin-post.php' );
			echo '<form method="post" action="' . esc_url( $action ) . '" style="display:inline-block;margin:0 0 12px 0;">';
			echo '<input type="hidden" name="action" value="biopentra_inbox_ticket_unarchive" />';
			wp_nonce_field( 'biopentra_inbox_ticket_unarchive_' . $tid );
			echo '<input type="hidden" name="ticket_id" value="' . esc_attr( (string) $tid ) . '" />';
			echo '<input type="hidden" name="redirect_to" value="ticket" />';
			submit_button( __( 'Unarchive', 'biopentra-contact-inbox' ), 'secondary small', 'submit', false );
			echo '</form>';
		}

		$st = isset( $ticket->status ) ? sanitize_key( (string) $ticket->status ) : 'open';
		echo '<p><span class="biopentra-inbox-ticket-status status-' . esc_attr( $st ) . '"><strong>' . esc_html( ucfirst( $st ) ) . '</strong></span></p>';

		$action = admin_url( 'admin-post.php' );
		echo '<form method="post" action="' . esc_url( $action ) . '" style="margin-bottom:16px;display:inline-block;">';
		echo '<input type="hidden" name="action" value="biopentra_inbox_ticket_status" />';
		wp_nonce_field( 'biopentra_inbox_ticket_status_' . $tid );
		echo '<input type="hidden" name="ticket_id" value="' . esc_attr( (string) $tid ) . '" />';
		echo '<label for="bsd_new_status">' . esc_html__( 'Status', 'biopentra-contact-inbox' ) . '</label> ';
		echo '<select name="new_status" id="bsd_new_status">';
		foreach ( array( 'open', 'pending', 'closed' ) as $opt ) {
			echo '<option value="' . esc_attr( $opt ) . '"' . selected( $st, $opt, false ) . '>' . esc_html( ucfirst( $opt ) ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Update status', 'biopentra-contact-inbox' ), 'secondary small', 'submit', false );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Ticket details', 'biopentra-contact-inbox' ) . '</h3>';
		echo '<table class="widefat striped"><tbody>';
		echo '<tr><th>' . esc_html__( 'Ticket #', 'biopentra-contact-inbox' ) . '</th><td>';
		$tn_row = isset( $ticket->ticket_number ) && (int) $ticket->ticket_number > 0 ? (int) $ticket->ticket_number : $tid;
		$tag_r = Biopentra_Contact_Inbox_Ticket_Ref::bracket_tag( $tn_row );
		echo $tag_r !== '' ? '<code>' . esc_html( $tag_r ) . '</code>' : '—';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Subject', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( isset( $ticket->subject ) ? (string) $ticket->subject : '' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Customer', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( isset( $ticket->customer_email ) ? (string) $ticket->customer_email : '—' ) . '</td></tr>';
		$to_box = isset( $ticket->to_email ) && is_string( $ticket->to_email ) && $ticket->to_email !== '' ? sanitize_email( $ticket->to_email ) : '';
		echo '<tr><th>' . esc_html__( 'To (mailbox)', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( is_email( $to_box ) ? $to_box : '—' ) . '</td></tr>';
		$src = isset( $ticket->source ) ? sanitize_key( (string) $ticket->source ) : '';
		$ref = isset( $ticket->source_ref ) && $ticket->source_ref !== null ? (string) $ticket->source_ref : '';
		echo '<tr><th>' . esc_html__( 'Source', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( $src !== '' ? $src : '—' );
		if ( $ref !== '' ) {
			echo ' <span class="description">#' . esc_html( $ref ) . '</span>';
		}
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Last message', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( isset( $ticket->last_message_at ) ? (string) $ticket->last_message_at : '—' ) . '</td></tr>';
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Thread', 'biopentra-contact-inbox' ) . '</h3>';
		if ( empty( $messages ) ) {
			echo '<p>' . esc_html__( 'No messages yet.', 'biopentra-contact-inbox' ) . '</p>';
		} else {
			foreach ( $messages as $m ) {
				self::render_message_row( $m );
			}
		}

		$recipient = isset( $ticket->customer_email ) ? sanitize_email( (string) $ticket->customer_email ) : '';
		if ( ! is_email( $recipient ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No valid customer email; replies are disabled.', 'biopentra-contact-inbox' ) . '</p></div>';
			return;
		}

		$default_subject = get_option( 'biopentra_inbox_default_reply_subject', 'Re: Your Biopentra inquiry' );
		$subj            = isset( $ticket->subject ) && (string) $ticket->subject !== ''
			? 'Re: ' . (string) $ticket->subject
			: $default_subject;
		$tn              = isset( $ticket->ticket_number ) && (int) $ticket->ticket_number > 0 ? (int) $ticket->ticket_number : $tid;
		$subj            = Biopentra_Contact_Inbox_Ticket_Ref::format_subject( $subj, $tn );

		echo '<h3>' . esc_html__( 'Send reply', 'biopentra-contact-inbox' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( $action ) . '" class="biopentra-inbox-reply-form">';
		echo '<input type="hidden" name="action" value="biopentra_inbox_reply" />';
		wp_nonce_field( 'biopentra_inbox_reply_ticket_' . $tid );
		echo '<input type="hidden" name="ticket_id" value="' . esc_attr( (string) $tid ) . '" />';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="bsd_reply_to">' . esc_html__( 'To', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<input type="email" class="regular-text" id="bsd_reply_to" name="reply_to" value="' . esc_attr( $recipient ) . '" />';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="bsd_reply_subject">' . esc_html__( 'Subject', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<input type="text" class="large-text" id="bsd_reply_subject" name="reply_subject" value="' . esc_attr( $subj ) . '" />';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="bsd_reply_body">' . esc_html__( 'Message', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<textarea name="reply_body" id="bsd_reply_body" rows="8" class="large-text" required></textarea>';
		echo '<p class="description">' . esc_html__( 'Sent through the Support Desk mail settings with ticket threading headers.', 'biopentra-contact-inbox' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Send reply', 'biopentra-contact-inbox' ) );
		echo '</form>';
	}

	/**
	 * @param object $m Message row.
	 */
	private static function render_message_row( $m ) {
		$dir = isset( $m->direction ) ? sanitize_key( (string) $m->direction ) : '';
		$lbl = 'inbound' === $dir ? __( 'Inbound', 'biopentra-contact-inbox' ) : __( 'Outbound', 'biopentra-contact-inbox' );
		$src = isset( $m->source ) ? sanitize_key( (string) $m->source ) : '';
		$when = isset( $m->created_at ) ? $m->created_at : '';

		$row_class = 'biopentra-inbox-thread-msg biopentra-inbox-thread-msg--' . ( 'inbound' === $dir ? 'inbound' : 'outbound' );

		echo '<div class="' . esc_attr( $row_class ) . '">';
		echo '<p><strong>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $when ) ) . '</strong> — ';
		echo esc_html( $lbl );
		if ( $src !== '' ) {
			echo ' <span class="description">(' . esc_html( $src ) . ')</span>';
		}
		echo '</p>';

		$from = isset( $m->from_email ) ? (string) $m->from_email : '';
		$to   = isset( $m->to_email ) && $m->to_email !== null ? (string) $m->to_email : '';
		if ( $from !== '' ) {
			echo '<p><em>' . esc_html__( 'From:', 'biopentra-contact-inbox' ) . '</em> ' . esc_html( $from );
			if ( ! empty( $m->from_name ) ) {
				echo ' (' . esc_html( (string) $m->from_name ) . ')';
			}
			echo '</p>';
		}
		if ( $to !== '' ) {
			echo '<p><em>' . esc_html__( 'To:', 'biopentra-contact-inbox' ) . '</em> ' . esc_html( $to ) . '</p>';
		}
		if ( ! empty( $m->subject ) ) {
			echo '<p><em>' . esc_html__( 'Subject:', 'biopentra-contact-inbox' ) . '</em> ' . esc_html( (string) $m->subject ) . '</p>';
		}

		$is_email_inbound = ( 'inbound' === $dir && in_array( $src, array( 'email', 'worker' ), true ) );

		$html = isset( $m->body_html ) && $m->body_html !== null && $m->body_html !== '';
		if ( $is_email_inbound ) {
			self::render_inbound_email_body_admin( $m );
		} elseif ( $html ) {
			echo '<div class="biopentra-inbox-reply-body">' . wp_kses_post( wpautop( (string) $m->body_html ) ) . '</div>';
		} else {
			$text = isset( $m->body_text ) ? (string) $m->body_text : '';
			if ( $text !== '' && 'fluent' === $src && 'inbound' === $dir ) {
				$decoded = json_decode( $text, true );
				if ( is_array( $decoded ) ) {
					echo '<table class="widefat striped" style="margin-top:8px;"><tbody>';
					$name = Biopentra_Contact_Inbox_Submission_Repository::extract_submitter_name( $decoded );
					if ( $name !== '' ) {
						echo '<tr><th scope="row">' . esc_html__( 'Name', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( $name ) . '</td></tr>';
					}
					$em = Biopentra_Contact_Inbox_Submission_Repository::extract_submitter_email( $decoded );
					if ( $em !== '' ) {
						echo '<tr><th scope="row">' . esc_html__( 'Email', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( $em ) . '</td></tr>';
					}
					if ( ! empty( $decoded['reason'] ) && is_string( $decoded['reason'] ) ) {
						echo '<tr><th scope="row">' . esc_html__( 'Reason', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( Biopentra_Contact_Inbox_Submission_Repository::reason_label( $decoded['reason'] ) ) . '</td></tr>';
					}
					if ( ! empty( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
						echo '<tr><th scope="row">' . esc_html__( 'Message', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( wp_strip_all_tags( $decoded['message'] ) ) . '</td></tr>';
					}
					$ctx = Biopentra_Contact_Inbox_Submission_Repository::context_summary( $decoded );
					if ( $ctx !== '' && $ctx !== '—' ) {
						echo '<tr><th scope="row">' . esc_html__( 'Context', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( $ctx ) . '</td></tr>';
					}
					echo '</tbody></table>';
					echo '<details style="margin-top:10px;"><summary>' . esc_html__( 'Technical: raw submission JSON', 'biopentra-contact-inbox' ) . '</summary>';
					echo '<pre style="white-space:pre-wrap;max-height:240px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #c3c4c7;">' . esc_html( wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
					echo '</details>';
				} else {
					echo '<div class="biopentra-inbox-reply-body"><pre style="white-space:pre-wrap;">' . esc_html( $text ) . '</pre></div>';
				}
			} elseif ( $text !== '' ) {
				echo '<div class="biopentra-inbox-reply-body">' . esc_html( $text ) . '</div>';
			}
		}
		echo '</div>';
	}

	/**
	 * Inbound IMAP/worker email: hide quoted thread behind &lt;details&gt;; strip display-only CSS noise.
	 *
	 * @param object $m Message row.
	 */
	private static function render_inbound_email_body_admin( $m ) {
		$html_raw = isset( $m->body_html ) && $m->body_html !== null ? (string) $m->body_html : '';
		$text_raw = isset( $m->body_text ) && $m->body_text !== null ? (string) $m->body_text : '';

		$html_clean = self::strip_email_html_boilerplate( $html_raw );
		$plain      = self::build_plain_for_quote_detection( $html_clean, $text_raw );
		$plain      = self::strip_css_artifact_lines( $plain );
		$split_at   = self::find_inbound_quote_split_offset( $plain );

		$min_new    = 4;
		$min_quoted = 40;
		if ( null !== $split_at && $split_at >= 10 && strlen( $plain ) > $split_at + $min_quoted ) {
			$new_part    = trim( substr( $plain, 0, $split_at ) );
			$quoted_part = trim( substr( $plain, $split_at ) );
			if ( strlen( $new_part ) >= $min_new && strlen( $quoted_part ) >= $min_quoted ) {
				echo '<div class="biopentra-inbox-reply-body biopentra-inbox-email-new">' . nl2br( esc_html( $new_part ) ) . '</div>';
				echo '<details class="biopentra-inbox-quoted-history">';
				echo '<summary>' . esc_html__( 'Show quoted previous message', 'biopentra-contact-inbox' ) . '</summary>';
				echo '<div class="biopentra-inbox-quoted-history__body">' . nl2br( esc_html( $quoted_part ) ) . '</div>';
				echo '</details>';
				return;
			}
		}

		if ( trim( wp_strip_all_tags( $html_clean ) ) !== '' ) {
			echo '<div class="biopentra-inbox-reply-body">' . wp_kses_post( wpautop( $html_clean ) ) . '</div>';
			return;
		}

		$text_fallback = self::strip_css_artifact_lines( self::strip_email_html_boilerplate( $text_raw ) );
		if ( $text_fallback !== '' ) {
			echo '<div class="biopentra-inbox-reply-body">' . nl2br( esc_html( $text_fallback ) ) . '</div>';
		}
	}

	/**
	 * Remove script/style blocks and common HTML-wrapped CSS snippets (display only).
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private static function strip_email_html_boilerplate( $html ) {
		$html = (string) $html;
		if ( $html === '' ) {
			return '';
		}
		$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
		$html = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
		// Outlook-style paragraph that is only a CSS rule as text.
		$html = preg_replace( '/<p[^>]*>\s*P\s*\{[^}]*margin[^}]*\}[^<]*<\/p>/i', '', $html );
		$html = preg_replace( '/<div[^>]*>\s*P\s*\{[^}]*margin[^}]*\}[^<]*<\/div>/i', '', $html );
		// Raw CSS rule text sometimes embedded without tags.
		$html = preg_replace( '/P\s*\{[^}]*margin-top\s*:\s*0[^}]*\}/i', '', $html );
		return $html;
	}

	/**
	 * Single plain string for quote-boundary search (prefer text part when present).
	 *
	 * @param string $html_clean HTML after strip_email_html_boilerplate.
	 * @param string $text_raw   Raw body_text.
	 * @return string
	 */
	private static function build_plain_for_quote_detection( $html_clean, $text_raw ) {
		$text_raw = (string) $text_raw;
		$text_raw = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $text_raw );
		$text_raw = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $text_raw );

		$from_text = trim( self::strip_css_artifact_lines( $text_raw ) );
		if ( strlen( $from_text ) >= 32 ) {
			return $from_text;
		}

		$break_tags = array( '<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '<table', '</table>', '</h1>', '</h2>', '</h3>', '<hr', '<hr/>' );
		$normalized = str_ireplace( $break_tags, "\n", $html_clean );
		$from_html  = wp_strip_all_tags( $normalized );
		$from_html  = html_entity_decode( $from_html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$from_html  = trim( self::strip_css_artifact_lines( $from_html ) );

		if ( $from_html !== '' ) {
			return $from_html;
		}
		return $from_text;
	}

	/**
	 * Drop standalone CSS-rule lines and stray style fragments from plain text.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function strip_css_artifact_lines( $text ) {
		$text = (string) $text;
		if ( $text === '' ) {
			return '';
		}
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$lines = explode( "\n", $text );
		$keep  = array();
		foreach ( $lines as $line ) {
			$t = trim( $line );
			if ( $t === '' ) {
				$keep[] = $line;
				continue;
			}
			// Outlook / webmail: literal "P {margin-top:0;margin-bottom:0;}" and similar.
			if ( preg_match( '/^[a-z0-9*#.:\s,]+\{[^}]+\}$/iu', $t ) && strlen( $t ) < 400 && strpos( $t, '{' ) !== false ) {
				continue;
			}
			if ( preg_match( '/^P\s*\{[^}]*margin-top\s*:\s*0/i', $t ) ) {
				continue;
			}
			if ( preg_match( '/^div\.?ExternalClass/i', $t ) ) {
				continue;
			}
			$keep[] = $line;
		}
		return implode( "\n", $keep );
	}

	/**
	 * Find start offset in plain text where quoted previous thread begins.
	 *
	 * @param string $plain Normalized plain body.
	 * @return int|null Byte offset of first quote marker, or null.
	 */
	private static function find_inbound_quote_split_offset( $plain ) {
		$plain = (string) $plain;
		if ( strlen( $plain ) < 60 ) {
			return null;
		}
		$plain = str_replace( array( "\r\n", "\r" ), "\n", $plain );

		$candidates = array();

		$patterns = array(
			'/\n-{5,}\s*\n/',
			'/\n-----+\s*Original Message\s*-----+\s*\n/i',
			'/\nRecent conversation\s*\n/i',
			'/\nOn [^\n]{1,260}wrote:\s*\n/i',
			// Outlook / Biopentra: From / Sent / To / Subject block.
			'/\nFrom:\s[^\n]+\n\s*Sent:\s[^\n]+\n\s*To:\s[^\n]+\n\s*Subject:\s/im',
			'/\nFrom:\s[^\n]*[@<][^\n]*\n\s*Sent:\s[^\n]+/im',
			'/\n_{12,}\s*\n/',
			'/\n>?\s*From:\s[^\n]+\n>?\s*Sent:\s[^\n]+\n>?\s*To:\s/im',
		);

		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $plain, $m, PREG_OFFSET_CAPTURE ) ) {
				$pos = isset( $m[0][1] ) ? (int) $m[0][1] : null;
				if ( null !== $pos && $pos >= 8 ) {
					$candidates[] = $pos;
				}
			}
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		return min( $candidates );
	}
}
