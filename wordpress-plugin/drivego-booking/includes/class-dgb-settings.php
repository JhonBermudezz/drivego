<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DGB_Settings {
	const OPTION = 'drivego_booking_settings';

	public static function defaults() {
		$opts = array(
			array( 'label' => '1 hour', 'minutes' => 60, 'price' => 65, 'pickup' => 0, 'pay' => '' ),
			array( 'label' => '1.5 hours', 'minutes' => 90, 'price' => 95, 'pickup' => 0, 'pay' => '' ),
			array( 'label' => '2 hours', 'minutes' => 120, 'price' => 125, 'pickup' => 0, 'pay' => '' ),
		);
		return array(
			'notify_email'   => get_option( 'admin_email' ),
			'from_name'      => 'Drive Go London',
			'phone'          => '(519) 694 3762',
			'hours'          => array(
				0 => array( '09:00', '16:00' ), 1 => array( '09:00', '18:00' ), 2 => array( '09:00', '18:00' ),
				3 => array( '09:00', '18:00' ), 4 => array( '09:00', '18:00' ), 5 => array( '09:00', '18:00' ),
				6 => array( '09:00', '16:00' ),
			),
			'slot_minutes'   => 30,
			'buffer_minutes' => 30,
			'capacity'       => 1,
			'lead_days'      => 1,
			'lead_hours'     => 0,
			'horizon_days'   => 60,
			'closed_dates'   => array(),
			'hst_percent'    => 13,
			'square_env'     => 'sandbox',
			'square_token'   => '',
			'square_location'=> '',
			'square_webhook_key' => '',
			'services'       => array(
				'lessons'   => array( 'name' => 'Single lesson', 'when' => 'Lesson day and start time', 'options' => $opts ),
				'refresher' => array( 'name' => 'G-Full & G2 Refresher', 'when' => 'Lesson day and start time', 'options' => $opts ),
				'roadtest'  => array( 'name' => 'Road test package', 'when' => 'Your DriveTest date and test time', 'options' => array(
					array( 'label' => 'G2 road test', 'minutes' => 120, 'price' => 189, 'pickup' => 0, 'pay' => '' ),
					array( 'label' => 'Full G road test', 'minutes' => 120, 'price' => 209, 'pickup' => 0, 'pay' => '' ),
				) ),
			),
		);
	}

	public static function ensure_defaults() {
		if ( false === get_option( self::OPTION ) ) { add_option( self::OPTION, self::defaults(), '', 'no' ); }
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		$d     = self::defaults();
		$s     = is_array( $saved ) ? array_replace( $d, $saved ) : $d;
		$s['hours'] = is_array( $s['hours'] ) ? $s['hours'] : $d['hours'];
		return $s;
	}

	public static function get( $key ) {
		$s = self::all();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	/** Services without secrets, for the front end. */
	public static function public_services() {
		$out = array();
		foreach ( self::get( 'services' ) as $key => $svc ) {
			$opts = array();
			foreach ( $svc['options'] as $o ) {
				if ( '' === trim( $o['label'] ) ) { continue; }
				$opts[] = array( 'label' => $o['label'], 'minutes' => (int) $o['minutes'], 'price' => (float) $o['price'], 'pickup' => isset( $o['pickup'] ) ? (float) $o['pickup'] : 0 );
			}
			$out[ $key ] = array( 'name' => $svc['name'], 'when' => $svc['when'], 'options' => $opts );
		}
		return $out;
	}

	public static function sanitize( $in ) {
		$d   = self::defaults();
		$old = self::all();
		$out = $old;
		$out['notify_email']   = sanitize_email( isset( $in['notify_email'] ) ? $in['notify_email'] : $old['notify_email'] );
		$out['from_name']      = sanitize_text_field( isset( $in['from_name'] ) ? $in['from_name'] : $old['from_name'] );
		$out['phone']          = sanitize_text_field( isset( $in['phone'] ) ? $in['phone'] : $old['phone'] );
		foreach ( array( 'slot_minutes' => array( 5, 120 ), 'buffer_minutes' => array( 0, 240 ), 'capacity' => array( 1, 20 ), 'lead_days' => array( 0, 60 ), 'lead_hours' => array( 0, 72 ), 'horizon_days' => array( 1, 365 ), 'hst_percent' => array( 0, 30 ) ) as $k => $r ) {
			if ( isset( $in[ $k ] ) ) { $out[ $k ] = max( $r[0], min( $r[1], (int) $in[ $k ] ) ); }
		}
		if ( isset( $in['hours'] ) && is_array( $in['hours'] ) ) {
			for ( $i = 0; $i < 7; $i++ ) {
				$o = isset( $in['hours'][ $i ][0] ) ? $in['hours'][ $i ][0] : '';
				$c = isset( $in['hours'][ $i ][1] ) ? $in['hours'][ $i ][1] : '';
				$ok = preg_match( '/^\d{2}:\d{2}$/', $o ) && preg_match( '/^\d{2}:\d{2}$/', $c );
				$out['hours'][ $i ] = $ok ? array( $o, $c ) : array( '', '' );
			}
		}
		if ( isset( $in['closed_dates'] ) ) {
			$dates = preg_split( '/[\s,]+/', (string) $in['closed_dates'], -1, PREG_SPLIT_NO_EMPTY );
			$out['closed_dates'] = array_values( array_filter( $dates, function ( $x ) { return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $x ); } ) );
		}
		$out['square_env']      = ( isset( $in['square_env'] ) && 'production' === $in['square_env'] ) ? 'production' : 'sandbox';
		if ( isset( $in['square_token'] ) && '' !== trim( $in['square_token'] ) ) { $out['square_token'] = sanitize_text_field( $in['square_token'] ); }
		if ( ! empty( $in['square_token_clear'] ) ) { $out['square_token'] = ''; }
		$out['square_location'] = isset( $in['square_location'] ) ? sanitize_text_field( $in['square_location'] ) : $old['square_location'];
		if ( isset( $in['square_webhook_key'] ) && '' !== trim( $in['square_webhook_key'] ) ) { $out['square_webhook_key'] = sanitize_text_field( $in['square_webhook_key'] ); }
		if ( isset( $in['services'] ) && is_array( $in['services'] ) ) {
			foreach ( array_keys( $d['services'] ) as $key ) {
				if ( ! isset( $in['services'][ $key ] ) ) { continue; }
				$svc = $in['services'][ $key ];
				$opts = array();
				if ( isset( $svc['options'] ) && is_array( $svc['options'] ) ) {
					foreach ( $svc['options'] as $o ) {
						$label = sanitize_text_field( isset( $o['label'] ) ? $o['label'] : '' );
						if ( '' === $label ) { continue; }
						$opts[] = array(
							'label'   => $label,
							'minutes' => max( 15, min( 600, (int) ( isset( $o['minutes'] ) ? $o['minutes'] : 60 ) ) ),
							'price'   => max( 0, (float) ( isset( $o['price'] ) ? $o['price'] : 0 ) ),
							'pickup'  => max( 0, (float) ( isset( $o['pickup'] ) ? $o['pickup'] : 0 ) ),
							'pay'     => esc_url_raw( isset( $o['pay'] ) ? $o['pay'] : '' ),
						);
					}
				}
				if ( $opts ) {
					$out['services'][ $key ] = array(
						'name'    => sanitize_text_field( isset( $svc['name'] ) ? $svc['name'] : $d['services'][ $key ]['name'] ),
						'when'    => sanitize_text_field( isset( $svc['when'] ) ? $svc['when'] : $d['services'][ $key ]['when'] ),
						'options' => $opts,
					);
				}
			}
		}
		return $out;
	}
}
