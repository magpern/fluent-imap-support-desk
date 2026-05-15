<?php
/**
 * wp_mail wrapper for support desk replies (Fluent + tickets).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Mailer {

	/**
	 * Max prior thread messages to quote in customer-facing replies (not including the new reply).
	 */
	const CUSTOMER_QUOTE_PRIOR_LIMIT = 5;

	/**
	 * @var string Plain-text AltBody queued for the next wp_mail send.
	 */
	private static $pending_plain_alt = '';

	/**
	 * @param string $to      Recipient.
	 * @param string $subject Subject line.
	 * @param string $body    Message body (HTML allowed; sanitized with wp_kses_post).
	 * @return bool|\WP_Error
	 */
	public static function send_reply( $to, $subject, $body ) {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid recipient email.', 'biopentra-contact-inbox' ) );
		}
		return self::send_html_mail( $to, $subject, $body, array(), null );
	}

	/**
	 * Send staff reply on a ticket; persist outbound message and update ticket.
	 *
	 * @param int    $ticket_id Ticket ID.
	 * @param string $to        Recipient.
	 * @param string $subject   Subject.
	 * @param string $body      HTML body (wp_kses_post applied).
	 * @return bool|\WP_Error
	 */
	public static function send_ticket_reply( $ticket_id, $to, $subject, $body ) {
		$ticket_id = (int) $ticket_id;
		$ticket    = Biopentra_Contact_Inbox_Ticket_Repository::get( $ticket_id );
		if ( ! $ticket ) {
			return new \WP_Error( 'no_ticket', __( 'Ticket not found.', 'biopentra-contact-inbox' ) );
		}

		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid recipient email.', 'biopentra-contact-inbox' ) );
		}

		$from_name  = sanitize_text_field( get_option( 'biopentra_inbox_from_name', 'Biopentra' ) );
		$from_email = sanitize_email( get_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) ) );
		if ( ! is_email( $from_email ) ) {
			$from_email = sanitize_email( get_option( 'admin_email' ) );
		}

		$subject = sanitize_text_field( $subject );
		if ( $subject === '' ) {
			$subject = get_option( 'biopentra_inbox_default_reply_subject', 'Re: Your Biopentra inquiry' );
		}

		$tn = isset( $ticket->ticket_number ) && (int) $ticket->ticket_number > 0 ? (int) $ticket->ticket_number : $ticket_id;
		$subject = Biopentra_Contact_Inbox_Ticket_Ref::format_subject( $subject, $tn );

		$reply_html = wp_kses_post( $body );

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || $host === '' ) {
			$host = 'localhost';
		}
		$new_raw = sprintf( '<%s@%s>', uniqid( 'bsd.', true ), $host );

		$chain = Biopentra_Contact_Inbox_Message_Repository::get_message_id_chain( $ticket_id, 20 );
		$in_reply = '';
		if ( ! empty( $chain ) ) {
			$in_reply = (string) end( $chain );
		}

		$refs_header = '';
		if ( ! empty( $chain ) ) {
			$parts = array();
			foreach ( $chain as $id ) {
				$parts[] = '<' . $id . '>';
			}
			$refs_header = implode( ' ', $parts );
		}

		$extra = array(
			'Message-ID: ' . $new_raw,
		);
		if ( $in_reply !== '' ) {
			$extra[] = 'In-Reply-To: <' . $in_reply . '>';
		}
		if ( $refs_header !== '' ) {
			$extra[] = 'References: ' . $refs_header;
		}

		$prior_msgs   = Biopentra_Contact_Inbox_Message_Repository::get_by_ticket( $ticket_id );
		$quote_limit  = max( 1, (int) self::CUSTOMER_QUOTE_PRIOR_LIMIT );
		$prior_slice  = array_slice( $prior_msgs, -1 * $quote_limit );
		$quote        = self::build_customer_quote_blocks( $prior_slice );

		if ( class_exists( 'Biopentra_Contact_Inbox_Email_Reply_Template', false ) && Biopentra_Contact_Inbox_Email_Reply_Template::is_enabled() ) {
			$bodies    = Biopentra_Contact_Inbox_Email_Reply_Template::build_ticket_bodies( $ticket, $to, $reply_html, $quote['html'], $quote['plain'] );
			$full_html = $bodies['html'];
			$full_plain = $bodies['plain'];
			$result     = self::send_html_mail( $to, $subject, $full_html, $extra, $full_plain, true );
		} else {
			$full_html  = self::wrap_reply_with_quote( $reply_html, $quote['html'] );
			$reply_plain = trim( wp_strip_all_tags( $reply_html ) );
			$full_plain  = $reply_plain;
			if ( $quote['plain'] !== '' ) {
				$full_plain .= "\n\n" . '---' . "\n" . $quote['plain'];
			}
			$result = self::send_html_mail( $to, $subject, $full_html, $extra, $full_plain, false );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$now = current_time( 'mysql' );
		Biopentra_Contact_Inbox_Message_Repository::insert(
			array(
				'ticket_id'   => $ticket_id,
				'direction'   => 'outbound',
				'source'      => 'manual',
				'message_id'  => $new_raw,
				'in_reply_to' => $in_reply !== '' ? $in_reply : null,
				'from_email'  => $from_email,
				'from_name'   => $from_name,
				'to_email'    => $to,
				'subject'     => $subject,
				'body_text'   => $full_plain,
				'body_html'   => $full_html,
				'raw_headers' => implode( "\r\n", $extra ),
				'created_at'  => $now,
			)
		);

		Biopentra_Contact_Inbox_Ticket_Repository::update_status( $ticket_id, 'pending' );
		Biopentra_Contact_Inbox_Ticket_Repository::touch( $ticket_id, $now, false );

		return true;
	}

	/**
	 * @param array<int, object> $messages Chronological slice (e.g. last N prior messages).
	 * @return array{html: string, plain: string}
	 */
	private static function build_customer_quote_blocks( array $messages ) {
		if ( empty( $messages ) ) {
			return array(
				'html'  => '',
				'plain' => '',
			);
		}
		$html_blocks  = array();
		$plain_blocks = array();
		foreach ( $messages as $m ) {
			$one = self::format_one_prior_message_for_customer( $m );
			if ( $one['plain'] === '' ) {
				continue;
			}
			$html_blocks[]  = $one['html'];
			$plain_blocks[] = $one['plain'];
		}
		if ( empty( $plain_blocks ) ) {
			return array(
				'html'  => '',
				'plain' => '',
			);
		}
		$title = __( 'Recent conversation', 'biopentra-contact-inbox' );
		$html  = '<div style="margin-top:28px;padding-top:20px;border-top:1px solid #dcdcde;">';
		$html .= '<p style="margin:0 0 12px;font-size:12px;color:#646970;text-transform:uppercase;letter-spacing:0.04em;">' . esc_html( $title ) . '</p>';
		$html .= implode( '', $html_blocks );
		$html .= '</div>';

		$plain = $title . "\n" . str_repeat( '-', min( 40, strlen( $title ) + 5 ) ) . "\n\n";
		$plain .= implode( "\n\n" . str_repeat( '-', 24 ) . "\n\n", $plain_blocks );

		return array(
			'html'  => $html,
			'plain' => $plain,
		);
	}

	/**
	 * @param object $m Message row.
	 * @return array{html: string, plain: string}
	 */
	private static function format_one_prior_message_for_customer( $m ) {
		$dir = isset( $m->direction ) ? sanitize_key( (string) $m->direction ) : '';
		$src = isset( $m->source ) ? sanitize_key( (string) $m->source ) : '';
		$when = isset( $m->created_at ) ? (string) $m->created_at : '';
		$when_disp = $when !== ''
			? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $when )
			: '';

		if ( 'outbound' === $dir ) {
			$who = __( 'Biopentra', 'biopentra-contact-inbox' );
		} else {
			$who = __( 'You', 'biopentra-contact-inbox' );
		}

		$body_plain = self::message_plain_excerpt_for_customer( $m );
		$body_plain = trim( (string) $body_plain );
		if ( $body_plain === '' ) {
			return array(
				'html'  => '',
				'plain' => '',
			);
		}
		$body_plain = self::truncate_plain( $body_plain, 6000 );

		$head_plain = '[' . $who . '] ' . $when_disp;
		$head_html  = '<p style="margin:0 0 6px;font-size:12px;color:#646970;"><strong>' . esc_html( $who ) . '</strong>';
		if ( $when_disp !== '' ) {
			$head_html .= ' · <span style="color:#646970;">' . esc_html( $when_disp ) . '</span>';
		}
		$head_html .= '</p>';

		$safe_body_html = esc_html( $body_plain );
		$safe_body_html = nl2br( $safe_body_html, false );

		$html  = '<blockquote style="margin:0 0 16px;padding:12px 14px;border-left:3px solid #c3c4c7;background:#f6f7f7;font-size:13px;line-height:1.45;color:#1d2327;">';
		$html .= $head_html;
		$html .= '<div style="margin:0;">' . $safe_body_html . '</div>';
		$html .= '</blockquote>';

		$plain = $head_plain . "\n" . $body_plain;

		return array(
			'html'  => $html,
			'plain' => $plain,
		);
	}

	/**
	 * Plain excerpt for customer-visible quotes (no raw Fluent JSON or internal dumps).
	 *
	 * @param object $m Message row.
	 * @return string
	 */
	private static function message_plain_excerpt_for_customer( $m ) {
		$src  = isset( $m->source ) ? sanitize_key( (string) $m->source ) : '';
		$dir  = isset( $m->direction ) ? sanitize_key( (string) $m->direction ) : '';
		$text = isset( $m->body_text ) && $m->body_text !== null ? (string) $m->body_text : '';
		$html = isset( $m->body_html ) && $m->body_html !== null ? (string) $m->body_html : '';

		if ( 'fluent' === $src && 'inbound' === $dir && $text !== '' ) {
			$decoded = json_decode( $text, true );
			if ( is_array( $decoded ) ) {
				$lines = Biopentra_Contact_Inbox_Submission_Repository::human_summary_lines( $decoded );
				if ( ! empty( $lines ) ) {
					return implode( "\n", $lines );
				}
				$snippet = trim( wp_strip_all_tags( $text ) );
				if ( strlen( $snippet ) > 1 && '{' !== substr( ltrim( $snippet ), 0, 1 ) ) {
					return self::truncate_plain( $snippet, 2000 );
				}
				return __( '(Form submission)', 'biopentra-contact-inbox' );
			}
		}

		if ( $html !== '' ) {
			return trim( wp_strip_all_tags( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		}

		if ( $text !== '' ) {
			$snippet = trim( wp_strip_all_tags( $text ) );
			if ( strlen( $snippet ) > 0 && '{' === substr( ltrim( $snippet ), 0, 1 ) ) {
				$try = json_decode( $text, true );
				if ( is_array( $try ) ) {
					$lines = Biopentra_Contact_Inbox_Submission_Repository::human_summary_lines( $try );
					if ( ! empty( $lines ) ) {
						return implode( "\n", $lines );
					}
				}
				return self::truncate_plain( $snippet, 2000 );
			}
			return $snippet;
		}

		return '';
	}

	/**
	 * @param string $s   Plain text.
	 * @param int    $max Max characters.
	 * @return string
	 */
	private static function truncate_plain( $s, $max ) {
		$s = (string) $s;
		$max = max( 100, (int) $max );
		if ( strlen( $s ) <= $max ) {
			return $s;
		}
		return substr( $s, 0, $max - 1 ) . '…';
	}

	/**
	 * @param string $reply_html Sanitized staff reply HTML.
	 * @param string $quote_html HTML for quoted prior messages (already escaped inner body).
	 * @return string
	 */
	private static function wrap_reply_with_quote( $reply_html, $quote_html ) {
		$out  = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif;font-size:15px;line-height:1.55;color:#1d2327;">';
		$out .= '<div class="bsd-inbox-mail-reply">' . $reply_html . '</div>';
		if ( $quote_html !== '' ) {
			$out .= $quote_html;
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * @param string   $to            Recipient.
	 * @param string   $subject       Subject.
	 * @param string   $body          HTML body (already sanitized).
	 * @param string[] $extra_headers Extra RFC headers.
	 * @param string|null $plain_alt          Plain-text body for multipart/alternative; null = derive from HTML.
	 * @param bool        $skip_body_wpautop When true, body is already final HTML (do not run wpautop() on it).
	 * @return bool|\WP_Error
	 */
	private static function send_html_mail( $to, $subject, $body, array $extra_headers, $plain_alt = null, $skip_body_wpautop = false ) {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid recipient email.', 'biopentra-contact-inbox' ) );
		}
		$from_name  = sanitize_text_field( get_option( 'biopentra_inbox_from_name', 'Biopentra' ) );
		$from_email = sanitize_email( get_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) ) );
		if ( ! is_email( $from_email ) ) {
			$from_email = sanitize_email( get_option( 'admin_email' ) );
		}

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$headers[] = sprintf(
			'From: %s <%s>',
			self::encode_header_name( $from_name ),
			$from_email
		);

		foreach ( $extra_headers as $h ) {
			$h = trim( (string) $h );
			if ( $h !== '' ) {
				$headers[] = $h;
			}
		}

		$bcc = sanitize_email( get_option( 'biopentra_inbox_bcc_email', '' ) );
		if ( $bcc && is_email( $bcc ) ) {
			$headers[] = 'Bcc: ' . $bcc;
		}

		if ( null === $plain_alt ) {
			$plain_alt = trim( wp_strip_all_tags( $body ) );
		} else {
			$plain_alt = trim( (string) $plain_alt );
		}

		$body_for_mail = $skip_body_wpautop ? $body : wpautop( $body );
		$args = apply_filters(
			'biopentra_inbox_wp_mail_args',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body_for_mail,
				'headers' => $headers,
			)
		);

		self::$pending_plain_alt = $plain_alt;
		add_action( 'phpmailer_init', array( __CLASS__, 'filter_phpmailer_set_altbody' ), 10, 1 );

		// plugin_only: Bridge_Smtp re-applies Bridge after WP Mail SMTP’s phpmailer_init (priority 10) and restores
		// From / Sender via wp_mail_from* filters registered in begin_plugin_mail() (same PHP_INT_MAX priority, added last).
		$scope = get_option( 'biopentra_inbox_smtp_scope', 'plugin_only' );
		if ( 'plugin_only' === $scope ) {
			Biopentra_Contact_Inbox_Bridge_Smtp::begin_plugin_mail();
		}
		try {
			$sent = wp_mail( $args['to'], $args['subject'], $args['body'], $args['headers'] );
		} finally {
			remove_action( 'phpmailer_init', array( __CLASS__, 'filter_phpmailer_set_altbody' ), 10 );
			self::$pending_plain_alt = '';
			if ( 'plugin_only' === $scope ) {
				Biopentra_Contact_Inbox_Bridge_Smtp::end_plugin_mail();
			}
		}

		if ( ! $sent ) {
			return new \WP_Error( 'send_failed', __( 'Could not send email.', 'biopentra-contact-inbox' ) );
		}

		return true;
	}

	/**
	 * @param \PHPMailer\PHPMailer\PHPMailer|\PHPMailer $phpmailer Mailer.
	 */
	public static function filter_phpmailer_set_altbody( $phpmailer ) {
		if ( ! is_object( $phpmailer ) ) {
			return;
		}
		if ( self::$pending_plain_alt === '' ) {
			return;
		}
		$phpmailer->AltBody = self::$pending_plain_alt;
	}

	/**
	 * @param string $name From display name.
	 * @return string
	 */
	private static function encode_header_name( $name ) {
		return sanitize_text_field( $name );
	}
}
