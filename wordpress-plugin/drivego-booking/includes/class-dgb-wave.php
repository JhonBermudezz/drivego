<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Wave accounting (waveapps.com). When a booking or package is paid, create the customer and an invoice in Wave,
 * approve it and record the payment. Wave errors are saved on the booking and never stop a booking or a payment.
 * The access token only lives on the server.
 */
class DGB_Wave {
	const ENDPOINT = 'https://gql.waveapps.com/graphql/public';
	const CACHE    = 'dgb_wave_cache';

	public static function init() {
		add_action( 'admin_post_dgb_wave_load', array( __CLASS__, 'load' ) );
		add_action( 'admin_post_dgb_wave_send', array( __CLASS__, 'send_now' ) );
		add_action( 'dgb_wave_poll', array( __CLASS__, 'poll' ) );
		add_action( 'init', function () {
			if ( DGB_Settings::get( 'wave_pay' ) && self::configured() && ! wp_next_scheduled( 'dgb_wave_poll' ) ) { wp_schedule_event( time() + 120, 'dgb_ten_minutes', 'dgb_wave_poll' ); }
		} );
	}

	public static function configured() {
		return '' !== DGB_Settings::get( 'wave_token' ) && '' !== DGB_Settings::get( 'wave_business' ) && '' !== DGB_Settings::get( 'wave_product' ) && '' !== DGB_Settings::get( 'wave_account' );
	}

	/** Run one GraphQL document. @return array|WP_Error the "data" object */
	public static function gql( $query, array $vars = array() ) {
		$res = wp_remote_post( self::ENDPOINT, array(
			'timeout' => 25,
			'headers' => array( 'Authorization' => 'Bearer ' . DGB_Settings::get( 'wave_token' ), 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'query' => $query, 'variables' => (object) $vars ) ),
		) );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 401 === $code || 403 === $code ) { return new WP_Error( 'dgb_wave', 'Wave refused the token (HTTP ' . $code . '). Check it in the Wave developer portal.' ); }
		if ( ! is_array( $json ) ) { return new WP_Error( 'dgb_wave', 'Wave answered with HTTP ' . $code . '.' ); }
		if ( ! empty( $json['errors'] ) ) {
			$m = isset( $json['errors'][0]['message'] ) ? $json['errors'][0]['message'] : 'Wave returned an error.';
			return new WP_Error( 'dgb_wave', $m );
		}
		return isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : array();
	}

	/** Mutations answer with didSucceed and inputErrors. */
	private static function check( $payload, $what ) {
		if ( is_wp_error( $payload ) ) { return $payload; }
		if ( empty( $payload['didSucceed'] ) ) {
			$msg = isset( $payload['inputErrors'][0]['message'] ) ? $payload['inputErrors'][0]['message'] : 'Wave did not accept it.';
			return new WP_Error( 'dgb_wave', $what . ': ' . $msg );
		}
		return $payload;
	}

	/* ---------- admin: load the choices (business, product, account, tax) ---------- */

	public static function cache() {
		$c = get_option( self::CACHE, array() );
		return is_array( $c ) ? $c : array();
	}

	public static function load() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_wave' ) ) { wp_die( 'Not allowed.' ); }
		$back = function ( $msg, $ok ) {
			set_transient( 'dgb_wave_notice', array( 'ok' => $ok, 'msg' => $msg ), 120 );
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . DGB_Bookings::CPT . '&page=drivego-booking#wave' ) );
			exit;
		};
		if ( '' === DGB_Settings::get( 'wave_token' ) ) { $back( 'Paste the Wave token and save the settings first.', false ); }
		$d = self::gql( 'query { businesses(page: 1, pageSize: 50) { edges { node { id name } } } }' );
		if ( is_wp_error( $d ) ) { $back( 'Could not reach Wave: ' . $d->get_error_message(), false ); }
		$cache = array( 'businesses' => array(), 'products' => array(), 'accounts' => array(), 'taxes' => array() );
		foreach ( isset( $d['businesses']['edges'] ) ? $d['businesses']['edges'] : array() as $e ) { $cache['businesses'][ $e['node']['id'] ] = $e['node']['name']; }
		if ( ! $cache['businesses'] ) { $back( 'The token works but Wave shows no businesses for it.', false ); }
		$biz = DGB_Settings::get( 'wave_business' );
		if ( ! isset( $cache['businesses'][ $biz ] ) ) { $biz = (string) key( $cache['businesses'] ); DGB_Settings::update( array( 'wave_business' => $biz ) ); }
		$q = 'query($id: ID!) { business(id: $id) {
			products(page: 1, pageSize: 100) { edges { node { id name isArchived } } }
			accounts(page: 1, pageSize: 200, subtypes: [CASH_AND_BANK]) { edges { node { id name } } }
			salesTaxes(page: 1, pageSize: 50) { edges { node { id name abbreviation isArchived } } }
		} }';
		$b = self::gql( $q, array( 'id' => $biz ) );
		if ( is_wp_error( $b ) ) { $back( 'Connected, but Wave could not list the products and accounts: ' . $b->get_error_message(), false ); }
		$bz = isset( $b['business'] ) ? $b['business'] : array();
		foreach ( isset( $bz['products']['edges'] ) ? $bz['products']['edges'] : array() as $e ) { if ( empty( $e['node']['isArchived'] ) ) { $cache['products'][ $e['node']['id'] ] = $e['node']['name']; } }
		foreach ( isset( $bz['accounts']['edges'] ) ? $bz['accounts']['edges'] : array() as $e ) { $cache['accounts'][ $e['node']['id'] ] = $e['node']['name']; }
		foreach ( isset( $bz['salesTaxes']['edges'] ) ? $bz['salesTaxes']['edges'] : array() as $e ) { if ( empty( $e['node']['isArchived'] ) ) { $cache['taxes'][ $e['node']['id'] ] = $e['node']['name'] . ( ! empty( $e['node']['abbreviation'] ) ? ' (' . $e['node']['abbreviation'] . ')' : '' ); } }
		update_option( self::CACHE, $cache, false );
		$back( sprintf( 'Connected to Wave: %d business(es), %d products, %d bank accounts, %d taxes. Choose the product, account and tax below, then save.', count( $cache['businesses'] ), count( $cache['products'] ), count( $cache['accounts'] ), count( $cache['taxes'] ) ), true );
	}

	/* ---------- invoices ---------- */

	private static function money( $n ) { return number_format( round( (float) $n, 2 ), 2, '.', '' ); }

	private static function method( $pay ) {
		return array( 'square' => 'CREDIT_CARD', 'etransfer' => 'BANK_TRANSFER', 'cash' => 'CASH' )[ $pay ] ?? 'UNSPECIFIED';
	}

	/** Called when a booking or package becomes paid. Safe to call many times: only the first creates an invoice. */
	public static function on_paid( $id ) {
		if ( ! DGB_Settings::get( 'wave_auto' ) || ! self::configured() ) { return; }
		if ( get_post_meta( $id, '_dgb_wave_invoice', true ) ) { return; }
		$r = self::invoice( $id );
		if ( is_wp_error( $r ) ) { update_post_meta( $id, '_dgb_wave_error', $r->get_error_message() ); }
		else { delete_post_meta( $id, '_dgb_wave_error' ); }
	}

	/** @return string|WP_Error Wave invoice id */
	public static function invoice( $id ) {
		$biz  = DGB_Settings::get( 'wave_business' );
		$mail = get_post_meta( $id, '_dgb_email', true );

		$cid = self::customer( $id );
		if ( is_wp_error( $cid ) ) { return $cid; }

		// 2. invoice, one line with the service and the tax chosen in settings
		$lines = DGB_Bookings::summary_lines( $id );
		$desc  = isset( $lines['Service'] ) ? $lines['Service'] : $lines['Package'];
		if ( ! empty( $lines['When'] ) ) { $desc .= ' · ' . $lines['When']; }
		$net   = (float) get_post_meta( $id, '_dgb_price', true );
		$item  = array( 'productId' => DGB_Settings::get( 'wave_product' ), 'description' => $desc, 'quantity' => '1', 'unitPrice' => self::money( $net ) );
		if ( DGB_Settings::get( 'wave_tax' ) ) { $item['taxes'] = array( array( 'salesTaxId' => DGB_Settings::get( 'wave_tax' ) ) ); }
		$d = self::gql( 'mutation($input: InvoiceCreateInput!) { invoiceCreate(input: $input) { didSucceed inputErrors { path message code } invoice { id invoiceNumber } } }',
			array( 'input' => array( 'businessId' => $biz, 'customerId' => $cid, 'status' => 'DRAFT', 'invoiceDate' => wp_date( 'Y-m-d' ), 'currency' => 'CAD', 'title' => 'Invoice', 'memo' => 'DriveGo booking #' . $id, 'items' => array( $item ) ) ) );
		$p = self::check( is_wp_error( $d ) ? $d : $d['invoiceCreate'], 'Invoice' );
		if ( is_wp_error( $p ) ) { return $p; }
		$inv = $p['invoice']['id'];
		update_post_meta( $id, '_dgb_wave_invoice', $inv );
		update_post_meta( $id, '_dgb_wave_number', isset( $p['invoice']['invoiceNumber'] ) ? $p['invoice']['invoiceNumber'] : '' );

		// 3. approve so it becomes a real invoice
		$d = self::gql( 'mutation($input: InvoiceApproveInput!) { invoiceApprove(input: $input) { didSucceed inputErrors { path message code } } }', array( 'input' => array( 'invoiceId' => $inv ) ) );
		$p = self::check( is_wp_error( $d ) ? $d : $d['invoiceApprove'], 'Approve' );
		if ( is_wp_error( $p ) ) { return $p; }

		// 4. record the payment (the invoice total includes the tax)
		$hst   = (float) DGB_Settings::get( 'hst_percent' );
		$gross = DGB_Settings::get( 'wave_tax' ) ? $net * ( 1 + $hst / 100 ) : $net;
		$d = self::gql( 'mutation($input: InvoicePaymentCreateManualInput!) { invoicePaymentCreateManual(input: $input) { didSucceed inputErrors { path message code } } }',
			array( 'input' => array( 'invoiceId' => $inv, 'paymentAccountId' => DGB_Settings::get( 'wave_account' ), 'amount' => self::money( $gross ), 'paymentDate' => wp_date( 'Y-m-d' ), 'paymentMethod' => self::method( get_post_meta( $id, '_dgb_pay_method', true ) ), 'exchangeRate' => '1', 'memo' => 'DriveGo booking #' . $id ) ) );
		$p = self::check( is_wp_error( $d ) ? $d : $d['invoicePaymentCreateManual'], 'Payment' );
		if ( is_wp_error( $p ) ) { return $p; }
		update_post_meta( $id, '_dgb_wave_paid', 1 );

		// 5. optionally email it
		if ( DGB_Settings::get( 'wave_send' ) && is_email( $mail ) ) {
			self::gql( 'mutation($input: InvoiceSendInput!) { invoiceSend(input: $input) { didSucceed inputErrors { path message code } } }',
				array( 'input' => array( 'invoiceId' => $inv, 'to' => array( $mail ), 'attachPDF' => true ) ) );
		}
		return $inv;
	}

	/**
	 * "Pay with Wave": create the customer and an approved, unpaid invoice and hand back its payment page.
	 * @return string URL, or '' when Wave failed (the error is saved on the booking).
	 */
	public static function pay_link( $id ) {
		if ( ! self::configured() ) { return ''; }
		$r = self::open_invoice( $id );
		if ( is_wp_error( $r ) ) { update_post_meta( $id, '_dgb_wave_error', $r->get_error_message() ); return ''; }
		delete_post_meta( $id, '_dgb_wave_error' );
		return $r['url'];
	}

	/** Customer + invoice + approve. @return array|WP_Error ['id'=>..., 'url'=>...] */
	private static function open_invoice( $id ) {
		$biz  = DGB_Settings::get( 'wave_business' );
		$mail = get_post_meta( $id, '_dgb_email', true );
		$cid  = self::customer( $id );
		if ( is_wp_error( $cid ) ) { return $cid; }
		$lines = DGB_Bookings::summary_lines( $id );
		$desc  = isset( $lines['Service'] ) ? $lines['Service'] : $lines['Package'];
		if ( ! empty( $lines['When'] ) ) { $desc .= ' · ' . $lines['When']; }
		$item = array( 'productId' => DGB_Settings::get( 'wave_product' ), 'description' => $desc, 'quantity' => '1', 'unitPrice' => self::money( (float) get_post_meta( $id, '_dgb_price', true ) ) );
		if ( DGB_Settings::get( 'wave_tax' ) ) { $item['taxes'] = array( array( 'salesTaxId' => DGB_Settings::get( 'wave_tax' ) ) ); }
		$d = self::gql( 'mutation($input: InvoiceCreateInput!) { invoiceCreate(input: $input) { didSucceed inputErrors { path message code } invoice { id invoiceNumber } } }',
			array( 'input' => array( 'businessId' => $biz, 'customerId' => $cid, 'status' => 'DRAFT', 'invoiceDate' => wp_date( 'Y-m-d' ), 'dueDate' => wp_date( 'Y-m-d', strtotime( '+7 days' ) ), 'currency' => 'CAD', 'title' => 'Invoice', 'memo' => 'DriveGo booking #' . $id, 'items' => array( $item ) ) ) );
		$p = self::check( is_wp_error( $d ) ? $d : $d['invoiceCreate'], 'Invoice' );
		if ( is_wp_error( $p ) ) { return $p; }
		$inv = $p['invoice']['id'];
		update_post_meta( $id, '_dgb_wave_invoice', $inv );
		update_post_meta( $id, '_dgb_wave_number', isset( $p['invoice']['invoiceNumber'] ) ? $p['invoice']['invoiceNumber'] : '' );
		$d = self::gql( 'mutation($input: InvoiceApproveInput!) { invoiceApprove(input: $input) { didSucceed inputErrors { path message code } invoice { id status viewUrl } } }', array( 'input' => array( 'invoiceId' => $inv ) ) );
		$p = self::check( is_wp_error( $d ) ? $d : $d['invoiceApprove'], 'Approve' );
		if ( is_wp_error( $p ) ) { return $p; }
		$url = isset( $p['invoice']['viewUrl'] ) ? $p['invoice']['viewUrl'] : '';
		if ( '' === $url ) { return new WP_Error( 'dgb_wave', 'Wave created the invoice but gave no payment link.' ); }
		return array( 'id' => $inv, 'url' => $url );
	}

	/** The Wave customer of this booking: one per email. @return string|WP_Error */
	private static function customer( $id ) {
		$mail = get_post_meta( $id, '_dgb_email', true );
		$map  = get_option( 'dgb_wave_customers', array() );
		$map  = is_array( $map ) ? $map : array();
		if ( isset( $map[ strtolower( $mail ) ] ) ) { return $map[ strtolower( $mail ) ]; }
		$d = self::gql( 'mutation($input: CustomerCreateInput!) { customerCreate(input: $input) { didSucceed inputErrors { path message code } customer { id } } }',
			array( 'input' => array( 'businessId' => DGB_Settings::get( 'wave_business' ), 'name' => get_post_meta( $id, '_dgb_name', true ), 'email' => $mail, 'phone' => get_post_meta( $id, '_dgb_phone', true ) ) ) );
		$p = self::check( is_wp_error( $d ) ? $d : $d['customerCreate'], 'Customer' );
		if ( is_wp_error( $p ) ) { return $p; }
		$map[ strtolower( $mail ) ] = $p['customer']['id'];
		update_option( 'dgb_wave_customers', $map, false );
		return $p['customer']['id'];
	}

	/** Wave does not call us when an invoice is paid, so look every few minutes. */
	public static function poll() {
		if ( ! self::configured() ) { return; }
		$ids = get_posts( array(
			'post_type' => DGB_Bookings::CPT, 'post_status' => 'any', 'numberposts' => 40, 'fields' => 'ids',
			'meta_query' => array(
				array( 'key' => '_dgb_pay_method', 'value' => 'wave' ),
				array( 'key' => '_dgb_payment', 'value' => 'unpaid' ),
				array( 'key' => '_dgb_status', 'value' => array( 'pending', 'confirmed' ), 'compare' => 'IN' ),
				array( 'key' => '_dgb_wave_invoice', 'compare' => 'EXISTS' ),
			),
		) );
		foreach ( $ids as $id ) { self::check_paid( $id ); }
	}

	/** Is the Wave invoice of this booking paid? Marks the booking when it is. */
	public static function check_paid( $id ) {
		$inv = get_post_meta( $id, '_dgb_wave_invoice', true );
		if ( ! $inv ) { return false; }
		$d = self::gql( 'query($b: ID!, $i: ID!) { business(id: $b) { invoice(id: $i) { id status amountDue { value } } } }', array( 'b' => DGB_Settings::get( 'wave_business' ), 'i' => $inv ) );
		if ( is_wp_error( $d ) ) { update_post_meta( $id, '_dgb_wave_error', $d->get_error_message() ); return false; }
		$i    = isset( $d['business']['invoice'] ) ? $d['business']['invoice'] : array();
		$paid = ( isset( $i['status'] ) && 'PAID' === strtoupper( $i['status'] ) ) || ( isset( $i['amountDue']['value'] ) && 0.0 === (float) $i['amountDue']['value'] && ! empty( $i['status'] ) && 'DRAFT' !== strtoupper( $i['status'] ) );
		if ( $paid ) { update_post_meta( $id, '_dgb_wave_paid', 1 ); delete_post_meta( $id, '_dgb_wave_error' ); DGB_Bookings::mark_paid( $id ); }
		return $paid;
	}

	/** Button on a booking: try again after fixing the settings. */
	public static function send_now() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'edit_post', $id ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'dgb_wavesend_' . $id ) ) { wp_die( 'Not allowed.' ); }
		if ( ! empty( $_GET['check'] ) ) { self::check_paid( $id ); wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); exit; }
		if ( self::configured() && ! get_post_meta( $id, '_dgb_wave_paid', true ) ) {
			if ( get_post_meta( $id, '_dgb_wave_invoice', true ) ) { update_post_meta( $id, '_dgb_wave_error', 'The invoice exists in Wave but the payment step did not finish. Open it in Wave and record the payment there.' ); }
			else {
				$r = self::invoice( $id );
				if ( is_wp_error( $r ) ) { update_post_meta( $id, '_dgb_wave_error', $r->get_error_message() ); } else { delete_post_meta( $id, '_dgb_wave_error' ); }
			}
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}
}
