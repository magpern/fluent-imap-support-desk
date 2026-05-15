<?php
/**
 * Resolve monitored Fluent Form ID from settings (option-first).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Form_Resolver {

	const FALLBACK_TITLE = 'Biopentra Contact Form';

	/**
	 * @return int Fluent form ID or 0.
	 */
	public static function get_form_id() {
		$opt = (int) get_option( 'biopentra_inbox_contact_form_id', 0 );
		if ( $opt > 0 && self::form_exists( $opt ) ) {
			return $opt;
		}

		$resolved = self::resolve_by_title( self::FALLBACK_TITLE );
		if ( $resolved > 0 ) {
			update_option( 'biopentra_inbox_contact_form_id', $resolved );
			return $resolved;
		}

		return 0;
	}

	/**
	 * @param int $id Form ID.
	 * @return bool
	 */
	public static function form_exists( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_forms WHERE id = %d",
				$id
			)
		);
		return (int) $n > 0;
	}

	/**
	 * @param string $title Exact form title.
	 * @return int
	 */
	public static function resolve_by_title( $title ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}fluentform_forms WHERE title = %s ORDER BY id DESC LIMIT 1",
				$title
			)
		);
		return $found ? (int) $found : 0;
	}

	/**
	 * @return bool
	 */
	public static function fluent_tables_exist() {
		global $wpdb;
		$t = $wpdb->prefix . 'fluentform_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;
	}

	/**
	 * @return array<int, array{id:int, title:string}>
	 */
	public static function get_all_forms() {
		global $wpdb;
		if ( ! self::fluent_tables_exist() ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT id, title FROM {$wpdb->prefix}fluentform_forms ORDER BY title ASC",
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
