<?php
/**
 * Public ticket reference: default subject tag [BP-1234], parsing, subject prepending.
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Ticket_Ref {

	/** Default bracket tag prefix (immutable display convention). */
	const TAG_PREFIX = 'BP';

	/**
	 * Bracket tag only, e.g. [BP-1234].
	 *
	 * @param int $ticket_number Stored ticket_number (equals id by default).
	 * @return string
	 */
	public static function bracket_tag( $ticket_number ) {
		$n = (int) $ticket_number;
		if ( $n <= 0 ) {
			return '';
		}
		return '[' . self::TAG_PREFIX . '-' . (string) $n . ']';
	}

	/**
	 * Whether the subject already contains a BP ticket tag or legacy Biopentra tag (do not double-wrap).
	 *
	 * @param string $subject Subject line.
	 * @return bool
	 */
	public static function subject_already_tagged( $subject ) {
		if ( ! is_string( $subject ) || $subject === '' ) {
			return false;
		}
		if ( preg_match( '/\[\s*' . preg_quote( self::TAG_PREFIX, '/' ) . '\s*-\s*\d+\s*\]/i', $subject ) ) {
			return true;
		}
		if ( preg_match( '/\[\s*Biopentra\s*#\s*\d+\s*\]/i', $subject ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Prepend default [BP-n] when missing; leave Re:/[BP-n]/… unchanged.
	 *
	 * @param string $subject     Raw subject.
	 * @param int    $ticket_number Ticket row ticket_number.
	 * @return string
	 */
	public static function format_subject( $subject, $ticket_number ) {
		$s = is_string( $subject ) ? trim( $subject ) : '';
		$n = (int) $ticket_number;
		if ( $n <= 0 ) {
			return $s;
		}
		if ( self::subject_already_tagged( $s ) ) {
			return $s;
		}
		$tag = self::bracket_tag( $n );
		if ( $tag === '' ) {
			return $s;
		}
		return $s === '' ? $tag : $tag . ' ' . $s;
	}

	/**
	 * Remove leading ticket tag markers for subject threading (BP and legacy Biopentra).
	 *
	 * @param string $subject Raw subject.
	 * @return string
	 */
	public static function strip_tag_markers( $subject ) {
		if ( ! is_string( $subject ) ) {
			return '';
		}
		$s = $subject;
		for ( $i = 0; $i < 15; $i++ ) {
			$next = preg_replace(
				'/^\s*(?:\[\s*' . preg_quote( self::TAG_PREFIX, '/' ) . '\s*-\s*\d+\s*\]|\[\s*Biopentra\s*#\s*\d+\s*\])\s*/iu',
				'',
				$s
			);
			if ( ! is_string( $next ) || $next === $s ) {
				break;
			}
			$s = $next;
		}
		return trim( $s );
	}

	/**
	 * Extract ticket numbers from free text (order preserved, unique). Parses [BP-n], BP-n, [Biopentra #n].
	 *
	 * @param string $text Haystack.
	 * @return array<int, int> List of positive ticket numbers.
	 */
	public static function parse_ticket_numbers( $text ) {
		if ( ! is_string( $text ) || $text === '' ) {
			return array();
		}
		$found = array();
		$nums  = array();
		if ( preg_match_all( '/\[\s*' . preg_quote( self::TAG_PREFIX, '/' ) . '\s*-\s*(\d+)\s*\]/iu', $text, $m1 ) ) {
			foreach ( $m1[1] as $d ) {
				$nums[] = (int) $d;
			}
		}
		if ( preg_match_all( '/\[\s*Biopentra\s*#\s*(\d+)\s*\]/iu', $text, $m2 ) ) {
			foreach ( $m2[1] as $d ) {
				$nums[] = (int) $d;
			}
		}
		if ( preg_match_all( '/(?<![\w-])' . preg_quote( self::TAG_PREFIX, '/' ) . '\s*-\s*(\d+)\b/iu', $text, $m3 ) ) {
			foreach ( $m3[1] as $d ) {
				$nums[] = (int) $d;
			}
		}
		foreach ( $nums as $n ) {
			if ( $n > 0 && ! in_array( $n, $found, true ) ) {
				$found[] = $n;
			}
		}
		return $found;
	}
}
