<?php
/**
 * Proton Bridge diagnostics (IMAP/SMTP tests, logging).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Bridge_Diagnostics {

	const LOG_PREFIX = '[biopentra-support]';

	/**
	 * @param string $message Safe, non-secret message.
	 */
	public static function log_line( $message ) {
		$line = self::LOG_PREFIX . ' ' . $message;
		if ( function_exists( 'error_log' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $line );
		}
	}

	/**
	 * @param string $detail Raw detail (will be sanitized).
	 * @return string
	 */
	public static function truncate_detail( $detail ) {
		$s = is_string( $detail ) ? wp_strip_all_tags( $detail ) : '';
		$s = preg_replace( '/\s+/', ' ', $s );
		if ( strlen( $s ) > 200 ) {
			$s = substr( $s, 0, 197 ) . '...';
		}
		return $s;
	}

	/**
	 * @return array{code: string, detail: string}
	 */
	public static function run_imap_test() {
		if ( ! extension_loaded( 'imap' ) ) {
			self::log_line( 'IMAP test: PHP IMAP extension not loaded.' );
			return array(
				'code'   => 'missing_ext',
				'detail' => '',
			);
		}

		$host    = trim( (string) get_option( 'biopentra_inbox_imap_host', '' ) );
		$port    = absint( get_option( 'biopentra_inbox_imap_port', 2143 ) );
		$user    = trim( (string) get_option( 'biopentra_inbox_imap_user', '' ) );
		$pass    = Biopentra_Contact_Inbox_Imap_Sync::get_imap_password();
		$mailbox = trim( (string) get_option( 'biopentra_inbox_imap_mailbox', 'INBOX' ) );
		if ( $mailbox === '' ) {
			$mailbox = 'INBOX';
		}

		if ( $host === '' || $port <= 0 || $user === '' || $pass === '' ) {
			self::log_line( 'IMAP test: incomplete configuration (host/port/user/password).' );
			return array(
				'code'   => 'config_incomplete',
				'detail' => '',
			);
		}

		$mb = '{' . $host . ':' . (string) $port . '/imap/tls/novalidate-cert}' . $mailbox;

		try {
			$ro_flag = defined( 'OP_READONLY' ) ? (int) constant( 'OP_READONLY' ) : 2;
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$conn = @imap_open( $mb, $user, $pass, $ro_flag );
			if ( ! $conn ) {
				$err = (string) imap_last_error();
				imap_errors();
				imap_alerts();
				$code = self::classify_imap_error( $err );
				self::log_line( 'IMAP test failed: ' . $code . ' — ' . self::truncate_detail( $err ) );
				return array(
					'code'   => $code,
					'detail' => self::truncate_detail( $err ),
				);
			}

			// Read-only mailbox check; does not search or change flags.
			$chk = @imap_check( $conn );
			if ( false === $chk ) {
				$err = (string) imap_last_error();
				imap_errors();
				imap_alerts();
				imap_close( $conn );
				self::log_line( 'IMAP test: mailbox check failed — ' . self::truncate_detail( $err ) );
				return array(
					'code'   => 'connection_fail',
					'detail' => self::truncate_detail( $err ),
				);
			}

			imap_close( $conn );
			self::log_line( 'IMAP test: success.' );
			return array(
				'code'   => 'success',
				'detail' => '',
			);
		} catch ( \Throwable $e ) {
			$msg = $e->getMessage();
			self::log_line( 'IMAP test exception: ' . self::truncate_detail( $msg ) );
			return array(
				'code'   => 'exception',
				'detail' => self::truncate_detail( $msg ),
			);
		}
	}

	/**
	 * @param string $err IMAP error string.
	 * @return string auth_fail|connection_fail
	 */
	private static function classify_imap_error( $err ) {
		$e = strtolower( (string) $err );
		if ( strpos( $e, 'authentication' ) !== false || strpos( $e, 'auth' ) !== false || strpos( $e, 'credentials' ) !== false || strpos( $e, 'login' ) !== false ) {
			return 'auth_fail';
		}
		return 'connection_fail';
	}

	/**
	 * @return array{code: string, detail: string}
	 */
	public static function run_smtp_test() {
		$user = wp_get_current_user();
		$to    = $user && is_email( $user->user_email ) ? $user->user_email : '';

		if ( ! is_email( $to ) ) {
			self::log_line( 'SMTP test: current user has no valid email.' );
			return array(
				'code'   => 'bad_recipient',
				'detail' => '',
			);
		}

		if ( get_option( 'biopentra_inbox_email_enabled', 'no' ) !== 'yes' ) {
			self::log_line( 'SMTP test skipped: email inbox not enabled (Bridge SMTP not applied).' );
			return array(
				'code'   => 'bridge_disabled',
				'detail' => '',
			);
		}

		$host = trim( (string) get_option( 'biopentra_inbox_smtp_host', '' ) );
		$port  = absint( get_option( 'biopentra_inbox_smtp_port', 2025 ) );
		$suser = trim( (string) get_option( 'biopentra_inbox_smtp_user', '' ) );
		$pass  = Biopentra_Contact_Inbox_Bridge_Smtp::get_smtp_password();

		if ( $host === '' || $port <= 0 || $suser === '' || $pass === '' ) {
			self::log_line( 'SMTP test: incomplete SMTP configuration.' );
			return array(
				'code'   => 'config_incomplete',
				'detail' => '',
			);
		}

		$from_name  = fisd_get_from_name();
		$from_email = sanitize_email( get_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) ) );
		if ( ! is_email( $from_email ) ) {
			$from_email = sanitize_email( get_option( 'admin_email' ) );
		}

		$ts       = gmdate( 'c' );
		$subject  = sprintf(
			/* translators: %s: UTC ISO8601 timestamp */
			__( '[Fluent IMAP Support Desk] SMTP test %s', 'biopentra-contact-inbox' ),
			$ts
		);
		$site     = home_url();
		$phpv     = PHP_VERSION;
		$wpv      = get_bloginfo( 'version' );
		$body     = '<p>' . esc_html__( 'This is an automated SMTP connectivity test from Fluent IMAP Support Desk.', 'biopentra-contact-inbox' ) . '</p>';
		$body    .= '<ul>';
		$body    .= '<li><strong>' . esc_html__( 'Time (UTC)', 'biopentra-contact-inbox' ) . ':</strong> ' . esc_html( $ts ) . '</li>';
		$body    .= '<li><strong>' . esc_html__( 'Site', 'biopentra-contact-inbox' ) . ':</strong> ' . esc_html( $site ) . '</li>';
		$body    .= '<li><strong>PHP:</strong> ' . esc_html( $phpv ) . '</li>';
		$body    .= '<li><strong>WordPress:</strong> ' . esc_html( $wpv ) . '</li>';
		$body    .= '</ul>';

		$headers   = array();
		$headers[] = sprintf(
			'From: %s <%s>',
			sanitize_text_field( $from_name ),
			$from_email
		);

		$scope = get_option( 'biopentra_inbox_smtp_scope', 'plugin_only' );
		$last  = '';

		$capture = static function ( $wp_error ) use ( &$last ) {
			$last = $wp_error instanceof \WP_Error ? $wp_error->get_error_message() : '';
		};
		add_action( 'wp_mail_failed', $capture, 10, 1 );

		add_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_html' ), 99 );

		$sent = false;
		try {
			if ( 'plugin_only' === $scope ) {
				Biopentra_Contact_Inbox_Bridge_Smtp::begin_plugin_mail();
			}
			try {
				$sent = wp_mail( $to, $subject, $body, $headers );
			} finally {
				if ( 'plugin_only' === $scope ) {
					Biopentra_Contact_Inbox_Bridge_Smtp::end_plugin_mail();
				}
			}
		} catch ( \Throwable $e ) {
			remove_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_html' ), 99 );
			remove_action( 'wp_mail_failed', $capture, 10 );
			self::log_line( 'SMTP test exception: ' . self::truncate_detail( $e->getMessage() ) );
			return array(
				'code'   => 'exception',
				'detail' => self::truncate_detail( $e->getMessage() ),
			);
		}

		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_html' ), 99 );
		remove_action( 'wp_mail_failed', $capture, 10 );

		if ( $sent ) {
			self::log_line( 'SMTP test: message accepted for delivery (wp_mail returned true).' );
			return array(
				'code'   => 'success',
				'detail' => '',
			);
		}

		$detail = self::truncate_detail( $last );
		if ( $detail === '' && class_exists( 'PHPMailer\PHPMailer\PHPMailer' ) && isset( $GLOBALS['phpmailer'] ) && is_object( $GLOBALS['phpmailer'] ) && property_exists( $GLOBALS['phpmailer'], 'ErrorInfo' ) ) {
			$detail = self::truncate_detail( (string) $GLOBALS['phpmailer']->ErrorInfo );
		}

		$code = 'wp_mail_failed';
		$low  = strtolower( $detail );
		if ( strpos( $low, 'auth' ) !== false || strpos( $low, '535' ) !== false || strpos( $low, 'password' ) !== false ) {
			$code = 'auth_fail';
		} elseif ( strpos( $low, 'connect' ) !== false || strpos( $low, 'connection' ) !== false || strpos( $low, 'could not' ) !== false ) {
			$code = 'connection_fail';
		} elseif ( strpos( $low, 'timed out' ) !== false || strpos( $low, 'timeout' ) !== false ) {
			$code = 'timeout';
		} elseif ( $detail === '' ) {
			$code = 'suspected_conflict';
		}

		self::log_line( 'SMTP test failed: ' . $code . ( $detail !== '' ? ' — ' . $detail : '' ) );

		return array(
			'code'   => $code,
			'detail' => $detail,
		);
	}

	/**
	 * @return string
	 */
	public static function filter_mail_html() {
		return 'text/html; charset=UTF-8';
	}

	/**
	 * @param string $option_key Option name for stored password.
	 * @param string $const_name PHP constant name (without quotes).
	 * @return bool
	 */
	public static function is_password_configured( $option_key, $const_name ) {
		if ( is_string( $const_name ) && defined( $const_name ) ) {
			$v = constant( $const_name );
			if ( is_string( $v ) && $v !== '' ) {
				return true;
			}
		}
		$v = (string) get_option( $option_key, '' );
		return $v !== '';
	}
}
