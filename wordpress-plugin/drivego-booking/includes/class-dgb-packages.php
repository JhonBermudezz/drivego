<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Driving packages (Basic, Standard, Complete): one set of data, edited in Booking settings,
 * shown on the home page and the Packages page (cards and compare table) and sold with a Square checkout,
 * no calendar needed.
 *
 * Pages hold empty placeholder blocks with the class dgb-pk-cards or dgb-pk-table and the plugin fills them in.
 */
class DGB_Packages {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'inject' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'offers_schema' ), 20 );
		add_action( 'update_option_' . DGB_Settings::OPTION, array( __CLASS__, 'purge_cache' ) );
	}

	/** Prices for Google (schema.org), straight from the settings so they never go out of date. Home page only. */
	public static function offers_schema() {
		if ( ! is_front_page() ) { return; }
		$offers = array();
		foreach ( self::all()['items'] as $pk ) { $offers[] = array( '@type' => 'Offer', 'name' => $pk['name'], 'price' => number_format( (float) $pk['price'], 2, '.', '' ), 'priceCurrency' => 'CAD' ); }
		$svc = (array) DGB_Settings::get( 'services' );
		foreach ( array( 'lessons' => 'Single lesson', 'refresher' => 'G-Full & G2 Refresher' ) as $k => $label ) {
			if ( ! empty( $svc[ $k ]['options'][0] ) ) { $o = $svc[ $k ]['options'][0]; $offers[] = array( '@type' => 'Offer', 'name' => $label . ' (' . $o['label'] . ')', 'price' => number_format( (float) $o['price'], 2, '.', '' ), 'priceCurrency' => 'CAD' ); }
		}
		foreach ( isset( $svc['roadtest']['options'] ) ? $svc['roadtest']['options'] : array() as $o ) { $offers[] = array( '@type' => 'Offer', 'name' => $o['label'], 'price' => number_format( (float) $o['price'], 2, '.', '' ), 'priceCurrency' => 'CAD' ); }
		$ld = array( '@context' => 'https://schema.org', '@type' => 'DrivingSchool', '@id' => home_url( '/#school' ), 'hasOfferCatalog' => array( '@type' => 'OfferCatalog', 'name' => 'Driving packages and lessons', 'itemListElement' => $offers ) );
		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/** Pages are cached: after a price change, clear the cache so visitors see it. */
	public static function purge_cache() {
		do_action( 'litespeed_purge_all' );
	}

	public static function defaults() {
		return array(
			'items' => array(
				array( 'key' => 'basic', 'label' => 'Basic', 'name' => 'Package 1 · Basic', 'tagline' => 'The certified course and core lessons. We meet you at White Oaks Mall.', 'price' => 649, 'hours' => 10, 'featured' => 0 ),
				array( 'key' => 'standard', 'label' => 'Standard', 'name' => 'Package 2 · Standard', 'tagline' => 'Our most chosen plan. Extra practice and we pick you up at home.', 'price' => 869, 'hours' => 12, 'featured' => 1 ),
				array( 'key' => 'complete', 'label' => 'Complete', 'name' => 'Package 3 · Complete', 'tagline' => 'Everything from your first lesson to test day, with our car for the exam.', 'price' => 1029, 'hours' => 16, 'featured' => 0 ),
			),
			// card = also listed on the cards; v = included in Basic, Standard, Complete
			'features' => array(
				array( 'label' => 'BDE course, online or in class', 'card' => 1, 'v' => array( 1, 1, 1 ) ),
				array( 'label' => 'G2 road test four months sooner', 'card' => 1, 'v' => array( 1, 1, 1 ) ),
				array( 'label' => 'BDE certificate for insurance savings', 'card' => 0, 'v' => array( 1, 1, 1 ) ),
				array( 'label' => 'Meeting point at White Oaks Mall', 'card' => 1, 'v' => array( 1, 1, 1 ) ),
				array( 'label' => 'Pick up and drop off at home', 'card' => 1, 'v' => array( 0, 1, 1 ) ),
				array( 'label' => 'Flexible lesson times, weekdays and weekends', 'card' => 1, 'v' => array( 0, 1, 1 ) ),
				array( 'label' => 'Warm up lesson on the test route', 'card' => 0, 'v' => array( 0, 0, 1 ) ),
				array( 'label' => 'Our car for your road test', 'card' => 1, 'v' => array( 0, 0, 1 ) ),
			),
		);
	}

	public static function all() {
		$p = DGB_Settings::get( 'packages' );
		$d = self::defaults();
		if ( ! is_array( $p ) || empty( $p['items'] ) || count( $p['items'] ) < 3 ) { return $d; }
		if ( empty( $p['features'] ) || ! is_array( $p['features'] ) ) { $p['features'] = array(); }
		return $p;
	}

	/** What a package says about a feature: 'yes' (check), 'no' (red X) or 'hide' (the line is not shown for that package). Old saved values 1 and 0 still work. */
	public static function cell( $v ) {
		if ( 'hide' === $v ) { return 'hide'; }
		return ( 'yes' === $v || ( is_numeric( $v ) && (int) $v > 0 ) || true === $v ) ? 'yes' : 'no';
	}

	public static function find( $key ) {
		foreach ( self::all()['items'] as $it ) { if ( $it['key'] === $key ) { return $it; } }
		return null;
	}

	/** Public data for the checkout window (no secrets). */
	public static function public_items() {
		$out = array();
		foreach ( self::all()['items'] as $it ) {
			$out[] = array( 'key' => $it['key'], 'name' => $it['name'], 'tagline' => $it['tagline'], 'price' => (float) $it['price'], 'hours' => (int) $it['hours'] );
		}
		return $out;
	}

	public static function money( $n ) {
		$n = (float) $n;
		return '$' . ( floor( $n ) == $n ? number_format( $n, 0 ) : number_format( $n, 2 ) );
	}

	/* ---------- rendering ---------- */

	private static function rows( $items ) {
		$feat = self::all()['features'];
		$rows = array();
		foreach ( $feat as $i => $f ) {
			$rows[] = array( 'label' => $f['label'], 'card' => ! empty( $f['card'] ), 'v' => $f['v'], 'hours' => false );
			if ( 0 === $i ) { $rows[] = array( 'label' => 'Hours behind the wheel', 'card' => true, 'v' => null, 'hours' => true ); }
		}
		if ( ! $feat ) { $rows[] = array( 'label' => 'Hours behind the wheel', 'card' => true, 'v' => null, 'hours' => true ); }
		return $rows;
	}

	public static function cards_html() {
		$items = self::all()['items'];
		$rows  = self::rows( $items );
		$out   = '';
		foreach ( $items as $i => $it ) {
			$feat = ! empty( $it['featured'] );
			$out .= '<article class="plan' . ( $feat ? ' plan--featured' : '' ) . ' reveal">';
			if ( $feat ) { $out .= '<p class="plan__tag dg-tag-span">Best value</p>'; }
			$out .= '<h3 class="plan__name">' . esc_html( $it['name'] ) . '</h3><p class="plan__desc">' . esc_html( $it['tagline'] ) . '</p>';
			$out .= '<div class="plan__price"><p class="plan__amount dg-tag-span">' . esc_html( self::money( $it['price'] ) ) . '</p><p class="plan__tax dg-tag-span">+ HST</p></div>';
			$out .= '<div class="plan__list dg-tag-ul">';
			foreach ( $rows as $r ) {
				if ( ! $r['card'] ) { continue; }
				if ( $r['hours'] ) { $on = true; $label = (int) $it['hours'] . ' hours behind the wheel'; }
				else {
					$cell = self::cell( isset( $r['v'][ $i ] ) ? $r['v'][ $i ] : 0 );
					if ( 'hide' === $cell ) { continue; } // this package does not mention it
					$on = 'yes' === $cell; $label = $r['label'];
				}
				$out .= '<p class="' . ( $on ? 'is-on dgi-fill-check-circle-b' : 'is-off dgi-fill-x-circle-b' ) . ' dg-tag-li">' . ( $on ? esc_html( $label ) : '<span>' . esc_html( $label ) . '</span>' ) . '</p>';
			}
			$out .= '</div>';
			$out .= '<a href="#buy" class="btn ' . ( $feat ? 'btn--primary' : 'btn--outline' ) . ' btn--block" data-buy-package="' . esc_attr( $it['key'] ) . '">Buy now</a>';
			$out .= '<p class="plan__secure dgi-regular-lock-simple-b">Secure checkout</p></article>';
		}
		return $out;
	}

	public static function table_html() {
		$items = self::all()['items'];
		$rows  = self::rows( $items );
		$yes = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#2a7a00"/><path d="M7 12.5l3.2 3.2L17 9" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="screen-reader-text">Included</span>';
		$no  = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#d93025"/><path d="M8.5 8.5l7 7M15.5 8.5l-7 7" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round"/></svg><span class="screen-reader-text">Not included</span>';
		$out = '<div class="dgb-cmp-wrap"><table class="dgb-cmp"><thead><tr><th scope="col">What you get</th>';
		foreach ( $items as $it ) {
			$out .= '<th scope="col"' . ( ! empty( $it['featured'] ) ? ' class="is-feat"' : '' ) . '>' . esc_html( $it['label'] ) . '<small>' . esc_html( self::money( $it['price'] ) ) . ' + HST</small></th>';
		}
		$out .= '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$out .= '<tr><th scope="row">' . esc_html( $r['label'] ) . '</th>';
			foreach ( $items as $i => $it ) {
				$cf = ! empty( $it['featured'] ) ? ' class="is-feat"' : '';
				if ( $r['hours'] ) { $out .= '<td' . $cf . '>' . (int) $it['hours'] . ' h</td>'; continue; }
				$cell = self::cell( isset( $r['v'][ $i ] ) ? $r['v'][ $i ] : 0 );
				$out .= '<td' . $cf . '>' . ( 'hide' === $cell ? '' : ( 'yes' === $cell ? $yes : $no ) ) . '</td>';
			}
			$out .= '</tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/** Fill the empty placeholder blocks the pages carry. */
	public static function inject( $html ) {
		if ( false === strpos( $html, 'dgb-pk-' ) ) { return $html; }
		$html = preg_replace_callback( '#(<div\b[^>]*\bclass="[^"]*\bdgb-pk-cards\b[^"]*"[^>]*>)\s*(</div>)#', function ( $m ) { return $m[1] . DGB_Packages::cards_html() . $m[2]; }, $html );
		$html = preg_replace_callback( '#(<div\b[^>]*\bclass="[^"]*\bdgb-pk-table\b[^"]*"[^>]*>)\s*(</div>)#', function ( $m ) { return $m[1] . DGB_Packages::table_html() . $m[2]; }, $html );
		return $html;
	}

	/* ---------- settings ---------- */

	public static function sanitize( $in, $old ) {
		$d   = self::defaults();
		$cur = is_array( $old ) && ! empty( $old['items'] ) ? $old : $d;
		$out = $cur;
		if ( isset( $in['items'] ) && is_array( $in['items'] ) ) {
			$feat = isset( $in['featured'] ) ? (int) $in['featured'] : -1;
			foreach ( $cur['items'] as $i => $it ) {
				if ( ! isset( $in['items'][ $i ] ) ) { continue; }
				$x = $in['items'][ $i ];
				$out['items'][ $i ] = array(
					'key'      => $it['key'],
					'label'    => sanitize_text_field( isset( $x['label'] ) ? $x['label'] : $it['label'] ),
					'name'     => sanitize_text_field( isset( $x['name'] ) ? $x['name'] : $it['name'] ),
					'tagline'  => sanitize_text_field( isset( $x['tagline'] ) ? $x['tagline'] : $it['tagline'] ),
					'price'    => max( 0, (float) ( isset( $x['price'] ) ? $x['price'] : $it['price'] ) ),
					'hours'    => max( 0, (int) ( isset( $x['hours'] ) ? $x['hours'] : $it['hours'] ) ),
					'featured' => $feat === $i ? 1 : 0,
				);
			}
		}
		if ( isset( $in['features'] ) && is_array( $in['features'] ) ) {
			$rows = array();
			foreach ( $in['features'] as $f ) {
				$label = sanitize_text_field( isset( $f['label'] ) ? $f['label'] : '' );
				if ( '' === $label ) { continue; }
				$rows[] = array( 'label' => $label, 'card' => empty( $f['card'] ) ? 0 : 1, 'v' => array( self::cell( isset( $f['v'][0] ) ? $f['v'][0] : 0 ), self::cell( isset( $f['v'][1] ) ? $f['v'][1] : 0 ), self::cell( isset( $f['v'][2] ) ? $f['v'][2] : 0 ) ) );
			}
			$out['features'] = $rows;
		}
		return $out;
	}

	public static function admin_fields( $n, $p ) {
		$items = $p['items'];
		$feat  = $p['features'];
		$base  = esc_attr( $n ) . '[packages]';
		?>
		<h2 id="packages">Driving packages</h2>
		<p class="description">These feed the package cards on the home page and the Packages page, the compare table, and the Buy now checkout (paid online, no calendar). Change them here and both pages update.</p>
		<table class="widefat" style="max-width:1100px"><thead><tr><th>Card title</th><th>Table name</th><th>Short description</th><th>Price (CAD, before HST)</th><th>Hours behind the wheel</th><th>Best value</th></tr></thead><tbody>
		<?php foreach ( $items as $i => $it ) : ?>
			<tr>
				<td><input type="text" style="width:170px" name="<?php echo $base; ?>[items][<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $it['name'] ); ?>"></td>
				<td><input type="text" style="width:100px" name="<?php echo $base; ?>[items][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $it['label'] ); ?>"></td>
				<td><input type="text" style="width:100%;min-width:260px" name="<?php echo $base; ?>[items][<?php echo (int) $i; ?>][tagline]" value="<?php echo esc_attr( $it['tagline'] ); ?>"></td>
				<td><input type="number" min="0" step="0.01" style="width:110px" name="<?php echo $base; ?>[items][<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( $it['price'] ); ?>"></td>
				<td><input type="number" min="0" step="1" style="width:80px" name="<?php echo $base; ?>[items][<?php echo (int) $i; ?>][hours]" value="<?php echo esc_attr( $it['hours'] ); ?>"></td>
				<td><input type="radio" name="<?php echo $base; ?>[featured]" value="<?php echo (int) $i; ?>" <?php checked( ! empty( $it['featured'] ) ); ?>></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<h3>What each package includes</h3>
		<p class="description">For each package choose: <strong>Included</strong> (green check), <strong>Not included</strong> (red X) or <strong>Don't show</strong> (the line does not appear at all for that package). "On cards" also lists the line on the cards; every line always shows in the compare table. The hours line is added automatically from the table above. Leave a name empty to remove a line.</p>
		<table class="widefat" style="max-width:1100px"><thead><tr><th>Feature</th><th>On cards</th><?php foreach ( $items as $it ) : ?><th><?php echo esc_html( $it['label'] ); ?></th><?php endforeach; ?></tr></thead><tbody>
		<?php
		$blank = array( 'label' => '', 'card' => 1, 'v' => array( 'yes', 'yes', 'yes' ) );
		$rows  = array_pad( $feat, max( 8, count( $feat ) + 3 ), $blank );
		foreach ( $rows as $r => $f ) : ?>
			<tr>
				<td><input type="text" style="width:100%;min-width:320px" name="<?php echo $base; ?>[features][<?php echo (int) $r; ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>"></td>
				<td><input type="checkbox" name="<?php echo $base; ?>[features][<?php echo (int) $r; ?>][card]" value="1" <?php checked( ! empty( $f['card'] ) ); ?>></td>
				<?php for ( $c = 0; $c < 3; $c++ ) : ?>
				<td><?php $cv = self::cell( isset( $f['v'][ $c ] ) ? $f['v'][ $c ] : 'yes' ); ?><select name="<?php echo $base; ?>[features][<?php echo (int) $r; ?>][v][<?php echo (int) $c; ?>]">
					<option value="yes" <?php selected( $cv, 'yes' ); ?>>✓ Included</option>
					<option value="no" <?php selected( $cv, 'no' ); ?>>✗ Not included</option>
					<option value="hide" <?php selected( $cv, 'hide' ); ?>>— Don't show</option>
				</select></td>
				<?php endfor; ?>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}
}
