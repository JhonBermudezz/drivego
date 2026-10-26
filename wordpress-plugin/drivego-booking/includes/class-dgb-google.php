<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Two-way Google Calendar link.
 *  - Every booking becomes an event on the shared calendar, with the instructor invited so it shows on their calendar too.
 *  - Busy time on that calendar (events not made by this plugin) blocks the same slots on the website.
 *  - A cron job notices events that were moved or deleted in Google and updates the booking.
 */
class DGB_Google {
	const AUTH  = 'dgb_google_auth';
	const CRON  = 'dgb_google_sync';
	const SCOPE = 'openid email https://www.googleapis.com/auth/calendar.events';

	public static $last_error = '';

	public static function init() {
		add_action( 'admin_post_dgb_google_cb', array( __CLASS__, 'callback' ) );
		add_action( 'admin_post_dgb_google_connect', array( __CLASS__, 'connect' ) );
		add_action( 'admin_post_dgb_google_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_dgb_google_push', array( __CLASS__, 'push_now' ) );
		add_action( 'admin_post_dgb_google_test', array( __CLASS__, 'test' ) );
		add_filter( 'cron_schedules', function ( $s ) { $s['dgb_ten_minutes'] = array( 'interval' => 600, 'display' => 'Every 10 minutes' ); return $s; } );
		add_action( self::CRON, array( __CLASS__, 'sync' ) );
		add_action( 'init', function () {
			if ( self::connected() && ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + 120, 'dgb_ten_minutes', self::CRON ); }
		} );
	}

	/* ---------- connection ---------- */

	public static function redirect_uri() { return admin_url( 'admin-post.php?action=dgb_google_cb' ); }

	public static function auth() {
		$a = get_option( self::AUTH, array() );
		return is_array( $a ) ? $a : array();
	}

	public static function configured() {
		return DGB_Settings::get( 'google_client_id' ) && DGB_Settings::get( 'google_client_secret' );
	}

	public static function connected() {
		$a = self::auth();
		return ! empty( $a['refresh'] ) && self::configured();
	}

	public static function calendar_id() {
		$c = trim( (string) DGB_Settings::get( 'google_calendar' ) );
		return '' === $c ? 'primary' : $c;
	}

	public static function instructors() {
		$list = preg_split( '/[\s,;]+/', (string) DGB_Settings::get( 'google_instructors' ), -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_filter( $list, 'is_email' ) );
	}

	private static function admin_back( $notice, $ok = true ) {
		set_transient( 'dgb_google_notice', array( 'ok' => $ok, 'msg' => $notice ), 120 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . DGB_Bookings::CPT . '&page=drivego-booking#google' ) );
		exit;
	}

	public static function connect() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_google' ) ) { wp_die( 'Not allowed.' ); }
		if ( ! self::configured() ) { self::admin_back( 'Save the Client ID and Client secret first, then press Connect.', false ); }
		$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
			'client_id' => DGB_Settings::get( 'google_client_id' ), 'redirect_uri' => self::redirect_uri(), 'response_type' => 'code',
			'scope' => self::SCOPE, 'access_type' => 'offline', 'prompt' => 'consent', 'state' => wp_create_nonce( 'dgb_google_state' ),
		) );
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	public static function callback() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		if ( ! wp_verify_nonce( isset( $_GET['state'] ) ? $_GET['state'] : '', 'dgb_google_state' ) ) { wp_die( 'Invalid state. Start the connection again from Booking settings.' ); }
		if ( ! empty( $_GET['error'] ) ) { self::admin_back( 'Google said: ' . sanitize_text_field( $_GET['error'] ), false ); }
		$res = self::token_request( array( 'code' => isset( $_GET['code'] ) ? $_GET['code'] : '', 'grant_type' => 'authorization_code', 'redirect_uri' => self::redirect_uri() ) );
		if ( is_wp_error( $res ) ) { self::admin_back( 'Could not connect: ' . $res->get_error_message(), false ); }
		if ( empty( $res['refresh_token'] ) ) { self::admin_back( 'Google did not return a refresh token. Remove DriveGo from myaccount.google.com/permissions and connect again.', false ); }
		$email = '';
		if ( ! empty( $res['id_token'] ) ) {
			$parts = explode( '.', $res['id_token'] );
			$pl    = isset( $parts[1] ) ? json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ) : array();
			$email = isset( $pl['email'] ) ? sanitize_email( $pl['email'] ) : '';
		}
		update_option( self::AUTH, array( 'refresh' => $res['refresh_token'], 'email' => $email, 'since' => time() ), false );
		delete_transient( 'dgb_google_access' );
		self::admin_back( 'Google Calendar connected' . ( $email ? ' as ' . $email : '' ) . '. Press "Test connection" to check it end to end.' );
	}

	public static function disconnect() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_google' ) ) { wp_die( 'Not allowed.' ); }
		delete_option( self::AUTH );
		delete_transient( 'dgb_google_access' );
		wp_clear_scheduled_hook( self::CRON );
		self::admin_back( 'Google Calendar disconnected. Existing events stay in Google.' );
	}

	/** Admin button on a booking: send it to Google again and show the answer. */
	public static function push_now() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'edit_post', $id ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_gpush_' . $id ) ) { wp_die( 'Not allowed.' ); }
		self::push( $id );
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}

	/** Creates a test event for the instructors, checks it can be read back, then removes it. */
	public static function test() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_google' ) ) { wp_die( 'Not allowed.' ); }
		if ( ! self::connected() ) { self::admin_back( 'Not connected yet.', false ); }
		$tz = wp_timezone();
		$s  = new DateTime( 'tomorrow 03:00', $tz );
		$e  = new DateTime( 'tomorrow 03:15', $tz );
		$ev = self::api( 'POST', 'calendars/' . rawurlencode( self::calendar_id() ) . '/events?sendUpdates=none', array(
			'summary' => 'DriveGo connection test (safe to ignore)', 'start' => array( 'dateTime' => $s->format( 'c' ), 'timeZone' => $tz->getName() ),
			'end' => array( 'dateTime' => $e->format( 'c' ), 'timeZone' => $tz->getName() ), 'transparency' => 'transparent',
			'extendedProperties' => array( 'private' => array( 'dgb_booking' => 'test' ) ),
		) );
		if ( is_wp_error( $ev ) ) { self::admin_back( 'Test failed: ' . $ev->get_error_message(), false ); }
		$read = self::api( 'GET', 'calendars/' . rawurlencode( self::calendar_id() ) . '/events/' . rawurlencode( $ev['id'] ) );
		self::api( 'DELETE', 'calendars/' . rawurlencode( self::calendar_id() ) . '/events/' . rawurlencode( $ev['id'] ) );
		if ( is_wp_error( $read ) ) { self::admin_back( 'Test failed while reading the event: ' . $read->get_error_message(), false ); }
		self::admin_back( 'Test passed: an event was created on calendar "' . self::calendar_id() . '", read back and removed.' );
	}

	/* ---------- HTTP ---------- */

	private static function token_request( array $params ) {
		$r = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'body' => array_merge( array(
			'client_id' => DGB_Settings::get( 'google_client_id' ), 'client_secret' => DGB_Settings::get( 'google_client_secret' ),
		), $params ) ) );
		if ( is_wp_error( $r ) ) { return $r; }
		$b = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || ! is_array( $b ) ) {
			$msg = is_array( $b ) && isset( $b['error_description'] ) ? $b['error_description'] : ( is_array( $b ) && isset( $b['error'] ) ? $b['error'] : 'token request failed' );
			return new WP_Error( 'dgb_google_token', $msg );
		}
		return $b;
	}

	private static function access_token() {
		$t = get_transient( 'dgb_google_access' );
		if ( $t ) { return $t; }
		$a = self::auth();
		if ( empty( $a['refresh'] ) ) { return new WP_Error( 'dgb_google_off', 'Google Calendar is not connected.' ); }
		$res = self::token_request( array( 'grant_type' => 'refresh_token', 'refresh_token' => $a['refresh'] ) );
		if ( is_wp_error( $res ) ) { return $res; }
		set_transient( 'dgb_google_access', $res['access_token'], max( 60, (int) $res['expires_in'] - 120 ) );
		return $res['access_token'];
	}

	/** @return array|WP_Error decoded JSON ([] for empty 204) */
	public static function api( $method, $path, $body = null ) {
		$tok = self::access_token();
		if ( is_wp_error( $tok ) ) { self::$last_error = $tok->get_error_message(); return $tok; }
		$args = array( 'method' => $method, 'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ) );
		if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
		$r = wp_remote_request( 'https://www.googleapis.com/calendar/v3/' . $path, $args );
		if ( is_wp_error( $r ) ) { self::$last_error = $r->get_error_message(); return $r; }
		$code = (int) wp_remote_retrieve_response_code( $r );
		$b    = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( $code >= 200 && $code < 300 ) { return is_array( $b ) ? $b : array(); }
		$msg = is_array( $b ) && isset( $b['error']['message'] ) ? $b['error']['message'] : 'HTTP ' . $code;
		self::$last_error = $msg;
		return new WP_Error( 'dgb_google_api', $msg, array( 'status' => $code ) );
	}

	/* ---------- Google -> website: busy time blocks slots ---------- */

	private static function day_bounds( $date ) {
		$tz = wp_timezone();
		$a  = new DateTime( $date . ' 00:00:00', $tz );
		$b  = clone $a; $b->modify( '+1 day' );
		return array( $a, $b );
	}

	/** Busy intervals on one date, in minutes from midnight. One API call covers a whole month and is cached for a minute. */
	public static function busy_on( $date ) {
		if ( ! self::connected() ) { return array(); }
		$month = substr( $date, 0, 7 );
		$key   = 'dgb_gbusy_' . $month;
		$events = get_transient( $key );
		if ( false === $events ) {
			$tz = wp_timezone();
			$from = new DateTime( $month . '-01 00:00:00', $tz ); $from->modify( '-1 day' );
			$to   = new DateTime( $month . '-01 00:00:00', $tz ); $to->modify( '+1 month +1 day' );
			$res = self::api( 'GET', 'calendars/' . rawurlencode( self::calendar_id() ) . '/events?' . http_build_query( array(
				'timeMin' => $from->format( 'c' ), 'timeMax' => $to->format( 'c' ), 'singleEvents' => 'true', 'maxResults' => 250, 'orderBy' => 'startTime',
				'fields' => 'items(status,transparency,start,end,extendedProperties/private)',
			) ) );
			if ( is_wp_error( $res ) ) { return array(); } // fail open: the website keeps working if Google is unreachable
			$events = array();
			foreach ( isset( $res['items'] ) ? $res['items'] : array() as $it ) {
				if ( isset( $it['status'] ) && 'cancelled' === $it['status'] ) { continue; }
				if ( isset( $it['transparency'] ) && 'transparent' === $it['transparency'] ) { continue; } // marked "Free"
				if ( ! empty( $it['extendedProperties']['private']['dgb_booking'] ) ) { continue; } // our own bookings are counted already
				$events[] = array( 'start' => isset( $it['start']['dateTime'] ) ? $it['start']['dateTime'] : $it['start']['date'], 'end' => isset( $it['end']['dateTime'] ) ? $it['end']['dateTime'] : $it['end']['date'], 'all_day' => isset( $it['start']['date'] ) );
			}
			set_transient( $key, $events, 60 );
		}
		return self::clip( $events, $date );
	}

	/** Pure: clip events to one date and return minute ranges. */
	public static function clip( array $events, $date ) {
		list( $d0, $d1 ) = self::day_bounds( $date );
		$out = array();
		foreach ( $events as $e ) {
			$tz = wp_timezone();
			$s  = $e['all_day'] ? new DateTime( $e['start'] . ' 00:00:00', $tz ) : new DateTime( $e['start'] );
			$en = $e['all_day'] ? new DateTime( $e['end'] . ' 00:00:00', $tz ) : new DateTime( $e['end'] );
			if ( $en <= $d0 || $s >= $d1 ) { continue; }
			$a = $s <= $d0 ? 0 : (int) round( ( $s->getTimestamp() - $d0->getTimestamp() ) / 60 );
			$b = $en >= $d1 ? 1440 : (int) round( ( $en->getTimestamp() - $d0->getTimestamp() ) / 60 );
			if ( $b > $a ) { $out[] = array( 'start' => $a, 'end' => $b ); }
		}
		return $out;
	}

	public static function forget_busy( $date ) { delete_transient( 'dgb_gbusy_' . substr( $date, 0, 7 ) ); }

	/* ---------- website -> Google ---------- */

	private static function event_body( $id ) {
		$g     = function ( $k ) use ( $id ) { return get_post_meta( $id, '_dgb_' . $k, true ); };
		$tz    = wp_timezone();
		$start = new DateTime( $g( 'date' ) . ' 00:00:00', $tz ); $start->modify( '+' . (int) $g( 'start' ) . ' minutes' );
		$end   = clone $start; $end->modify( '+' . (int) $g( 'minutes' ) . ' minutes' );
		$status = $g( 'status' ) ?: 'pending';
		$lines = DGB_Bookings::summary_lines( $id );
		$desc  = '';
		foreach ( $lines as $k => $v ) { $desc .= $k . ': ' . $v . "\n"; }
		$desc .= "\nManage: " . admin_url( 'post.php?post=' . $id . '&action=edit' );
		$att = array();
		foreach ( self::instructors() as $m ) { $att[] = array( 'email' => $m ); }
		if ( 'confirmed' === $status && DGB_Settings::get( 'google_invite_customer' ) && is_email( $g( 'email' ) ) ) { $att[] = array( 'email' => $g( 'email' ), 'displayName' => $g( 'name' ) ); }
		$where = $lines['Where'];
		return array(
			'summary' => ( 'confirmed' === $status ? '' : '[Pending] ' ) . $g( 'name' ) . ' · ' . $g( 'service_name' ) . ' (' . $g( 'option' ) . ')',
			'location' => $where, 'description' => $desc,
			'start' => array( 'dateTime' => $start->format( 'c' ), 'timeZone' => $tz->getName() ),
			'end' => array( 'dateTime' => $end->format( 'c' ), 'timeZone' => $tz->getName() ),
			'status' => 'confirmed' === $status ? 'confirmed' : 'tentative',
			'colorId' => 'confirmed' === $status ? '10' : '5',
			'attendees' => $att,
			'guestsCanModify' => false, 'guestsCanInviteOthers' => false, 'guestsCanSeeOtherGuests' => false,
			'reminders' => array( 'useDefault' => false, 'overrides' => array( array( 'method' => 'popup', 'minutes' => 60 ) ) ),
			'extendedProperties' => array( 'private' => array( 'dgb_booking' => (string) $id ) ),
		);
	}

	/** Create, update or delete the Google event so it matches the booking. Never throws: errors are saved on the booking. */
	public static function push( $id ) {
		if ( ! self::connected() ) { return; }
		$cal  = 'calendars/' . rawurlencode( self::calendar_id() ) . '/events';
		$ev   = get_post_meta( $id, '_dgb_gcal_event', true );
		$st   = get_post_meta( $id, '_dgb_status', true ) ?: 'pending';
		$send = '?sendUpdates=all';
		if ( 'cancelled' === $st ) {
			if ( $ev ) { $r = self::api( 'DELETE', $cal . '/' . rawurlencode( $ev ) . $send ); delete_post_meta( $id, '_dgb_gcal_event' ); }
			else { $r = array(); }
		} elseif ( $ev ) {
			$r = self::api( 'PATCH', $cal . '/' . rawurlencode( $ev ) . $send, self::event_body( $id ) );
			if ( is_wp_error( $r ) && 404 === (int) ( $r->get_error_data()['status'] ?? 0 ) ) { // deleted in Google meanwhile: recreate
				$r = self::api( 'POST', $cal . $send, self::event_body( $id ) );
				if ( ! is_wp_error( $r ) ) { update_post_meta( $id, '_dgb_gcal_event', $r['id'] ); }
			}
		} else {
			$r = self::api( 'POST', $cal . $send, self::event_body( $id ) );
			if ( ! is_wp_error( $r ) ) { update_post_meta( $id, '_dgb_gcal_event', $r['id'] ); }
		}
		if ( is_wp_error( $r ) ) { update_post_meta( $id, '_dgb_gcal_error', $r->get_error_message() ); }
		else { delete_post_meta( $id, '_dgb_gcal_error' ); update_post_meta( $id, '_dgb_gcal_synced', time() ); }
		self::forget_busy( get_post_meta( $id, '_dgb_date', true ) );
	}

	/* ---------- Google -> website: moved or deleted events ---------- */

	public static function sync() {
		if ( ! self::connected() ) { return; }
		$ids = get_posts( array(
			'post_type' => DGB_Bookings::CPT, 'post_status' => 'any', 'numberposts' => 50, 'fields' => 'ids',
			'meta_query' => array(
				array( 'key' => '_dgb_status', 'value' => array( 'pending', 'confirmed' ), 'compare' => 'IN' ),
				array( 'key' => '_dgb_gcal_event', 'compare' => 'EXISTS' ),
				array( 'key' => '_dgb_date', 'value' => wp_date( 'Y-m-d' ), 'compare' => '>=' ),
			),
		) );
		foreach ( $ids as $id ) { self::pull( $id ); }
	}

	public static function pull( $id ) {
		$ev = get_post_meta( $id, '_dgb_gcal_event', true );
		if ( ! $ev ) { return; }
		$r = self::api( 'GET', 'calendars/' . rawurlencode( self::calendar_id() ) . '/events/' . rawurlencode( $ev ) );
		if ( is_wp_error( $r ) ) {
			$code = (int) ( $r->get_error_data()['status'] ?? 0 );
			if ( 404 === $code || 410 === $code ) { self::cancelled_in_google( $id ); }
			return;
		}
		if ( isset( $r['status'] ) && 'cancelled' === $r['status'] ) { self::cancelled_in_google( $id ); return; }
		// A student who declines the invitation does not cancel anything: tell the owner once, who decides.
		$cust = strtolower( (string) get_post_meta( $id, '_dgb_email', true ) );
		foreach ( isset( $r['attendees'] ) ? $r['attendees'] : array() as $a ) {
			if ( strtolower( isset( $a['email'] ) ? $a['email'] : '' ) === $cust && isset( $a['responseStatus'] ) && 'declined' === $a['responseStatus'] && ! get_post_meta( $id, '_dgb_declined_notice', true ) ) {
				update_post_meta( $id, '_dgb_declined_notice', 1 );
				wp_mail( DGB_Settings::get( 'notify_email' ), 'Student declined the calendar invitation: ' . get_the_title( $id ),
					"The student declined the Google Calendar invitation. The booking is still active until you cancel it.\n\nContact them: " . get_post_meta( $id, '_dgb_phone', true ) . ' / ' . $cust . "\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
			}
		}
		if ( empty( $r['start']['dateTime'] ) || empty( $r['end']['dateTime'] ) ) { return; }
		$tz = wp_timezone();
		$s  = ( new DateTime( $r['start']['dateTime'] ) )->setTimezone( $tz );
		$e  = ( new DateTime( $r['end']['dateTime'] ) )->setTimezone( $tz );
		$date = $s->format( 'Y-m-d' ); $start = (int) $s->format( 'G' ) * 60 + (int) $s->format( 'i' ); $mins = (int) round( ( $e->getTimestamp() - $s->getTimestamp() ) / 60 );
		if ( $date === get_post_meta( $id, '_dgb_date', true ) && $start === (int) get_post_meta( $id, '_dgb_start', true ) && $mins === (int) get_post_meta( $id, '_dgb_minutes', true ) ) { return; }
		$old = DGB_Bookings::summary_lines( $id )['When'];
		update_post_meta( $id, '_dgb_date', $date ); update_post_meta( $id, '_dgb_start', $start ); update_post_meta( $id, '_dgb_minutes', $mins );
		wp_update_post( array( 'ID' => $id, 'post_title' => sprintf( '%s · %s (%s) · %s %s', get_post_meta( $id, '_dgb_name', true ), get_post_meta( $id, '_dgb_service_name', true ), get_post_meta( $id, '_dgb_option', true ), $date, DGB_Bookings::fmt_time( $start ) ) ) );
		wp_mail( DGB_Settings::get( 'notify_email' ), 'Booking moved in Google Calendar: ' . get_the_title( $id ),
			"A booking was moved from Google Calendar.\n\nBefore: $old\nNow: " . DGB_Bookings::summary_lines( $id )['When'] . "\n\nThe student has not been notified. Contact them: " . get_post_meta( $id, '_dgb_phone', true ) . "\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	}

	private static function cancelled_in_google( $id ) {
		delete_post_meta( $id, '_dgb_gcal_event' );
		update_post_meta( $id, '_dgb_status', 'cancelled' );
		update_post_meta( $id, '_dgb_cancel_src', 'google' );
		wp_mail( DGB_Settings::get( 'notify_email' ), 'Booking cancelled in Google Calendar: ' . get_the_title( $id ),
			"The event for this booking was deleted in Google Calendar, so the booking was marked as cancelled and the slot is free again.\n\nThe student has not been notified. Contact them: " . get_post_meta( $id, '_dgb_phone', true ) . ' / ' . get_post_meta( $id, '_dgb_email', true ) . "\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	}
}
