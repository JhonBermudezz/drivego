<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DGB_Rest {
	const NS = 'drivego/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route( self::NS, '/days', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'days' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/slots', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'slots' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/book', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'book' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/square-webhook', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'webhook' ), 'permission_callback' => '__return_true' ) );
	}

	private static function minutes_for( $service, $option ) {
		$services = DGB_Settings::get( 'services' );
		if ( ! isset( $services[ $service ]['options'][ $option ] ) ) { return 0; }
		return (int) $services[ $service ]['options'][ $option ]['minutes'];
	}

	/** Which days of a month still have at least one free slot. */
	public static function days( WP_REST_Request $r ) {
		$minutes = self::minutes_for( sanitize_key( $r['service'] ), (int) $r['option'] );
		$month   = (string) $r['month'];
		if ( ! $minutes || ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) { return new WP_Error( 'dgb_bad', 'Bad request.', array( 'status' => 400 ) ); }
		$n = (int) gmdate( 't', strtotime( $month . '-01 UTC' ) );
		$out = array();
		for ( $d = 1; $d <= $n; $d++ ) {
			$date = sprintf( '%s-%02d', $month, $d );
			if ( DGB_Bookings::free_slots( $date, $minutes ) ) { $out[] = $date; }
		}
		return array( 'days' => $out );
	}

	public static function slots( WP_REST_Request $r ) {
		$minutes = self::minutes_for( sanitize_key( $r['service'] ), (int) $r['option'] );
		$date    = (string) $r['date'];
		if ( ! $minutes || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { return new WP_Error( 'dgb_bad', 'Bad request.', array( 'status' => 400 ) ); }
		return array( 'slots' => DGB_Bookings::free_slots( $date, $minutes ) );
	}

	public static function book( WP_REST_Request $r ) {
		$p = $r->get_json_params();
		if ( ! empty( $p['website'] ) ) { return array( 'ok' => true ); } // honeypot
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'x';
		$key = 'dgb_rate_' . md5( $ip );
		$cnt = (int) get_transient( $key );
		if ( $cnt >= 5 ) { return new WP_Error( 'dgb_rate', 'Too many requests. Please call us instead.', array( 'status' => 429 ) ); }
		set_transient( $key, $cnt + 1, HOUR_IN_SECONDS );

		$d = array(
			'service' => isset( $p['service'] ) ? sanitize_key( $p['service'] ) : '',
			'option'  => isset( $p['option'] ) ? (int) $p['option'] : -1,
			'date'    => isset( $p['date'] ) ? sanitize_text_field( $p['date'] ) : '',
			'start'   => isset( $p['start'] ) ? (int) $p['start'] : -1,
			'name'    => isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '',
			'email'   => isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '',
			'phone'   => isset( $p['phone'] ) ? sanitize_text_field( $p['phone'] ) : '',
			'licence' => isset( $p['licence'] ) ? sanitize_text_field( $p['licence'] ) : '',
			'lang'    => isset( $p['lang'] ) ? sanitize_text_field( $p['lang'] ) : '',
			'meet'    => isset( $p['meet'] ) ? sanitize_text_field( $p['meet'] ) : '',
			'centre'  => isset( $p['centre'] ) ? sanitize_text_field( $p['centre'] ) : '',
			'address' => isset( $p['address'] ) ? sanitize_text_field( $p['address'] ) : '',
			'pay'     => isset( $p['pay'] ) ? sanitize_key( $p['pay'] ) : '',
			'notes'   => isset( $p['notes'] ) ? sanitize_textarea_field( $p['notes'] ) : '',
		);
		if ( strlen( $d['name'] ) < 2 || ! is_email( $d['email'] ) || strlen( preg_replace( '/\D/', '', $d['phone'] ) ) < 10 || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['date'] ) ) {
			return new WP_Error( 'dgb_invalid', 'Please check your name, email and phone number.', array( 'status' => 400 ) );
		}
		$methods = DGB_Settings::pay_methods();
		if ( 'roadtest' === $d['service'] ) { unset( $methods['cash'] ); }
		if ( ! isset( $methods[ $d['pay'] ] ) ) { $d['pay'] = isset( $methods['square'] ) ? 'square' : 'etransfer'; }
		$id = DGB_Bookings::create( $d );
		if ( is_wp_error( $id ) ) { return $id; }
		DGB_Google::push( $id );

		// Payment: Square API when connected, otherwise the static checkout link set for that option.
		$services = DGB_Settings::get( 'services' );
		$opt      = $services[ $d['service'] ]['options'][ $d['option'] ];
		$pay_url  = '';
		$total = (float) get_post_meta( $id, '_dgb_price', true );
		if ( 'square' === $d['pay'] && DGB_Square::enabled() && $total > 0 ) {
			$label = $services[ $d['service'] ]['name'] . ' · ' . $opt['label'] . ( (float) get_post_meta( $id, '_dgb_pickup_extra', true ) > 0 ? ' + home pick up' : '' );
			$res = DGB_Square::create_payment_link( $id, $label, $total, $d['email'], home_url( '/' ) );
			if ( is_wp_error( $res ) ) {
				update_post_meta( $id, '_dgb_square_error', $res->get_error_message() );
			} else {
				$pay_url = $res['url'];
				update_post_meta( $id, '_dgb_square_order', $res['order_id'] );
			}
		}
		if ( 'square' === $d['pay'] && ! $pay_url && ! empty( $opt['pay'] ) ) { $pay_url = $opt['pay']; }
		if ( $pay_url ) { update_post_meta( $id, '_dgb_pay_url', $pay_url ); }
		DGB_Bookings::notify_new( $id, $pay_url );
		return array( 'ok' => true, 'id' => $id, 'pay_url' => $pay_url, 'pay' => $d['pay'], 'etransfer' => DGB_Settings::etransfer_email() );
	}

	/** Square tells us when an order is paid. */
	public static function webhook( WP_REST_Request $r ) {
		$body = $r->get_body();
		$sig  = $r->get_header( 'x_square_hmacsha256_signature' );
		$url  = rest_url( self::NS . '/square-webhook' );
		if ( ! DGB_Square::valid_signature( $body, (string) $sig, $url ) ) { return new WP_Error( 'dgb_sig', 'Invalid signature.', array( 'status' => 403 ) ); }
		$e = json_decode( $body, true );
		$payment = isset( $e['data']['object']['payment'] ) ? $e['data']['object']['payment'] : array();
		if ( ! empty( $payment['order_id'] ) && isset( $payment['status'] ) && 'COMPLETED' === $payment['status'] ) {
			$q = get_posts( array( 'post_type' => DGB_Bookings::CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_dgb_square_order', 'meta_value' => $payment['order_id'] ) );
			if ( $q ) { update_post_meta( $q[0], '_dgb_payment', 'paid' ); }
		}
		return array( 'ok' => true );
	}
}
