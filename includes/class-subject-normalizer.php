<?php
/**
 * Normalize email subjects for threading heuristics.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Subject_Normalizer {

	/**
	 * @param string $subject Raw subject.
	 * @return string
	 */
	public static function normalize( $subject ) {
		if ( ! is_string( $subject ) ) {
			return '';
		}
		$s = trim( $subject );
		$s = Biopentra_Contact_Inbox_Ticket_Ref::strip_tag_markers( $s );
		$re = '/^\s*(re|fwd)\s*:\s*/iu';
		for ( $i = 0; $i < 10; $i++ ) {
			$next = preg_replace( $re, '', $s );
			if ( $next === $s ) {
				break;
			}
			$s = trim( $next );
		}
		$s = preg_replace( '/\s+/u', ' ', $s );
		return is_string( $s ) ? trim( $s ) : '';
	}
}
