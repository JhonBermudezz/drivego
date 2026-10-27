<?php
/**
 * Plugin Name: DriveGo Booking
 * Description: Booking calendar for DriveGo London: lessons, refresher and road test packages, with Square payments and an admin dashboard.
 * Version:     1.9.2
 * Requires PHP: 7.4
 * Author: DriveGo London
 * Text Domain: drivego-booking
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'DGB_VERSION', '1.9.2' );
define( 'DGB_DIR', plugin_dir_path( __FILE__ ) );
define( 'DGB_URL', plugin_dir_url( __FILE__ ) );

require_once DGB_DIR . 'includes/class-dgb-slots.php';
require_once DGB_DIR . 'includes/class-dgb-settings.php';
require_once DGB_DIR . 'includes/class-dgb-square.php';
require_once DGB_DIR . 'includes/class-dgb-google.php';
require_once DGB_DIR . 'includes/class-dgb-bookings.php';
require_once DGB_DIR . 'includes/class-dgb-rest.php';
require_once DGB_DIR . 'includes/class-dgb-admin.php';
require_once DGB_DIR . 'includes/class-dgb-mail.php';
require_once DGB_DIR . 'includes/class-dgb-spam.php';

add_action( 'plugins_loaded', function () {
	DGB_Bookings::init();
	DGB_Google::init();
	DGB_Rest::init();
	DGB_Admin::init();
	DGB_Mail::init();
	DGB_Spam::init();
} );

/* Front end: script and styles load only where the calendar can appear. */
add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'drivego-booking', DGB_URL . 'assets/booking.css', array(), DGB_VERSION );
	wp_register_script( 'drivego-booking', DGB_URL . 'assets/booking.js', array(), DGB_VERSION, true );
	if ( ! apply_filters( 'dgb_load_everywhere', true ) ) { return; }
	DGB_Plugin::enqueue();
} );

class DGB_Plugin {
	public static function enqueue() {
		wp_enqueue_style( 'drivego-booking' );
		wp_enqueue_script( 'drivego-booking' );
		wp_localize_script( 'drivego-booking', 'DGB', array(
			'rest'     => esc_url_raw( rest_url( 'drivego/v1/' ) ),
			'services' => DGB_Settings::public_services(),
			'window'   => array(
				'leadDays'    => (int) DGB_Settings::get( 'lead_days' ),
				'horizonDays' => (int) DGB_Settings::get( 'horizon_days' ),
				'today'       => wp_date( 'Y-m-d' ),
			),
			'phone'    => DGB_Settings::get( 'phone' ),
			'pay'      => DGB_Settings::pay_methods(),
			'etransfer'=> DGB_Settings::etransfer_email(),
		) );
	}
}

/* Shortcode: [drivego_booking service="lessons"] puts an inline calendar on any page. */
add_shortcode( 'drivego_booking', function ( $atts ) {
	$a = shortcode_atts( array( 'service' => 'lessons' ), $atts );
	DGB_Plugin::enqueue();
	return '<div class="dgb-mount" data-booking-calendar="' . esc_attr( $a['service'] ) . '"></div>';
} );

register_activation_hook( __FILE__, function () {
	DGB_Settings::ensure_defaults();
	DGB_Bookings::register_post_type();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( DGB_Google::CRON );
} );
