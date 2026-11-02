<?php
/**
 * Pure scheduling logic (no WordPress calls) so it can be unit tested.
 * Times are minutes from midnight; dates are Y-m-d strings in the site time zone.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'DGB_TESTING' ) ) { exit; }

class DGB_Slots {

	/** Hours for a date: [open, close] in minutes, or null when closed. */
	public static function hours_for( $date, array $s ) {
		if ( in_array( $date, $s['closed_dates'], true ) ) { return null; }
		$dow = (int) gmdate( 'w', strtotime( $date . ' 12:00:00 UTC' ) );
		$h   = isset( $s['hours'][ $dow ] ) ? $s['hours'][ $dow ] : null;
		if ( ! $h || '' === $h[0] || '' === $h[1] ) { return null; }
		$open  = self::to_min( $h[0] );
		$close = self::to_min( $h[1] );
		return ( $open < $close ) ? array( $open, $close ) : null;
	}

	/** Lunch (or any) break inside the opening hours of a date: [from, to] in minutes, or null. */
	public static function break_for( $date, array $s ) {
		$dow = (int) gmdate( 'w', strtotime( $date . ' 12:00:00 UTC' ) );
		$b   = isset( $s['breaks'][ $dow ] ) ? $s['breaks'][ $dow ] : null;
		if ( ! $b || '' === $b[0] || '' === $b[1] ) { return null; }
		$from = self::to_min( $b[0] );
		$to   = self::to_min( $b[1] );
		return ( $from < $to ) ? array( $from, $to ) : null;
	}

	public static function to_min( $t ) {
		$p = explode( ':', $t );
		return (int) $p[0] * 60 + (int) ( isset( $p[1] ) ? $p[1] : 0 );
	}

	/** Number of existing bookings that overlap [start, end) once the travel buffer is added. */
	public static function overlaps( array $booked, $start, $end, $buffer ) {
		$n = 0;
		foreach ( $booked as $b ) {
			if ( $start < $b['end'] + $buffer && $end + $buffer > $b['start'] ) { $n++; }
		}
		return $n;
	}

	/**
	 * Free start times for one date.
	 *
	 * @param string $date      Y-m-d
	 * @param int    $minutes   Length of the service.
	 * @param array  $booked    List of ['start'=>min,'end'=>min] already taken that day.
	 * @param array  $s         Settings.
	 * @param int    $now_min   Current minute of the day when $date is today, else -1.
	 */
	public static function free_slots( $date, $minutes, array $booked, array $s, $now_min = -1 ) {
		$hours = self::hours_for( $date, $s );
		if ( ! $hours ) { return array(); }
		$break = self::break_for( $date, $s );
		$out = array();
		for ( $t = $hours[0]; $t + $minutes <= $hours[1]; $t += $s['slot_minutes'] ) {
			if ( $now_min >= 0 && $t <= $now_min + $s['lead_hours'] * 60 ) { continue; }
			if ( $break && $t < $break[1] && $t + $minutes > $break[0] ) { continue; } // a lesson cannot run into the break
			if ( self::overlaps( $booked, $t, $t + $minutes, $s['buffer_minutes'] ) < $s['capacity'] ) { $out[] = $t; }
		}
		return $out;
	}

	/** Is the date inside the booking window? $today and $date are Y-m-d. */
	public static function in_window( $date, $today, array $s ) {
		$d = strtotime( $date . ' UTC' );
		$t = strtotime( $today . ' UTC' );
		$days = (int) round( ( $d - $t ) / 86400 );
		return $days >= $s['lead_days'] && $days <= $s['horizon_days'];
	}
}
