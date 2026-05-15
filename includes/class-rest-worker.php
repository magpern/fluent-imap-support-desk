<?php
/**
 * REST API for external mail worker (biopentra-support/v1).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Rest_Worker {

	const API_NAMESPACE = 'biopentra-support/v1';

	const HASH_ALGO = 'sha256';

	/** Docker Compose service hostname for the mail worker (internal HTTP, not published). */
	const WORKER_CONTAINER_HTTP_HOST = 'biopentra-mail-worker';

	/** Internal HTTP port (must match WORKER_HTTP_PORT in Compose / worker image). */
	const WORKER_CONTAINER_HTTP_PORT = 8080;

	/**
	 * Register routes (called from rest_api_init).
	 */
	public static function register_routes() {
		register_rest_route(
			self::API_NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_health' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/messages/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_import' ),
				'permission_callback' => array( __CLASS__, 'permission_bearer' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/worker/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_status' ),
				'permission_callback' => array( __CLASS__, 'permission_bearer' ),
			)
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function handle_health() {
		return new WP_REST_Response(
			array(
				'plugin'                   => 'biopentra-contact-inbox',
				'plugin_active'              => true,
				'version'                    => BIOPENTRA_INBOX_VERSION,
				'time'                       => gmdate( 'c' ),
				'rest_namespace'             => self::API_NAMESPACE,
				'worker_token_configured'    => self::is_token_configured(),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_import( WP_REST_Request $request ) {
		if ( 'yes' !== get_option( 'biopentra_inbox_worker_import_enabled', 'yes' ) ) {
			return new WP_Error(
				'worker_import_disabled',
				__( 'Worker import is disabled in plugin settings.', 'biopentra-contact-inbox' ),
				array( 'status' => 403 )
			);
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error(
				'invalid_json',
				__( 'Invalid JSON body.', 'biopentra-contact-inbox' ),
				array( 'status' => 400 )
			);
		}

		$res = Biopentra_Contact_Inbox_Inbound_Import::import_payload( $params );
		if ( ! empty( $res['success'] ) && isset( $res['status'] ) && 'skipped_duplicate' === $res['status'] ) {
			return new WP_REST_Response(
				array(
					'status' => 'skipped_duplicate',
					'reason' => isset( $res['reason'] ) ? (string) $res['reason'] : '',
				),
				200
			);
		}

		if ( ! empty( $res['success'] ) && isset( $res['status'] ) && 'imported' === $res['status'] ) {
			return new WP_REST_Response(
				array(
					'status' => 'imported',
				),
				200
			);
		}

		$code = isset( $res['error_code'] ) ? (string) $res['error_code'] : 'validation';
		$msg  = isset( $res['message'] ) ? (string) $res['message'] : __( 'Import failed.', 'biopentra-contact-inbox' );
		return new WP_Error(
			$code,
			$msg,
			array( 'status' => 400 )
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_status( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error(
				'invalid_json',
				__( 'Invalid JSON body.', 'biopentra-contact-inbox' ),
				array( 'status' => 400 )
			);
		}

		$heartbeat = isset( $params['heartbeat_at'] ) ? sanitize_text_field( (string) $params['heartbeat_at'] ) : '';
		if ( $heartbeat === '' ) {
			$heartbeat = current_time( 'mysql' );
		}

		$worker_version = isset( $params['worker_version'] ) ? sanitize_text_field( (string) $params['worker_version'] ) : '';
		$worker_version = substr( $worker_version, 0, 64 );

		$poll_status = isset( $params['poll_status'] ) ? sanitize_key( (string) $params['poll_status'] ) : '';
		$poll_status = substr( $poll_status, 0, 32 );

		$imported = isset( $params['imported'] ) ? (int) $params['imported'] : 0;
		$skipped  = isset( $params['skipped'] ) ? (int) $params['skipped'] : 0;
		$errors_n = 0;
		if ( isset( $params['error_count'] ) ) {
			$errors_n = (int) $params['error_count'];
		} elseif ( isset( $params['errors'] ) ) {
			$errors_n = (int) $params['errors'];
		}

		$last_error = '';
		if ( ! empty( $params['last_error'] ) && is_string( $params['last_error'] ) ) {
			$last_error = sanitize_text_field( $params['last_error'] );
			$last_error = substr( $last_error, 0, 800 );
		}

		update_option( 'biopentra_inbox_last_worker_heartbeat', $heartbeat );

		$summary = array(
			'source'          => 'worker',
			'worker_version'  => $worker_version,
			'poll_status'     => $poll_status,
			'imported'        => $imported,
			'skipped'         => $skipped,
			'errors'          => $errors_n,
			'last_error'      => $last_error,
			'received_at_gmt' => gmdate( 'c' ),
		);

		update_option( 'biopentra_inbox_last_sync_at', current_time( 'mysql' ) );
		update_option( 'biopentra_inbox_last_sync_result', wp_json_encode( $summary ) );

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * @return bool|WP_Error
	 */
	public static function permission_bearer() {
		$auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( $auth === '' && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		}
		if ( ! is_string( $auth ) || $auth === '' ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Missing Authorization header.', 'biopentra-contact-inbox' ),
				array( 'status' => 401 )
			);
		}

		if ( ! preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid Authorization scheme.', 'biopentra-contact-inbox' ),
				array( 'status' => 401 )
			);
		}

		$token = $m[1];
		if ( $token === '' ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Empty bearer token.', 'biopentra-contact-inbox' ),
				array( 'status' => 401 )
			);
		}

		$stored = (string) get_option( 'biopentra_inbox_worker_token_hash', '' );
		if ( $stored === '' ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Worker token is not configured.', 'biopentra-contact-inbox' ),
				array( 'status' => 401 )
			);
		}

		$calc = hash( self::HASH_ALGO, $token );
		if ( ! hash_equals( $stored, $calc ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid bearer token.', 'biopentra-contact-inbox' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public static function is_token_configured() {
		$h = (string) get_option( 'biopentra_inbox_worker_token_hash', '' );
		return $h !== '';
	}

	/**
	 * Generate a new worker token; persist hash; return plaintext once.
	 *
	 * @return string Plaintext token.
	 */
	public static function generate_worker_token() {
		$plain = bin2hex( random_bytes( 32 ) );
		$hash  = hash( self::HASH_ALGO, $plain );
		update_option( 'biopentra_inbox_worker_token_hash', $hash );
		return $plain;
	}

	/**
	 * Internal Docker URL for GET /health on the worker (no auth).
	 *
	 * @return string
	 */
	public static function worker_container_http_health_url() {
		return 'http://' . self::WORKER_CONTAINER_HTTP_HOST . ':' . self::WORKER_CONTAINER_HTTP_PORT . '/health';
	}

	/**
	 * Internal Docker URL for POST /poll on the worker (internal network only; no Authorization).
	 *
	 * @return string
	 */
	public static function worker_container_http_poll_url() {
		return 'http://' . self::WORKER_CONTAINER_HTTP_HOST . ':' . self::WORKER_CONTAINER_HTTP_PORT . '/poll';
	}

	/**
	 * HTTP GET worker container /health from WordPress (e.g. inside the wordpress service on bridge-net).
	 *
	 * @return array{ healthy: bool, message: string }
	 */
	public static function probe_worker_container_http_health() {
		$url      = self::worker_container_http_health_url();
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'healthy' => false,
				'message' => sprintf(
					/* translators: %s: error message from HTTP client */
					__( 'Worker HTTP health check: unhealthy — request failed (%s).', 'biopentra-contact-inbox' ),
					$response->get_error_message()
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		$ok   = is_array( $data ) && array_key_exists( 'ok', $data ) && true === $data['ok'];

		if ( 200 === $code && $ok ) {
			return array(
				'healthy' => true,
				'message' => __( 'Worker HTTP health check: healthy (HTTP 200, JSON ok: true).', 'biopentra-contact-inbox' ),
			);
		}

		$snippet = $body;
		if ( strlen( $snippet ) > 240 ) {
			$snippet = substr( $snippet, 0, 240 ) . '…';
		}

		return array(
			'healthy' => false,
			'message' => sprintf(
				/* translators: 1: HTTP status code, 2: response body snippet */
				__( 'Worker HTTP health check: unhealthy (HTTP %1$d; expected 200 with JSON ok: true). Body: %2$s', 'biopentra-contact-inbox' ),
				$code,
				$snippet !== '' ? $snippet : '—'
			),
		);
	}

	/**
	 * Ask the mail worker to run an immediate mailbox check (internal HTTP POST /poll, no Authorization).
	 *
	 * @return array{ ok: bool, message: string }
	 */
	public static function trigger_worker_mailbox_check() {
		$url      = self::worker_container_http_poll_url();
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Could not reach the mail worker (%s).', 'biopentra-contact-inbox' ),
					$response->get_error_message()
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return array(
				'ok'      => true,
				'message' => '',
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > 200 ) {
			$body = substr( $body, 0, 200 ) . '…';
		}

		return array(
			'ok'      => false,
			'message' => sprintf(
				/* translators: %1$d: HTTP status, %2$s: body snippet */
				__( 'Mail worker returned an error (HTTP %1$d). %2$s', 'biopentra-contact-inbox' ),
				$code,
				$body !== '' ? $body : '—'
			),
		);
	}
}
