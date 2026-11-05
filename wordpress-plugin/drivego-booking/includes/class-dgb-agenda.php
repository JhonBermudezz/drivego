<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bookings > Agenda: one week view with what the website knows (bookings, lunch, opening hours) and what is
 * in Google Calendar, so the owner sees the same picture the website uses to offer times.
 * Also lets the owner block time from WordPress; the block goes to Google and hides those times on the website.
 */
class DGB_Agenda {
	const SLUG = 'drivego-agenda';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );
		add_action( 'admin_post_dgb_block', array( __CLASS__, 'block' ) );
		add_action( 'admin_post_dgb_unblock', array( __CLASS__, 'unblock' ) );
		add_action( 'admin_notices', array( __CLASS__, 'problem_notice' ) );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . DGB_Bookings::CPT, 'Agenda', 'Agenda', 'edit_posts', self::SLUG, array( __CLASS__, 'page' ) );
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'post_type' => DGB_Bookings::CPT, 'page' => self::SLUG ), $args ), admin_url( 'edit.php' ) );
	}

	/** A red notice on every Bookings screen while Google cannot be read. */
	public static function problem_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, DGB_Bookings::CPT ) || ! DGB_Google::connected() ) { return; }
		$h = DGB_Google::health();
		if ( $h && empty( $h['ok'] ) ) {
			echo '<div class="notice notice-error"><p><strong>Google Calendar is not being read.</strong> The website may offer times that are taken. ' . esc_html( $h['msg'] ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . DGB_Bookings::CPT . '&page=drivego-booking#google' ) ) . '">Open the settings</a></p></div>';
		}
	}

	/** Everything that happens on one date, sorted: [start, end, title, kind, extra]. */
	public static function day_items( $date, array $bookings, array $gevents, array $s ) {
		$items = array();
		foreach ( $bookings as $b ) {
			if ( $b['date'] !== $date ) { continue; }
			$items[] = array( 'start' => $b['start'], 'end' => $b['end'], 'title' => $b['title'], 'kind' => 'booking-' . $b['status'], 'url' => $b['url'], 'tag' => 'pending' === $b['status'] ? 'Pending' : 'Confirmed', 'paid' => $b['paid'] );
		}
		$block_free = (bool) DGB_Settings::get( 'google_block_free' );
		foreach ( $gevents as $e ) {
			if ( $e['booking'] ) { continue; } // already shown as a website booking
			foreach ( DGB_Google::clip( array( $e ), $date ) as $r ) {
				$blocking = $e['block'] || ! $e['free'] || $block_free;
				$items[] = array( 'start' => $r['start'], 'end' => $r['end'], 'title' => $e['summary'], 'kind' => $e['block'] ? 'block' : ( $blocking ? 'google' : 'free' ), 'url' => $e['link'], 'tag' => $e['block'] ? 'Blocked' : ( $blocking ? 'Google' : 'Free, not blocking' ), 'all_day' => $e['all_day'], 'event' => $e['block'] ? $e['id'] : '', 'cal' => $e['cal'] );
			}
		}
		$br = DGB_Slots::break_for( $date, $s );
		if ( $br ) { $items[] = array( 'start' => $br[0], 'end' => $br[1], 'title' => 'Lunch (not bookable)', 'kind' => 'lunch', 'url' => '', 'tag' => 'Lunch' ); }
		usort( $items, function ( $a, $b ) { return $a['start'] - $b['start'] ?: $a['end'] - $b['end']; } );
		return $items;
	}

	/** Bookings of the website between two dates (cancelled and packages left out). */
	private static function bookings_between( $from, $to ) {
		$ids = get_posts( array(
			'post_type' => DGB_Bookings::CPT, 'post_status' => 'any', 'numberposts' => 300, 'fields' => 'ids',
			'meta_query' => array(
				array( 'key' => '_dgb_date', 'value' => array( $from, $to ), 'compare' => 'BETWEEN', 'type' => 'CHAR' ),
				array( 'key' => '_dgb_status', 'value' => array( 'pending', 'confirmed' ), 'compare' => 'IN' ),
			),
		) );
		$out = array();
		foreach ( $ids as $id ) {
			if ( 'package' === get_post_meta( $id, '_dgb_kind', true ) ) { continue; }
			$start = (int) get_post_meta( $id, '_dgb_start', true );
			$out[] = array( 'date' => get_post_meta( $id, '_dgb_date', true ), 'start' => $start, 'end' => $start + (int) get_post_meta( $id, '_dgb_minutes', true ),
				'title' => get_post_meta( $id, '_dgb_name', true ) . ' · ' . get_post_meta( $id, '_dgb_service_name', true ) . ' (' . get_post_meta( $id, '_dgb_option', true ) . ')',
				'status' => get_post_meta( $id, '_dgb_status', true ) ?: 'pending', 'paid' => 'paid' === get_post_meta( $id, '_dgb_payment', true ), 'url' => admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		}
		return $out;
	}

	/** Create a busy event in Google so that time is hidden on the website. */
	public static function block() {
		if ( ! current_user_can( 'edit_posts' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '', 'dgb_block' ) ) { wp_die( 'Not allowed.' ); }
		$date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
		$from = isset( $_POST['from'] ) ? sanitize_text_field( $_POST['from'] ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( $_POST['to'] ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_text_field( $_POST['note'] ) : '';
		$week = $date ? gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $date . ' 12:00:00 UTC' ) ) ) : '';
		$msg  = '';
		if ( ! DGB_Google::connected() ) { $msg = 'Connect Google Calendar first.'; }
		elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $from ) || ! preg_match( '/^\d{2}:\d{2}$/', $to ) || $from >= $to ) { $msg = 'Choose a date and a start time before the end time.'; }
		else {
			$tz = wp_timezone();
			$s  = new DateTime( $date . ' ' . $from . ':00', $tz );
			$e  = new DateTime( $date . ' ' . $to . ':00', $tz );
			$r  = DGB_Google::api( 'POST', 'calendars/' . rawurlencode( DGB_Google::calendar_id() ) . '/events?sendUpdates=none', array(
				'summary' => 'Blocked' . ( $note ? ': ' . $note : ' from the website' ),
				'description' => 'Time blocked in DriveGo Bookings > Agenda. The website does not offer lessons at this time.',
				'start' => array( 'dateTime' => $s->format( 'c' ), 'timeZone' => $tz->getName() ), 'end' => array( 'dateTime' => $e->format( 'c' ), 'timeZone' => $tz->getName() ),
				'transparency' => 'opaque', 'colorId' => '8', 'reminders' => array( 'useDefault' => false ),
				'extendedProperties' => array( 'private' => array( 'dgb_block' => '1' ) ),
			) );
			$msg = is_wp_error( $r ) ? 'Google refused it: ' . $r->get_error_message() : 'Time blocked. It is in Google Calendar and hidden on the website.';
			DGB_Google::forget_busy( $date );
		}
		set_transient( 'dgb_agenda_notice', array( 'ok' => 0 === strpos( $msg, 'Time blocked' ), 'msg' => $msg ), 60 );
		wp_safe_redirect( self::url( $week ? array( 'w' => $week ) : array() ) );
		exit;
	}

	public static function unblock() {
		$ev  = isset( $_GET['event'] ) ? sanitize_text_field( $_GET['event'] ) : '';
		$cal = isset( $_GET['cal'] ) ? sanitize_text_field( $_GET['cal'] ) : '';
		if ( ! current_user_can( 'edit_posts' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_unblock_' . $ev ) ) { wp_die( 'Not allowed.' ); }
		$r = DGB_Google::api( 'DELETE', 'calendars/' . rawurlencode( $cal ) . '/events/' . rawurlencode( $ev ) . '?sendUpdates=none' );
		delete_transient( 'dgb_gev_' . gmdate( 'Y-m' ) ); delete_transient( 'dgb_gev_' . gmdate( 'Y-m', strtotime( '+1 month' ) ) ); delete_transient( 'dgb_gev_' . gmdate( 'Y-m', strtotime( '-1 month' ) ) );
		set_transient( 'dgb_agenda_notice', array( 'ok' => ! is_wp_error( $r ), 'msg' => is_wp_error( $r ) ? 'Google refused it: ' . $r->get_error_message() : 'Block removed. Those times are bookable again.' ), 60 );
		wp_safe_redirect( wp_get_referer() ?: self::url() );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		$s    = DGB_Settings::all();
		$tz   = wp_timezone();
		$ref  = isset( $_GET['w'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['w'] ) ? $_GET['w'] : wp_date( 'Y-m-d' );
		$mon  = new DateTime( $ref . ' 12:00:00', $tz ); $mon->modify( 'monday this week' );
		$days = array();
		for ( $i = 0; $i < 7; $i++ ) { $d = clone $mon; $d->modify( '+' . $i . ' day' ); $days[] = $d; }
		$from = $days[0]->format( 'Y-m-d' ); $to = $days[6]->format( 'Y-m-d' );
		if ( isset( $_GET['refresh'] ) && wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_agenda_refresh' ) ) {
			foreach ( array_unique( array( substr( $from, 0, 7 ), substr( $to, 0, 7 ) ) ) as $m ) { delete_transient( 'dgb_gev_' . $m ); }
		}
		$connected = DGB_Google::connected();
		$gev = array();
		if ( $connected ) { foreach ( array_unique( array( substr( $from, 0, 7 ), substr( $to, 0, 7 ) ) ) as $m ) { $gev = array_merge( $gev, DGB_Google::month_events( $m ) ); } }
		$bk   = self::bookings_between( $from, $to );
		$h    = DGB_Google::health();
		$prev = ( clone $mon )->modify( '-7 day' )->format( 'Y-m-d' ); $next = ( clone $mon )->modify( '+7 day' )->format( 'Y-m-d' );
		$note = get_transient( 'dgb_agenda_notice' ); if ( $note ) { delete_transient( 'dgb_agenda_notice' ); }
		$cals = $connected ? array_map( function ( $c ) { $all = DGB_Google::calendars(); return isset( $all[ $c ] ) ? $all[ $c ] : $c; }, DGB_Google::read_calendars() ) : array();
		?>
		<style>
			.dgb-ag{max-width:1500px}
			.dgb-ag-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;margin-top:14px}
			@media (max-width:1180px){.dgb-ag-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
			@media (max-width:640px){.dgb-ag-grid{grid-template-columns:1fr}}
			.dgb-day{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px;min-height:140px}
			.dgb-day.is-today{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
			.dgb-day.is-past{opacity:.6}
			.dgb-day h3{margin:0 0 2px;font-size:14px}
			.dgb-day .sub{color:#646970;font-size:12px;margin-bottom:8px}
			.dgb-it{border-left:4px solid #8c8f94;background:#f6f7f7;padding:6px 8px;margin:6px 0;border-radius:4px;font-size:12px;line-height:1.35}
			.dgb-it b{display:block}
			.dgb-it .tag{display:inline-block;font-size:11px;color:#50575e}
			.dgb-it a{text-decoration:none}
			.dgb-booking-confirmed{border-left-color:#1a7f37;background:#edf7ee}
			.dgb-booking-pending{border-left-color:#dba617;background:#fcf6e5}
			.dgb-google{border-left-color:#2271b1;background:#eaf2fa}
			.dgb-block{border-left-color:#1d2327;background:#e9e9ea}
			.dgb-free{border-left-color:#c3c4c7;background:#fff;color:#646970;border-style:dashed;border-width:1px 1px 1px 4px}
			.dgb-lunch{border-left-color:#b26a00;background:#fff4e0}
			.dgb-empty{color:#8c8f94;font-size:12px}
			.dgb-legend span{display:inline-block;margin-right:14px;font-size:12px}
			.dgb-legend i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:4px;vertical-align:-1px}
		</style>
		<div class="wrap dgb-ag"><h1 class="wp-heading-inline">Agenda</h1>
		<?php if ( $note ) { echo '<div class="notice notice-' . ( $note['ok'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $note['msg'] ) . '</p></div>'; } ?>
		<p style="margin:6px 0">
			<a class="button" href="<?php echo esc_url( self::url( array( 'w' => $prev ) ) ); ?>">&larr; Previous week</a>
			<a class="button" href="<?php echo esc_url( self::url() ); ?>">This week</a>
			<a class="button" href="<?php echo esc_url( self::url( array( 'w' => $next ) ) ); ?>">Next week &rarr;</a>
			<strong style="margin-left:12px"><?php echo esc_html( $days[0]->format( 'M j' ) . ' – ' . $days[6]->format( 'M j, Y' ) ); ?></strong>
			<?php if ( $connected ) : ?><a class="button" style="margin-left:12px" href="<?php echo esc_url( wp_nonce_url( self::url( array( 'w' => $from, 'refresh' => 1 ) ), 'dgb_agenda_refresh' ) ); ?>">Refresh from Google</a><?php endif; ?>
		</p>
		<?php if ( ! $connected ) : ?>
			<div class="notice notice-warning inline"><p>Google Calendar is not connected, so this shows only the website bookings. Connect it in <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . DGB_Bookings::CPT . '&page=drivego-booking#google' ) ); ?>">Settings</a> and the website will also hide the times busy in your calendar.</p></div>
		<?php elseif ( $h && empty( $h['ok'] ) ) : ?>
			<div class="notice notice-error inline"><p><strong>Google Calendar could not be read.</strong> <?php echo esc_html( $h['msg'] ); ?> Until this is fixed the website cannot hide your busy times. <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . DGB_Bookings::CPT . '&page=drivego-booking#google' ) ); ?>">Open the Google settings</a></p></div>
		<?php else : $auth = DGB_Google::auth(); ?>
			<p class="description" style="margin:0 0 6px">Reading <strong><?php echo esc_html( implode( ', ', $cals ) ); ?></strong> from <?php echo esc_html( ! empty( $auth['email'] ) ? $auth['email'] : 'your Google account' ); ?>. The website hides anything busy here.</p>
		<?php endif; ?>
		<p class="dgb-legend"><span><i style="background:#1a7f37"></i>Confirmed booking</span><span><i style="background:#dba617"></i>Pending booking</span><span><i style="background:#2271b1"></i>Google event (blocks)</span><span><i style="background:#1d2327"></i>Blocked here</span><span><i style="background:#b26a00"></i>Lunch</span><span><i style="background:#c3c4c7"></i>Free in Google (does not block)</span></p>
		<div class="dgb-ag-grid">
		<?php
		$today = wp_date( 'Y-m-d' );
		foreach ( $days as $d ) :
			$date  = $d->format( 'Y-m-d' );
			$hrs   = DGB_Slots::hours_for( $date, $s );
			$items = self::day_items( $date, $bk, $gev, $s );
			$cls   = 'dgb-day' . ( $date === $today ? ' is-today' : '' ) . ( $date < $today ? ' is-past' : '' );
			?>
			<div class="<?php echo esc_attr( $cls ); ?>">
				<h3><?php echo esc_html( $d->format( 'D, M j' ) ); ?></h3>
				<div class="sub"><?php echo $hrs ? esc_html( DGB_Bookings::fmt_time( $hrs[0] ) . ' – ' . DGB_Bookings::fmt_time( $hrs[1] ) ) : 'Closed'; ?></div>
				<?php if ( ! $items ) { echo '<div class="dgb-empty">Nothing booked</div>'; }
				foreach ( $items as $it ) :
					$when = ! empty( $it['all_day'] ) ? 'All day' : DGB_Bookings::fmt_time( $it['start'] ) . ' – ' . ( $it['end'] >= 1440 ? '12:00 AM' : DGB_Bookings::fmt_time( $it['end'] ) );
					$k    = $it['kind'];
					$title = esc_html( $it['title'] );
					if ( ! empty( $it['url'] ) ) { $title = '<a href="' . esc_url( $it['url'] ) . '"' . ( 0 === strpos( $k, 'booking' ) ? '' : ' target="_blank" rel="noopener"' ) . '>' . $title . '</a>'; }
					?>
					<div class="dgb-it dgb-<?php echo esc_attr( $k ); ?>"><b><?php echo esc_html( $when ); ?></b><?php echo $title; // phpcs:ignore ?>
						<br><span class="tag"><?php echo esc_html( $it['tag'] . ( ! empty( $it['paid'] ) ? ' · Paid' : '' ) ); ?></span>
						<?php if ( ! empty( $it['event'] ) ) : ?> · <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dgb_unblock&event=' . rawurlencode( $it['event'] ) . '&cal=' . rawurlencode( $it['cal'] ) ), 'dgb_unblock_' . $it['event'] ) ); ?>">Remove block</a><?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		</div>
		<?php if ( $connected ) : ?>
		<h2 style="margin-top:28px">Block time</h2>
		<p class="description">Need a doctor visit or a day off? This puts a busy event in your Google Calendar and the website stops offering those times.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
			<input type="hidden" name="action" value="dgb_block"><?php wp_nonce_field( 'dgb_block' ); ?>
			<input type="date" name="date" value="<?php echo esc_attr( $today ); ?>" required>
			<input type="time" name="from" value="09:00" required> to <input type="time" name="to" value="10:00" required>
			<input type="text" name="note" placeholder="Reason (optional)" class="regular-text">
			<button class="button button-primary">Block this time</button>
		</form>
		<?php endif; ?>
		</div>
		<?php
	}
}
