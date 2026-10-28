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
			if ( 'package' === get_post_meta( $id, '_dgb_kind', true ) ) { continue; } // packages have no time slot
			$start = (int) get_post_meta( $id, '_dgb_start', true );
			$out[] = array( 'start' => $start, 'end' => $start + (int) get_post_meta( $id, '_dgb_minutes', true ) );
		}
		// Time marked busy in Google Calendar blocks the slot completely, whatever the capacity.
		$cap = max( 1, (int) DGB_Settings::get( 'capacity' ) );
		foreach ( DGB_Google::busy_on( $date ) as $b ) {
			for ( $i = 0; $i < $cap; $i++ ) { $out[] = $b; }
		}
		return $out;
	}

	/** Does this booking include a home pick up? Road tests always do; lessons only when the student asks for it. */
	public static function needs_pickup( $service, $meet, $home = true ) {
		return in_array( $service, array( 'roadtest', 'outside' ), true ) ? (bool) $home : 'Pick me up at home' === $meet;
	}

	/** Server-side total so the price can never be changed from the browser. */
	public static function price_for( array $opt, $service, $meet, $home = true, $car = '' ) {
		$extra = self::needs_pickup( $service, $meet, $home ) ? (float) ( isset( $opt['pickup'] ) ? $opt['pickup'] : 0 ) : 0.0;
		$carx  = 'school' === $car && ! empty( $opt['car'] ) ? (float) $opt['car'] : 0.0;
		return array( 'base' => (float) $opt['price'], 'extra' => $extra, 'car' => $carx, 'total' => (float) $opt['price'] + $extra + $carx );
	}

	public static function now_minute_if_today( $date ) {
		return ( wp_date( 'Y-m-d' ) === $date ) ? ( (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' ) ) : -1;
	}

	public static function free_slots( $date, $minutes ) {
		$s = DGB_Settings::all();
		if ( ! DGB_Slots::in_window( $date, wp_date( 'Y-m-d' ), $s ) ) { return array(); }
		return DGB_Slots::free_slots( $date, $minutes, self::booked_on( $date ), $s, self::now_minute_if_today( $date ) );
	}

	public static function pay_label( $m ) {
		$all = array( 'wave' => 'Wave invoice', 'square' => 'Card online (Square)', 'etransfer' => 'Interac e-Transfer', 'cash' => 'Cash at the lesson' );
		return isset( $all[ $m ] ) ? $all[ $m ] : '-';
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
			DGB_Google::forget_busy( $d['date'] ); // last-second check against Google, not the one-minute cache
			if ( ! in_array( (int) $d['start'], self::free_slots( $d['date'], (int) $opt['minutes'] ), true ) ) {
				return new WP_Error( 'dgb_taken', 'Sorry, that time was just taken. Please pick another one.', array( 'status' => 409 ) );
			}
			if ( 'outside' === $d['service'] ) { $d['centre'] = trim( explode( ' · ', $opt['label'] )[0] ); }
			$price = self::price_for( $opt, $d['service'], $d['meet'], DGB_Settings::option_home( $opt, $d['option'], $d['service'] ), isset( $d['car'] ) ? $d['car'] : '' );
			$title = sprintf( '%s · %s · %s %s', $d['name'], $svc['name'] . ' (' . $opt['label'] . ')', $d['date'], self::fmt_time( (int) $d['start'] ) );
			$id = wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => $title ), true );
			if ( is_wp_error( $id ) ) { return $id; }
			$meta = array(
				'_dgb_status' => 'pending', '_dgb_payment' => 'unpaid', '_dgb_service' => $d['service'], '_dgb_service_name' => $svc['name'],
				'_dgb_option' => $opt['label'], '_dgb_price' => $price['total'], '_dgb_base_price' => $price['base'], '_dgb_pickup_extra' => $price['extra'], '_dgb_car_extra' => $price['car'], '_dgb_car' => ! empty( $opt['car'] ) ? ( 'school' === ( isset( $d['car'] ) ? $d['car'] : '' ) ? 'school' : 'own' ) : '', '_dgb_minutes' => (int) $opt['minutes'],
				'_dgb_date' => $d['date'], '_dgb_start' => (int) $d['start'],
				'_dgb_name' => $d['name'], '_dgb_email' => $d['email'], '_dgb_phone' => $d['phone'],
				'_dgb_licence' => $d['licence'], '_dgb_lang' => $d['lang'], '_dgb_meet' => $d['meet'], '_dgb_centre' => $d['centre'],
				'_dgb_address' => $d['address'], '_dgb_pay_method' => isset( $d['pay'] ) ? $d['pay'] : 'square', '_dgb_notes' => $d['notes'],
			);
			foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); }
			return $id;
		} finally {
			delete_transient( $lock );
		}
	}

	public static function summary_lines( $id ) {
		$g = function ( $k ) use ( $id ) { return get_post_meta( $id, '_dgb_' . $k, true ); };
		if ( 'package' === $g( 'kind' ) ) {
			return array(
				'Package' => $g( 'service_name' ),
				'Price'   => '$' . number_format( (float) $g( 'price' ), 2 ) . ' + HST',
				'Payment' => self::pay_label( $g( 'pay_method' ) ),
				'Name'    => $g( 'name' ),
				'Phone'   => $g( 'phone' ),
				'Email'   => $g( 'email' ),
				'Licence' => $g( 'licence' ) . ' · classes in ' . $g( 'lang' ),
				'Notes'   => $g( 'notes' ) ?: '-',
			);
		}
		$where = in_array( $g( 'service' ), array( 'roadtest', 'outside' ), true ) ? 'DriveTest ' . ( 'Other' === $g( 'centre' ) ? 'other location (price to be confirmed)' : $g( 'centre' ) ) . ( $g( 'address' ) ? ', pick up at ' . $g( 'address' ) : ', meet at White Oaks Mall' )
			: ( 'Pick me up at home' === $g( 'meet' ) ? 'Pick up at ' . $g( 'address' ) : 'White Oaks Mall' );
		$out = array(
			'Service' => $g( 'service_name' ) . ' · ' . $g( 'option' ),
			'When'    => wp_date( 'l, F j, Y', strtotime( $g( 'date' ) . ' 12:00:00' ) ) . ' at ' . self::fmt_time( (int) $g( 'start' ) ),
			'Where'   => $where,
			'Price'   => '$' . number_format( (float) $g( 'price' ), 2 ) . ' + HST' . ( (float) $g( 'pickup_extra' ) > 0 || (float) $g( 'car_extra' ) > 0 ? ' (includes ' . implode( ' and ', array_filter( array( (float) $g( 'pickup_extra' ) > 0 ? '$' . number_format( (float) $g( 'pickup_extra' ), 2 ) . ' home pick up' : '', (float) $g( 'car_extra' ) > 0 ? '$' . number_format( (float) $g( 'car_extra' ), 2 ) . ' DriveGo car' : '' ) ) ) . ')' : '' ),
			'Car'     => $g( 'car' ) ? ( 'school' === $g( 'car' ) ? 'DriveGo school car' : 'Own car' ) : '-',
			'Payment' => self::pay_label( $g( 'pay_method' ) ),
			'Name'    => $g( 'name' ),
			'Phone'   => $g( 'phone' ),
			'Email'   => $g( 'email' ),
			'Licence' => $g( 'licence' ) . ' · lessons in ' . $g( 'lang' ),
			'Notes'   => $g( 'notes' ) ?: '-',
		);
		if ( ! $g( 'car' ) ) { unset( $out['Car'] ); }
		return $out;
	}

	private static function send( $to, $subject, $body ) {
		$name = DGB_Settings::get( 'from_name' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . DGB_Settings::notify_first() . '>' );
		wp_mail( $to, $subject, $body, $headers );
	}

	private static function block( $id ) {
		$out = '';
		foreach ( self::summary_lines( $id ) as $k => $v ) { $out .= str_pad( $k . ':', 10 ) . $v . "\n"; }
		return $out;
	}

	public static function pay_text( $id, $pay_url ) {
		$m = get_post_meta( $id, '_dgb_pay_method', true );
		$total = '$' . number_format( (float) get_post_meta( $id, '_dgb_price', true ) * ( 1 + (int) DGB_Settings::get( 'hst_percent' ) / 100 ), 2 );
		if ( 'etransfer' === $m ) { return "\nHow to pay: send an Interac e-Transfer of $total (HST included) to " . DGB_Settings::etransfer_email() . ' and write your name in the message. No password needed.' . "\n"; }
		if ( 'cash' === $m ) { return "\nHow to pay: cash at your lesson, $total (HST included). Please bring the exact amount if you can.\n"; }
		if ( 'wave' === $m ) { return $pay_url ? "\nPay your invoice securely with Wave (card or bank):\n" . $pay_url . "\n" : ''; }
		return $pay_url ? "\nPay securely with Square:\n" . $pay_url . "\n" : '';
	}

	/** A package bought online: no date, the school contacts the student to plan the course. */
	public static function create_package( array $d ) {
		$pk = DGB_Packages::find( $d['package'] );
		if ( ! $pk ) { return new WP_Error( 'dgb_package', 'Unknown package.', array( 'status' => 400 ) ); }
		$id = wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => $d['name'] . ' · ' . $pk['name'] . ' · package' ), true );
		if ( is_wp_error( $id ) ) { return $id; }
		$meta = array(
			'_dgb_kind' => 'package', '_dgb_status' => 'pending', '_dgb_payment' => 'unpaid', '_dgb_service' => 'package', '_dgb_package' => $pk['key'], '_dgb_service_name' => $pk['name'],
			'_dgb_option' => '', '_dgb_price' => (float) $pk['price'], '_dgb_base_price' => (float) $pk['price'], '_dgb_pickup_extra' => 0, '_dgb_car_extra' => 0, '_dgb_minutes' => 0,
			'_dgb_date' => wp_date( 'Y-m-d' ), '_dgb_start' => 0, '_dgb_name' => $d['name'], '_dgb_email' => $d['email'], '_dgb_phone' => $d['phone'],
			'_dgb_licence' => $d['licence'], '_dgb_lang' => $d['lang'], '_dgb_meet' => '', '_dgb_centre' => '', '_dgb_address' => '', '_dgb_notes' => $d['notes'], '_dgb_pay_method' => $d['pay'],
		);
		foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); }
		return $id;
	}

	public static function notify_package( $id, $pay_url ) {
		$phone = DGB_Settings::get( 'phone' );
		self::send( DGB_Settings::get( 'notify_email' ), 'New package purchase: ' . get_the_title( $id ),
			"A package was bought on the website.\n\n" . self::block( $id ) . "\nOpen it here:\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n" );
		self::send( get_post_meta( $id, '_dgb_email', true ), 'We received your DriveGo London package',
			'Hi ' . get_post_meta( $id, '_dgb_name', true ) . ",\n\nThanks for choosing DriveGo London. We received your package and will contact you by phone or WhatsApp within one business day to plan your course and your first lessons.\n\n" . self::block( $id ) .
			self::pay_text( $id, $pay_url ) . "\nQuestions? Call or WhatsApp " . $phone . ".\n" );
	}

	public static function notify_new( $id, $pay_url ) {
		$phone = DGB_Settings::get( 'phone' );
		self::send( DGB_Settings::get( 'notify_email' ), 'New booking request: ' . get_the_title( $id ),
			"A new booking request came in from the website.\n\n" . self::block( $id ) . "\nConfirm or cancel it here:\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n" );
		self::send( get_post_meta( $id, '_dgb_email', true ), 'We received your DriveGo London booking request',
			'Hi ' . get_post_meta( $id, '_dgb_name', true ) . ",\n\nThanks for booking with DriveGo London. We received your request and will confirm it by phone or WhatsApp within one business day.\n\n" . self::block( $id ) .
			self::pay_text( $id, $pay_url ) . "\nFree reschedule up to 24 hours before. Questions? Call or WhatsApp " . $phone . ".\n" );
	}

	public static function find_by_order( $order_id ) {
		if ( '' === (string) $order_id ) { return 0; }
		$q = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_dgb_square_order', 'meta_value' => $order_id ) );
		return $q ? (int) $q[0] : 0;
	}

	/** Mark a booking as paid once and tell the owner. */
	public static function mark_paid( $id ) {
		if ( 'paid' === get_post_meta( $id, '_dgb_payment', true ) ) { return; }
		update_post_meta( $id, '_dgb_payment', 'paid' );
		update_post_meta( $id, '_dgb_paid_at', time() );
		self::send( DGB_Settings::get( 'notify_email' ), 'Payment received: ' . get_the_title( $id ),
			"The student paid with Square.\n\n" . self::block( $id ) . "\nConfirm the booking here:\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n" );
		DGB_Wave::on_paid( $id );
	}

	public static function set_status( $id, $status ) {
		if ( ! isset( self::statuses()[ $status ] ) ) { return; }
		$prev = get_post_meta( $id, '_dgb_status', true );
		if ( $prev === $status ) { return; }
		update_post_meta( $id, '_dgb_status', $status );
		DGB_Google::push( $id );
		$phone = DGB_Settings::get( 'phone' );
		$name  = get_post_meta( $id, '_dgb_name', true );
		$to    = get_post_meta( $id, '_dgb_email', true );
		if ( 'confirmed' === $status ) {
			self::send( $to, 'Your DriveGo London booking is confirmed', "Hi $name,\n\nYour booking is confirmed.\n\n" . self::block( $id ) . "\nNeed to move it? Let us know at least 24 hours before: $phone.\n" );
		} elseif ( 'cancelled' === $status ) {
			self::send( $to, 'Your DriveGo London booking was cancelled', "Hi $name,\n\nYour booking was cancelled. If this is a surprise or you want a new time, call or WhatsApp us at $phone.\n\n" . self::block( $id ) );
		}
	}
}
