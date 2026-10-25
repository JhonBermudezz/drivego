<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DGB_Bookings {
	const CPT = 'dgb_booking';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	public static function register_post_type() {
		register_post_type( self::CPT, array(
			'labels'       => array( 'name' => 'Bookings', 'singular_name' => 'Booking', 'menu_name' => 'Bookings', 'all_items' => 'All bookings', 'edit_item' => 'Booking', 'search_items' => 'Search bookings', 'not_found' => 'No bookings yet' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'menu_position'=> 26,
			'supports'     => array( 'title' ),
			'capability_type' => 'post',
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		) );
	}

	public static function statuses() {
		return array( 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled' );
	}

	/** Busy intervals on a date (pending and confirmed bookings), excluding cancelled ones. */
	public static function booked_on( $date ) {
		$q = new WP_Query( array(
			'post_type' => self::CPT, 'post_status' => 'any', 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true,
			'meta_query' => array(
				array( 'key' => '_dgb_date', 'value' => $date ),
				array( 'key' => '_dgb_status', 'value' => array( 'pending', 'confirmed' ), 'compare' => 'IN' ),
			),
		) );
		$out = array();
		foreach ( $q->posts as $id ) {
			$start = (int) get_post_meta( $id, '_dgb_start', true );
			$out[] = array( 'start' => $start, 'end' => $start + (int) get_post_meta( $id, '_dgb_minutes', true ) );
		}
		return $out;
	}

	public static function now_minute_if_today( $date ) {
		return ( wp_date( 'Y-m-d' ) === $date ) ? ( (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' ) ) : -1;
	}

	public static function free_slots( $date, $minutes ) {
		$s = DGB_Settings::all();
		if ( ! DGB_Slots::in_window( $date, wp_date( 'Y-m-d' ), $s ) ) { return array(); }
		return DGB_Slots::free_slots( $date, $minutes, self::booked_on( $date ), $s, self::now_minute_if_today( $date ) );
	}

	public static function fmt_time( $min ) {
		$h = (int) floor( $min / 60 ); $m = $min % 60;
		return sprintf( '%d:%02d %s', ( ( $h + 11 ) % 12 ) + 1, $m, $h < 12 ? 'AM' : 'PM' );
	}

	/**
	 * Create a booking after re-checking the slot. A short lock stops two people taking the same slot at the same moment.
	 * @return int|WP_Error booking id
	 */
	public static function create( array $d ) {
		$services = DGB_Settings::get( 'services' );
		if ( ! isset( $services[ $d['service'] ] ) || ! isset( $services[ $d['service'] ]['options'][ $d['option'] ] ) ) {
			return new WP_Error( 'dgb_service', 'Unknown service.', array( 'status' => 400 ) );
		}
		$svc = $services[ $d['service'] ];
		$opt = $svc['options'][ $d['option'] ];
		$lock = 'dgb_lock_' . md5( $d['date'] );
		for ( $i = 0; $i < 20 && get_transient( $lock ); $i++ ) { usleep( 150000 ); }
		set_transient( $lock, 1, 15 );
		try {
			if ( ! in_array( (int) $d['start'], self::free_slots( $d['date'], (int) $opt['minutes'] ), true ) ) {
				return new WP_Error( 'dgb_taken', 'Sorry, that time was just taken. Please pick another one.', array( 'status' => 409 ) );
			}
			$title = sprintf( '%s · %s · %s %s', $d['name'], $svc['name'] . ' (' . $opt['label'] . ')', $d['date'], self::fmt_time( (int) $d['start'] ) );
			$id = wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => $title ), true );
			if ( is_wp_error( $id ) ) { return $id; }
			$meta = array(
				'_dgb_status' => 'pending', '_dgb_payment' => 'unpaid', '_dgb_service' => $d['service'], '_dgb_service_name' => $svc['name'],
				'_dgb_option' => $opt['label'], '_dgb_price' => $opt['price'], '_dgb_minutes' => (int) $opt['minutes'],
				'_dgb_date' => $d['date'], '_dgb_start' => (int) $d['start'],
				'_dgb_name' => $d['name'], '_dgb_email' => $d['email'], '_dgb_phone' => $d['phone'],
				'_dgb_licence' => $d['licence'], '_dgb_lang' => $d['lang'], '_dgb_meet' => $d['meet'], '_dgb_centre' => $d['centre'],
				'_dgb_address' => $d['address'], '_dgb_notes' => $d['notes'],
			);
			foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); }
			return $id;
		} finally {
			delete_transient( $lock );
		}
	}

	public static function summary_lines( $id ) {
		$g = function ( $k ) use ( $id ) { return get_post_meta( $id, '_dgb_' . $k, true ); };
		$where = 'roadtest' === $g( 'service' ) ? 'DriveTest ' . $g( 'centre' ) . ( $g( 'address' ) ? ', pick up at ' . $g( 'address' ) : '' )
			: ( 'Pick me up at home' === $g( 'meet' ) ? 'Pick up at ' . $g( 'address' ) : 'White Oaks Mall' );
		return array(
			'Service' => $g( 'service_name' ) . ' · ' . $g( 'option' ),
			'When'    => wp_date( 'l, F j, Y', strtotime( $g( 'date' ) . ' 12:00:00' ) ) . ' at ' . self::fmt_time( (int) $g( 'start' ) ),
			'Where'   => $where,
			'Price'   => '$' . number_format( (float) $g( 'price' ), 2 ) . ' + HST',
			'Name'    => $g( 'name' ),
			'Phone'   => $g( 'phone' ),
			'Email'   => $g( 'email' ),
			'Licence' => $g( 'licence' ) . ' · lessons in ' . $g( 'lang' ),
			'Notes'   => $g( 'notes' ) ?: '-',
		);
	}

	private static function send( $to, $subject, $body ) {
		$name = DGB_Settings::get( 'from_name' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . DGB_Settings::get( 'notify_email' ) . '>' );
		wp_mail( $to, $subject, $body, $headers );
	}

	private static function block( $id ) {
		$out = '';
		foreach ( self::summary_lines( $id ) as $k => $v ) { $out .= str_pad( $k . ':', 10 ) . $v . "\n"; }
		return $out;
	}

	public static function notify_new( $id, $pay_url ) {
		$phone = DGB_Settings::get( 'phone' );
		self::send( DGB_Settings::get( 'notify_email' ), 'New booking request: ' . get_the_title( $id ),
			"A new booking request came in from the website.\n\n" . self::block( $id ) . "\nConfirm or cancel it here:\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n" );
		self::send( get_post_meta( $id, '_dgb_email', true ), 'We received your Drive Go London booking request',
			'Hi ' . get_post_meta( $id, '_dgb_name', true ) . ",\n\nThanks for booking with Drive Go London. We received your request and will confirm it by phone or WhatsApp within one business day.\n\n" . self::block( $id ) .
			( $pay_url ? "\nPay securely with Square:\n" . $pay_url . "\n" : '' ) . "\nFree reschedule up to 24 hours before. Questions? Call or WhatsApp " . $phone . ".\n" );
	}

	public static function set_status( $id, $status ) {
		if ( ! isset( self::statuses()[ $status ] ) ) { return; }
		$prev = get_post_meta( $id, '_dgb_status', true );
		if ( $prev === $status ) { return; }
		update_post_meta( $id, '_dgb_status', $status );
		$phone = DGB_Settings::get( 'phone' );
		$name  = get_post_meta( $id, '_dgb_name', true );
		$to    = get_post_meta( $id, '_dgb_email', true );
		if ( 'confirmed' === $status ) {
			self::send( $to, 'Your Drive Go London booking is confirmed', "Hi $name,\n\nYour booking is confirmed.\n\n" . self::block( $id ) . "\nNeed to move it? Let us know at least 24 hours before: $phone.\n" );
		} elseif ( 'cancelled' === $status ) {
			self::send( $to, 'Your Drive Go London booking was cancelled', "Hi $name,\n\nYour booking was cancelled. If this is a surprise or you want a new time, call or WhatsApp us at $phone.\n\n" . self::block( $id ) );
		}
	}
}
