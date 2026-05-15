<?php
/**
 * Activation: schema, defaults, capability, upgrades.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Activator {

	const DB_VERSION = '2.2.0';

	const DEFAULT_FORM_TITLE = 'Support desk contact form';

	/**
	 * Run dbDelta for all plugin tables (replies + tickets + messages).
	 */
	public static function install_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$replies = $wpdb->prefix . 'biopentra_inbox_replies';
		$sql_r   = "CREATE TABLE {$replies} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) unsigned NOT NULL,
			form_id int(10) unsigned NOT NULL,
			admin_user_id bigint(20) unsigned NOT NULL,
			recipient_email varchar(255) NOT NULL,
			subject varchar(255) NOT NULL,
			body longtext NOT NULL,
			sent_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY form_sent (form_id, sent_at)
		) {$charset};";
		dbDelta( $sql_r );

		$tickets = $wpdb->prefix . 'biopentra_inbox_tickets';
		$sql_t   = "CREATE TABLE {$tickets} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(20) NOT NULL DEFAULT 'email',
			source_ref varchar(191) NULL,
			subject varchar(255) NOT NULL,
			customer_email varchar(255) NOT NULL,
			customer_name varchar(255) NULL,
			to_email varchar(255) NULL,
			ticket_number bigint(20) unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			is_unread tinyint(1) NOT NULL DEFAULT 1,
			assigned_user_id bigint(20) unsigned NULL,
			last_message_at datetime NOT NULL,
			archived_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY customer_email (customer_email),
			KEY last_message_at (last_message_at),
			KEY source_source_ref (source, source_ref),
			KEY to_email (to_email),
			KEY archived_at (archived_at),
			UNIQUE KEY ticket_number (ticket_number)
		) {$charset};";
		dbDelta( $sql_t );

		$messages = $wpdb->prefix . 'biopentra_inbox_messages';
		$sql_m    = "CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ticket_id bigint(20) unsigned NOT NULL,
			direction varchar(10) NOT NULL,
			source varchar(20) NOT NULL,
			message_id varchar(191) NULL,
			in_reply_to varchar(191) NULL,
			imap_folder varchar(191) NULL,
			imap_uidvalidity varchar(64) NULL,
			imap_uid bigint(20) unsigned NULL,
			imap_dedupe_key varchar(191) NULL,
			from_email varchar(255) NOT NULL,
			from_name varchar(255) NULL,
			to_email varchar(255) NULL,
			subject varchar(255) NOT NULL,
			body_text longtext NULL,
			body_html longtext NULL,
			raw_headers longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id),
			KEY message_id (message_id),
			KEY created_at (created_at),
			UNIQUE KEY message_id_unique (message_id),
			UNIQUE KEY imap_dedupe_key_unique (imap_dedupe_key)
		) {$charset};";
		dbDelta( $sql_m );

		update_option( 'biopentra_inbox_db_version', self::DB_VERSION );
	}

	public static function activate() {
		self::install_tables();
		self::maybe_add_capability();
		self::maybe_default_options();
	}

	/**
	 * Idempotent schema/options bump for existing installs.
	 */
	public static function maybe_upgrade() {
		$current = get_option( 'biopentra_inbox_db_version', '0' );
		if ( version_compare( (string) $current, self::DB_VERSION, '<' ) ) {
			self::install_tables();
			if ( version_compare( (string) $current, '2.1.0', '<' ) ) {
				delete_option( 'biopentra_inbox_backfill_to_email_done' );
				delete_option( 'biopentra_inbox_backfill_to_email_last_id' );
			}
			if ( version_compare( (string) $current, '2.2.0', '<' ) ) {
				global $wpdb;
				$t = $wpdb->prefix . 'biopentra_inbox_tickets';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "UPDATE `{$t}` SET ticket_number = id WHERE ticket_number IS NULL" );
			}
		}
		self::maybe_default_options();
		self::maybe_migrate_import_driver();
	}

	private static function maybe_migrate_import_driver() {
		$v = get_option( 'biopentra_inbox_import_driver', null );
		if ( null === $v || $v === '' ) {
			update_option( 'biopentra_inbox_import_driver', 'worker' );
		}
		if ( null === get_option( 'biopentra_inbox_worker_import_enabled', null ) ) {
			update_option( 'biopentra_inbox_worker_import_enabled', 'yes' );
		}
		if ( null === get_option( 'biopentra_inbox_worker_token_hash', null ) ) {
			add_option( 'biopentra_inbox_worker_token_hash', '' );
		}
		if ( null === get_option( 'biopentra_inbox_last_worker_heartbeat', null ) ) {
			add_option( 'biopentra_inbox_last_worker_heartbeat', '' );
		}
	}

	private static function maybe_add_capability() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( BIOPENTRA_INBOX_CAP ) ) {
			$role->add_cap( BIOPENTRA_INBOX_CAP );
		}
	}

	private static function maybe_default_options() {
		global $wpdb;
		$form_id = 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}fluentform_forms WHERE title = %s ORDER BY id DESC LIMIT 1",
				self::DEFAULT_FORM_TITLE
			)
		);
		if ( $found ) {
			$form_id = (int) $found;
		}

		add_option( 'biopentra_inbox_contact_form_id', $form_id );
		$from_default = get_bloginfo( 'name', 'display' );
		add_option( 'biopentra_inbox_from_name', ( is_string( $from_default ) && $from_default !== '' ) ? $from_default : '' );
		add_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) );
		add_option( 'biopentra_inbox_default_reply_subject', __( 'Re: Your support inquiry', 'biopentra-contact-inbox' ) );
		add_option( 'biopentra_inbox_bcc_email', '' );
		add_option( 'biopentra_inbox_store_reply_history', 'yes' );
		add_option( 'biopentra_inbox_delete_on_uninstall', 'no' );
		add_option( 'biopentra_inbox_display_name', __( 'Fluent IMAP Support Desk', 'biopentra-contact-inbox' ) );

		add_option( 'biopentra_inbox_email_enabled', 'no' );
		add_option( 'biopentra_inbox_imap_host', 'proton-bridge' );
		add_option( 'biopentra_inbox_imap_port', '2143' );
		add_option( 'biopentra_inbox_imap_user', '' );
		add_option( 'biopentra_inbox_imap_pass', '' );
		add_option( 'biopentra_inbox_imap_mailbox', 'INBOX' );
		add_option( 'biopentra_inbox_imap_search', 'UNSEEN' );
		add_option( 'biopentra_inbox_imap_mark_seen', 'yes' );

		add_option( 'biopentra_inbox_smtp_host', 'proton-bridge' );
		add_option( 'biopentra_inbox_smtp_port', '2025' );
		add_option( 'biopentra_inbox_smtp_user', '' );
		add_option( 'biopentra_inbox_smtp_pass', '' );
		add_option( 'biopentra_inbox_smtp_scope', 'plugin_only' );

		add_option( 'biopentra_inbox_sync_enabled', 'no' );
		add_option( 'biopentra_inbox_sync_interval', 300 );
		add_option( 'biopentra_inbox_sync_message_cap', 50 );
		add_option( 'biopentra_inbox_last_sync_at', '' );
		add_option( 'biopentra_inbox_last_sync_result', '' );

		add_option( 'biopentra_inbox_import_driver', 'worker' );
		add_option( 'biopentra_inbox_worker_import_enabled', 'yes' );
		add_option( 'biopentra_inbox_worker_token_hash', '' );
		add_option( 'biopentra_inbox_last_worker_heartbeat', '' );

		add_option( 'biopentra_inbox_fluent_migrate_cursor', '0' );

		add_option( 'biopentra_inbox_archive_auto_delete_days', 30 );
		add_option( 'biopentra_inbox_reply_template_enabled', 'yes' );
		add_option( 'biopentra_inbox_reply_logo_source', 'site_logo' );
		add_option( 'biopentra_inbox_reply_logo_custom_url', '' );
		add_option( 'biopentra_inbox_reply_header', "Hello {customer_name},\n" );
		add_option( 'biopentra_inbox_reply_footer', "Best regards,\n{site_name} Support\n\nTicket: {ticket_number}" );
		add_option( 'biopentra_inbox_reply_company_source', 'wc_address' );
		add_option( 'biopentra_inbox_reply_company_custom', '' );
		add_option( 'biopentra_inbox_backfill_to_email_last_id', 0 );
		add_option( 'biopentra_inbox_backfill_to_email_done', '' );
	}

	/**
	 * Clear scheduled IMAP sync (deactivation).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'biopentra_inbox_imap_sync' );
		wp_clear_scheduled_hook( 'biopentra_inbox_archived_cleanup' );
	}
}
