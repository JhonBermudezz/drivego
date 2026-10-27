<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Square Checkout API: creates a payment link for the exact amount of a booking and
 * verifies the webhook that tells us it was paid. The access token only ever lives on the server.
 */
class DGB_Square {

	public static function enabled() {
		return '' !== DGB_Settings::get( 'square_token' ) && '' !== DGB_Settings::get( 'square_location' );
	}

	private static function base() {
		return 'production' === DGB_Settings::get( 'square_env' ) ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';
	}

	/** @return array|WP_Error  ['url'=>..., 'order_id'=>...] */
	public static function create_payment_link( $booking_id, $name, $price, $buyer_email, $redirect ) {
		$hst   = (float) DGB_Settings::get( 'hst_percent' );
		$body  = array(
			'idempotency_key' => 'dgb-' . $booking_id . '-' . wp_generate_password( 8, false ),
			'order'           => array(
				'location_id'  => DGB_Settings::get( 'square_location' ),
				'reference_id' => 'booking-' . $booking_id,
				'line_items'   => array( array(
					'name'             => $name,
					'quantity'         => '1',
					'base_price_money' => array( 'amount' => (int) round( $price * 100 ), 'currency' => 'CAD' ),
					'applied_taxes'    => $hst > 0 ? array( array( 'tax_uid' => 'hst' ) ) : array(),
				) ),
				'taxes'        => $hst > 0 ? array( array( 'uid' => 'hst', 'name' => 'HST', 'percentage' => (string) $hst, 'scope' => 'LINE_ITEM' ) ) : array(),
			),
			'checkout_options' => array( 'redirect_url' => $redirect, 'ask_for_shipping_address' => false ),
			'pre_populated_data' => array( 'buyer_email' => $buyer_email ),
		);
		$res = wp_remote_post( self::base() . '/v2/online-checkout/payment-links', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . DGB_Settings::get( 'square_token' ),
				'Content-Type'  => 'application/json',
				'Square-Version'=> '2024-10-17',
			),
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = wp_remote_retrieve_response_code( $res );
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 300 || empty( $json['payment_link']['url'] ) ) {
			$msg = isset( $json['errors'][0]['detail'] ) ? $json['errors'][0]['detail'] : 'Square returned HTTP ' . $code;
			return new WP_Error( 'dgb_square', $msg );
		}
		return array(
			'url'      => $json['payment_link']['url'],
			'order_id' => isset( $json['payment_link']['order_id'] ) ? $json['payment_link']['order_id'] : '',
		);
	}

	/** Ask Square for an order and say whether it is fully paid. @return bool|WP_Error */
	public static function order_is_paid( $order_id ) {
		$res = wp_remote_get( self::base() . '/v2/orders/' . rawurlencode( $order_id ), array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . DGB_Settings::get( 'square_token' ), 'Square-Version' => '2024-10-17' ),
		) );
		if ( is_wp_error( $res ) ) { return $res; }
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || empty( $json['order'] ) ) {
			return new WP_Error( 'dgb_square', isset( $json['errors'][0]['detail'] ) ? $json['errors'][0]['detail'] : 'Square could not find that order.' );
		}
		return self::order_paid( $json['order'] );
	}

	/** Pure: is this Square order paid in full? */
	public static function order_paid( array $o ) {
		if ( isset( $o['state'] ) && 'COMPLETED' === $o['state'] ) { return true; }
		$due = isset( $o['net_amount_due_money']['amount'] ) ? (int) $o['net_amount_due_money']['amount'] : -1;
		return 0 === $due && ! empty( $o['tenders'] );
	}

	/** Verify Square's HMAC-SHA256 webhook signature. */
	public static function valid_signature( $body, $signature, $url ) {
		$key = DGB_Settings::get( 'square_webhook_key' );
		if ( '' === $key || '' === $signature ) { return false; }
		$expected = base64_encode( hash_hmac( 'sha256', $url . $body, $key, true ) );
		return hash_equals( $expected, $signature );
	}
}
