<?php
if ( ! defined( 'ABSPATH' ) && ! defined( 'DGB_TESTING' ) ) { exit; }

/**
 * Elementor's atomic forms only send plain "Label: value" lines. This turns those lines into the
 * DriveGo email card (HTML) before the message goes out, so it reads well in Gmail and Outlook.
 */
class DGB_Mail {

	public static function init() {
		add_filter( 'wp_mail', array( __CLASS__, 'style_form_email' ), 20 );
	}

	/** Parse "Label: value" lines. Lines without a label continue the previous value (multi line messages). */
	public static function parse_lines( $text ) {
		$text  = str_replace( array( '<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>' ), "\n", (string) $text );
		$text  = html_entity_decode( wp_strip_all_tags_safe( $text ), ENT_QUOTES, 'UTF-8' );
		$rows  = array();
		foreach ( preg_split( '/\R/', trim( $text ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) { continue; }
			if ( preg_match( '/^dgb_\w+\s*:/i', $line ) ) { continue; } // spam-protection helper fields
			if ( preg_match( '/^([^:]{1,80}):\s*(.*)$/u', $line, $m ) && ( ! $rows || ! self::looks_like_url_or_time( $m[1] ) ) ) {
				$rows[] = array( trim( $m[1] ), trim( $m[2] ) );
			} elseif ( $rows ) {
				$rows[ count( $rows ) - 1 ][1] .= "\n" . $line;
			}
		}
		return $rows;
	}

	private static function looks_like_url_or_time( $label ) {
		return (bool) preg_match( '/^(https?|mailto|tel)$/i', $label ) || preg_match( '/^\d{1,2}$/', $label );
	}

	/** Friendly label for the fields on our forms, whatever Elementor called them. */
	public static function nice_label( $label, $value, $i ) {
		$l = strtolower( $label );
		if ( false !== strpos( $l, 'select' ) || false !== strpos( $l, 'interested' ) || false !== strpos( $l, 'topic' ) ) { return 'Interested in'; }
		if ( is_email( $value ) ) { return 'Email'; }
		if ( preg_match( '/^[\d\s().+\-]{7,}$/', $value ) ) { return 'Phone'; }
		if ( false !== strpos( $l, 'name' ) || 0 === $i ) { return 'Name'; }
		if ( false !== strpos( $l, 'message' ) || false !== strpos( $l, 'tell us' ) ) { return 'Message'; }
		return $label;
	}

	public static function build_html( array $rows, $phone ) {
		$tr = '';
		$n  = count( $rows );
		foreach ( $rows as $i => $r ) {
			$label = self::nice_label( $r[0], $r[1], $i );
			$last  = ( $i === $n - 1 );
			$val   = nl2br( esc_html( $r[1] ) );
			if ( 'Email' === $label ) { $val = '<a href="mailto:' . esc_attr( $r[1] ) . '" style="color:#111">' . esc_html( $r[1] ) . '</a>'; }
			if ( 'Phone' === $label ) { $val = '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $r[1] ) ) . '" style="color:#111">' . esc_html( $r[1] ) . '</a>'; }
			if ( 'Name' === $label ) { $val = '<strong>' . $val . '</strong>'; }
			$b = $last ? '' : 'border-bottom:1px solid #eef3e6;';
			$tr .= '<tr><td style="padding:14px 24px;' . $b . 'width:130px;color:#5b5d57;vertical-align:top">' . esc_html( $label ) . '</td><td style="padding:14px 24px;' . $b . 'line-height:1.5;vertical-align:top">' . $val . '</td></tr>';
		}
		return '<!doctype html><html><body style="margin:0;padding:24px;background:#f4f6f0">'
			. '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;background:#fff;border:1px solid #dfe6d4;border-radius:12px;overflow:hidden">'
			. '<div style="background:#111;padding:20px 24px"><span style="color:#a2f933;font-size:20px;font-weight:800;letter-spacing:.5px">DRIVEGO LONDON</span><br><span style="color:#fff;font-size:14px">New message from the website</span></div>'
			. '<table style="width:100%;border-collapse:collapse;font-size:15px;color:#111" cellpadding="0" cellspacing="0">' . $tr . '</table>'
			. '<div style="background:#eef8e2;padding:14px 24px;font-size:13px;color:#5b5d57">Reply to this email to answer the sender' . ( $phone ? ', or call ' . esc_html( $phone ) : '' ) . '.</div>'
			. '</div></body></html>';
	}

	/** Confirmation sent to the customer: plain paragraphs from Elementor become a branded card. */
	public static function build_confirmation( $text, $phone ) {
		$text  = str_replace( array( '<br>', '<br/>', '<br />', '</p>', '</div>' ), "\n", (string) $text );
		$text  = html_entity_decode( wp_strip_all_tags_safe( $text ), ENT_QUOTES, 'UTF-8' );
		$paras = preg_split( '/\R{2,}/', trim( $text ) );
		$body  = '';
		foreach ( $paras as $i => $p ) {
			$p = trim( $p );
			if ( '' === $p ) { continue; }
			$style = 0 === $i ? 'font-size:18px;font-weight:700;margin:0 0 14px' : 'margin:0 0 14px;line-height:1.6';
			$body .= '<p style="' . $style . '">' . nl2br( esc_html( $p ) ) . '</p>';
		}
		$digits = preg_replace( '/\D/', '', $phone );
		return '<!doctype html><html><body style="margin:0;padding:24px;background:#f4f6f0">'
			. '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;background:#fff;border:1px solid #dfe6d4;border-radius:12px;overflow:hidden;color:#111">'
			. '<div style="background:#111;padding:20px 24px"><span style="color:#a2f933;font-size:20px;font-weight:800;letter-spacing:.5px">DRIVEGO LONDON</span><br><span style="color:#fff;font-size:14px">MTO approved driving school · London, Ontario</span></div>'
			. '<div style="padding:24px;font-size:15px">' . $body
			. '<p style="margin:20px 0 0"><a href="https://wa.me/1' . esc_attr( ltrim( $digits, '1' ) ) . '" style="display:inline-block;background:#a2f933;color:#000;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:999px">Message us on WhatsApp</a></p></div>'
			. '<div style="background:#eef8e2;padding:14px 24px;font-size:13px;color:#5b5d57">' . ( $phone ? 'Call ' . esc_html( $phone ) . ' · ' : '' ) . 'Weekdays and weekends, by appointment</div>'
			. '</div></body></html>';
	}

	public static function style_form_email( $args ) {
		if ( ! empty( $args['subject'] ) && 0 === strpos( $args['subject'], 'We received your message' ) ) {
			$headers = isset( $args['headers'] ) ? $args['headers'] : array();
			if ( is_string( $headers ) ) { $headers = preg_split( '/\R/', $headers, -1, PREG_SPLIT_NO_EMPTY ); }
			$headers = array_values( array_filter( (array) $headers, function ( $h ) { return 0 !== stripos( $h, 'content-type:' ); } ) );
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$args['headers'] = $headers;
			$args['message'] = self::build_confirmation( is_array( $args['message'] ) ? implode( "\n", $args['message'] ) : $args['message'], DGB_Settings::get( 'phone' ) );
			return $args;
		}
		if ( empty( $args['subject'] ) || 0 !== strpos( $args['subject'], 'New message from' ) ) { return $args; }
		$rows = self::parse_lines( is_array( $args['message'] ) ? implode( "\n", $args['message'] ) : $args['message'] );
		if ( count( $rows ) < 2 ) { return $args; }
		$headers = isset( $args['headers'] ) ? $args['headers'] : array();
		if ( is_string( $headers ) ) { $headers = preg_split( '/\R/', $headers, -1, PREG_SPLIT_NO_EMPTY ); }
		$headers = array_values( array_filter( (array) $headers, function ( $h ) { return 0 !== stripos( $h, 'content-type:' ); } ) );
		$reply = '';
		foreach ( $rows as $r ) { if ( is_email( $r[1] ) ) { $reply = $r[1]; break; } }
		if ( $reply && ! preg_grep( '/^reply-to:/i', $headers ) ) { $headers[] = 'Reply-To: ' . $reply; }
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$args['headers'] = $headers;
		$args['message'] = self::build_html( $rows, DGB_Settings::get( 'phone' ) );
		return $args;
	}
}

if ( ! function_exists( 'wp_strip_all_tags_safe' ) ) {
	function wp_strip_all_tags_safe( $s ) { return function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $s ) : strip_tags( $s ); }
}
