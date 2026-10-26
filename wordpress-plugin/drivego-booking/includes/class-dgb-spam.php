<?php
if ( ! defined( 'ABSPATH' ) && ! defined( 'DGB_TESTING' ) ) { exit; }

/**
 * Spam protection for the Elementor contact forms. Three layers, all checked on the server:
 * 1. a hidden trap field that people leave empty and bots fill in,
 * 2. a minimum time on the page before sending,
 * 3. Google reCAPTCHA v3 (invisible), when the keys are set in Bookings > Settings.
 */
class DGB_Spam {
	const ACTION = 'elementor_pro_atomic_forms_send_form';

	public static function init() {
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'check' ), 1 );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'check' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	public static function enabled_recaptcha() {
		return '' !== DGB_Settings::get( 'recaptcha_site' ) && '' !== DGB_Settings::get( 'recaptcha_secret' );
	}

	public static function enqueue() {
		wp_register_script( 'drivego-spam', DGB_URL . 'assets/spam.js', array(), DGB_VERSION, true );
		wp_localize_script( 'drivego-spam', 'DGBSPAM', array(
			'site'   => self::enabled_recaptcha() ? DGB_Settings::get( 'recaptcha_site' ) : '',
			'action' => 'contact_form',
		) );
		wp_enqueue_script( 'drivego-spam' );
	}

	/** Pure rule check used by the AJAX hook and by the tests. Returns an error string or ''. */
	public static function evaluate( $honeypot, $started_ms, $now_ms, $recaptcha_ok ) {
		if ( '' !== trim( (string) $honeypot ) ) { return 'trap'; }
		if ( $started_ms > 0 && ( $now_ms - $started_ms ) < 2500 ) { return 'too_fast'; }
		if ( ! $recaptcha_ok ) { return 'recaptcha'; }
		return '';
	}

	public static function verify_recaptcha( $token ) {
		if ( ! self::enabled_recaptcha() ) { return true; }
		if ( '' === (string) $token ) { return false; }
		$res = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 10,
			'body'    => array( 'secret' => DGB_Settings::get( 'recaptcha_secret' ), 'response' => $token, 'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ),
		) );
		if ( is_wp_error( $res ) ) { return true; } // never block real customers because Google is unreachable
		$j = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $j['success'] ) ) { return false; }
		if ( isset( $j['action'] ) && 'contact_form' !== $j['action'] ) { return false; }
		return ( isset( $j['score'] ) ? (float) $j['score'] : 1.0 ) >= (float) DGB_Settings::get( 'recaptcha_score' );
	}

	public static function check() {
		// phpcs:ignore WordPress.Security.NonceVerification -- Elementor verifies its own nonce right after this.
		$hp    = isset( $_POST['dgb_hp'] ) ? wp_unslash( $_POST['dgb_hp'] ) : '';
		$start = isset( $_POST['dgb_ts'] ) ? (int) $_POST['dgb_ts'] : 0;
		$tok   = isset( $_POST['dgb_recaptcha'] ) ? wp_unslash( $_POST['dgb_recaptcha'] ) : '';
		$err   = self::evaluate( $hp, $start, (int) round( microtime( true ) * 1000 ), self::verify_recaptcha( $tok ) );
		if ( '' === $err ) { return; }
		if ( 'trap' === $err ) { // pretend it worked so bots do not learn anything
			wp_send_json_success( array( 'message' => 'Your submission was successful.', 'data' => array( 'actionResults' => array(), 'allActionsSucceeded' => true, 'failedActions' => array() ) ) );
		}
		wp_send_json_error( array( 'message' => 'recaptcha' === $err ? 'We could not verify you are human. Please try again or call us.' : 'Please wait a moment and send again.' ), 400 );
	}
}
