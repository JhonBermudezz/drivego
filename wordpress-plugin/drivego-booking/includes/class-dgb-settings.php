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
		$lessons = array(
			array( 'label' => '1 hour', 'minutes' => 60, 'price' => 55, 'pickup' => 0, 'pay' => '' ),
			array( 'label' => '1.5 hours', 'minutes' => 90, 'price' => 75, 'pickup' => 0, 'pay' => '' ),
			array( 'label' => '2 hours', 'minutes' => 120, 'price' => 105, 'pickup' => 0, 'pay' => '' ),
		);
		return array(
			'notify_email'   => get_option( 'admin_email' ),
			'from_name'      => 'DriveGo London',
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
			'recaptcha_site'   => '',
			'recaptcha_secret' => '',
			'recaptcha_score'  => 0.5,
			'etransfer_email'      => '',
			'google_client_id'     => '',
			'google_client_secret' => '',
			'google_calendar'      => 'primary',
			'google_instructors'   => '',
			'google_invite_customer' => 1,
			'services'       => array(
				'lessons'   => array( 'name' => 'Single lesson', 'when' => 'Lesson day and start time', 'options' => $lessons ),
				'refresher' => array( 'name' => 'G-Full & G2 Refresher', 'when' => 'Lesson day and start time', 'options' => $opts ),
				'roadtest'  => array( 'name' => 'Road test package', 'when' => 'Your DriveTest date and test time', 'options' => array(
					array( 'label' => 'Basic road test', 'minutes' => 120, 'price' => 135, 'pickup' => 0, 'home' => 0, 'pay' => '' ),
					array( 'label' => 'Complete road test', 'minutes' => 120, 'price' => 175, 'pickup' => 0, 'home' => 1, 'pay' => '' ),
				) ),
				'outside'   => array( 'name' => 'Road test outside of London', 'when' => 'Your DriveTest date and test time', 'options' => array(
					array( 'label' => 'Strathroy · 2.5 h', 'minutes' => 150, 'price' => 260, 'pickup' => 0, 'pay' => '' ),
					array( 'label' => 'Woodstock · 3 h', 'minutes' => 180, 'price' => 290, 'pickup' => 0, 'pay' => '' ),
					array( 'label' => 'Tillsonburg · 3.5 h', 'minutes' => 210, 'price' => 330, 'pickup' => 0, 'pay' => '' ),
					array( 'label' => 'Stratford · 4 h', 'minutes' => 240, 'price' => 360, 'pickup' => 0, 'pay' => '' ),
					array( 'label' => 'Clinton · 4 h 20 min', 'minutes' => 260, 'price' => 390, 'pickup' => 0, 'pay' => '' ),
					array( 'label' => 'Chatham · 5 h 30 min', 'minutes' => 330, 'price' => 450, 'pickup' => 0, 'pay' => '' ),
					array( 'label' => 'Sarnia · 6 h', 'minutes' => 360, 'price' => 500, 'pickup' => 0, 'pay' => '' ),
					array( 'label' => 'Windsor · 8 h', 'minutes' => 480, 'price' => 600, 'pickup' => 0, 'pay' => '' ),
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
		foreach ( $d['services'] as $k => $svc ) { if ( ! isset( $s['services'][ $k ] ) ) { $s['services'][ $k ] = $svc; } } // services added in newer versions
		return $s;
	}

	/** Does this road test option include home pick up and drop off? Older saved settings have no flag: the first option meets at the mall, the others pick up. */
	public static function option_home( array $o, $index, $service = 'roadtest' ) {
		if ( 'outside' === $service ) { return true; } // transport to and from the test centre is part of this package
		return isset( $o['home'] ) ? (bool) $o['home'] : ( (int) $index > 0 );
	}

	public static function get( $key ) {
		$s = self::all();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	public static function etransfer_email() {
		$e = self::get( 'etransfer_email' );
		return $e ? $e : self::get( 'notify_email' );
	}

	/** Payment methods offered at checkout: Square only when it can really produce a payment link. */
	public static function pay_methods() {
		$m = array();
		$square = DGB_Square::enabled();
		foreach ( (array) self::get( 'services' ) as $svc ) { foreach ( $svc['options'] as $o ) { if ( ! empty( $o['pay'] ) ) { $square = true; } } }
		if ( $square ) { $m['square'] = 'Card online (Square)'; }
		$m['etransfer'] = 'Interac e-Transfer';
		$m['cash']      = 'Cash at the lesson';
		return $m;
	}

	/** Services without secrets, for the front end. */
	public static function public_services() {
		$out = array();
		foreach ( self::get( 'services' ) as $key => $svc ) {
			$opts = array();
			foreach ( $svc['options'] as $i => $o ) {
				if ( '' === trim( $o['label'] ) ) { continue; }
				$opts[] = array( 'label' => $o['label'], 'minutes' => (int) $o['minutes'], 'price' => (float) $o['price'], 'pickup' => isset( $o['pickup'] ) ? (float) $o['pickup'] : 0, 'home' => self::option_home( $o, $i, $key ) );
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
		if ( isset( $in['recaptcha_site'] ) ) { $out['recaptcha_site'] = sanitize_text_field( $in['recaptcha_site'] ); }
		if ( isset( $in['recaptcha_secret'] ) && '' !== trim( $in['recaptcha_secret'] ) ) { $out['recaptcha_secret'] = sanitize_text_field( $in['recaptcha_secret'] ); }
		if ( ! empty( $in['recaptcha_secret_clear'] ) ) { $out['recaptcha_secret'] = ''; }
		if ( isset( $in['recaptcha_score'] ) ) { $out['recaptcha_score'] = max( 0.1, min( 0.9, (float) $in['recaptcha_score'] ) ); }
		if ( isset( $in['etransfer_email'] ) ) { $out['etransfer_email'] = sanitize_email( $in['etransfer_email'] ); }
		if ( isset( $in['google_client_id'] ) ) { $out['google_client_id'] = sanitize_text_field( $in['google_client_id'] ); }
		if ( isset( $in['google_client_secret'] ) && '' !== trim( $in['google_client_secret'] ) ) { $out['google_client_secret'] = sanitize_text_field( $in['google_client_secret'] ); }
		if ( isset( $in['google_calendar'] ) ) { $out['google_calendar'] = sanitize_text_field( $in['google_calendar'] ) ?: 'primary'; }
		if ( isset( $in['google_instructors'] ) ) { $out['google_instructors'] = sanitize_text_field( $in['google_instructors'] ); }
		if ( isset( $in['google_calendar'] ) ) { $out['google_invite_customer'] = empty( $in['google_invite_customer'] ) ? 0 : 1; }
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
							'home'    => empty( $o['home'] ) ? 0 : 1,
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
