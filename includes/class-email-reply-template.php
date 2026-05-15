<?php
/**
 * Branded HTML/plain wrappers for ticket replies (placeholders, logo, company block).
 *
 * @package Biopentra_Contact_Inbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biopentra_Contact_Inbox_Email_Reply_Template {

	const OPTION_ENABLED       = 'biopentra_inbox_reply_template_enabled';
	const OPTION_LOGO_SOURCE   = 'biopentra_inbox_reply_logo_source';
	const OPTION_LOGO_CUSTOM   = 'biopentra_inbox_reply_logo_custom_url';
	const OPTION_HEADER        = 'biopentra_inbox_reply_header';
	const OPTION_FOOTER        = 'biopentra_inbox_reply_footer';
	const OPTION_COMPANY_SOURCE = 'biopentra_inbox_reply_company_source';
	const OPTION_COMPANY_CUSTOM = 'biopentra_inbox_reply_company_custom';

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		return get_option( self::OPTION_ENABLED, 'yes' ) === 'yes';
	}

	/**
	 * Default header text (with placeholders).
	 *
	 * @return string
	 */
	public static function default_header() {
		return "Hello {customer_name},\n";
	}

	/**
	 * Default footer text (with placeholders).
	 *
	 * @return string
	 */
	public static function default_footer() {
		return "Best regards,\nBiopentra Support\n\nTicket: {ticket_number}";
	}

	/**
	 * @param mixed $value Raw.
	 * @return string yes|no
	 */
	public static function sanitize_enabled( $value ) {
		return ( isset( $value ) && ( $value === 'yes' || $value === true || $value === 1 || $value === '1' ) ) ? 'yes' : 'no';
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_logo_source( $value ) {
		$v = is_string( $value ) ? $value : '';
		$allowed = array( 'site_logo', 'wc_store_logo', 'custom_url', 'none' );
		return in_array( $v, $allowed, true ) ? $v : 'site_logo';
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_company_source( $value ) {
		$v = is_string( $value ) ? $value : '';
		$allowed = array( 'wc_address', 'site_admin', 'custom', 'none' );
		return in_array( $v, $allowed, true ) ? $v : 'wc_address';
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_fragment_html( $value ) {
		return wp_kses_post( is_string( $value ) ? $value : '' );
	}

	/**
	 * Convert kses-sanitized textarea fragment to HTML (paragraphs / line breaks).
	 * Used only for settings-driven blocks, not the full branded document.
	 *
	 * @param string $sanitized Already passed through wp_kses_post().
	 * @return string
	 */
	private static function textarea_fragment_to_html( $sanitized ) {
		$s = trim( (string) $sanitized );
		if ( $s === '' ) {
			return '';
		}
		return wpautop( $s );
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_logo_url( $value ) {
		$url = esc_url_raw( is_string( $value ) ? trim( $value ) : '', array( 'http', 'https' ) );
		return $url !== '' ? $url : '';
	}

	/**
	 * Resolve logo URL for current logo source setting (no output of secrets).
	 *
	 * @return string Absolute URL or empty.
	 */
	public static function resolve_logo_url() {
		$src = (string) get_option( self::OPTION_LOGO_SOURCE, 'site_logo' );
		if ( 'none' === $src ) {
			return '';
		}
		if ( 'custom_url' === $src ) {
			$u = (string) get_option( self::OPTION_LOGO_CUSTOM, '' );
			return $u !== '' ? esc_url_raw( $u, array( 'http', 'https' ) ) : '';
		}
		if ( 'wc_store_logo' === $src ) {
			if ( class_exists( 'WooCommerce', false ) ) {
				$wc_img = (string) get_option( 'woocommerce_email_header_image', '' );
				if ( $wc_img !== '' ) {
					return esc_url_raw( $wc_img, array( 'http', 'https' ) );
				}
			}
			return self::resolve_site_custom_logo_url();
		}
		// site_logo
		return self::resolve_site_custom_logo_url();
	}

	/**
	 * @return string
	 */
	private static function resolve_site_custom_logo_url() {
		$logo_id = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $logo_id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $logo_id, 'full' );
		return is_string( $url ) && $url !== '' ? esc_url_raw( $url, array( 'http', 'https' ) ) : '';
	}

	/**
	 * Plain multiline store address from WooCommerce options (no state decoding).
	 *
	 * @return string
	 */
	public static function woocommerce_store_address_plain() {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return '';
		}
		$parts = array();
		foreach (
			array(
				'woocommerce_store_address',
				'woocommerce_store_address_2',
				'woocommerce_store_city',
				'woocommerce_store_postcode',
			) as $key
		) {
			$line = trim( (string) get_option( $key, '' ) );
			if ( $line !== '' ) {
				$parts[] = $line;
			}
		}
		$country = trim( (string) get_option( 'woocommerce_default_country', '' ) );
		if ( $country !== '' ) {
			$parts[] = $country;
		}
		return implode( "\n", $parts );
	}

	/**
	 * Turn plain multiline address into minimal safe HTML.
	 *
	 * @param string $plain Plain text.
	 * @return string
	 */
	public static function format_plain_address_as_html( $plain ) {
		$plain = trim( (string) $plain );
		if ( $plain === '' ) {
			return '';
		}
		return nl2br( esc_html( $plain ), false );
	}

	/**
	 * HTML escaped company lines for WC address.
	 *
	 * @return string
	 */
	public static function woocommerce_store_address_html() {
		$plain = self::woocommerce_store_address_plain();
		if ( $plain === '' ) {
			return '';
		}
		$lines = array_map( 'trim', explode( "\n", $plain ) );
		$out   = '';
		foreach ( $lines as $line ) {
			if ( $line !== '' ) {
				$out .= '<p style="margin:0 0 4px;">' . esc_html( $line ) . '</p>';
			}
		}
		return $out;
	}

	/**
	 * Company block HTML from source setting.
	 *
	 * @return string
	 */
	public static function resolve_company_html() {
		$src = (string) get_option( self::OPTION_COMPANY_SOURCE, 'wc_address' );
		if ( 'none' === $src ) {
			return '';
		}
		if ( 'custom' === $src ) {
			return self::textarea_fragment_to_html( wp_kses_post( (string) get_option( self::OPTION_COMPANY_CUSTOM, '' ) ) );
		}
		if ( 'site_admin' === $src ) {
			$name = get_bloginfo( 'name', 'display' );
			$url  = home_url( '/' );
			$mail = (string) get_option( 'admin_email', '' );
			$html = '<p style="margin:0 0 4px;">' . esc_html( $name ) . '</p>';
			$html .= '<p style="margin:0 0 4px;"><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>';
			if ( is_email( $mail ) ) {
				$html .= '<p style="margin:0;">' . esc_html( $mail ) . '</p>';
			}
			return $html;
		}
		// wc_address
		$wc = self::woocommerce_store_address_html();
		if ( $wc !== '' ) {
			return $wc;
		}
		$name = get_bloginfo( 'name', 'display' );
		$url  = home_url( '/' );
		$html = '<p style="margin:0 0 4px;">' . esc_html( $name ) . '</p>';
		$html .= '<p style="margin:0;"><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>';
		return $html;
	}

	/**
	 * Company block plain text.
	 *
	 * @return string
	 */
	public static function resolve_company_plain() {
		$src = (string) get_option( self::OPTION_COMPANY_SOURCE, 'wc_address' );
		if ( 'none' === $src ) {
			return '';
		}
		if ( 'custom' === $src ) {
			return trim( wp_strip_all_tags( (string) get_option( self::OPTION_COMPANY_CUSTOM, '' ) ) );
		}
		if ( 'site_admin' === $src ) {
			$name = get_bloginfo( 'name', 'display' );
			$url  = home_url( '/' );
			$mail = (string) get_option( 'admin_email', '' );
			$lines = array_filter( array( $name, $url, is_email( $mail ) ? $mail : '' ) );
			return implode( "\n", $lines );
		}
		$wc = self::woocommerce_store_address_plain();
		if ( $wc !== '' ) {
			return $wc;
		}
		return trim( get_bloginfo( 'name', 'display' ) . "\n" . home_url( '/' ) );
	}

	/**
	 * Build placeholder map for a ticket (HTML-oriented values except company_logo handled per mode).
	 *
	 * @param object $ticket Ticket row.
	 * @param string $to     Recipient email (sanitized).
	 * @param bool   $for_html Whether company_logo should be an img tag.
	 * @return array<string, string>
	 */
	public static function build_placeholder_map( $ticket, $to, $for_html ) {
		$site_name = get_bloginfo( 'name', 'display' );
		$site_url  = home_url( '/' );
		$cust_name = isset( $ticket->customer_name ) && is_string( $ticket->customer_name ) ? trim( $ticket->customer_name ) : '';
		$cust_mail = isset( $ticket->customer_email ) ? sanitize_email( (string) $ticket->customer_email ) : '';
		if ( ! is_email( $cust_mail ) ) {
			$cust_mail = $to;
		}
		$tn = isset( $ticket->ticket_number ) && (int) $ticket->ticket_number > 0 ? (int) $ticket->ticket_number : (int) $ticket->id;
		$tag = Biopentra_Contact_Inbox_Ticket_Ref::bracket_tag( $tn );

		$support = sanitize_email( get_option( 'biopentra_inbox_from_email', get_option( 'admin_email' ) ) );
		if ( ! is_email( $support ) ) {
			$support = '';
		}

		$logo_url = self::resolve_logo_url();
		if ( $for_html && $logo_url !== '' ) {
			$company_logo = '<p style="margin:0 0 20px;"><img src="' . esc_url( $logo_url ) . '" alt="" width="180" style="max-width:180px;height:auto;display:block;" /></p>';
		} else {
			$company_logo = '';
		}

		$addr_plain = self::woocommerce_store_address_plain();
		$addr_disp  = $for_html ? self::format_plain_address_as_html( $addr_plain ) : $addr_plain;

		return array(
			'{site_name}'       => $for_html ? esc_html( $site_name ) : $site_name,
			'{site_url}'        => $for_html ? esc_html( $site_url ) : $site_url,
			'{customer_name}'   => $for_html ? esc_html( $cust_name ) : $cust_name,
			'{customer_email}'  => $for_html ? esc_html( $cust_mail ) : $cust_mail,
			'{ticket_number}'   => $for_html ? esc_html( $tag ) : $tag,
			'{support_email}'   => $for_html ? esc_html( $support ) : $support,
			'{company_logo}'    => $company_logo,
			'{store_name}'      => $for_html ? esc_html( $site_name ) : $site_name,
			'{store_address}'   => $addr_disp,
		);
	}

	/**
	 * Replace known placeholders in template string (longest keys first).
	 *
	 * @param string               $template With {placeholders}.
	 * @param array<string, string> $map      Replacement map.
	 * @return string
	 */
	public static function replace_placeholders( $template, array $map ) {
		$s = is_string( $template ) ? $template : '';
		$keys = array_keys( $map );
		usort(
			$keys,
			static function ( $a, $b ) {
				return strlen( (string) $b ) <=> strlen( (string) $a );
			}
		);
		foreach ( $keys as $k ) {
			$s = str_replace( $k, isset( $map[ $k ] ) ? (string) $map[ $k ] : '', $s );
		}
		return $s;
	}

	/**
	 * Build branded HTML and plain bodies for a ticket reply.
	 *
	 * @param object $ticket      Ticket row.
	 * @param string $to          Recipient.
	 * @param string $reply_html  Staff reply (already wp_kses_post).
	 * @param string $quote_html  Prior conversation HTML.
	 * @param string $quote_plain Prior conversation plain.
	 * @return array{html: string, plain: string}
	 */
	public static function build_ticket_bodies( $ticket, $to, $reply_html, $quote_html, $quote_plain ) {
		$header_raw  = (string) get_option( self::OPTION_HEADER, self::default_header() );
		$footer_raw  = (string) get_option( self::OPTION_FOOTER, self::default_footer() );
		if ( trim( $header_raw ) === '' ) {
			$header_raw = self::default_header();
		}
		if ( trim( $footer_raw ) === '' ) {
			$footer_raw = self::default_footer();
		}

		$map_html = self::build_placeholder_map( $ticket, $to, true );
		$header_h = self::textarea_fragment_to_html( wp_kses_post( self::replace_placeholders( $header_raw, $map_html ) ) );
		$footer_h = self::textarea_fragment_to_html( wp_kses_post( self::replace_placeholders( $footer_raw, $map_html ) ) );

		$map_plain = self::build_placeholder_map( $ticket, $to, false );
		$header_p  = trim( wp_strip_all_tags( self::replace_placeholders( $header_raw, $map_plain ) ) );
		$footer_p  = trim( wp_strip_all_tags( self::replace_placeholders( $footer_raw, $map_plain ) ) );

		$company_h = self::resolve_company_html();
		$company_p = self::resolve_company_plain();

		$staff_block_h = wpautop( $reply_html );
		$staff_block_p = trim( wp_strip_all_tags( $reply_html ) );

		$logo_block = $map_html['{company_logo}'];

		$font = '-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif';
		$html  = '<div style="font-family:' . $font . ';font-size:15px;line-height:1.55;color:#1d2327;">';
		$html .= $logo_block;
		if ( $header_h !== '' ) {
			$html .= '<div class="bsd-reply-template-header" style="margin:0 0 16px;">' . $header_h . '</div>';
		}
		$html .= '<div class="bsd-inbox-mail-reply" style="margin:0 0 20px;">' . $staff_block_h . '</div>';
		if ( $footer_h !== '' ) {
			$html .= '<div class="bsd-reply-template-footer" style="margin:0 0 16px;">' . $footer_h . '</div>';
		}
		if ( $company_h !== '' ) {
			$html .= '<div class="bsd-reply-template-company" style="margin:0 0 24px;font-size:13px;color:#646970;">' . $company_h . '</div>';
		}
		if ( $quote_html !== '' ) {
			$html .= $quote_html;
		}
		$html .= '</div>';

		$plain_parts = array_filter( array( $header_p, $staff_block_p, $footer_p, $company_p ) );
		$plain       = implode( "\n\n", $plain_parts );
		if ( $quote_plain !== '' ) {
			$plain .= "\n\n" . '---' . "\n" . $quote_plain;
		}

		return array(
			'html'  => $html,
			'plain' => $plain,
		);
	}

	/**
	 * Sample ticket object for preview (saved settings only).
	 *
	 * @return object
	 */
	public static function preview_sample_ticket() {
		return (object) array(
			'id'             => 123,
			'ticket_number'  => 123,
			'customer_name'  => __( 'Customer', 'biopentra-contact-inbox' ),
			'customer_email' => 'customer@example.com',
		);
	}

	/**
	 * Output full HTML document for preview (exit after).
	 */
	public static function output_preview_document() {
		if ( ! current_user_can( BIOPENTRA_INBOX_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to preview this template.', 'biopentra-contact-inbox' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'biopentra_inbox_preview_reply_template' );

		nocache_headers();
		$ticket = self::preview_sample_ticket();
		$to     = 'customer@example.com';

		$quote_html  = '<div style="margin-top:28px;padding-top:20px;border-top:1px solid #dcdcde;"><p style="margin:0 0 12px;font-size:12px;color:#646970;">' . esc_html__( 'Recent conversation', 'biopentra-contact-inbox' ) . '</p><p style="color:#646970;font-size:13px;">' . esc_html__( '(Sample quoted thread would appear here.)', 'biopentra-contact-inbox' ) . '</p></div>';
		$quote_plain = __( 'Recent conversation', 'biopentra-contact-inbox' ) . "\n" . __( '(Sample quoted thread would appear here.)', 'biopentra-contact-inbox' );

		$sample_reply = '<p>' . esc_html__( 'This is a sample staff reply. Operators write only this part; the header, footer, and branding wrap it automatically.', 'biopentra-contact-inbox' ) . '</p>';

		$bodies = self::build_ticket_bodies( $ticket, $to, $sample_reply, $quote_html, $quote_plain );

		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />';
		echo '<title>' . esc_html__( 'Reply template preview', 'biopentra-contact-inbox' ) . '</title></head><body style="margin:0;padding:24px;background:#f0f0f1;">';
		echo '<div style="max-width:640px;margin:0 auto;background:#fff;padding:24px;border:1px solid #c3c4c7;">';
		echo '<p style="margin:0 0 16px;font-size:13px;color:#646970;">' . esc_html__( 'Preview only — no email was sent. Values use saved template settings and sample data.', 'biopentra-contact-inbox' ) . '</p>';
		echo $bodies['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
		echo '<hr style="margin:24px 0;border:none;border-top:1px solid #dcdcde;" />';
		echo '<p style="margin:0 0 8px;font-size:12px;color:#646970;text-transform:uppercase;">' . esc_html__( 'Plain-text alternative (excerpt)', 'biopentra-contact-inbox' ) . '</p>';
		echo '<pre style="white-space:pre-wrap;font-size:13px;background:#f6f7f7;padding:12px;border:1px solid #dcdcde;">' . esc_html( $bodies['plain'] ) . '</pre>';
		echo '</div></body></html>';
		exit;
	}
}
