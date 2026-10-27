<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DGB_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'manage_' . DGB_Bookings::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . DGB_Bookings::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_dgb_paid', array( __CLASS__, 'mark_paid' ) );
		add_action( 'admin_post_dgb_status', array( __CLASS__, 'change_status' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_filter( 'manage_edit-' . DGB_Bookings::CPT . '_sortable_columns', function ( $c ) { $c['dgb_when'] = 'dgb_when'; return $c; } );
		add_action( 'pre_get_posts', array( __CLASS__, 'order' ) );
		add_filter( 'bulk_actions-edit-' . DGB_Bookings::CPT, function ( $a ) { unset( $a['edit'] ); return $a; } );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . DGB_Bookings::CPT, 'Booking settings', 'Settings', 'manage_options', 'drivego-booking', array( __CLASS__, 'settings_page' ) );
	}

	public static function register() {
		register_setting( 'drivego_booking', DGB_Settings::OPTION, array( 'sanitize_callback' => array( 'DGB_Settings', 'sanitize' ) ) );
	}

	public static function order( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || DGB_Bookings::CPT !== $q->get( 'post_type' ) ) { return; }
		if ( ! $q->get( 'orderby' ) || 'dgb_when' === $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_dgb_date' ); $q->set( 'orderby', 'meta_value' ); $q->set( 'order', $q->get( 'order' ) ?: 'DESC' );
		}
	}

	public static function columns() {
		return array( 'cb' => '<input type="checkbox">', 'title' => 'Booking', 'dgb_when' => 'When', 'dgb_who' => 'Student', 'dgb_price' => 'Price', 'dgb_status' => 'Status', 'dgb_payment' => 'Payment' );
	}

	public static function column( $col, $id ) {
		$g = function ( $k ) use ( $id ) { return get_post_meta( $id, '_dgb_' . $k, true ); };
		switch ( $col ) {
			case 'dgb_when': echo esc_html( wp_date( 'D, M j, Y', strtotime( $g( 'date' ) . ' 12:00:00' ) ) . ' · ' . DGB_Bookings::fmt_time( (int) $g( 'start' ) ) ); break;
			case 'dgb_who':  echo esc_html( $g( 'name' ) ) . '<br><a href="tel:' . esc_attr( $g( 'phone' ) ) . '">' . esc_html( $g( 'phone' ) ) . '</a><br><a href="mailto:' . esc_attr( $g( 'email' ) ) . '">' . esc_html( $g( 'email' ) ) . '</a>'; break;
			case 'dgb_price': echo esc_html( '$' . number_format( (float) $g( 'price' ), 2 ) . ' + HST' ); break;
			case 'dgb_status':
				$st = $g( 'status' ) ?: 'pending';
				$colour = array( 'pending' => '#b26a00', 'confirmed' => '#1a7f37', 'cancelled' => '#a40e26' );
				echo '<strong style="color:' . esc_attr( $colour[ $st ] ) . '">' . esc_html( DGB_Bookings::statuses()[ $st ] ) . '</strong>'; break;
			case 'dgb_payment': echo ( 'paid' === $g( 'payment' ) ? '<strong style="color:#1a7f37">Paid</strong>' : 'Unpaid' ) . '<br><small>' . esc_html( DGB_Bookings::pay_label( $g( 'pay_method' ) ) ) . '</small>'; break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( DGB_Bookings::CPT !== $post->post_type ) { return $actions; }
		$mk = function ( $status, $label ) use ( $post ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=dgb_status&id=' . $post->ID . '&status=' . $status ), 'dgb_status_' . $post->ID );
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		};
		return array( 'dgb_confirm' => $mk( 'confirmed', 'Confirm' ), 'dgb_cancel' => $mk( 'cancelled', 'Cancel' ), 'trash' => isset( $actions['trash'] ) ? $actions['trash'] : '' );
	}

	public static function change_status() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'edit_post', $id ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_status_' . $id ) ) { wp_die( 'Not allowed.' ); }
		DGB_Bookings::set_status( $id, sanitize_key( isset( $_GET['status'] ) ? $_GET['status'] : '' ) );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'edit.php?post_type=' . DGB_Bookings::CPT ) );
		exit;
	}

	public static function mark_paid() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'edit_post', $id ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_paid_' . $id ) ) { wp_die( 'Not allowed.' ); }
		update_post_meta( $id, '_dgb_payment', 'paid' === get_post_meta( $id, '_dgb_payment', true ) ? 'unpaid' : 'paid' );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}

	public static function metabox() {
		add_meta_box( 'dgb_details', 'Booking details', array( __CLASS__, 'details' ), DGB_Bookings::CPT, 'normal', 'high' );
	}

	public static function details( $post ) {
		echo '<table class="widefat striped"><tbody>';
		foreach ( DGB_Bookings::summary_lines( $post->ID ) as $k => $v ) { echo '<tr><th style="width:120px">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>'; }
		$st = get_post_meta( $post->ID, '_dgb_status', true ) ?: 'pending';
		echo '<tr><th>Status</th><td>' . esc_html( DGB_Bookings::statuses()[ $st ] ) . '</td></tr>';
		echo '<tr><th>Payment</th><td>' . ( 'paid' === get_post_meta( $post->ID, '_dgb_payment', true ) ? 'Paid' : 'Unpaid' ) . ' · ' . esc_html( DGB_Bookings::pay_label( get_post_meta( $post->ID, '_dgb_pay_method', true ) ) ) . '</td></tr>';
		if ( $err = get_post_meta( $post->ID, '_dgb_square_error', true ) ) { echo '<tr><th>Square error</th><td>' . esc_html( $err ) . '</td></tr>'; }
		if ( DGB_Google::connected() ) {
			$gerr = get_post_meta( $post->ID, '_dgb_gcal_error', true );
			echo '<tr><th>Google Calendar</th><td>' . ( $gerr ? 'Error: ' . esc_html( $gerr ) : ( get_post_meta( $post->ID, '_dgb_gcal_event', true ) ? 'Event on the calendar' : 'No event' ) ) . ( 'google' === get_post_meta( $post->ID, '_dgb_cancel_src', true ) ? ' · cancelled from Google' : '' ) . '</td></tr>';
		}
		if ( $url = get_post_meta( $post->ID, '_dgb_pay_url', true ) ) { echo '<tr><th>Payment link</th><td><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Open</a></td></tr>'; }
		echo '</tbody></table><p>';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dgb_paid&id=' . $post->ID ), 'dgb_paid_' . $post->ID ) ) . '">' . ( 'paid' === get_post_meta( $post->ID, '_dgb_payment', true ) ? 'Mark as unpaid' : 'Mark as paid' ) . '</a> ';
		if ( DGB_Google::connected() ) {
			echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dgb_google_push&id=' . $post->ID ), 'dgb_gpush_' . $post->ID ) ) . '">Send to Google Calendar again</a> ';
		}
		foreach ( array( 'confirmed' => 'Confirm booking', 'cancelled' => 'Cancel booking' ) as $s => $label ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=dgb_status&id=' . $post->ID . '&status=' . $s ), 'dgb_status_' . $post->ID );
			echo '<a class="button' . ( 'confirmed' === $s ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '</p>';
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = DGB_Settings::all();
		$days = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		$n = DGB_Settings::OPTION;
		$webhook = rest_url( DGB_Rest::NS . '/square-webhook' );
		?>
		<div class="wrap"><h1>Booking settings</h1>
		<form method="post" action="options.php"><?php settings_fields( 'drivego_booking' ); ?>

		<h2>General</h2>
		<table class="form-table">
			<tr><th>Send booking emails to</th><td><input type="email" class="regular-text" name="<?php echo esc_attr( $n ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>"></td></tr>
			<tr><th>Business name in emails</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>"></td></tr>
			<tr><th>Phone shown to customers</th><td><input type="text" name="<?php echo esc_attr( $n ); ?>[phone]" value="<?php echo esc_attr( $s['phone'] ); ?>"></td></tr>
			<tr><th>Interac e-Transfer email</th><td><input type="email" class="regular-text" name="<?php echo esc_attr( $n ); ?>[etransfer_email]" value="<?php echo esc_attr( $s['etransfer_email'] ); ?>" placeholder="<?php echo esc_attr( $s['notify_email'] ); ?>"> <span class="description">Where students send e-Transfers. Empty uses the booking email above.</span></td></tr>
			<tr><th>Bookings at the same time</th><td><input type="number" min="1" max="20" name="<?php echo esc_attr( $n ); ?>[capacity]" value="<?php echo esc_attr( $s['capacity'] ); ?>"> <span class="description">How many lessons can run at once (cars or instructors available).</span></td></tr>
			<tr><th>Travel time between lessons</th><td><input type="number" min="0" max="240" step="5" name="<?php echo esc_attr( $n ); ?>[buffer_minutes]" value="<?php echo esc_attr( $s['buffer_minutes'] ); ?>"> minutes</td></tr>
			<tr><th>Start times every</th><td><input type="number" min="5" max="120" step="5" name="<?php echo esc_attr( $n ); ?>[slot_minutes]" value="<?php echo esc_attr( $s['slot_minutes'] ); ?>"> minutes</td></tr>
			<tr><th>Earliest booking</th><td><input type="number" min="0" max="60" name="<?php echo esc_attr( $n ); ?>[lead_days]" value="<?php echo esc_attr( $s['lead_days'] ); ?>"> days from today <span class="description">(1 = tomorrow)</span></td></tr>
			<tr><th>Furthest booking</th><td><input type="number" min="1" max="365" name="<?php echo esc_attr( $n ); ?>[horizon_days]" value="<?php echo esc_attr( $s['horizon_days'] ); ?>"> days ahead</td></tr>
			<tr><th>HST</th><td><input type="number" min="0" max="30" name="<?php echo esc_attr( $n ); ?>[hst_percent]" value="<?php echo esc_attr( $s['hst_percent'] ); ?>"> % added in Square checkout</td></tr>
		</table>

		<h2>Opening hours</h2>
		<p class="description">Leave both boxes empty on a day you are closed.</p>
		<table class="form-table"><?php foreach ( $days as $i => $label ) : $h = isset( $s['hours'][ $i ] ) ? $s['hours'][ $i ] : array( '', '' ); ?>
			<tr><th><?php echo esc_html( $label ); ?></th><td>
				<input type="time" name="<?php echo esc_attr( $n ); ?>[hours][<?php echo (int) $i; ?>][0]" value="<?php echo esc_attr( $h[0] ); ?>"> to
				<input type="time" name="<?php echo esc_attr( $n ); ?>[hours][<?php echo (int) $i; ?>][1]" value="<?php echo esc_attr( $h[1] ); ?>"></td></tr>
		<?php endforeach; ?>
			<tr><th>Closed dates</th><td><textarea name="<?php echo esc_attr( $n ); ?>[closed_dates]" rows="3" class="large-text" placeholder="2026-12-25, 2027-01-01"><?php echo esc_textarea( implode( ', ', $s['closed_dates'] ) ); ?></textarea><p class="description">Holidays or days off, format YYYY-MM-DD, separated by commas.</p></td></tr>
		</table>

		<h2>Services and prices</h2>
		<?php foreach ( $s['services'] as $key => $svc ) : ?>
			<h3><?php echo esc_html( $svc['name'] ); ?> <code>[drivego_booking service="<?php echo esc_html( $key ); ?>"]</code></h3>
			<p><label>Name <input type="text" name="<?php echo esc_attr( $n ); ?>[services][<?php echo esc_attr( $key ); ?>][name]" value="<?php echo esc_attr( $svc['name'] ); ?>"></label>
			<label>&nbsp; Day question <input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[services][<?php echo esc_attr( $key ); ?>][when]" value="<?php echo esc_attr( $svc['when'] ); ?>"></label></p>
			<table class="widefat" style="max-width:900px"><thead><tr><th>Option name</th><th>Minutes</th><th>Price (CAD, before HST)</th><th>Extra for home pick up</th><?php if ( 'refresher' === $key ) : ?><th>Extra when using DriveGo's car</th><?php endif; ?><?php if ( 'roadtest' === $key ) : ?><th>Home pick up and drop off included</th><?php endif; ?><th>Fixed Square link (optional)</th></tr></thead><tbody>
			<?php $opts = array_pad( $svc['options'], max( 5, count( $svc['options'] ) + 2 ), array( 'label' => '', 'minutes' => 60, 'price' => 0, 'pickup' => 0, 'pay' => '' ) );
			foreach ( $opts as $i => $o ) : $b = esc_attr( $n ) . '[services][' . esc_attr( $key ) . '][options][' . (int) $i . ']'; ?>
				<tr><td><input type="text" name="<?php echo $b; ?>[label]" value="<?php echo esc_attr( $o['label'] ); ?>"></td>
				<td><input type="number" min="15" step="15" style="width:80px" name="<?php echo $b; ?>[minutes]" value="<?php echo esc_attr( $o['minutes'] ); ?>"></td>
				<td><input type="number" min="0" step="0.01" style="width:100px" name="<?php echo $b; ?>[price]" value="<?php echo esc_attr( $o['price'] ); ?>"></td>
				<td><input type="number" min="0" step="0.01" style="width:100px" name="<?php echo $b; ?>[pickup]" value="<?php echo esc_attr( isset( $o['pickup'] ) ? $o['pickup'] : 0 ); ?>"></td>
				<?php if ( 'refresher' === $key ) : ?><td><input type="number" min="0" step="0.01" style="width:100px" name="<?php echo $b; ?>[car]" value="<?php echo esc_attr( isset( $o['car'] ) ? $o['car'] : 0 ); ?>"></td><?php endif; ?>
				<?php if ( 'roadtest' === $key ) : ?><td><label><input type="checkbox" name="<?php echo $b; ?>[home]" value="1" <?php checked( DGB_Settings::option_home( $o, $i ) ); ?>> yes</label></td><?php endif; ?>
				<td><input type="url" class="regular-text" name="<?php echo $b; ?>[pay]" value="<?php echo esc_attr( $o['pay'] ); ?>" placeholder="https://square.link/u/..."></td></tr>
			<?php endforeach; ?></tbody></table>
		<?php endforeach; ?>
		<p class="description">Empty option rows are ignored. "Extra for home pick up" is added to the price when the student asks to be picked up at home (always for road tests); leave 0 if pick up is included. For Refresher, the price is for the student's own car and "Extra when using DriveGo's car" is added when they choose the school car (leave 0 to hide that question). A fixed Square link is only used when the Square connection below is off.</p>

		<h2>Square payments</h2>
		<table class="form-table">
			<tr><th>Mode</th><td><select name="<?php echo esc_attr( $n ); ?>[square_env]"><option value="sandbox" <?php selected( $s['square_env'], 'sandbox' ); ?>>Sandbox (test, no real money)</option><option value="production" <?php selected( $s['square_env'], 'production' ); ?>>Production (real payments)</option></select></td></tr>
			<tr><th>Access token</th><td><input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $n ); ?>[square_token]" placeholder="<?php echo $s['square_token'] ? 'Saved. Type a new one to replace it.' : 'Paste the Square access token'; ?>">
				<?php if ( $s['square_token'] ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[square_token_clear]" value="1"> remove saved token</label><?php endif; ?>
				<p class="description">Square Developer Dashboard → your application → Credentials. Stored on this server only, never shown on the website.</p></td></tr>
			<tr><th>Location ID</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[square_location]" value="<?php echo esc_attr( $s['square_location'] ); ?>"></td></tr>
			<tr><th>Webhook signature key</th><td><input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $n ); ?>[square_webhook_key]" placeholder="<?php echo $s['square_webhook_key'] ? 'Saved. Type a new one to replace it.' : 'Optional: marks bookings as paid automatically'; ?>">
				<p class="description">In Square → Webhooks, add a subscription for <strong>payment.updated</strong> with this URL:<br><code><?php echo esc_html( $webhook ); ?></code></p></td></tr>
		</table>
		<h2 id="google">Google Calendar</h2>
		<?php $note = get_transient( 'dgb_google_notice' ); if ( $note ) { delete_transient( 'dgb_google_notice' ); echo '<div class="notice notice-' . ( $note['ok'] ? 'success' : 'error' ) . ' inline"><p>' . esc_html( $note['msg'] ) . '</p></div>'; }
		$ga = DGB_Google::auth(); $gn = wp_create_nonce( 'dgb_google' ); ?>
		<table class="form-table">
			<tr><th>Status</th><td><?php if ( DGB_Google::connected() ) : ?><strong style="color:#1a7f37">Connected</strong><?php echo ! empty( $ga['email'] ) ? ' as ' . esc_html( $ga['email'] ) : ''; ?>
				&nbsp; <a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=dgb_google_test&_wpnonce=' . $gn ) ); ?>">Test connection</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=dgb_google_disconnect&_wpnonce=' . $gn ) ); ?>">Disconnect</a>
				<?php elseif ( DGB_Google::configured() ) : ?>Not connected yet. Save the settings, then
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin-post.php?action=dgb_google_connect&_wpnonce=' . $gn ) ); ?>">Connect Google Calendar</a>
				<?php else : ?>Fill in the Client ID and secret below, save, and a Connect button will appear.<?php endif; ?></td></tr>
			<tr><th>OAuth Client ID</th><td><input type="text" class="large-text" name="<?php echo esc_attr( $n ); ?>[google_client_id]" value="<?php echo esc_attr( $s['google_client_id'] ); ?>"></td></tr>
			<tr><th>OAuth Client secret</th><td><input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $n ); ?>[google_client_secret]" placeholder="<?php echo $s['google_client_secret'] ? 'Saved. Type a new one to replace it.' : 'Paste the client secret'; ?>">
				<p class="description">Google Cloud Console → APIs &amp; Services → enable <strong>Google Calendar API</strong> → Credentials → Create OAuth client ID → <strong>Web application</strong>. Add this under "Authorized redirect URIs":<br><code><?php echo esc_html( DGB_Google::redirect_uri() ); ?></code></p></td></tr>
			<tr><th>Calendar</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[google_calendar]" value="<?php echo esc_attr( $s['google_calendar'] ); ?>"> <span class="description">"primary" is the main calendar of the connected account. For a dedicated calendar paste its ID (Calendar settings → Integrate calendar).</span></td></tr>
			<tr><th>Instructor email(s)</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[google_instructors]" value="<?php echo esc_attr( $s['google_instructors'] ); ?>" placeholder="instructor@gmail.com, another@gmail.com"> <span class="description">Each booking is sent to them as an invitation, so it shows on their calendar too. Separate several with commas.</span></td></tr>
			<tr><th>Invite the student</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[google_invite_customer]" value="1" <?php checked( $s['google_invite_customer'] ); ?>> When you confirm a booking, send the student a calendar invitation</label></td></tr>
		</table>
		<p class="description">Both directions: new bookings appear in Google Calendar, and anything you or the instructor add there (as busy) blocks those hours on the website. Events moved or deleted in Google update the booking within 10 minutes. Mark an event as "Free" in Google if it should not block the website.</p>

		<h2>Spam protection (contact forms)</h2>
		<table class="form-table">
			<tr><th>reCAPTCHA v3 site key</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[recaptcha_site]" value="<?php echo esc_attr( $s['recaptcha_site'] ); ?>"><p class="description">Create a <strong>reCAPTCHA v3</strong> key at google.com/recaptcha/admin and add your domain.</p></td></tr>
			<tr><th>reCAPTCHA v3 secret key</th><td><input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $n ); ?>[recaptcha_secret]" placeholder="<?php echo $s['recaptcha_secret'] ? 'Saved. Type a new one to replace it.' : 'Paste the secret key'; ?>">
				<?php if ( $s['recaptcha_secret'] ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[recaptcha_secret_clear]" value="1"> remove saved key</label><?php endif; ?></td></tr>
			<tr><th>Minimum score</th><td><input type="number" min="0.1" max="0.9" step="0.1" style="width:80px" name="<?php echo esc_attr( $n ); ?>[recaptcha_score]" value="<?php echo esc_attr( $s['recaptcha_score'] ); ?>"> <span class="description">0.5 is the usual value. Raise it to block more, lower it if real people are blocked.</span></td></tr>
		</table>
		<p class="description">Without the keys the forms are still protected by a hidden trap field and a minimum time on the page.</p>
		<?php submit_button(); ?></form></div>
		<?php
	}
}
