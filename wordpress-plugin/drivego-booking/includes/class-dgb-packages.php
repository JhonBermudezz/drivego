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
		add_action( 'update_option_' . DGB_Settings::OPTION, array( __CLASS__, 'purge_cache' ) );
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
				else { $on = ! empty( $r['v'][ $i ] ); $label = $r['label']; }
				$out .= '<p class="' . ( $on ? 'is-on dgi-fill-check-circle-b' : 'is-off dgi-fill-x-circle-b' ) . ' dg-tag-li">' . ( $on ? esc_html( $label ) : '<span>' . esc_html( $label ) . '</span>' ) . '</p>';
			}
			$out .= '</div>';
			$out .= '<a href="#buy" class="btn ' . ( $feat ? 'btn--primary' : 'btn--outline' ) . ' btn--block" data-buy-package="' . esc_attr( $it['key'] ) . '">Buy now</a>';
			$out .= '<p class="plan__secure dgi-regular-lock-simple-b">Secure checkout with Square</p></article>';
		}
		return $out;
	}

	public static function table_html() {
		$items = self::all()['items'];
		$rows  = self::rows( $items );
		$out   = '<div class="dg-tag-table"><div class="dg-tag-thead"><div class="dg-tag-tr"><p class="dg-tag-th">What you get</p>';
		foreach ( $items as $it ) {
			$out .= '<div class="' . ( ! empty( $it['featured'] ) ? 'col-featured ' : '' ) . 'dg-tag-th"><p>' . esc_html( $it['label'] ) . '</p><p class="dg-tag-small">' . esc_html( self::money( $it['price'] ) ) . ' + HST</p></div>';
		}
		$out .= '</div></div><div class="dg-tag-tbody">';
		foreach ( $rows as $r ) {
			$out .= '<div class="dg-tag-tr"><p class="dg-tag-td">' . esc_html( $r['label'] ) . '</p>';
			foreach ( $items as $i => $it ) {
				$cf = ! empty( $it['featured'] ) ? 'col-featured ' : '';
				if ( $r['hours'] ) { $out .= '<p class="' . $cf . 'dg-tag-td">' . (int) $it['hours'] . ' h</p>'; }
				elseif ( ! empty( $r['v'][ $i ] ) ) { $out .= '<p class="' . $cf . 'dg-tag-td yes dgi-fill-check-circle-b">&nbsp;</p>'; }
				else { $out .= '<p class="' . $cf . 'dg-tag-td no dgi-fill-x-circle-b">&nbsp;</p>'; }
			}
			$out .= '</div>';
		}
		return $out . '</div></div>';
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
				$rows[] = array( 'label' => $label, 'card' => empty( $f['card'] ) ? 0 : 1, 'v' => array( empty( $f['v'][0] ) ? 0 : 1, empty( $f['v'][1] ) ? 0 : 1, empty( $f['v'][2] ) ? 0 : 1 ) );
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
		<p class="description">Tick what is included. What is not included shows as a red X. "On cards" also lists the line on the cards; every line always shows in the compare table. The hours line is added automatically from the table above. Leave a name empty to remove a line.</p>
		<table class="widefat" style="max-width:1100px"><thead><tr><th>Feature</th><th>On cards</th><?php foreach ( $items as $it ) : ?><th><?php echo esc_html( $it['label'] ); ?></th><?php endforeach; ?></tr></thead><tbody>
		<?php
		$blank = array( 'label' => '', 'card' => 1, 'v' => array( 0, 0, 0 ) );
		$rows  = array_pad( $feat, max( 8, count( $feat ) + 3 ), $blank );
		foreach ( $rows as $r => $f ) : ?>
			<tr>
				<td><input type="text" style="width:100%;min-width:320px" name="<?php echo $base; ?>[features][<?php echo (int) $r; ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>"></td>
				<td><input type="checkbox" name="<?php echo $base; ?>[features][<?php echo (int) $r; ?>][card]" value="1" <?php checked( ! empty( $f['card'] ) ); ?>></td>
				<?php for ( $c = 0; $c < 3; $c++ ) : ?>
				<td><input type="checkbox" name="<?php echo $base; ?>[features][<?php echo (int) $r; ?>][v][<?php echo (int) $c; ?>]" value="1" <?php checked( ! empty( $f['v'][ $c ] ) ); ?>></td>
				<?php endfor; ?>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}
}
