<?php
/**
 * WP-Cron registration for IMAP sync (settings-driven).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Cron {

	const HOOK = 'biopentra_inbox_imap_sync';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_intervals' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 15 );
		add_action( self::HOOK, array( __CLASS__, 'run_sync' ) );

		$watch = array(
			'biopentra_inbox_email_enabled',
			'biopentra_inbox_sync_enabled',
			'biopentra_inbox_sync_interval',
			'biopentra_inbox_import_driver',
		);
		foreach ( $watch as $opt ) {
			add_action( "update_option_{$opt}", array( __CLASS__, 'on_option_changed' ), 10, 3 );
		}

		if ( class_exists( 'Biopentra_Contact_Inbox_Archived_Email_Cleanup' ) ) {
			Biopentra_Contact_Inbox_Archived_Email_Cleanup::init();
			add_action( 'update_option_biopentra_inbox_archive_auto_delete_days', array( __CLASS__, 'on_archive_days_changed' ), 10, 0 );
		}
	}

	/**
	 * Reschedule archived-email cleanup when retention days change.
	 */
	public static function on_archive_days_changed() {
		if ( class_exists( 'Biopentra_Contact_Inbox_Archived_Email_Cleanup' ) ) {
			wp_clear_scheduled_hook( Biopentra_Contact_Inbox_Archived_Email_Cleanup::HOOK );
			Biopentra_Contact_Inbox_Archived_Email_Cleanup::maybe_reschedule();
		}
	}

	/**
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 */
	public static function on_option_changed( $old, $new, $option ) {
		self::reschedule();
	}

	/**
	 * @param array<string, array<string, int|string>> $schedules Schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public static function register_intervals( $schedules ) {
		$interval = self::get_interval_seconds();
		$key       = self::schedule_key_for_interval( $interval );
		$schedules[ $key ] = array(
			'interval' => $interval,
			/* translators: %d: seconds */
			'display'  => sprintf( __( 'Biopentra Support Desk every %d seconds', 'biopentra-contact-inbox' ), $interval ),
		);
		return $schedules;
	}

	/**
	 * @return int
	 */
	public static function get_interval_seconds() {
		$i = (int) get_option( 'biopentra_inbox_sync_interval', 300 );
		return max( 60, min( 3600, $i ) );
	}

	/**
	 * @param int $interval Seconds.
	 * @return string
	 */
	public static function schedule_key_for_interval( $interval ) {
		return 'biopentra_inbox_every_' . (int) $interval;
	}

	public static function reschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		self::maybe_schedule();
	}

	public static function maybe_schedule() {
		if ( self::import_driver() !== 'php_imap' ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}

		$email = get_option( 'biopentra_inbox_email_enabled', 'no' );
		$sync  = get_option( 'biopentra_inbox_sync_enabled', 'no' );
		if ( 'yes' !== $email || 'yes' !== $sync ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}

		$interval = self::get_interval_seconds();
		$key      = self::schedule_key_for_interval( $interval );

		if ( function_exists( 'wp_get_scheduled_event' ) ) {
			$evt = wp_get_scheduled_event( self::HOOK );
			if ( $evt && isset( $evt->schedule ) && $evt->schedule !== $key ) {
				wp_unschedule_event( $evt->timestamp, self::HOOK );
			}
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, $key, self::HOOK );
		}
	}

	public static function run_sync() {
		if ( self::import_driver() !== 'php_imap' ) {
			return;
		}
		if ( ! class_exists( 'Biopentra_Contact_Inbox_Imap_Sync' ) ) {
			return;
		}
		Biopentra_Contact_Inbox_Imap_Sync::instance()->run_and_record();
	}

	/**
	 * @return string worker|php_imap
	 */
	public static function import_driver() {
		$d = (string) get_option( 'biopentra_inbox_import_driver', 'worker' );
		return 'php_imap' === $d ? 'php_imap' : 'worker';
	}
}
