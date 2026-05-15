<?php
/**
 * Biopentra Support Desk — tabbed settings.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Settings {

	const OPTION_GROUP = 'biopentra_inbox_settings';

	public static function register() {
		add_filter(
			'option_page_capability_' . self::OPTION_GROUP,
			array( __CLASS__, 'option_page_capability' )
		);

		$opts = array(
			'biopentra_inbox_display_name'           => array( 'type' => 'string', 'cb' => 'sanitize_text_field', 'def' => '' ),
			'biopentra_inbox_contact_form_id'        => array( 'type' => 'integer', 'cb' => array( __CLASS__, 'sanitize_form_id' ), 'def' => 0 ),
			'biopentra_inbox_from_name'              => array( 'type' => 'string', 'cb' => 'sanitize_text_field', 'def' => 'Biopentra' ),
			'biopentra_inbox_from_email'             => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_from_email' ), 'def' => '' ),
			'biopentra_inbox_default_reply_subject'  => array( 'type' => 'string', 'cb' => 'sanitize_text_field', 'def' => '' ),
			'biopentra_inbox_bcc_email'              => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_bcc' ), 'def' => '' ),
			'biopentra_inbox_store_reply_history'    => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_yes_no' ), 'def' => 'yes' ),
			'biopentra_inbox_delete_on_uninstall'    => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_yes_no' ), 'def' => 'no' ),
			'biopentra_inbox_email_enabled'          => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_yes_no' ), 'def' => 'no' ),
			'biopentra_inbox_imap_host'              => array( 'type' => 'string', 'cb' => 'sanitize_text_field', 'def' => 'proton-bridge' ),
			'biopentra_inbox_imap_port'              => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_digits' ), 'def' => '2143' ),
			'biopentra_inbox_imap_user'              => array( 'type' => 'string', 'cb' => 'sanitize_text_field', 'def' => '' ),
			'biopentra_inbox_imap_pass'              => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_imap_pass' ), 'def' => '' ),
			'biopentra_inbox_imap_mailbox'           => array( 'type' => 'string', 'cb' => 'sanitize_text_field', 'def' => 'INBOX' ),
			'biopentra_inbox_imap_search'            => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_imap_search' ), 'def' => 'UNSEEN' ),
			'biopentra_inbox_imap_mark_seen'         => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_yes_no' ), 'def' => 'yes' ),
			'biopentra_inbox_smtp_host'              => array( 'type' => 'string', 'cb' => 'sanitize_text_field', 'def' => 'proton-bridge' ),
			'biopentra_inbox_smtp_port'              => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_digits' ), 'def' => '2025' ),
			'biopentra_inbox_smtp_user'              => array( 'type' => 'string', 'cb' => 'sanitize_text_field', 'def' => '' ),
			'biopentra_inbox_smtp_pass'              => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_smtp_pass' ), 'def' => '' ),
			'biopentra_inbox_smtp_scope'             => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_smtp_scope' ), 'def' => 'plugin_only' ),
			'biopentra_inbox_sync_enabled'           => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_yes_no' ), 'def' => 'no' ),
			'biopentra_inbox_sync_interval'          => array( 'type' => 'integer', 'cb' => array( __CLASS__, 'sanitize_interval' ), 'def' => 300 ),
			'biopentra_inbox_sync_message_cap'      => array( 'type' => 'integer', 'cb' => array( __CLASS__, 'sanitize_cap' ), 'def' => 50 ),
			'biopentra_inbox_import_driver'         => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_import_driver' ), 'def' => 'worker' ),
			'biopentra_inbox_worker_import_enabled' => array( 'type' => 'string', 'cb' => array( __CLASS__, 'sanitize_yes_no' ), 'def' => 'yes' ),
			'biopentra_inbox_archive_auto_delete_days' => array( 'type' => 'integer', 'cb' => array( __CLASS__, 'sanitize_archive_delete_days' ), 'def' => 30 ),
			'biopentra_inbox_reply_template_enabled'   => array( 'type' => 'string', 'cb' => array( 'Biopentra_Contact_Inbox_Email_Reply_Template', 'sanitize_enabled' ), 'def' => 'yes' ),
			'biopentra_inbox_reply_logo_source'        => array( 'type' => 'string', 'cb' => array( 'Biopentra_Contact_Inbox_Email_Reply_Template', 'sanitize_logo_source' ), 'def' => 'site_logo' ),
			'biopentra_inbox_reply_logo_custom_url'    => array( 'type' => 'string', 'cb' => array( 'Biopentra_Contact_Inbox_Email_Reply_Template', 'sanitize_logo_url' ), 'def' => '' ),
			'biopentra_inbox_reply_header'             => array( 'type' => 'string', 'cb' => array( 'Biopentra_Contact_Inbox_Email_Reply_Template', 'sanitize_fragment_html' ), 'def' => "Hello {customer_name},\n" ),
			'biopentra_inbox_reply_footer'             => array( 'type' => 'string', 'cb' => array( 'Biopentra_Contact_Inbox_Email_Reply_Template', 'sanitize_fragment_html' ), 'def' => "Best regards,\nBiopentra Support\n\nTicket: {ticket_number}" ),
			'biopentra_inbox_reply_company_source'     => array( 'type' => 'string', 'cb' => array( 'Biopentra_Contact_Inbox_Email_Reply_Template', 'sanitize_company_source' ), 'def' => 'wc_address' ),
			'biopentra_inbox_reply_company_custom'     => array( 'type' => 'string', 'cb' => array( 'Biopentra_Contact_Inbox_Email_Reply_Template', 'sanitize_fragment_html' ), 'def' => '' ),
		);

		foreach ( $opts as $name => $cfg ) {
			register_setting(
				self::OPTION_GROUP,
				$name,
				array(
					'type'              => $cfg['type'],
					'sanitize_callback' => $cfg['cb'],
					'default'           => $cfg['def'],
				)
			);
		}

		add_filter( 'pre_update_option', array( __CLASS__, 'prevent_null_option_wipe' ), 5, 3 );
	}

	/**
	 * Block options.php from clearing inbox options when they are absent from POST (null value).
	 *
	 * WordPress options.php calls update_option( $name, null ) for every allowed option not present
	 * in the request. Partial saves (e.g. a small custom form) would wipe IMAP/SMTP and other settings.
	 *
	 * @param mixed  $value     New value (null means absent from POST in this context).
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value from DB.
	 * @return mixed Value to store (keep old when null would wipe real configuration).
	 */
	public static function prevent_null_option_wipe( $value, $option, $old_value ) {
		if ( ! is_string( $option ) || strpos( $option, 'biopentra_inbox_' ) !== 0 ) {
			return $value;
		}
		if ( null !== $value ) {
			return $value;
		}
		if ( false !== $old_value && null !== $old_value && '' !== $old_value ) {
			return $old_value;
		}
		return $value;
	}

	public static function option_page_capability() {
		return BIOPENTRA_INBOX_CAP;
	}

	/**
	 * @param mixed $value Raw.
	 * @return int
	 */
	public static function sanitize_form_id( $value ) {
		$id = absint( $value );
		if ( $id > 0 && ! Biopentra_Contact_Inbox_Form_Resolver::form_exists( $id ) ) {
			add_settings_error(
				'biopentra_inbox',
				'invalid_form',
				__( 'Selected form was not found in Fluent Forms. Saving ID anyway — verify your Fluent Forms install.', 'biopentra-contact-inbox' ),
				'warning'
			);
		}
		return $id;
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_from_email( $value ) {
		$v = sanitize_email( is_string( $value ) ? $value : '' );
		if ( $v === '' || ! is_email( $v ) ) {
			add_settings_error(
				'biopentra_inbox',
				'invalid_from',
				__( 'Invalid from email; reverted to WordPress admin email.', 'biopentra-contact-inbox' ),
				'error'
			);
			return sanitize_email( get_option( 'admin_email' ) );
		}
		return $v;
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_bcc( $value ) {
		$v = trim( is_string( $value ) ? $value : '' );
		if ( $v === '' ) {
			return '';
		}
		$v = sanitize_email( $v );
		if ( ! is_email( $v ) ) {
			add_settings_error(
				'biopentra_inbox',
				'invalid_bcc',
				__( 'Optional BCC was invalid and cleared.', 'biopentra-contact-inbox' ),
				'error'
			);
			return '';
		}
		return $v;
	}

	/**
	 * @param mixed $value Raw.
	 * @return string yes|no
	 */
	public static function sanitize_yes_no( $value ) {
		return ( isset( $value ) && ( $value === 'yes' || $value === true || $value === 1 || $value === '1' ) ) ? 'yes' : 'no';
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_digits( $value ) {
		$s = preg_replace( '/\D+/', '', (string) $value );
		return $s !== '' ? $s : '0';
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_imap_pass( $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return (string) get_option( 'biopentra_inbox_imap_pass', '' );
		}
		return $value;
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_smtp_pass( $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return (string) get_option( 'biopentra_inbox_smtp_pass', '' );
		}
		return $value;
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_imap_search( $value ) {
		$s = is_string( $value ) ? trim( $value ) : '';
		if ( $s === '' ) {
			return 'ALL';
		}
		return preg_replace( '/[^A-Z0-9 _\-]/i', '', $s );
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_smtp_scope( $value ) {
		$v = is_string( $value ) ? $value : '';
		return in_array( $v, array( 'plugin_only', 'all_wp_mail' ), true ) ? $v : 'plugin_only';
	}

	/**
	 * @param mixed $value Raw.
	 * @return int
	 */
	public static function sanitize_interval( $value ) {
		$i = absint( $value );
		return max( 60, min( 3600, $i ) );
	}

	/**
	 * @param mixed $value Raw.
	 * @return int
	 */
	public static function sanitize_cap( $value ) {
		$i = absint( $value );
		return max( 1, min( 500, $i ) );
	}

	/**
	 * @param mixed $value Raw.
	 * @return int 0 = never auto-delete archived email tickets.
	 */
	public static function sanitize_archive_delete_days( $value ) {
		$i = absint( $value );
		return max( 0, min( 3650, $i ) );
	}

	/**
	 * @param mixed $value Raw.
	 * @return string worker|php_imap
	 */
	public static function sanitize_import_driver( $value ) {
		$v = is_string( $value ) ? $value : '';
		return 'php_imap' === $v ? 'php_imap' : 'worker';
	}

	public static function render_page() {
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'biopentra-contact-inbox' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, array( 'general', 'email', 'template', 'sync', 'advanced' ), true ) ) {
			$tab = 'general';
		}

		$base = add_query_arg( array( 'page' => 'biopentra-inbox-settings' ), admin_url( 'admin.php' ) );

		if ( ! extension_loaded( 'imap' ) && Biopentra_Contact_Inbox_Cron::import_driver() === 'php_imap' ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'PHP IMAP extension is not enabled. Legacy PHP IMAP import will not run until it is installed.', 'biopentra-contact-inbox' ) . '</p></div>';
		}

		if ( ! Biopentra_Contact_Inbox_Form_Resolver::fluent_tables_exist() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Fluent Forms tables were not found. Install and activate Fluent Forms for form-based tickets.', 'biopentra-contact-inbox' ) . '</p></div>';
		}

		$forms = Biopentra_Contact_Inbox_Form_Resolver::get_all_forms();

		echo '<div class="wrap">';
		settings_errors( 'biopentra_inbox' );

		if ( isset( $_GET['bsd_archive_saved'] ) && '1' === $_GET['bsd_archive_saved'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Archive retention saved.', 'biopentra-contact-inbox' ) . '</p></div>';
		}

		if ( isset( $_GET['bsd_sync'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$st = sanitize_key( wp_unslash( $_GET['bsd_sync'] ) );
			if ( 'busy' === $st ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Legacy PHP IMAP sync was skipped because another run is already in progress (or a stale lock is present; it clears after a few minutes).', 'biopentra-contact-inbox' ) . '</p></div>';
			} elseif ( '1' === $st ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Legacy PHP IMAP sync finished. See “Legacy PHP IMAP sync status” on the Sync / Worker tab for details.', 'biopentra-contact-inbox' ) . '</p></div>';
			} elseif ( 'worker_driver' === $st ) {
				echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Legacy PHP IMAP sync is not used while the import driver is the Docker mail worker.', 'biopentra-contact-inbox' ) . '</p></div>';
			}
		}

		if ( isset( $_GET['bsd_migrate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$m = sanitize_key( wp_unslash( $_GET['bsd_migrate'] ) );
			if ( 'no_form' === $m ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Migration skipped: select a Fluent Forms form on the General tab first.', 'biopentra-contact-inbox' ) . '</p></div>';
			} elseif ( '1' === $m ) {
				$n = isset( $_GET['migrated'] ) ? absint( wp_unslash( $_GET['migrated'] ) ) : 0;
				$done = isset( $_GET['done'] ) && '1' === $_GET['done'];
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html(
					sprintf(
						/* translators: 1: number of rows migrated, 2: completion hint */
						__( 'Migration batch complete: %1$d new ticket(s) created. %2$s', 'biopentra-contact-inbox' ),
						$n,
						$done
							? __( 'All submissions in this form appear to be migrated.', 'biopentra-contact-inbox' )
							: __( 'Run the button again if more submissions remain.', 'biopentra-contact-inbox' )
					)
				);
				echo '</p></div>';
			}
		}

		self::render_bridge_test_notices( $tab );

		echo '<h1>' . esc_html__( 'Biopentra Support Desk — Settings', 'biopentra-contact-inbox' ) . '</h1>';

		echo '<h2 class="nav-tab-wrapper">';
		foreach (
			array(
				'general'  => __( 'General', 'biopentra-contact-inbox' ),
				'email'    => __( 'Email / Proton Bridge', 'biopentra-contact-inbox' ),
				'template' => __( 'Email template', 'biopentra-contact-inbox' ),
				'sync'     => __( 'Sync / Worker', 'biopentra-contact-inbox' ),
				'advanced' => __( 'Advanced', 'biopentra-contact-inbox' ),
			) as $tid => $label
		) {
			$url   = esc_url( add_query_arg( 'tab', $tid, $base ) );
			$class = 'nav-tab' . ( $tab === $tid ? ' nav-tab-active' : '' );
			echo '<a href="' . $url . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		if ( 'email' === $tab ) {
			self::render_email_bridge_panel();
		}

		if ( 'advanced' === $tab ) {
			self::render_desk_reset_success_notice();
			self::render_advanced_tab();
			echo '</div>';
			return;
		}

		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		echo '<input type="hidden" name="bsd_settings_tab" value="' . esc_attr( $tab ) . '" />';

		$disp = get_option( 'biopentra_inbox_display_name', __( 'Biopentra Support Desk', 'biopentra-contact-inbox' ) );

		// General tab panel (always in DOM).
		$show_gen = ( 'general' === $tab ) ? 'block' : 'none';
		echo '<div class="bsd-tab-panel" id="bsd-panel-general" style="display:' . esc_attr( $show_gen ) . '">';

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="biopentra_inbox_display_name">' . esc_html__( 'Support desk title', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<input name="biopentra_inbox_display_name" id="biopentra_inbox_display_name" type="text" class="regular-text" value="' . esc_attr( $disp ) . '" />';
		echo '</td></tr>';

		$form_id = (int) get_option( 'biopentra_inbox_contact_form_id', 0 );
		echo '<tr><th scope="row"><label for="biopentra_inbox_contact_form_id">' . esc_html__( 'Fluent Forms contact form', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<select name="biopentra_inbox_contact_form_id" id="biopentra_inbox_contact_form_id">';
		echo '<option value="0">' . esc_html__( '— Select —', 'biopentra-contact-inbox' ) . '</option>';
		foreach ( $forms as $f ) {
			$fid = (int) $f['id'];
			echo '<option value="' . esc_attr( (string) $fid ) . '"' . selected( $form_id, $fid, false ) . '>' . esc_html( $f['title'] . ' (#' . $fid . ')' ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Which Fluent Forms form creates tickets when migrated or submitted.', 'biopentra-contact-inbox' ) . '</p>';
		echo '</td></tr>';

		self::text_row( 'biopentra_inbox_default_reply_subject', __( 'Default reply subject', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_default_reply_subject', 'Re: Your Biopentra inquiry' ), 'large-text' );
		self::text_row( 'biopentra_inbox_from_name', __( 'From name', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_from_name', 'Biopentra' ), 'regular-text' );
		self::text_row( 'biopentra_inbox_from_email', __( 'From email', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) ), 'regular-text', 'email' );
		self::text_row( 'biopentra_inbox_bcc_email', __( 'Optional BCC', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_bcc_email', '' ), 'regular-text', 'email' );

		$store_hist = get_option( 'biopentra_inbox_store_reply_history', 'yes' );
		echo '<tr><th scope="row">' . esc_html__( 'Store legacy reply history', 'biopentra-contact-inbox' ) . '</th><td>';
		echo '<input type="hidden" name="biopentra_inbox_store_reply_history" value="no" />';
		echo '<label><input name="biopentra_inbox_store_reply_history" type="checkbox" value="yes"' . checked( $store_hist, 'yes', false ) . ' /> ';
		echo esc_html__( 'Save Fluent replies in the legacy replies table (when using submission-based replies).', 'biopentra-contact-inbox' ) . '</label></td></tr>';

		$del_unin = get_option( 'biopentra_inbox_delete_on_uninstall', 'no' );
		echo '<tr><th scope="row">' . esc_html__( 'Delete plugin data on uninstall', 'biopentra-contact-inbox' ) . '</th><td>';
		echo '<input type="hidden" name="biopentra_inbox_delete_on_uninstall" value="no" />';
		echo '<label><input name="biopentra_inbox_delete_on_uninstall" type="checkbox" value="yes"' . checked( $del_unin, 'yes', false ) . ' /> ';
		echo esc_html__( 'Remove plugin tables and options when the plugin is deleted.', 'biopentra-contact-inbox' ) . '</label></td></tr>';

		echo '</table></div>';

		$show_email = ( 'email' === $tab ) ? 'block' : 'none';
		echo '<div class="bsd-tab-panel" id="bsd-panel-email" style="display:' . esc_attr( $show_email ) . '">';
		echo '<p class="description">' . esc_html__( 'From name and email are configured on the General tab.', 'biopentra-contact-inbox' ) . '</p>';
		echo '<table class="form-table" role="presentation">';

		$em_en          = get_option( 'biopentra_inbox_email_enabled', 'no' );
		$import_driver  = Biopentra_Contact_Inbox_Cron::import_driver();
		$email_driver_note = ( 'worker' === $import_driver )
			? __( 'Bridge SMTP is used for outbound mail. Inbound import follows the driver on the Sync / Worker tab (Docker mail worker by default).', 'biopentra-contact-inbox' )
			: __( 'Import mail via legacy PHP IMAP when enabled, and send through SMTP / wp_mail.', 'biopentra-contact-inbox' );
		echo '<tr><th scope="row">' . esc_html__( 'Enable email inbox', 'biopentra-contact-inbox' ) . '</th><td>';
		echo '<input type="hidden" name="biopentra_inbox_email_enabled" value="no" />';
		echo '<label><input name="biopentra_inbox_email_enabled" type="checkbox" value="yes"' . checked( $em_en, 'yes', false ) . ' /> ';
		echo esc_html( $email_driver_note ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Turn off to stop applying Bridge SMTP settings to PHPMailer.', 'biopentra-contact-inbox' ) . '</p></td></tr>';

		if ( 'worker' === $import_driver ) {
			echo '<tr><td colspan="2" style="padding-top:4px;">';
			echo '<p class="description" style="margin:0 0 12px;">';
			echo esc_html__( 'Inbox import is handled by the mail worker. IMAP connection and search settings are controlled by `.env.worker`.', 'biopentra-contact-inbox' );
			echo '</p></td></tr>';

			$h = self::worker_display_imap_host();
			$p = self::worker_display_imap_port();
			$u = self::worker_display_imap_user();
			self::readonly_text_row_no_name(
				'bsd_worker_imap_host_ro',
				__( 'IMAP host', 'biopentra-contact-inbox' ),
				$h['value']
			);
			self::readonly_text_row_no_name(
				'bsd_worker_imap_port_ro',
				__( 'IMAP port', 'biopentra-contact-inbox' ),
				$p['value']
			);
			self::readonly_text_row_no_name(
				'bsd_worker_imap_user_ro',
				__( 'IMAP username', 'biopentra-contact-inbox' ),
				$u['value']
			);
			$src_bits = array();
			if ( $h['from_env'] ) {
				$src_bits[] = 'IMAP_HOST';
			}
			if ( $p['from_env'] ) {
				$src_bits[] = 'IMAP_PORT';
			}
			if ( $u['from_env'] ) {
				$src_bits[] = 'IMAP_USER';
			}
			if ( ! empty( $src_bits ) ) {
				echo '<tr class="bsd-worker-readonly-imap"><td colspan="2" style="padding-top:0;">';
				echo '<p class="description" style="margin:0 0 12px;">';
				echo esc_html(
					sprintf(
						/* translators: %s: comma-separated list of environment variable names */
						__( 'Host / port / username above include value(s) from the PHP environment (%s), matching the mail worker container.', 'biopentra-contact-inbox' ),
						implode( ', ', $src_bits )
					)
				);
				echo '</p></td></tr>';
			}
			echo '<tr class="bsd-worker-readonly-imap"><th scope="row">' . esc_html__( 'IMAP password', 'biopentra-contact-inbox' ) . '</th><td>';
			echo '<p style="margin:0;">' . esc_html( self::worker_imap_password_status_text() ) . '</p>';
			echo '<p class="description" style="margin:8px 0 0;">' . esc_html__( 'The password itself is never shown.', 'biopentra-contact-inbox' ) . '</p>';
			echo '</td></tr>';
		} else {
			self::text_row( 'biopentra_inbox_imap_host', __( 'IMAP host', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_imap_host', 'proton-bridge' ), 'regular-text' );
			self::text_row( 'biopentra_inbox_imap_port', __( 'IMAP port', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_imap_port', '2143' ), 'small-text' );
			self::text_row( 'biopentra_inbox_imap_user', __( 'IMAP username', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_imap_user', '' ), 'regular-text' );
			echo '<tr><th scope="row"><label for="biopentra_inbox_imap_pass">' . esc_html__( 'IMAP password', 'biopentra-contact-inbox' ) . '</label></th><td>';
			echo '<input name="biopentra_inbox_imap_pass" id="biopentra_inbox_imap_pass" type="password" class="regular-text" value="" autocomplete="new-password" />';
			echo '<p class="description">' . esc_html__( 'Leave blank to keep the current password.', 'biopentra-contact-inbox' ) . '</p></td></tr>';
			self::text_row( 'biopentra_inbox_imap_mailbox', __( 'IMAP mailbox', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_imap_mailbox', 'INBOX' ), 'regular-text' );
			self::text_row( 'biopentra_inbox_imap_search', __( 'IMAP search query', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_imap_search', 'UNSEEN' ), 'regular-text' );

			$mark_seen = get_option( 'biopentra_inbox_imap_mark_seen', 'yes' );
			echo '<tr><th scope="row">' . esc_html__( 'Mark imported mail as seen', 'biopentra-contact-inbox' ) . '</th><td>';
			echo '<input type="hidden" name="biopentra_inbox_imap_mark_seen" value="no" />';
			echo '<label><input name="biopentra_inbox_imap_mark_seen" type="checkbox" value="yes"' . checked( $mark_seen, 'yes', false ) . ' /></label></td></tr>';
		}

		self::text_row( 'biopentra_inbox_smtp_host', __( 'SMTP host', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_smtp_host', 'proton-bridge' ), 'regular-text' );
		self::text_row( 'biopentra_inbox_smtp_port', __( 'SMTP port', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_smtp_port', '2025' ), 'small-text' );
		self::text_row( 'biopentra_inbox_smtp_user', __( 'SMTP username', 'biopentra-contact-inbox' ), get_option( 'biopentra_inbox_smtp_user', '' ), 'regular-text' );
		echo '<tr><th scope="row"><label for="biopentra_inbox_smtp_pass">' . esc_html__( 'SMTP password', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<input name="biopentra_inbox_smtp_pass" id="biopentra_inbox_smtp_pass" type="password" class="regular-text" value="" autocomplete="new-password" />';
		echo '<p class="description">' . esc_html__( 'Leave blank to keep the current password.', 'biopentra-contact-inbox' ) . '</p></td></tr>';

		$scope = get_option( 'biopentra_inbox_smtp_scope', 'plugin_only' );
		echo '<tr><th scope="row"><label for="biopentra_inbox_smtp_scope">' . esc_html__( 'SMTP scope', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<select name="biopentra_inbox_smtp_scope" id="biopentra_inbox_smtp_scope">';
		echo '<option value="plugin_only"' . selected( $scope, 'plugin_only', false ) . '>' . esc_html__( 'Plugin only (support desk mail)', 'biopentra-contact-inbox' ) . '</option>';
		echo '<option value="all_wp_mail"' . selected( $scope, 'all_wp_mail', false ) . '>' . esc_html__( 'All wp_mail() (entire site)', 'biopentra-contact-inbox' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Use “Plugin only” unless you intentionally want every WordPress email to use Proton Bridge SMTP.', 'biopentra-contact-inbox' ) . '</p>';
		echo '</td></tr>';

		echo '</table></div>';

		$show_template = ( 'template' === $tab ) ? 'block' : 'none';
		echo '<div class="bsd-tab-panel" id="bsd-panel-template" style="display:' . esc_attr( $show_template ) . '">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Branded ticket replies', 'biopentra-contact-inbox' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Staff write only the answer in the ticket reply box; the plugin wraps it with the header, footer, optional logo, and company details below.', 'biopentra-contact-inbox' ) . '</p>';
		echo '<table class="form-table" role="presentation">';

		$tpl_en = get_option( 'biopentra_inbox_reply_template_enabled', 'yes' );
		echo '<tr><th scope="row">' . esc_html__( 'Enable branded reply template', 'biopentra-contact-inbox' ) . '</th><td>';
		echo '<input type="hidden" name="biopentra_inbox_reply_template_enabled" value="no" />';
		echo '<label><input name="biopentra_inbox_reply_template_enabled" type="checkbox" value="yes"' . checked( $tpl_en, 'yes', false ) . ' /> ';
		echo esc_html__( 'Wrap ticket replies with the template (recommended).', 'biopentra-contact-inbox' ) . '</label></td></tr>';

		$logo_src = (string) get_option( 'biopentra_inbox_reply_logo_source', 'site_logo' );
		echo '<tr><th scope="row"><label for="biopentra_inbox_reply_logo_source">' . esc_html__( 'Logo source', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<select name="biopentra_inbox_reply_logo_source" id="biopentra_inbox_reply_logo_source">';
		echo '<option value="site_logo"' . selected( $logo_src, 'site_logo', false ) . '>' . esc_html__( 'Site logo (theme custom logo)', 'biopentra-contact-inbox' ) . '</option>';
		echo '<option value="wc_store_logo"' . selected( $logo_src, 'wc_store_logo', false ) . '>' . esc_html__( 'WooCommerce email header image, or site logo if empty', 'biopentra-contact-inbox' ) . '</option>';
		echo '<option value="custom_url"' . selected( $logo_src, 'custom_url', false ) . '>' . esc_html__( 'Custom logo URL', 'biopentra-contact-inbox' ) . '</option>';
		echo '<option value="none"' . selected( $logo_src, 'none', false ) . '>' . esc_html__( 'No logo', 'biopentra-contact-inbox' ) . '</option>';
		echo '</select></td></tr>';

		$logo_url = (string) get_option( 'biopentra_inbox_reply_logo_custom_url', '' );
		echo '<tr><th scope="row"><label for="biopentra_inbox_reply_logo_custom_url">' . esc_html__( 'Custom logo URL', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<input name="biopentra_inbox_reply_logo_custom_url" id="biopentra_inbox_reply_logo_custom_url" type="url" class="large-text" value="' . esc_attr( $logo_url ) . '" placeholder="https://…" />';
		echo '<p class="description">' . esc_html__( 'Used only when “Custom logo URL” is selected.', 'biopentra-contact-inbox' ) . '</p></td></tr>';

		$hdr = (string) get_option( 'biopentra_inbox_reply_header', Biopentra_Contact_Inbox_Email_Reply_Template::default_header() );
		echo '<tr><th scope="row"><label for="biopentra_inbox_reply_header">' . esc_html__( 'Reply header', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<textarea name="biopentra_inbox_reply_header" id="biopentra_inbox_reply_header" class="large-text" rows="4">' . esc_textarea( $hdr ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Placeholders:', 'biopentra-contact-inbox' ) . ' <code>{site_name}</code> <code>{site_url}</code> <code>{customer_name}</code> <code>{customer_email}</code> <code>{ticket_number}</code> <code>{support_email}</code> <code>{company_logo}</code> <code>{store_name}</code> <code>{store_address}</code></p></td></tr>';

		$ftr = (string) get_option( 'biopentra_inbox_reply_footer', Biopentra_Contact_Inbox_Email_Reply_Template::default_footer() );
		echo '<tr><th scope="row"><label for="biopentra_inbox_reply_footer">' . esc_html__( 'Reply footer / signature', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<textarea name="biopentra_inbox_reply_footer" id="biopentra_inbox_reply_footer" class="large-text" rows="6">' . esc_textarea( $ftr ) . '</textarea></td></tr>';

		$co_src = (string) get_option( 'biopentra_inbox_reply_company_source', 'wc_address' );
		echo '<tr><th scope="row"><label for="biopentra_inbox_reply_company_source">' . esc_html__( 'Company details source', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<select name="biopentra_inbox_reply_company_source" id="biopentra_inbox_reply_company_source">';
		echo '<option value="wc_address"' . selected( $co_src, 'wc_address', false ) . '>' . esc_html__( 'WooCommerce store address (or site name and URL if empty)', 'biopentra-contact-inbox' ) . '</option>';
		echo '<option value="site_admin"' . selected( $co_src, 'site_admin', false ) . '>' . esc_html__( 'Site name, site URL, and admin email', 'biopentra-contact-inbox' ) . '</option>';
		echo '<option value="custom"' . selected( $co_src, 'custom', false ) . '>' . esc_html__( 'Custom', 'biopentra-contact-inbox' ) . '</option>';
		echo '<option value="none"' . selected( $co_src, 'none', false ) . '>' . esc_html__( 'None', 'biopentra-contact-inbox' ) . '</option>';
		echo '</select></td></tr>';

		$co_custom = (string) get_option( 'biopentra_inbox_reply_company_custom', '' );
		echo '<tr><th scope="row"><label for="biopentra_inbox_reply_company_custom">' . esc_html__( 'Custom company details', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<textarea name="biopentra_inbox_reply_company_custom" id="biopentra_inbox_reply_company_custom" class="large-text" rows="5">' . esc_textarea( $co_custom ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Used when “Custom” is selected. Safe HTML allowed; scripts and iframes are stripped.', 'biopentra-contact-inbox' ) . '</p></td></tr>';

		echo '</table></div>';

		$show_sync = ( 'sync' === $tab ) ? 'block' : 'none';
		echo '<div class="bsd-tab-panel" id="bsd-panel-sync" style="display:' . esc_attr( $show_sync ) . '">';
		$flash = get_transient( 'biopentra_inbox_worker_token_show_' . get_current_user_id() );
		if ( is_string( $flash ) && $flash !== '' ) {
			delete_transient( 'biopentra_inbox_worker_token_show_' . get_current_user_id() );
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Copy this worker token now.', 'biopentra-contact-inbox' ) . '</strong> ';
			echo esc_html__( 'WordPress will not show it again. Add the same value to your Docker configuration (see Advanced / Developer details on this tab).', 'biopentra-contact-inbox' ) . '</p>';
			echo '<p><code style="word-break:break-all;font-size:13px;">' . esc_html( $flash ) . '</code></p></div>';
		}

		$health_flash = get_transient( 'biopentra_inbox_worker_http_health_notice_' . get_current_user_id() );
		if ( is_array( $health_flash ) && isset( $health_flash['healthy'], $health_flash['message'] ) && is_bool( $health_flash['healthy'] ) && is_string( $health_flash['message'] ) ) {
			delete_transient( 'biopentra_inbox_worker_http_health_notice_' . get_current_user_id() );
			$notice_class = $health_flash['healthy'] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $health_flash['message'] ) . '</p></div>';
		}

		$mailbox_flash = get_transient( 'biopentra_inbox_worker_mailbox_notice_' . get_current_user_id() );
		if ( is_array( $mailbox_flash ) && isset( $mailbox_flash['level'], $mailbox_flash['message'] ) && is_string( $mailbox_flash['message'] ) ) {
			delete_transient( 'biopentra_inbox_worker_mailbox_notice_' . get_current_user_id() );
			$lvl = sanitize_key( (string) $mailbox_flash['level'] );
			$notice_class = 'notice-error';
			if ( 'success' === $lvl ) {
				$notice_class = 'notice-success';
			} elseif ( 'warning' === $lvl ) {
				$notice_class = 'notice-warning';
			}
			echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $mailbox_flash['message'] ) . '</p></div>';
		}

		$driver = Biopentra_Contact_Inbox_Cron::import_driver();
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row"><label for="biopentra_inbox_import_driver">' . esc_html__( 'Import driver', 'biopentra-contact-inbox' ) . '</label></th><td>';
		$cur_drv = (string) get_option( 'biopentra_inbox_import_driver', 'worker' );
		echo '<select name="biopentra_inbox_import_driver" id="biopentra_inbox_import_driver">';
		echo '<option value="worker"' . selected( $cur_drv, 'worker', false ) . '>' . esc_html__( 'Docker mail worker', 'biopentra-contact-inbox' ) . '</option>';
		echo '<option value="php_imap"' . selected( $cur_drv, 'php_imap', false ) . '>' . esc_html__( 'Legacy PHP IMAP (requires ext-imap)', 'biopentra-contact-inbox' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Recommended: use the Docker mail worker for mail import. Legacy PHP IMAP runs only inside WordPress.', 'biopentra-contact-inbox' ) . '</p>';
		echo '</td></tr>';

		$w_en = get_option( 'biopentra_inbox_worker_import_enabled', 'yes' );
		echo '<tr><th scope="row">' . esc_html__( 'Mail worker import', 'biopentra-contact-inbox' ) . '</th><td>';
		echo '<input type="hidden" name="biopentra_inbox_worker_import_enabled" value="no" />';
		echo '<label><input name="biopentra_inbox_worker_import_enabled" type="checkbox" value="yes"' . checked( $w_en, 'yes', false ) . ' /> ';
		echo esc_html__( 'Allow the mail worker to deliver new messages into this site.', 'biopentra-contact-inbox' ) . '</label></td></tr>';

		$hash_ok = ( (string) get_option( 'biopentra_inbox_worker_token_hash', '' ) ) !== '';
		echo '<tr><th scope="row">' . esc_html__( 'Mail worker token', 'biopentra-contact-inbox' ) . '</th><td>';
		echo '<p><strong>' . esc_html__( 'Configured:', 'biopentra-contact-inbox' ) . '</strong> ' . ( $hash_ok ? esc_html__( 'Yes', 'biopentra-contact-inbox' ) : esc_html__( 'No', 'biopentra-contact-inbox' ) ) . '</p>';
		$rotate = wp_nonce_url( admin_url( 'admin-post.php?action=biopentra_inbox_rotate_worker_token' ), 'biopentra_inbox_rotate_worker_token' );
		echo '<p><a class="button button-secondary" href="' . esc_url( $rotate ) . '">' . esc_html__( 'Generate / rotate token', 'biopentra-contact-inbox' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Generate a token if you have not already. Use Advanced / Developer details for how to connect Docker.', 'biopentra-contact-inbox' ) . '</p>';
		echo '</td></tr>';

		$poll = (int) get_option( 'biopentra_inbox_sync_interval', 300 );
		echo '<tr><th scope="row">' . esc_html__( 'Background mail checks', 'biopentra-contact-inbox' ) . '</th><td>';
		if ( 'worker' === $driver ) {
			echo '<p class="description">' . esc_html__( 'The mail worker runs on its own schedule (configured in Docker). WordPress does not drive mail import on a timer in this mode.', 'biopentra-contact-inbox' ) . '</p>';
			/* translators: %d: stored interval in seconds (legacy PHP IMAP only; informational for operators). */
			echo '<p class="description">' . esc_html( sprintf( __( 'The number below (%d seconds) is kept for legacy PHP IMAP mode only.', 'biopentra-contact-inbox' ), $poll ) ) . '</p>';
		} else {
			/* translators: %d: legacy PHP IMAP cron interval in seconds */
			echo '<p class="description">' . esc_html( sprintf( __( 'Legacy PHP IMAP scheduled sync uses this interval (%d seconds). Use WP-Cron or a system cron job to trigger wp-cron.php on your schedule.', 'biopentra-contact-inbox' ), $poll ) ) . '</p>';
		}
		echo '</td></tr>';

		echo '</table>';

		if ( 'worker' === $driver ) {
			self::render_worker_mail_operator_panel();
		}

		if ( 'php_imap' === $driver ) {
			echo '<table class="form-table" role="presentation">';
			$sync_en = get_option( 'biopentra_inbox_sync_enabled', 'no' );
			echo '<tr><th scope="row">' . esc_html__( 'Enable scheduled PHP IMAP sync', 'biopentra-contact-inbox' ) . '</th><td>';
			echo '<input type="hidden" name="biopentra_inbox_sync_enabled" value="no" />';
			echo '<label><input name="biopentra_inbox_sync_enabled" type="checkbox" value="yes"' . checked( $sync_en, 'yes', false ) . ' /></label></td></tr>';

			echo '<tr><th scope="row"><label for="biopentra_inbox_sync_interval">' . esc_html__( 'Legacy PHP IMAP sync interval (seconds)', 'biopentra-contact-inbox' ) . '</label></th><td>';
			echo '<input name="biopentra_inbox_sync_interval" id="biopentra_inbox_sync_interval" type="number" min="60" max="3600" step="1" class="small-text" value="' . esc_attr( (string) (int) get_option( 'biopentra_inbox_sync_interval', 300 ) ) . '" />';
			echo '</td></tr>';

			echo '<tr><th scope="row"><label for="biopentra_inbox_sync_message_cap">' . esc_html__( 'Per-run message cap', 'biopentra-contact-inbox' ) . '</label></th><td>';
			echo '<input name="biopentra_inbox_sync_message_cap" id="biopentra_inbox_sync_message_cap" type="number" min="1" max="500" step="1" class="small-text" value="' . esc_attr( (string) (int) get_option( 'biopentra_inbox_sync_message_cap', 50 ) ) . '" />';
			echo '</td></tr>';
			echo '</table>';
		} else {
			echo '<p class="description">' . esc_html__( 'Legacy PHP IMAP scheduled sync is off while the mail worker is selected. Inbound mail is imported by the mail worker.', 'biopentra-contact-inbox' ) . '</p>';
			echo '<input type="hidden" name="biopentra_inbox_sync_enabled" value="' . esc_attr( (string) get_option( 'biopentra_inbox_sync_enabled', 'no' ) ) . '" />';
			echo '<input type="hidden" name="biopentra_inbox_sync_interval" value="' . esc_attr( (string) (int) get_option( 'biopentra_inbox_sync_interval', 300 ) ) . '" />';
			echo '<input type="hidden" name="biopentra_inbox_sync_message_cap" value="' . esc_attr( (string) (int) get_option( 'biopentra_inbox_sync_message_cap', 50 ) ) . '" />';
		}

		$last_at = get_option( 'biopentra_inbox_last_sync_at', '' );
		$last_rs = get_option( 'biopentra_inbox_last_sync_result', '' );
		$last_rs_s = is_string( $last_rs ) ? $last_rs : '';
		$worker_summary = self::parse_worker_status_json( $last_rs_s );

		if ( 'worker' === $driver ) {
			echo '<h3>' . esc_html__( 'Mail worker status', 'biopentra-contact-inbox' ) . '</h3>';
			$hb_opt = (string) get_option( 'biopentra_inbox_last_worker_heartbeat', '' );
			echo '<p><strong>' . esc_html__( 'Last heartbeat:', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html( $hb_opt !== '' ? $hb_opt : '—' ) . '</p>';
			if ( is_array( $worker_summary ) && ! empty( $worker_summary['received_at_gmt'] ) ) {
				echo '<p><strong>' . esc_html__( 'Last message from mail worker (GMT):', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html( (string) $worker_summary['received_at_gmt'] ) . '</p>';
			}
			if ( is_array( $worker_summary ) && ! empty( $worker_summary['worker_version'] ) ) {
				echo '<p><strong>' . esc_html__( 'Mail worker version:', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html( (string) $worker_summary['worker_version'] ) . '</p>';
			}
			echo '<p><strong>' . esc_html__( 'Last report time:', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html( $last_at !== '' ? (string) $last_at : '—' ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Last import result:', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html( self::format_worker_import_summary_line( $worker_summary ) ) . '</p>';
			if ( is_array( $worker_summary ) && isset( $worker_summary['poll_status'] ) && (string) $worker_summary['poll_status'] !== '' ) {
				echo '<p><strong>' . esc_html__( 'Last mailbox check status:', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html( (string) $worker_summary['poll_status'] ) . '</p>';
			}
			echo '<p><strong>' . esc_html__( 'Legacy PHP IMAP sync:', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html__( 'Not used in mail worker mode.', 'biopentra-contact-inbox' ) . '</p>';
		} else {
			echo '<h3>' . esc_html__( 'Legacy PHP IMAP sync status', 'biopentra-contact-inbox' ) . '</h3>';
			echo '<p><strong>' . esc_html__( 'Last legacy PHP IMAP run:', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html( $last_at !== '' ? (string) $last_at : '—' ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Last legacy PHP IMAP result:', 'biopentra-contact-inbox' ) . '</strong> <code>' . esc_html( $last_rs_s !== '' ? $last_rs_s : '—' ) . '</code></p>';
			$lock = get_transient( Biopentra_Contact_Inbox_Imap_Sync::LOCK_KEY );
			echo '<p><strong>' . esc_html__( 'Legacy PHP IMAP sync lock:', 'biopentra-contact-inbox' ) . '</strong> ' . ( $lock ? esc_html__( 'Running or stale lock (max ~5 minutes).', 'biopentra-contact-inbox' ) : esc_html__( 'Idle', 'biopentra-contact-inbox' ) ) . '</p>';
		}

		if ( 'php_imap' === $driver && extension_loaded( 'imap' ) ) {
			echo '<h3>' . esc_html__( 'Manual legacy PHP IMAP sync', 'biopentra-contact-inbox' ) . '</h3>';
			$sync_url = wp_nonce_url( admin_url( 'admin-post.php?action=biopentra_inbox_run_imap_sync' ), 'biopentra_inbox_run_imap_sync' );
			echo '<p><a class="button button-secondary" href="' . esc_url( $sync_url ) . '">' . esc_html__( 'Run legacy PHP IMAP sync now', 'biopentra-contact-inbox' ) . '</a></p>';
		}

		echo '<h3>' . esc_html__( 'Fluent migration', 'biopentra-contact-inbox' ) . '</h3>';
		$mig_url = wp_nonce_url( admin_url( 'admin-post.php?action=biopentra_inbox_migrate_fluent' ), 'biopentra_inbox_migrate_fluent' );
		echo '<p><a class="button" href="' . esc_url( $mig_url ) . '">' . esc_html__( 'Migrate next batch (25)', 'biopentra-contact-inbox' ) . '</a></p>';

		if ( 'worker' === $driver ) {
			echo '<h3>' . esc_html__( 'Scheduled mail import', 'biopentra-contact-inbox' ) . '</h3>';
			echo '<p>' . esc_html__( 'Inbound mail is handled by the Docker mail worker. WordPress cron is not used for mail import in this mode.', 'biopentra-contact-inbox' ) . '</p>';
		} else {
			echo '<h3>' . esc_html__( 'Legacy PHP IMAP cron', 'biopentra-contact-inbox' ) . '</h3>';
			echo '<p><strong>' . esc_html__( 'Legacy PHP IMAP scheduled sync', 'biopentra-contact-inbox' ) . '</strong> ';
			echo esc_html__( 'The system cron example below applies only to the legacy PHP IMAP import driver. It does not apply when using the Docker mail worker.', 'biopentra-contact-inbox' ) . '</p>';
			echo '<p>' . esc_html__( 'WP-Cron runs when the site receives traffic. For reliable legacy PHP IMAP sync, call wp-cron.php from the system scheduler (example every 5 minutes):', 'biopentra-contact-inbox' ) . '</p>';
			echo '<pre style="background:#f6f7f7;padding:12px;">*/5 * * * * curl -s &quot;' . esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ) . '&quot; &gt;/dev/null 2&gt;&amp;1</pre>';
			echo '<p>' . esc_html__( 'You may set DISABLE_WP_CRON in wp-config.php when using a real cron job.', 'biopentra-contact-inbox' ) . '</p>';
		}

		if ( 'worker' === $driver ) {
			self::render_worker_advanced_details_section( $last_rs_s );
		}

		echo '</div>';

		submit_button();
		echo '</form>';
		if ( 'template' === $tab ) {
			self::render_email_template_preview_outside_form();
		}
		echo '</div>';
	}

	/**
	 * Primary mail worker actions (Sync / Worker tab, mail worker mode).
	 */
	private static function render_worker_mail_operator_panel() {
		$trigger_url = wp_nonce_url( admin_url( 'admin-post.php?action=biopentra_inbox_trigger_worker_mailbox_check' ), 'biopentra_inbox_trigger_worker_mailbox_check' );
		$check_url   = wp_nonce_url( admin_url( 'admin-post.php?action=biopentra_inbox_check_worker_http_health' ), 'biopentra_inbox_check_worker_http_health' );

		$cache_raw = get_option( 'biopentra_inbox_worker_health_cached', '' );
		$cache     = json_decode( (string) $cache_raw, true );

		echo '<div class="postbox" style="margin:16px 0;">';
		echo '<div class="inside" style="padding:12px 16px;">';
		echo '<p>' . esc_html__( 'Inbound mail is handled by the Docker mail worker. WordPress cron is not used for mail import in this mode.', 'biopentra-contact-inbox' ) . '</p>';
		echo '<p style="margin-top:14px;">';
		echo '<a class="button button-primary" href="' . esc_url( $trigger_url ) . '">' . esc_html__( 'Check for new messages now', 'biopentra-contact-inbox' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $check_url ) . '">' . esc_html__( 'Check worker health', 'biopentra-contact-inbox' ) . '</a>';
		echo '</p>';
		if ( is_array( $cache ) && isset( $cache['checked_at_gmt'] ) && is_string( $cache['checked_at_gmt'] ) && isset( $cache['healthy'] ) && is_bool( $cache['healthy'] ) ) {
			$ready = $cache['healthy'];
			$lbl   = $ready ? __( 'Ready', 'biopentra-contact-inbox' ) : __( 'Not ready', 'biopentra-contact-inbox' );
			echo '<p class="description"><strong>' . esc_html__( 'Last health check:', 'biopentra-contact-inbox' ) . '</strong> ';
			echo esc_html( $cache['checked_at_gmt'] ) . ' — ' . esc_html( $lbl );
			echo '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Collapsed technical reference for operators who manage Docker / networking.
	 *
	 * @param string $last_json Last raw worker status JSON from the site (shown only inside nested details).
	 */
	private static function render_worker_advanced_details_section( $last_json ) {
		$last_json = is_string( $last_json ) ? $last_json : '';

		$curl_cmd = <<<'EOT'
docker compose exec biopentra-mail-worker \
  sh -lc 'curl -s -X POST http://localhost:8080/poll'
EOT;

		$py_cmd = <<<'EOT'
docker compose exec biopentra-mail-worker \
  python - <<'PY'
import urllib.request
req = urllib.request.Request("http://localhost:8080/poll", method="POST")
print(urllib.request.urlopen(req).read().decode())
PY
EOT;

		echo '<details class="bsd-worker-advanced" style="margin:20px 0;padding:12px;border:1px solid #c3c4c7;background:#fff;">';
		echo '<summary style="cursor:pointer;font-weight:600;">' . esc_html__( 'Advanced / Developer details', 'biopentra-contact-inbox' ) . '</summary>';
		echo '<div style="margin-top:12px;">';

		echo '<p>' . esc_html__( 'The mail worker runs as a separate Docker container.', 'biopentra-contact-inbox' ) . '</p>';

		echo '<p>' . esc_html__( 'WordPress uses the worker only for:', 'biopentra-contact-inbox' ) . '</p>';
		echo '<ul style="list-style:disc;margin-left:1.25em;">';
		echo '<li>' . esc_html__( 'checking worker health', 'biopentra-contact-inbox' ) . '</li>';
		echo '<li>' . esc_html__( 'asking it to check the mailbox now', 'biopentra-contact-inbox' ) . '</li>';
		echo '</ul>';

		echo '<p>' . esc_html__( 'The worker imports messages by calling this site’s REST API:', 'biopentra-contact-inbox' ) . '</p>';
		echo '<ul style="list-style:disc;margin-left:1.25em;">';
		echo '<li><code>POST /wp-json/biopentra-support/v1/messages/import</code></li>';
		echo '<li><code>POST /wp-json/biopentra-support/v1/worker/status</code></li>';
		echo '</ul>';

		echo '<p>' . esc_html__( 'Those worker-to-WordPress REST calls use the worker token.', 'biopentra-contact-inbox' ) . '</p>';
		echo '<p>' . esc_html__( 'The internal worker /poll endpoint does not use the worker token. It is protected by Docker network isolation. Do not publish port 8080 to the host.', 'biopentra-contact-inbox' ) . '</p>';

		echo '<details style="margin-top:14px;">';
		echo '<summary style="cursor:pointer;">' . esc_html__( 'CLI examples', 'biopentra-contact-inbox' ) . '</summary>';
		echo '<div style="margin-top:8px;">';
		echo '<p><strong>' . esc_html__( 'curl', 'biopentra-contact-inbox' ) . '</strong></p>';
		echo '<pre style="background:#f6f7f7;padding:12px;overflow:auto;">' . esc_html( $curl_cmd ) . '</pre>';
		echo '<p><strong>' . esc_html__( 'Python', 'biopentra-contact-inbox' ) . '</strong></p>';
		echo '<pre style="background:#f6f7f7;padding:12px;overflow:auto;">' . esc_html( $py_cmd ) . '</pre>';
		echo '</div></details>';

		echo '<details style="margin-top:14px;">';
		echo '<summary style="cursor:pointer;">' . esc_html__( 'Raw worker status', 'biopentra-contact-inbox' ) . '</summary>';
		echo '<div style="margin-top:8px;">';
		echo '<pre style="background:#f6f7f7;padding:12px;overflow:auto;max-height:240px;">' . esc_html( $last_json !== '' ? $last_json : '—' ) . '</pre>';
		echo '</div></details>';

		echo '</div></details>';
	}

	/**
	 * Human-readable import summary from worker status payload.
	 *
	 * @param array<string, mixed>|null $summary Parsed worker summary or null.
	 * @return string
	 */
	private static function format_worker_import_summary_line( $summary ) {
		if ( ! is_array( $summary ) ) {
			return '—';
		}
		$parts = array();
		if ( array_key_exists( 'imported', $summary ) ) {
			$parts[] = sprintf(
				/* translators: %d: messages imported */
				__( 'Imported: %d', 'biopentra-contact-inbox' ),
				(int) $summary['imported']
			);
		}
		if ( array_key_exists( 'skipped', $summary ) ) {
			$parts[] = sprintf(
				/* translators: %d: messages skipped */
				__( 'Skipped: %d', 'biopentra-contact-inbox' ),
				(int) $summary['skipped']
			);
		}
		if ( array_key_exists( 'errors', $summary ) ) {
			$parts[] = sprintf(
				/* translators: %d: error count */
				__( 'Errors: %d', 'biopentra-contact-inbox' ),
				(int) $summary['errors']
			);
		}
		if ( isset( $summary['last_error'] ) && is_string( $summary['last_error'] ) && $summary['last_error'] !== '' ) {
			$parts[] = __( 'Last error:', 'biopentra-contact-inbox' ) . ' ' . sanitize_text_field( $summary['last_error'] );
		}
		return $parts !== array() ? implode( ' · ', $parts ) : '—';
	}

	/**
	 * Parse worker status JSON saved from REST worker/status.
	 *
	 * @param string $json Raw option value.
	 * @return array<string, mixed>|null
	 */
	private static function parse_worker_status_json( $json ) {
		if ( ! is_string( $json ) || $json === '' ) {
			return null;
		}
		$d = json_decode( $json, true );
		if ( ! is_array( $d ) || ! isset( $d['source'] ) || 'worker' !== $d['source'] ) {
			return null;
		}
		return $d;
	}

	/**
	 * @param string $id    Input name/id.
	 * @param string $label Label.
	 * @param string $val   Value.
	 * @param string $class CSS class.
	 * @param string $type  Input type.
	 */
	private static function text_row( $id, $label, $val, $class = 'regular-text', $type = 'text' ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" class="' . esc_attr( $class ) . '" value="' . esc_attr( $val ) . '" />';
		echo '</td></tr>';
	}

	/**
	 * IMAP host shown in worker mode: prefer process env (same keys as the mail worker / `.env.worker`), else saved option.
	 *
	 * @return array{value: string, from_env: bool}
	 */
	private static function worker_display_imap_host() {
		$e = getenv( 'IMAP_HOST' );
		if ( is_string( $e ) && trim( $e ) !== '' ) {
			return array( 'value' => trim( $e ), 'from_env' => true );
		}
		return array(
			'value'    => (string) get_option( 'biopentra_inbox_imap_host', 'proton-bridge' ),
			'from_env' => false,
		);
	}

	/**
	 * @return array{value: string, from_env: bool}
	 */
	private static function worker_display_imap_port() {
		$e = getenv( 'IMAP_PORT' );
		if ( is_string( $e ) && trim( $e ) !== '' ) {
			return array( 'value' => trim( $e ), 'from_env' => true );
		}
		return array(
			'value'    => (string) get_option( 'biopentra_inbox_imap_port', '2143' ),
			'from_env' => false,
		);
	}

	/**
	 * @return array{value: string, from_env: bool}
	 */
	private static function worker_display_imap_user() {
		$e = getenv( 'IMAP_USER' );
		if ( is_string( $e ) && trim( $e ) !== '' ) {
			return array( 'value' => trim( $e ), 'from_env' => true );
		}
		return array(
			'value'    => (string) get_option( 'biopentra_inbox_imap_user', '' ),
			'from_env' => false,
		);
	}

	/**
	 * Human-readable IMAP password presence for worker mode (never exposes the secret).
	 *
	 * @return string Translated short status.
	 */
	private static function worker_imap_password_status_text() {
		$env_pass = getenv( 'IMAP_PASS' );
		if ( is_string( $env_pass ) && trim( $env_pass ) !== '' ) {
			return __( 'Configured (IMAP_PASS in this PHP environment — typically from the mail worker’s `.env.worker`)', 'biopentra-contact-inbox' );
		}
		if ( Biopentra_Contact_Inbox_Bridge_Diagnostics::is_password_configured( 'biopentra_inbox_imap_pass', 'BIOPENTRA_INBOX_IMAP_PASS' ) ) {
			return __( 'Configured in WordPress (saved option or wp-config constant)', 'biopentra-contact-inbox' );
		}
		return __( 'Not detected in WordPress; the mail worker may still use `.env.worker` only', 'biopentra-contact-inbox' );
	}

	/**
	 * One table row: read-only text input without a name (not submitted — avoids clearing options on save).
	 *
	 * @param string $id    Element id (for label).
	 * @param string $label Row label.
	 * @param string $value Display value.
	 */
	private static function readonly_text_row_no_name( $id, $label, $value ) {
		echo '<tr class="bsd-worker-readonly-imap"><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input id="' . esc_attr( $id ) . '" type="text" class="regular-text" value="' . esc_attr( $value ) . '" readonly="readonly" autocomplete="off" />';
		echo '</td></tr>';
	}

	/**
	 * Admin notices after IMAP/SMTP diagnostic redirects (email tab only).
	 *
	 * @param string $tab Active settings tab.
	 */
	private static function render_bridge_test_notices( $tab ) {
		if ( 'email' !== $tab ) {
			return;
		}
		if ( isset( $_GET['bsd_imap_test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code = sanitize_key( wp_unslash( $_GET['bsd_imap_test'] ) );
			$msg  = isset( $_GET['bsd_imap_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['bsd_imap_msg'] ) ) ) : '';
			$msg  = strlen( $msg ) > 500 ? substr( $msg, 0, 500 ) : $msg;

			$text = '';
			$type = 'error';
			switch ( $code ) {
				case 'success':
					$type = 'success';
					$text = __( 'Legacy PHP IMAP connection test: login succeeded.', 'biopentra-contact-inbox' );
					break;
				case 'skipped':
					$type = 'info';
					$text = __( 'Legacy PHP IMAP connection test was skipped. Switch to the legacy PHP IMAP import driver and enable the PHP IMAP extension to run this test from WordPress.', 'biopentra-contact-inbox' );
					break;
				case 'missing_ext':
					$text = __( 'PHP IMAP extension is not installed.', 'biopentra-contact-inbox' );
					break;
				case 'auth_fail':
					$text = __( 'IMAP test: authentication failed. Check Bridge username and the Bridge-generated password.', 'biopentra-contact-inbox' );
					break;
				case 'connection_fail':
					$text = __( 'IMAP test: could not connect. Check host, port, Docker networking, and that Proton Bridge is running.', 'biopentra-contact-inbox' );
					break;
				case 'config_incomplete':
					$text = __( 'IMAP test: host, port, username, and password are all required.', 'biopentra-contact-inbox' );
					break;
				case 'exception':
					$text = __( 'IMAP test: an unexpected error occurred.', 'biopentra-contact-inbox' );
					break;
				default:
					$text = __( 'IMAP test finished with an unknown status.', 'biopentra-contact-inbox' );
					break;
			}
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text );
			if ( $msg !== '' ) {
				echo ' <code style="word-break:break-all;">' . esc_html( $msg ) . '</code>';
			}
			echo '</p></div>';
		}

		if ( isset( $_GET['bsd_smtp_test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code = sanitize_key( wp_unslash( $_GET['bsd_smtp_test'] ) );
			$msg  = isset( $_GET['bsd_smtp_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['bsd_smtp_msg'] ) ) ) : '';
			$msg  = strlen( $msg ) > 500 ? substr( $msg, 0, 500 ) : $msg;

			$text = '';
			$type = 'error';
			switch ( $code ) {
				case 'success':
					$type = 'success';
					$text = __( 'SMTP test: a test email was handed to WordPress for delivery. Check the inbox of your current admin user.', 'biopentra-contact-inbox' );
					break;
				case 'auth_fail':
					$text = __( 'SMTP test: authentication failed. Check Bridge SMTP username and the Bridge-generated password.', 'biopentra-contact-inbox' );
					break;
				case 'connection_fail':
					$text = __( 'SMTP test: could not connect to the SMTP server.', 'biopentra-contact-inbox' );
					break;
				case 'timeout':
					$text = __( 'SMTP test: connection timed out.', 'biopentra-contact-inbox' );
					break;
				case 'bad_recipient':
					$text = __( 'SMTP test: your user account does not have a valid email address.', 'biopentra-contact-inbox' );
					break;
				case 'bridge_disabled':
					$type = 'warning';
					$text = __( 'SMTP test skipped: turn on “Enable email inbox” so Bridge SMTP settings are applied to PHPMailer.', 'biopentra-contact-inbox' );
					break;
				case 'config_incomplete':
					$text = __( 'SMTP test: host, port, username, and password are all required.', 'biopentra-contact-inbox' );
					break;
				case 'suspected_conflict':
					$type = 'warning';
					$text = __( 'SMTP test: wp_mail() failed without a detailed error. Another mail or SMTP plugin may be overriding PHPMailer; try disabling it temporarily.', 'biopentra-contact-inbox' );
					break;
				case 'wp_mail_failed':
					$text = __( 'SMTP test: wp_mail() reported failure.', 'biopentra-contact-inbox' );
					break;
				case 'exception':
					$text = __( 'SMTP test: an unexpected error occurred.', 'biopentra-contact-inbox' );
					break;
				default:
					$text = __( 'SMTP test finished with an unknown status.', 'biopentra-contact-inbox' );
					break;
			}
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text );
			if ( $msg !== '' ) {
				echo ' <code style="word-break:break-all;">' . esc_html( $msg ) . '</code>';
			}
			echo '</p></div>';
		}
	}

	/**
	 * Email tab: Bridge onboarding, connection status, and diagnostic actions (outside options form).
	 */
	private static function render_email_bridge_panel() {
		$u = wp_get_current_user();
		$admin_email = ( $u && is_email( $u->user_email ) ) ? $u->user_email : '';

		echo '<div class="metabox-holder columns-1" style="margin-top:12px;">';
		echo '<div class="postbox-container" style="width:100%;">';

		echo '<div class="postbox">';
		echo '<h2 class="hndle" style="padding:12px 12px 0;">' . esc_html__( 'Proton Bridge setup flow', 'biopentra-contact-inbox' ) . '</h2>';
		echo '<div class="inside" style="padding:0 12px 12px;">';
		echo '<ol style="margin-left:1.25em;">';
		echo '<li>' . esc_html__( 'Start your Docker stack (WordPress, database, and the proton-bridge service).', 'biopentra-contact-inbox' ) . '</li>';
		echo '<li>' . esc_html__( 'Stop the running Bridge daemon inside the container if your image starts it automatically and you need an interactive CLI first.', 'biopentra-contact-inbox' ) . '</li>';
		echo '<li>' . esc_html__( 'Run:', 'biopentra-contact-inbox' ) . '</li>';
		echo '</ol>';
		echo '<pre style="background:#f6f7f7;padding:12px;overflow:auto;">docker compose run --rm -it proton-bridge --cli</pre>';
		echo '<p>' . esc_html__( 'Inside the CLI:', 'biopentra-contact-inbox' ) . '</p>';
		echo '<pre style="background:#f6f7f7;padding:12px;">login
info
quit</pre>';
		echo '<p>' . esc_html__( 'Use the credentials shown by info in the fields below. The username is usually your full Proton email address. The password is the Bridge-generated password — do not use your normal Proton web password in WordPress.', 'biopentra-contact-inbox' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Important:', 'biopentra-contact-inbox' ) . '</strong> ';
		echo esc_html__( 'Proton Bridge must stay running for mail to flow. Named Docker volumes preserve Bridge login state across container restarts. IMAP and SMTP ports should stay internal to Docker (not published to the public internet). For local Bridge connections, TLS peer verification is intentionally relaxed in this plugin so self-signed Bridge certificates work.', 'biopentra-contact-inbox' );
		echo '</p>';
		echo '</div></div>';

		$driver_panel = Biopentra_Contact_Inbox_Cron::import_driver();
		$imap_ext      = extension_loaded( 'imap' );
		$imap_host     = trim( (string) get_option( 'biopentra_inbox_imap_host', '' ) ) !== '';
		$imap_user     = trim( (string) get_option( 'biopentra_inbox_imap_user', '' ) ) !== '';
		$imap_pass     = Biopentra_Contact_Inbox_Bridge_Diagnostics::is_password_configured( 'biopentra_inbox_imap_pass', 'BIOPENTRA_INBOX_IMAP_PASS' );

		$smtp_host = trim( (string) get_option( 'biopentra_inbox_smtp_host', '' ) ) !== '';
		$smtp_user = trim( (string) get_option( 'biopentra_inbox_smtp_user', '' ) ) !== '';
		$smtp_pass = Biopentra_Contact_Inbox_Bridge_Diagnostics::is_password_configured( 'biopentra_inbox_smtp_pass', 'BIOPENTRA_INBOX_SMTP_PASS' );
		$scope     = get_option( 'biopentra_inbox_smtp_scope', 'plugin_only' );

		$last_at = (string) get_option( 'biopentra_inbox_last_sync_at', '' );
		$last_rs_raw = (string) get_option( 'biopentra_inbox_last_sync_result', '' );
		$worker_summary_panel = self::parse_worker_status_json( $last_rs_raw );
		$last_rs_display = $last_rs_raw;
		if ( strlen( $last_rs_display ) > 200 ) {
			$last_rs_display = substr( $last_rs_display, 0, 197 ) . '...';
		}
		$lock = get_transient( Biopentra_Contact_Inbox_Imap_Sync::LOCK_KEY );

		if ( 'worker' === $driver_panel ) {
			$imap_ext_label = $imap_ext
				? __( 'Installed (optional; legacy PHP IMAP only)', 'biopentra-contact-inbox' )
				: __( 'Not required (Docker mail worker)', 'biopentra-contact-inbox' );
		} else {
			$imap_ext_label = $imap_ext
				? __( 'Yes', 'biopentra-contact-inbox' )
				: __( 'No — required for legacy PHP IMAP', 'biopentra-contact-inbox' );
		}

		echo '<div class="postbox">';
		echo '<h2 class="hndle" style="padding:12px 12px 0;">' . esc_html__( 'Current connection status', 'biopentra-contact-inbox' ) . '</h2>';
		echo '<div class="inside" style="padding:0 12px 12px;">';

		if ( 'worker' === $driver_panel ) {
			echo '<p class="description">' . esc_html__( 'Inbound mail is handled by the Docker mail worker.', 'biopentra-contact-inbox' ) . ' ';
			echo esc_html__( 'PHP IMAP inside WordPress is only required when using the legacy PHP IMAP import driver.', 'biopentra-contact-inbox' ) . '</p>';
		}

		$imap_section_title = ( 'worker' === $driver_panel )
			? __( 'IMAP (mail worker — troubleshooting)', 'biopentra-contact-inbox' )
			: __( 'IMAP', 'biopentra-contact-inbox' );
		echo '<h3>' . esc_html( $imap_section_title ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:640px;"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'PHP IMAP extension', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( $imap_ext_label ) . '</td></tr>';
		if ( 'worker' === $driver_panel ) {
			$wh = self::worker_display_imap_host();
			$wp = self::worker_display_imap_port();
			$wu = self::worker_display_imap_user();
			echo '<tr><th scope="row">' . esc_html__( 'IMAP host', 'biopentra-contact-inbox' ) . '</th><td><code>' . esc_html( $wh['value'] ) . '</code>';
			if ( $wh['from_env'] ) {
				echo ' <span class="description">' . esc_html__( '(IMAP_HOST in PHP env)', 'biopentra-contact-inbox' ) . '</span>';
			} else {
				echo ' <span class="description">' . esc_html__( '(saved site option — worker typically uses `.env.worker`)', 'biopentra-contact-inbox' ) . '</span>';
			}
			echo '</td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'IMAP port', 'biopentra-contact-inbox' ) . '</th><td><code>' . esc_html( $wp['value'] ) . '</code>';
			if ( $wp['from_env'] ) {
				echo ' <span class="description">' . esc_html__( '(IMAP_PORT in PHP env)', 'biopentra-contact-inbox' ) . '</span>';
			} else {
				echo ' <span class="description">' . esc_html__( '(saved site option)', 'biopentra-contact-inbox' ) . '</span>';
			}
			echo '</td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'IMAP username', 'biopentra-contact-inbox' ) . '</th><td><code>' . esc_html( $wu['value'] !== '' ? $wu['value'] : '—' ) . '</code>';
			if ( $wu['from_env'] ) {
				echo ' <span class="description">' . esc_html__( '(IMAP_USER in PHP env)', 'biopentra-contact-inbox' ) . '</span>';
			}
			echo '</td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'IMAP password', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( self::worker_imap_password_status_text() ) . '</td></tr>';
		} else {
			echo '<tr><th scope="row">' . esc_html__( 'IMAP host configured', 'biopentra-contact-inbox' ) . '</th><td>' . ( $imap_host ? esc_html__( 'Yes', 'biopentra-contact-inbox' ) : esc_html__( 'No', 'biopentra-contact-inbox' ) ) . '</td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'IMAP username configured', 'biopentra-contact-inbox' ) . '</th><td>' . ( $imap_user ? esc_html__( 'Yes', 'biopentra-contact-inbox' ) : esc_html__( 'No', 'biopentra-contact-inbox' ) ) . '</td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'IMAP password configured', 'biopentra-contact-inbox' ) . '</th><td>' . ( $imap_pass ? esc_html__( 'Yes', 'biopentra-contact-inbox' ) : esc_html__( 'No', 'biopentra-contact-inbox' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'SMTP', 'biopentra-contact-inbox' ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:640px;"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'SMTP host configured', 'biopentra-contact-inbox' ) . '</th><td>' . ( $smtp_host ? esc_html__( 'Yes', 'biopentra-contact-inbox' ) : esc_html__( 'No', 'biopentra-contact-inbox' ) ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'SMTP username configured', 'biopentra-contact-inbox' ) . '</th><td>' . ( $smtp_user ? esc_html__( 'Yes', 'biopentra-contact-inbox' ) : esc_html__( 'No', 'biopentra-contact-inbox' ) ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'SMTP password configured', 'biopentra-contact-inbox' ) . '</th><td>' . ( $smtp_pass ? esc_html__( 'Yes', 'biopentra-contact-inbox' ) : esc_html__( 'No', 'biopentra-contact-inbox' ) ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'SMTP scope', 'biopentra-contact-inbox' ) . '</th><td><code>' . esc_html( (string) $scope ) . '</code></td></tr>';
		echo '</tbody></table>';

		if ( 'worker' === $driver_panel ) {
			echo '<h3>' . esc_html__( 'Mail worker activity', 'biopentra-contact-inbox' ) . '</h3>';
			echo '<p><strong>' . esc_html__( 'Import source:', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html__( 'Docker mail worker', 'biopentra-contact-inbox' ) . '</p>';
			if ( is_array( $worker_summary_panel ) && ! empty( $worker_summary_panel['received_at_gmt'] ) ) {
				echo '<p><strong>' . esc_html__( 'Last mail worker message (GMT):', 'biopentra-contact-inbox' ) . '</strong> ' . esc_html( (string) $worker_summary_panel['received_at_gmt'] ) . '</p>';
			}
			echo '<table class="widefat striped" style="max-width:640px;"><tbody>';
			echo '<tr><th scope="row">' . esc_html__( 'Last worker report time', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( $last_at !== '' ? $last_at : '—' ) . '</td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'Last worker status (truncated)', 'biopentra-contact-inbox' ) . '</th><td><code style="word-break:break-all;">' . esc_html( $last_rs_display !== '' ? $last_rs_display : '—' ) . '</code></td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'Legacy PHP IMAP sync lock', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html__( 'Not used in worker mode', 'biopentra-contact-inbox' ) . '</td></tr>';
			echo '</tbody></table>';
		} else {
			echo '<h3>' . esc_html__( 'Legacy PHP IMAP sync status', 'biopentra-contact-inbox' ) . '</h3>';
			echo '<table class="widefat striped" style="max-width:640px;"><tbody>';
			echo '<tr><th scope="row">' . esc_html__( 'Last legacy PHP IMAP run', 'biopentra-contact-inbox' ) . '</th><td>' . esc_html( $last_at !== '' ? $last_at : '—' ) . '</td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'Last legacy PHP IMAP result (truncated)', 'biopentra-contact-inbox' ) . '</th><td><code style="word-break:break-all;">' . esc_html( $last_rs_display !== '' ? $last_rs_display : '—' ) . '</code></td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'Legacy PHP IMAP sync lock', 'biopentra-contact-inbox' ) . '</th><td>' . ( $lock ? esc_html__( 'Active (or stale; clears after a few minutes)', 'biopentra-contact-inbox' ) : esc_html__( 'Idle', 'biopentra-contact-inbox' ) ) . '</td></tr>';
			echo '</tbody></table>';
		}
		echo '</div></div>';

		$post = esc_url( admin_url( 'admin-post.php' ) );

		echo '<div class="postbox">';
		echo '<h2 class="hndle" style="padding:12px 12px 0;">' . esc_html__( 'Connection tests', 'biopentra-contact-inbox' ) . '</h2>';
		echo '<div class="inside" style="padding:0 12px 12px;">';
		$imap_test_ok = ( 'php_imap' === $driver_panel && extension_loaded( 'imap' ) );
		if ( $imap_test_ok ) {
			echo '<h4 style="margin-top:0;">' . esc_html__( 'Legacy PHP IMAP connection test', 'biopentra-contact-inbox' ) . '</h4>';
			echo '<p>' . esc_html__( 'Opens a read-only session from WordPress, checks the mailbox, and closes. It does not import mail or change flags.', 'biopentra-contact-inbox' ) . '</p>';
			echo '<form method="post" action="' . $post . '" style="margin-bottom:16px;">';
			echo '<input type="hidden" name="action" value="biopentra_inbox_test_imap" />';
			wp_nonce_field( 'biopentra_inbox_test_imap' );
			submit_button( __( 'Run legacy PHP IMAP connection test', 'biopentra-contact-inbox' ), 'secondary', 'submit', false );
			echo '</form>';
		} elseif ( 'php_imap' === $driver_panel && ! extension_loaded( 'imap' ) ) {
			echo '<p class="description">' . esc_html__( 'Legacy PHP IMAP connection test requires the PHP IMAP extension.', 'biopentra-contact-inbox' ) . '</p>';
		}

		echo '<p><strong>' . esc_html__( 'SMTP test email recipient:', 'biopentra-contact-inbox' ) . '</strong> ';
		if ( $admin_email !== '' ) {
			echo '<code>' . esc_html( $admin_email ) . '</code>';
		} else {
			echo esc_html__( '(set a valid email on your user profile)', 'biopentra-contact-inbox' );
		}
		echo '</p>';
		echo '<p>' . esc_html__( 'Sends one HTML test message via wp_mail() using your From settings and SMTP scope (plugin_only wraps try/finally like support replies).', 'biopentra-contact-inbox' ) . '</p>';
		echo '<form method="post" action="' . $post . '">';
		echo '<input type="hidden" name="action" value="biopentra_inbox_test_smtp" />';
		wp_nonce_field( 'biopentra_inbox_test_smtp' );
		submit_button( __( 'Send SMTP test email to current admin', 'biopentra-contact-inbox' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '</div></div>';

		echo '</div></div>';
	}

	/**
	 * Preview action: separate form outside options.php (no nested forms).
	 */
	private static function render_email_template_preview_outside_form() {
		$post = esc_url( admin_url( 'admin-post.php' ) );
		echo '<div class="bsd-template-preview-outside" style="margin-top:20px;padding:16px;border:1px solid #c3c4c7;background:#fff;max-width:960px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Preview', 'biopentra-contact-inbox' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Uses saved settings only. Opens in a new tab. No email is sent and no credentials are shown.', 'biopentra-contact-inbox' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $post ) . '" target="_blank" rel="noopener noreferrer">';
		echo '<input type="hidden" name="action" value="biopentra_inbox_preview_reply_template" />';
		wp_nonce_field( 'biopentra_inbox_preview_reply_template' );
		submit_button( __( 'Open preview in new tab', 'biopentra-contact-inbox' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Success notice after full Support Desk reset (one read per user).
	 */
	private static function render_desk_reset_success_notice() {
		$key  = 'biopentra_inbox_desk_reset_notice_' . get_current_user_id();
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			return;
		}
		delete_transient( $key );

		$tickets  = isset( $data['tickets_deleted'] ) ? (int) $data['tickets_deleted'] : 0;
		$messages = isset( $data['messages_deleted'] ) ? (int) $data['messages_deleted'] : 0;
		$replies  = isset( $data['replies_deleted'] ) ? (int) $data['replies_deleted'] : 0;
		$cleared  = ! empty( $data['import_state_cleared'] );

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: tickets removed, 2: message rows removed, 3: legacy reply rows removed */
				__( 'Support Desk data was removed: %1$d ticket(s), %2$d message row(s), %3$d legacy reply row(s).', 'biopentra-contact-inbox' ),
				$tickets,
				$messages,
				$replies
			)
		);
		if ( $cleared ) {
			echo ' ';
			echo esc_html__( 'Import status fields on this site were cleared; you can run a fresh import or sync.', 'biopentra-contact-inbox' );
		}
		echo '</p></div>';
	}

	/**
	 * Advanced tab: archive retention + full Support Desk conversation reset.
	 */
	private static function render_advanced_tab() {
		$confirm_json = wp_json_encode(
			__( 'This permanently deletes all Support Desk tickets and conversation history (Fluent and email), including staff replies. Plugin settings, the worker token hash, and the Fluent migration cursor are not removed. This cannot be undone. Continue?', 'biopentra-contact-inbox' )
		);
		$post = esc_url( admin_url( 'admin-post.php' ) );

		echo '<div class="bsd-tab-panel" id="bsd-panel-advanced" style="display:block;margin-top:16px;">';
		echo '<h2>' . esc_html__( 'Advanced', 'biopentra-contact-inbox' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Danger zone: resetting tickets does not remove WooCommerce data, users, media, or Fluent Forms submissions themselves—only Support Desk copies and threads stored in this plugin.', 'biopentra-contact-inbox' ) . '</p>';

		echo '<form method="post" action="' . $post . '" style="margin-bottom:24px;">';
		echo '<input type="hidden" name="action" value="biopentra_inbox_save_archive_retention" />';
		wp_nonce_field( 'biopentra_inbox_save_archive_retention' );
		echo '<h3>' . esc_html__( 'Archived mail', 'biopentra-contact-inbox' ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$days_raw = get_option( 'biopentra_inbox_archive_auto_delete_days', null );
		$days      = null === $days_raw ? 30 : (int) $days_raw;
		if ( $days < 0 || $days > 3650 ) {
			$days = 30;
		}
		echo '<tr><th scope="row"><label for="biopentra_inbox_archive_auto_delete_days">' . esc_html__( 'Auto-delete archived email tickets after', 'biopentra-contact-inbox' ) . '</label></th><td>';
		echo '<input name="biopentra_inbox_archive_auto_delete_days" id="biopentra_inbox_archive_auto_delete_days" type="number" min="0" max="3650" step="1" class="small-text" value="' . esc_attr( (string) $days ) . '" /> ';
		echo esc_html__( 'days', 'biopentra-contact-inbox' );
		echo '<p class="description">' . esc_html__( 'Applies only to archived tickets whose source is email (IMAP/worker). Fluent-originated tickets are not auto-deleted by this job. Use 0 to disable. Runs at most once per day, up to 100 tickets per run.', 'biopentra-contact-inbox' ) . '</p>';
		echo '</td></tr></tbody></table>';
		submit_button( __( 'Save archive retention', 'biopentra-contact-inbox' ) );
		echo '</form>';

		echo '<form method="post" action="' . $post . '" style="margin-top:16px;">';
		echo '<input type="hidden" name="action" value="biopentra_inbox_reset_support_desk" />';
		wp_nonce_field( 'biopentra_inbox_reset_support_desk' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Support Desk data', 'biopentra-contact-inbox' ) . '</th><td>';
		echo '<p class="description" style="margin-top:0;"><strong>' . esc_html__( 'Warning:', 'biopentra-contact-inbox' ) . '</strong> ';
		echo esc_html__( 'This deletes Fluent form tickets and email conversations from the Support Desk. Plugin settings and the Fluent migration cursor are not deleted. The worker token hash is not deleted.', 'biopentra-contact-inbox' );
		echo '</p>';
		echo '<input type="hidden" name="biopentra_inbox_clear_import_state" value="no" />';
		submit_button(
			__( 'Delete all support tickets and mail history', 'biopentra-contact-inbox' ),
			'delete',
			'submit',
			false,
			array(
				'id'      => 'biopentra-inbox-reset-support-desk-submit',
				'onclick' => 'return window.confirm(' . $confirm_json . ');',
			)
		);
		echo '<p><label><input name="biopentra_inbox_clear_import_state" type="checkbox" value="yes" checked="checked" /> ';
		echo esc_html__( 'Also clear import/sync status on this site (recommended)', 'biopentra-contact-inbox' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'When checked, clears last sync timestamps, worker heartbeat cache, and the legacy PHP IMAP lock transient. Uncheck to wipe tickets only while leaving those indicators unchanged.', 'biopentra-contact-inbox' ) . '</p>';
		echo '</td></tr></tbody></table>';
		echo '</form>';
		echo '</div>';
	}
}
