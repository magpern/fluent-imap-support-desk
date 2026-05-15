<?php
/**
 * RFC Message-ID normalization for storage and matching.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Message_Id {

	/**
	 * @param mixed $value Raw Message-ID or header fragment.
	 * @return string|null NULL when missing; never empty string.
	 */
	public static function normalize( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$v = trim( $value );
		if ( $v === '' ) {
			return null;
		}
		$v = trim( $v, '<>' );
		$v = strtolower( trim( $v ) );
		return $v !== '' ? $v : null;
	}
}
