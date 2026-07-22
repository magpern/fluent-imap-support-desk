<?php
/**
 * Proton Bridge SMTP via PHPMailer; optional plugin-only scope.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Bridge_Smtp {

	/**
	 * Run after WP Mail SMTP’s {@see phpmailer_init} (priority 10) and other early mailer tweaks.
	 * Use PHP_INT_MAX so this runs after any mailer using very large priorities (e.g. queue replay).
	 */
	const PHPMAILER_INIT_PRIORITY = PHP_INT_MAX;

	/**
	 * Match WP Mail SMTP’s {@see wp_mail_from} priority; register these in {@see begin_plugin_mail()} so they are
	 * added after WP Mail SMTP’s callbacks and run last among PHP_INT_MAX filters.
	 */
	const PLUGIN_MAIL_FROM_FILTER_PRIORITY = PHP_INT_MAX;

	/**
	 * @var bool
	 */
	private static $plugin_only_active = false;

	/**
	 * @var bool
	 */
	private static $fallback_hooks_registered = false;

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ), self::PHPMAILER_INIT_PRIORITY, 1 );
	}

	/**
	 * Mark next wp_mail as plugin-owned for Bridge SMTP (plugin_only scope).
	 */
	public static function begin_plugin_mail() {
		self::$plugin_only_active = true;
		self::register_plugin_mail_from_filters();
		self::register_fallback_hooks();
	}

	/**
	 * Clear plugin-only flag (primary path: caller try/finally).
	 */
	public static function end_plugin_mail() {
		self::unregister_plugin_mail_from_filters();
		self::$plugin_only_active = false;
	}

	/**
	 * WP Mail SMTP forces {@see wp_mail_from} / {@see wp_mail_from_name} at PHP_INT_MAX; register ours immediately
	 * before wp_mail so they run last and restore Support Desk From for this send only.
	 */
	private static function register_plugin_mail_from_filters() {
		self::unregister_plugin_mail_from_filters();
		add_filter( 'wp_mail_from', array( __CLASS__, 'filter_plugin_mail_from_email' ), self::PLUGIN_MAIL_FROM_FILTER_PRIORITY );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_plugin_mail_from_name' ), self::PLUGIN_MAIL_FROM_FILTER_PRIORITY );
	}

	private static function unregister_plugin_mail_from_filters() {
		remove_filter( 'wp_mail_from', array( __CLASS__, 'filter_plugin_mail_from_email' ), self::PLUGIN_MAIL_FROM_FILTER_PRIORITY );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_plugin_mail_from_name' ), self::PLUGIN_MAIL_FROM_FILTER_PRIORITY );
	}

	/**
	 * @param string $email Email after other filters (e.g. WP Mail SMTP).
	 * @return string
	 */
	public static function filter_plugin_mail_from_email( $email ) {
		if ( ! self::$plugin_only_active ) {
			return $email;
		}
		$configured = sanitize_email( get_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) ) );
		if ( ! is_email( $configured ) ) {
			return $email;
		}
		return $configured;
	}

	/**
	 * @param string $name From name after other filters.
	 * @return string
	 */
	public static function filter_plugin_mail_from_name( $name ) {
		if ( ! self::$plugin_only_active ) {
			return $name;
		}
		return fisd_get_from_name();
	}

	/**
	 * @return bool
	 */
	public static function is_plugin_mail_active() {
		return self::$plugin_only_active;
	}

	private static function register_fallback_hooks() {
		if ( self::$fallback_hooks_registered ) {
			return;
		}
		self::$fallback_hooks_registered = true;
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'end_plugin_mail' ), 999 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'end_plugin_mail' ), 999 );
		add_action( 'shutdown', array( __CLASS__, 'end_plugin_mail' ), 999 );
	}

	/**
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public static function configure_phpmailer( $phpmailer ) {
		if ( get_option( 'biopentra_inbox_email_enabled', 'no' ) !== 'yes' ) {
			return;
		}

		$scope = get_option( 'biopentra_inbox_smtp_scope', 'plugin_only' );
		if ( 'all_wp_mail' !== $scope && ! self::$plugin_only_active ) {
			return;
		}

		$host = (string) get_option( 'biopentra_inbox_smtp_host', '' );
		$port = absint( get_option( 'biopentra_inbox_smtp_port', 2025 ) );
		$user = (string) get_option( 'biopentra_inbox_smtp_user', '' );
		$pass = self::get_smtp_password();

		if ( $host === '' || $port <= 0 ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $host;
		$phpmailer->Port       = $port;
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $user;
		$phpmailer->Password   = $pass;
		$phpmailer->SMTPSecure = 'tls';
		$phpmailer->SMTPAutoTLS = true;

		$phpmailer->SMTPOptions = array(
			'ssl' => array(
				'verify_peer'       => false,
				'verify_peer_name'  => false,
				'allow_self_signed' => true,
			),
		);

		if ( self::$plugin_only_active || 'all_wp_mail' === $scope ) {
			$from_email = sanitize_email( get_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) ) );
			$from_name  = fisd_get_from_name();
			if ( is_email( $from_email ) ) {
				try {
					$phpmailer->setFrom( $from_email, $from_name, false );
				} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				}
				$phpmailer->Sender = $from_email;
			}
		}

		self::maybe_log_phpmailer_state( $phpmailer, $scope, $host, $port );
	}

	/**
	 * Optional debug line when sending through this plugin’s SMTP path (no secrets).
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer.
	 * @param string                        $scope     Current SMTP scope option.
	 * @param string                        $host      SMTP host applied.
	 * @param int                           $port      SMTP port applied.
	 */
	private static function maybe_log_phpmailer_state( $phpmailer, $scope, $host, $port ) {
		$enabled = apply_filters(
			'biopentra_inbox_log_outbound_smtp_debug',
			defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG
		);
		if ( ! $enabled || ! is_object( $phpmailer ) ) {
			return;
		}
		$from   = isset( $phpmailer->From ) ? (string) $phpmailer->From : '';
		$sender = isset( $phpmailer->Sender ) ? (string) $phpmailer->Sender : '';
		Biopentra_Contact_Inbox_Bridge_Diagnostics::log_line(
			sprintf(
				'Support desk Bridge SMTP applied: scope=%s plugin_mail=%s smtp_host=%s smtp_port=%d phpmailer_from=%s phpmailer_sender=%s',
				$scope,
				self::$plugin_only_active ? 'yes' : 'no',
				$host,
				$port,
				$from,
				$sender
			)
		);
	}

	/**
	 * @return string
	 */
	public static function get_smtp_password() {
		if ( defined( 'BIOPENTRA_INBOX_SMTP_PASS' ) && BIOPENTRA_INBOX_SMTP_PASS !== '' ) {
			return (string) BIOPENTRA_INBOX_SMTP_PASS;
		}
		return (string) get_option( 'biopentra_inbox_smtp_pass', '' );
	}
}
