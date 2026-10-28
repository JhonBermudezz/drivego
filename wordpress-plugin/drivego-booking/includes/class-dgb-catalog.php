<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * One standard list of everything that is sold: every service, length and variant (home pick up, school car)
 * and every package. Built from the settings, so it always matches the prices on the site.
 * Each item becomes a product in Wave and every invoice uses the product of what was bought.
 */
class DGB_Catalog {

	/** Key of what a booking bought. Same rules as items() so the two always agree. */
	public static function key( $service, $option, $home, $car ) {
		return $service . '|' . (int) $option . '|' . ( $home ? 'home' : 'mall' ) . '|' . ( $car ? $car : '' );
	}

	public static function package_key( $key ) { return 'package|' . $key; }

	/** @return array list of [key, name, price, desc, group] */
	public static function items() {
		$out = array();
		foreach ( (array) DGB_Settings::get( 'services' ) as $svc_key => $svc ) {
			$is_test = in_array( $svc_key, array( 'roadtest', 'outside' ), true );
			foreach ( array_values( $svc['options'] ) as $i => $o ) {
				if ( '' === trim( $o['label'] ) ) { continue; }
				$pickup = isset( $o['pickup'] ) ? (float) $o['pickup'] : 0;
				$carfee = isset( $o['car'] ) ? (float) $o['car'] : 0;
				if ( $is_test ) { $homes = array( DGB_Settings::option_home( $o, $i, $svc_key ) && $pickup > 0 ); }
				else { $homes = $pickup > 0 ? array( false, true ) : array( false ); }
				$cars = $carfee > 0 ? array( 'own', 'school' ) : array( '' );
				foreach ( $homes as $home ) {
					foreach ( $cars as $car ) {
						$price = (float) $o['price'] + ( $home ? $pickup : 0 ) + ( 'school' === $car ? $carfee : 0 );
						$name  = $svc['name'] . ' · ' . $o['label'] . ( $home ? ' · Home pick up' : '' ) . ( 'school' === $car ? ' · DriveGo car' : ( 'own' === $car ? ' · Own car' : '' ) );
						$out[] = array( 'key' => self::key( $svc_key, $i, $home, $car ), 'name' => $name, 'price' => $price, 'desc' => $svc['name'], 'group' => $svc['name'] );
					}
				}
			}
		}
		foreach ( DGB_Packages::all()['items'] as $pk ) {
			$out[] = array( 'key' => self::package_key( $pk['key'] ), 'name' => $pk['name'], 'price' => (float) $pk['price'], 'desc' => 'Driving package', 'group' => 'Driving packages' );
		}
		return $out;
	}

	/* ---------- Wave products ---------- */

	public static function map() {
		$m = get_option( 'dgb_wave_products', array() );
		return is_array( $m ) ? $m : array();
	}

	/** Wave product id for a booking, or '' when its product was not created yet. */
	public static function product_for( $booking_id ) {
		$key = get_post_meta( $booking_id, '_dgb_catalog_key', true );
		$map = self::map();
		return ( $key && isset( $map[ $key ]['id'] ) ) ? $map[ $key ]['id'] : '';
	}

	public static function missing() {
		$map = self::map();
		return array_values( array_filter( self::items(), function ( $it ) use ( $map ) { return ! isset( $map[ $it['key'] ] ); } ) );
	}

	/**
	 * Create in Wave every product that does not exist yet. Stops at the first error.
	 * @return array [created count, error message or '']
	 */
	public static function sync() {
		$map  = self::map();
		$made = 0;
		foreach ( self::missing() as $it ) {
			$input = array( 'businessId' => DGB_Settings::get( 'wave_business' ), 'name' => $it['name'], 'description' => $it['desc'], 'unitPrice' => number_format( $it['price'], 2, '.', '' ) );
			if ( DGB_Settings::get( 'wave_income' ) ) { $input['incomeAccountId'] = DGB_Settings::get( 'wave_income' ); }
			if ( DGB_Settings::get( 'wave_tax' ) ) { $input['defaultSalesTaxIds'] = array( DGB_Settings::get( 'wave_tax' ) ); }
			$d = DGB_Wave::gql( 'mutation($input: ProductCreateInput!) { productCreate(input: $input) { didSucceed inputErrors { path message code } product { id name } } }', array( 'input' => $input ) );
			if ( is_wp_error( $d ) ) { update_option( 'dgb_wave_products', $map, false ); return array( $made, $it['name'] . ': ' . $d->get_error_message() ); }
			$p = $d['productCreate'];
			if ( empty( $p['didSucceed'] ) || empty( $p['product']['id'] ) ) {
				update_option( 'dgb_wave_products', $map, false );
				$msg = isset( $p['inputErrors'][0]['message'] ) ? $p['inputErrors'][0]['message'] : 'Wave did not accept it.';
				return array( $made, $it['name'] . ': ' . $msg );
			}
			$map[ $it['key'] ] = array( 'id' => $p['product']['id'], 'name' => $it['name'] );
			$made++;
		}
		update_option( 'dgb_wave_products', $map, false );
		return array( $made, '' );
	}
}
