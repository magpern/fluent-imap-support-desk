<?php
/**
 * Plugin Name:       Fluent IMAP Support Desk
 * Description:       Support desk bridging Fluent Forms tickets with IMAP/SMTP (Proton Bridge and external mail worker compatible).
 * Version:           2.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Fluent IMAP Support Desk
 * Text Domain:       biopentra-contact-inbox
 *
 * Compatibility: internal constants, options, tables, cron hooks, and REST namespace
 * (biopentra-support/v1) are unchanged from the biopentra-contact-inbox lineage.
 *
 * @package Fluent_IMAP_Support_Desk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BIOPENTRA_INBOX_VERSION' ) ) {
	define( 'BIOPENTRA_INBOX_VERSION', '2.0.3' );
}

if ( ! defined( 'BIOPENTRA_INBOX_PATH' ) ) {
	define( 'BIOPENTRA_INBOX_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BIOPENTRA_INBOX_URL' ) ) {
	define( 'BIOPENTRA_INBOX_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'BIOPENTRA_INBOX_CAP' ) ) {
	define( 'BIOPENTRA_INBOX_CAP', 'manage_biopentra_inbox' );
}

/** Product slug for future use; not used for DB/REST yet. */
if ( ! defined( 'FISD_PLUGIN_SLUG' ) ) {
	define( 'FISD_PLUGIN_SLUG', 'fluent-imap-support-desk' );
}

/**
 * Visible default From name when biopentra_inbox_from_name is empty.
 *
 * @return string
 */
function fisd_fallback_from_name() {
	$blog = get_bloginfo( 'name', 'display' );
	return ( is_string( $blog ) && $blog !== '' ) ? $blog : __( 'Support', 'biopentra-contact-inbox' );
}

/**
 * Resolved outbound From name (option or site fallback).
 *
 * @return string
 */
function fisd_get_from_name() {
	$v = get_option( 'biopentra_inbox_from_name', '' );
	if ( is_string( $v ) && trim( $v ) !== '' ) {
		return sanitize_text_field( $v );
	}
	return fisd_fallback_from_name();
}

/**
 * Visible default reply subject when option is empty.
 *
 * @return string
 */
function fisd_fallback_reply_subject() {
	return __( 'Re: Your support inquiry', 'biopentra-contact-inbox' );
}

/**
 * Resolved default reply subject (option or fallback).
 *
 * @return string
 */
function fisd_get_default_reply_subject() {
	$v = get_option( 'biopentra_inbox_default_reply_subject', '' );
	if ( is_string( $v ) && trim( $v ) !== '' ) {
		return sanitize_text_field( $v );
	}
	return fisd_fallback_reply_subject();
}

require_once BIOPENTRA_INBOX_PATH . 'includes/class-activator.php';
register_activation_hook( __FILE__, array( 'Biopentra_Contact_Inbox_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Biopentra_Contact_Inbox_Activator', 'deactivate' ) );

/**
 * True when Bridge SMTP is configured to hijack all wp_mail() (WooCommerce, Elementor, etc.).
 *
 * @return bool
 */
function biopentra_inbox_smtp_applies_globally() {
	return get_option( 'biopentra_inbox_email_enabled', 'no' ) === 'yes'
		&& get_option( 'biopentra_inbox_smtp_scope', 'plugin_only' ) === 'all_wp_mail';
}

/**
 * Register Bridge SMTP for global wp_mail when scope is all_wp_mail (checkout, REST previews, etc.).
 */
function biopentra_inbox_bridge_smtp_init_once() {
	static $smtp_initialized = false;
	if ( $smtp_initialized ) {
		return;
	}
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-bridge-smtp.php';
	Biopentra_Contact_Inbox_Bridge_Smtp::init();
	$smtp_initialized = true;
}

/**
 * Register Bridge SMTP for global wp_mail when scope is all_wp_mail (checkout, REST previews, etc.).
 */
function biopentra_inbox_maybe_init_smtp() {
	if ( biopentra_inbox_smtp_applies_globally() ) {
		biopentra_inbox_bridge_smtp_init_once();
	}
}

/**
 * Load plugin runtime (admin, WP-CLI, WP-Cron, or global SMTP scope).
 */
function biopentra_inbox_should_load_runtime() {
	if ( biopentra_inbox_smtp_applies_globally() ) {
		return true;
	}
	if ( is_admin() ) {
		return true;
	}
	if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
		return true;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}
	return false;
}

function biopentra_inbox_init() {
	biopentra_inbox_maybe_init_smtp();
	if ( ! biopentra_inbox_should_load_runtime() ) {
		return;
	}

	require_once BIOPENTRA_INBOX_PATH . 'includes/class-ticket-ref.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-subject-normalizer.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-message-id.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-form-resolver.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-submission-repository.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-reply-repository.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-ticket-repository.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-message-repository.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-inbound-import.php';
	biopentra_inbox_bridge_smtp_init_once();
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-ticket-backfill.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-archived-email-cleanup.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-inbox-cron.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-imap-sync.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-support-desk-reset.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-bridge-diagnostics.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-fluent-migration.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-fluent-ticket-bridge.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-email-reply-template.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-mailer.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-settings.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-list-table.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-admin-detail.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-plugin.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-github-updater.php';

	FISD_Github_Updater::maybe_init();

	Biopentra_Contact_Inbox_Activator::maybe_upgrade();
	Biopentra_Contact_Inbox_Ticket_Backfill::maybe_run_to_email_chunk();

	Biopentra_Contact_Inbox_Cron::init();
	add_action( 'plugins_loaded', array( 'Biopentra_Contact_Inbox_Fluent_Ticket_Bridge', 'init' ), 100 );
	Biopentra_Contact_Inbox_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'biopentra_inbox_init', 20 );

/**
 * Load REST-only dependencies and register worker routes (runs on REST bootstrap).
 */
function biopentra_inbox_rest_api_init() {
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-ticket-ref.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-subject-normalizer.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-message-id.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-ticket-repository.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-message-repository.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-inbound-import.php';
	require_once BIOPENTRA_INBOX_PATH . 'includes/class-rest-worker.php';
	Biopentra_Contact_Inbox_Rest_Worker::register_routes();
}
add_action( 'rest_api_init', 'biopentra_inbox_rest_api_init', 5 );
