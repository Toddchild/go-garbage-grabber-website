<?php
// Child theme functions.php — merged with Quick Pickup fixes and runtime helpers.
// Exit if accessed directly. v31

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -------------------------------------------------------------------------
 * 1) Ensure WooCommerce checkout fields include id and autocomplete tokens
 * -------------------------------------------------------------------------
 * This helps Lighthouse / browser autofill detect the fields at render time.
 */
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
	if ( isset( $fields['billing'] ) ) {
		$map = [
			'first_name' => [ 'id' => 'billing_first_name', 'autocomplete' => 'given-name' ],
			'last_name'  => [ 'id' => 'billing_last_name',  'autocomplete' => 'family-name' ],
			'company'    => [ 'id' => 'billing_company',    'autocomplete' => 'organization' ],
			'email'      => [ 'id' => 'billing_email',      'autocomplete' => 'email' ],
			'phone'      => [ 'id' => 'billing_phone',      'autocomplete' => 'tel' ],
			'address_1'  => [ 'id' => 'billing_address_1',  'autocomplete' => 'address-line1' ],
			'address_2'  => [ 'id' => 'billing_address_2',  'autocomplete' => 'address-line2' ],
			'city'       => [ 'id' => 'billing_city',       'autocomplete' => 'address-level2' ],
			'state'      => [ 'id' => 'billing_state',      'autocomplete' => 'address-level1' ],
			'postcode'   => [ 'id' => 'billing_postcode',   'autocomplete' => 'postal-code' ],
			'country'    => [ 'id' => 'billing_country',    'autocomplete' => 'country' ],
		];

		foreach ( $map as $key => $attrs ) {
			if ( isset( $fields['billing'][ $key ] ) ) {
				$fields['billing'][ $key ]['id'] = $attrs['id'];
				if ( empty( $fields['billing'][ $key ]['autocomplete'] ) ) {
					$fields['billing'][ $key ]['autocomplete'] = $attrs['autocomplete'];
				}
				if ( empty( $fields['billing'][ $key ]['name'] ) ) {
					$fields['billing'][ $key ]['name'] = 'billing[' . $key . ']';
				}
			}
		}
	}

	// Optional: mirror mapping for shipping if needed.
	if ( isset( $fields['shipping'] ) ) {
		$map_ship = [
			'first_name' => [ 'id' => 'shipping_first_name', 'autocomplete' => 'given-name' ],
			'last_name'  => [ 'id' => 'shipping_last_name',  'autocomplete' => 'family-name' ],
			'address_1'  => [ 'id' => 'shipping_address_1',  'autocomplete' => 'address-line1' ],
			'address_2'  => [ 'id' => 'shipping_address_2',  'autocomplete' => 'address-line2' ],
			'city'       => [ 'id' => 'shipping_city',       'autocomplete' => 'address-level2' ],
			'postcode'   => [ 'id' => 'shipping_postcode',   'autocomplete' => 'postal-code' ],
			'country'    => [ 'id' => 'shipping_country',    'autocomplete' => 'country' ],
		];
		foreach ( $map_ship as $key => $attrs ) {
			if ( isset( $fields['shipping'][ $key ] ) ) {
				$fields['shipping'][ $key ]['id'] = $attrs['id'];
				if ( empty( $fields['shipping'][ $key ]['autocomplete'] ) ) {
					$fields['shipping'][ $key ]['autocomplete'] = $attrs['autocomplete'];
				}
			}
		}
	}

	return $fields;
}, 20 );

/**
 * -------------------------------------------------------------------------
 * 2) Enqueue parent & child theme styles and Quick Pickup assets
 * -------------------------------------------------------------------------
 */
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_styles' );
function hello_elementor_child_enqueue_styles() {
	// Parent style
	wp_enqueue_style( 'hello-elementor-parent-style', get_template_directory_uri() . '/style.css' );

	// Child style
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'hello-elementor-parent-style' ),
		wp_get_theme()->get( 'Version' )
	);
}

add_action( 'wp_enqueue_scripts', 'hello_elementor_child_qp_assets' );
function hello_elementor_child_qp_assets() {
	$dir_uri  = get_stylesheet_directory_uri();
	$dir_path = get_stylesheet_directory();

	$css_rel   = '/assets/css/quick-pickup.css';
	$cards_rel = '/assets/js/qp-cards.js';
	$main_rel  = '/assets/js/qp-main.js';

	$css_full   = $dir_path . $css_rel;
	$cards_full = $dir_path . $cards_rel;
	$main_full  = $dir_path . $main_rel;

	$css_uri   = $dir_uri . $css_rel;
	$cards_uri = $dir_uri . $cards_rel;
	$main_uri  = $dir_uri . $main_rel;

	$css_ver   = file_exists( $css_full ) ? filemtime( $css_full ) : wp_get_theme()->get( 'Version' );
	$cards_ver = file_exists( $cards_full ) ? filemtime( $cards_full ) : '1.0';
	$main_ver  = file_exists( $main_full ) ? filemtime( $main_full ) : '1.0';

	if ( is_front_page() || is_home() || is_page() || is_singular() ) {
		if ( file_exists( $css_full ) ) {
			wp_enqueue_style( 'hello-qpick-css', $css_uri, array(), $css_ver );
		}
		if ( file_exists( $cards_full ) ) {
			wp_enqueue_script( 'qp-cards', $cards_uri, array(), $cards_ver, true );
		}
		if ( file_exists( $main_full ) ) {
			$deps = file_exists( $cards_full ) ? array( 'qp-cards' ) : array();
			wp_enqueue_script( 'qp-main', $main_uri, $deps, $main_ver, true );

			$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : esc_url_raw( home_url( '/check-out/' ) );
			$cart_url     = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : esc_url_raw( home_url( '/cart/' ) );

			$qp_vars = array(
				'product_id'   => 521,
				'site_url'     => esc_url_raw( home_url( '/' ) ),
				'rest_url'     => esc_url_raw( rest_url() ),
				'checkout_url' => $checkout_url,
				'cart_url'     => $cart_url,
			);
			wp_localize_script( 'qp-main', 'qp_vars', $qp_vars );
		}
	}
}

/**
 * -------------------------------------------------------------------------
 * 3) Font Awesome & modal / cart scripts
 * -------------------------------------------------------------------------
 */
function gg_enqueue_font_awesome_cdn() {
	if ( is_admin() ) return;
	if ( wp_style_is( 'font-awesome-cdn', 'enqueued' ) || wp_style_is( 'font-awesome', 'enqueued' ) ) return;

	wp_enqueue_style(
		'font-awesome-cdn',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
		array(),
		'6.4.0'
	);
}
add_action( 'wp_enqueue_scripts', 'gg_enqueue_font_awesome_cdn' );

function gg_enqueue_qp_modal_script() {
	$path = get_stylesheet_directory() . '/assets/js/instant-quick-pickup.modal.js';
	if ( ! file_exists( $path ) ) return;
	wp_enqueue_script(
		'gg-qp-modal',
		get_stylesheet_directory_uri() . '/assets/js/instant-quick-pickup.modal.js',
		array(),
		filemtime( $path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'gg_enqueue_qp_modal_script' );

function qp_enqueue_cart_auto_continue_if_exists() {
	if ( is_admin() ) return;
	if ( function_exists( 'is_cart' ) && ! is_cart() ) return;

	$path = get_stylesheet_directory() . '/assets/js/qp-cart-auto-continue.js';
	if ( ! file_exists( $path ) ) return;

	$src = get_stylesheet_directory_uri() . '/assets/js/qp-cart-auto-continue.js';
	wp_enqueue_script( 'qp-cart-auto-continue', $src, array(), filemtime( $path ), true );
}
add_action( 'wp_enqueue_scripts', 'qp_enqueue_cart_auto_continue_if_exists' );

/**
 * -------------------------------------------------------------------------
 * 4) Instant Quote shortcode
 * -------------------------------------------------------------------------
 */
if ( ! function_exists( 'qp_register_instant_quote_shortcode' ) ) {
	add_action( 'init', 'qp_register_instant_quote_shortcode' );
	function qp_register_instant_quote_shortcode() {
		if ( ! shortcode_exists( 'instant_quote' ) ) {
			add_shortcode( 'instant_quote', 'qp_instant_quote_shortcode' );
		}
	}
}

if ( ! function_exists( 'qp_instant_quote_shortcode' ) ) {
	function qp_instant_quote_shortcode( $atts = array() ) {
		$dir_uri  = get_stylesheet_directory_uri();
		$dir_path = get_stylesheet_directory();

		$css_rel = '/assets/css/instant-quote.css';
		$js_rel  = '/assets/js/instant-quote.js';

		$css_path = $dir_path . $css_rel;
		$js_path  = $dir_path . $js_rel;

		$css_uri = $dir_uri . $css_rel;
		$js_uri  = $dir_uri . $js_rel;

		$css_ver = file_exists( $css_path ) ? filemtime( $css_path ) : wp_get_theme()->get( 'Version' );
		$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : wp_get_theme()->get( 'Version' );

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'qp-instant-quote-css', $css_uri, array(), $css_ver );
		}
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script( 'qp-instant-quote-js', $js_uri, array(), $js_ver, true );
		}

		// Build products list
		$products_out = array();
		if ( function_exists( 'wc_get_products' ) ) {
			$args = array( 'status' => 'publish', 'limit' => -1, 'return' => 'objects' );
			$all = wc_get_products( $args );
			foreach ( $all as $prod ) {
				if ( ! $prod ) continue;
				$pid   = $prod->get_id();
				$terms = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) );
				$img   = '';
				$img_id = $prod->get_image_id();
				if ( $img_id ) $img = wp_get_attachment_image_url( $img_id, 'medium' );

				$envfee = 0;
				$sku = method_exists( $prod, 'get_sku' ) ? $prod->get_sku() : '';

				if ( defined( 'QP_ENV_FEE_META' ) ) {
					$meta = get_post_meta( $pid, QP_ENV_FEE_META, true );
					if ( $meta ) $envfee = floatval( $meta );
				} else {
					$meta = get_post_meta( $pid, 'qp_env_fee', true );
					if ( $meta ) $envfee = floatval( $meta );
				}

				$products_out[] = array(
					'id'         => $pid,
					'name'       => $prod->get_name(),
					'price'      => (float) $prod->get_price(),
					'image'      => $img,
					'categories' => (array) $terms,
					'slug'       => $prod->get_slug(),
					'envfee'     => $envfee,
					'sku'        => $sku,
				);
			}
		} else {
			$q = new WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			) );
			while ( $q->have_posts() ) {
				$q->the_post();
				$pid = get_the_ID();
				$prod = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
				$terms = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) );
				$img_id = get_post_thumbnail_id( $pid );
				$img = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';

				$envfee = 0;
				$sku = $prod ? ( method_exists( $prod, 'get_sku' ) ? $prod->get_sku() : '' ) : '';
				$meta = get_post_meta( $pid, 'qp_env_fee', true );
				if ( $meta ) $envfee = floatval( $meta );

				$products_out[] = array(
					'id'         => $pid,
					'name'       => get_the_title(),
					'price'      => $prod ? (float) $prod->get_price() : 0.0,
					'image'      => $img,
					'categories' => (array) $terms,
					'slug'       => get_post_field( 'post_name', $pid ),
					'envfee'     => $envfee,
					'sku'        => $sku,
				);
			}
			wp_reset_postdata();
		}

		$categories_order = array( 'furniture', 'appliances' );
		$reordered = array();
		$seen = array();
		foreach ( $categories_order as $cat ) {
			foreach ( $products_out as $p ) {
				if ( in_array( $cat, $p['categories'], true ) ) {
					$reordered[] = $p;
					$seen[ $p['id'] ] = true;
				}
			}
		}
		foreach ( $products_out as $p ) {
			if ( empty( $seen[ $p['id'] ] ) ) $reordered[] = $p;
		}

		$full_load_price = defined( 'QP_FULL_LOAD_PRICE' ) ? QP_FULL_LOAD_PRICE : 250;
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : ( defined( 'QP_DEFAULT_CURRENCY' ) ? QP_DEFAULT_CURRENCY : 'CAD' );
		$checkout_path = '/check-out/';

		$runtime = array(
			'checkoutPath'  => $checkout_path,
			'currency'      => $currency,
			'fullLoadPrice' => $full_load_price,
		);

		wp_localize_script( 'qp-instant-quote-js', 'QP_PRODUCTS', $reordered );
		wp_localize_script( 'qp-instant-quote-js', 'QP_RUNTIME', $runtime );

		wp_localize_script( 'qp-instant-quote-js', 'qp_ajax', array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'qp_apply_nonce' ),
		) );

		$cart_url     = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : esc_url_raw( home_url( '/cart/' ) );
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : esc_url_raw( home_url( '/check-out/' ) );
		wp_localize_script( 'qp-instant-quote-js', 'qp_vars', array(
			'cart_url'     => $cart_url,
			'checkout_url' => $checkout_url,
		) );

		return '<div id="qp-instant-quote-root" class="qp-instant-quote-root"></div>';
	}
}

/**
 * -------------------------------------------------------------------------
 * 5) Checkout renderer enqueue and qp_apply_payload AJAX (keeps original logic)
 * -------------------------------------------------------------------------
 */
add_action( 'wp_enqueue_scripts', 'gg_enqueue_qp_checkout_renderer_if_exists' );
function gg_enqueue_qp_checkout_renderer_if_exists() {
	if ( ! ( function_exists( 'is_checkout' ) && is_checkout() ) ) return;
	$path = get_stylesheet_directory() . '/assets/js/qp-checkout-renderer.js';
	if ( ! file_exists( $path ) ) return;
	$src = get_stylesheet_directory_uri() . '/assets/js/qp-checkout-renderer.js';
	wp_enqueue_script( 'qp-checkout-renderer', $src, array(), filemtime( $path ), true );
}

/**
 * qp_apply_payload AJAX handler
 * (idempotent + fee handling)
 */
add_action( 'wp_ajax_qp_apply_payload', 'qp_apply_payload' );
add_action( 'wp_ajax_nopriv_qp_apply_payload', 'qp_apply_payload' );

function qp_apply_payload() {
	// Verify nonce
	if ( empty( $_POST['qp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qp_nonce'] ) ), 'qp_apply_nonce' ) ) {
		wp_send_json_error( array( 'error' => 'invalid_nonce' ), 400 );
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'error' => 'woocommerce_missing' ), 500 );
	}

	$raw = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';
	$payload = json_decode( $raw, true );
	if ( ! $payload || empty( $payload['items'] ) || ! is_array( $payload['items'] ) ) {
		wp_send_json_error( array( 'error' => 'invalid_payload' ), 400 );
	}

	// Ensure WC cart is initialized
	if ( ! isset( WC()->cart ) ) {
		wc_load_cart();
	}

	$results = array();
	$added_count = 0;
	$payload_ts = isset( $payload['ts'] ) ? sanitize_text_field( (string) $payload['ts'] ) : (string) time();

	// Idempotency guard
	$already_applied = false;
	try {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item['qp_payload_ts'] ) && (string) $cart_item['qp_payload_ts'] === (string) $payload_ts ) {
				$already_applied = true;
				break;
			}
		}
	} catch ( Exception $e ) {
		$already_applied = false;
	}

	if ( $already_applied ) {
		wp_send_json_success( array(
			'added'      => 0,
			'cart_count' => WC()->cart->get_cart_contents_count(),
			'items'      => array(),
			'message'    => 'payload_already_applied',
		) );
	}

	foreach ( $payload['items'] as $idx => $it ) {
		$result = array(
			'index' => $idx,
			'requested' => $it,
			'resolved_product_id' => null,
			'status' => 'skipped',
			'message' => '',
			'cart_key' => null,
		);

		$qty = isset( $it['qty'] ) ? intval( $it['qty'] ) : 0;
		if ( $qty <= 0 ) {
			$result['message'] = 'invalid_qty';
			$results[] = $result;
			continue;
		}

		$product_id = 0;

		if ( ! empty( $it['id'] ) && is_numeric( $it['id'] ) ) {
			$maybe = intval( $it['id'] );
			if ( $maybe > 0 && get_post_type( $maybe ) === 'product' ) {
				$product_id = $maybe;
			}
		}

		if ( ! $product_id && ! empty( $it['slug'] ) ) {
			$slug = sanitize_text_field( $it['slug'] );
			$prod = get_page_by_path( $slug, OBJECT, 'product' );
			if ( $prod ) $product_id = $prod->ID;
		}

		if ( ! $product_id && ! empty( $it['sku'] ) ) {
			$sku = sanitize_text_field( $it['sku'] );
			$sku_id = wc_get_product_id_by_sku( $sku );
			if ( $sku_id ) $product_id = $sku_id;
		}

		if ( ! $product_id && ! empty( $it['name'] ) ) {
			$maybe = sanitize_text_field( $it['name'] );
			$q = get_posts( array( 'post_type' => 'product', 'title' => $maybe, 'numberposts' => 1 ) );
			if ( ! empty( $q ) ) $product_id = $q[0]->ID;
		}

		if ( ! $product_id ) {
			$result['message'] = 'product_not_found';
			$results[] = $result;
			continue;
		}

		$result['resolved_product_id'] = $product_id;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$result['message'] = 'product_object_missing';
			$results[] = $result;
			continue;
		}

		if ( $product->is_type( 'variable' ) ) {
			$result['message'] = 'variable_product_needs_variation';
			$results[] = $result;
			continue;
		}

		if ( ! $product->is_purchasable() ) {
			$result['message'] = 'not_purchasable';
			$results[] = $result;
			continue;
		}

		if ( $product->managing_stock() && ! $product->is_in_stock() ) {
			$result['message'] = 'out_of_stock';
			$results[] = $result;
			continue;
		}

		$cart_item_data = array(
			'qp_instant'    => true,
			'qp_payload_ts' => $payload_ts,
		);

		$cart_key = WC()->cart->add_to_cart( $product_id, $qty, 0, array(), $cart_item_data );

		if ( $cart_key ) {
			$result['status'] = 'added';
			$result['message'] = 'added_to_cart';
			$result['cart_key'] = $cart_key;
			$added_count += $qty;
		} else {
			$result['status'] = 'failed';
			$result['message'] = 'add_to_cart_failed';
		}

		$results[] = $result;
	}

	// Enviro fee
	$total_env = 0.0;
	if ( isset( $payload['totalEnvFees'] ) ) {
		$total_env = floatval( $payload['totalEnvFees'] );
	} else {
		foreach ( $payload['items'] as $it ) {
			$qty = isset( $it['qty'] ) ? intval( $it['qty'] ) : 0;
			$env = isset( $it['env'] ) ? floatval( $it['env'] ) : 0.0;
			$total_env += $qty * $env;
		}
	}
	$total_env = round( $total_env, 2 );

	if ( $total_env > 0 ) {
		$fee_already = false;
		try {
			$fees = WC()->cart->get_fees();
			if ( is_array( $fees ) ) {
				foreach ( $fees as $fee_obj ) {
					if ( ! empty( $fee_obj->name ) && strpos( $fee_obj->name, 'qp_env_ts:' . $payload_ts ) !== false ) {
						$fee_already = true;
						break;
					}
				}
			}
		} catch ( Exception $e ) {
			$fee_already = false;
		}

		if ( ! $fee_already ) {
			$fee_name = 'Enviro fee (qp_env_ts:' . $payload_ts . ')';
			WC()->cart->add_fee( $fee_name, $total_env, false );
		}
	}

	WC()->cart->calculate_totals();

	wp_send_json_success( array(
		'added'      => $added_count,
		'cart_count' => WC()->cart->get_cart_contents_count(),
		'items'      => $results,
	) );
}

/**
 * Transfer qp_instant cart item meta to order line items.
 */
add_action( 'woocommerce_checkout_create_order_line_item', 'qp_transfer_cart_item_meta_to_order_item', 10, 4 );
function qp_transfer_cart_item_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
	if ( isset( $values['qp_instant'] ) && $values['qp_instant'] ) {
		$item->add_meta_data( 'qp_instant', '1', true );
		if ( ! empty( $values['qp_payload_ts'] ) ) {
			$item->add_meta_data( 'qp_payload_ts', sanitize_text_field( $values['qp_payload_ts'] ), true );
		}
	}
}

/**
 * Prevent full-page caching on Cart, Checkout, and wc-ajax fragment requests
 */
add_action( 'template_redirect', 'qp_no_cache_cart_and_checkout', 1 );
function qp_no_cache_cart_and_checkout() {
	if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
		return;
	}
	if ( is_cart() || is_checkout() || ( isset( $_GET['wc-ajax'] ) && sanitize_text_field( wp_unslash( $_GET['wc-ajax'] ) ) ) ) {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		header( 'Cache-Control: no-cache, no-store, must-revalidate, private, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}
}

/**
 * Safer: Conditionally unregister specific Quick Pickup REST routes (runs late).
 *
 * This avoids accidentally removing routes that don't exist or racing with route registration.
 */
add_action( 'rest_api_init', function() {
	if ( ! function_exists( 'rest_get_server' ) || ! function_exists( 'unregister_rest_route' ) ) {
		return;
	}
	$server = rest_get_server();
	if ( ! $server ) {
		return;
	}
	$routes = $server->get_routes();

	$targets = array(
		array( 'namespace' => 'qp/v1', 'route' => '/items' ),
		array( 'namespace' => 'qp/v1', 'route' => '/create-order' ),
		array( 'namespace' => 'qp/v1', 'route' => '/create-payment-intent' ),
		array( 'namespace' => 'qp/v1', 'route' => '/confirm-payment' ),
	);

	foreach ( $targets as $t ) {
		$full = '/' . trim( $t['namespace'] . '/' . ltrim( $t['route'], '/' ), '/' );
		if ( isset( $routes[ $full ] ) ) {
			@unregister_rest_route( $t['namespace'], $t['route'] );
		}
	}
}, 9999 );

/**
 * Server-side pickup date validation
 */
add_action( 'woocommerce_checkout_process', 'qp_validate_pickup_date_server' );
add_action( 'woocommerce_after_checkout_validation', 'qp_validate_pickup_date_after', 10, 2 );

function qp_get_min_pickup_iso( $rush = false ) {
	$tz_string = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : get_option( 'timezone_string' );
	$tz = $tz_string ? new DateTimeZone( $tz_string ) : new DateTimeZone( 'UTC' );

	$today = new DateTime( 'now', $tz );
	$today->setTime(0,0,0);
	if ( $rush ) { $today->modify('+1 day'); } else { $today->modify('+2 days'); }
	return $today->format( 'Y-m-d' );
}

function qp_pickup_date_from_input( $data = null ) {
	$val = '';
	if ( is_array( $data ) && isset( $data['pickup_date'] ) ) {
		$val = sanitize_text_field( wp_unslash( $data['pickup_date'] ) );
	} elseif ( isset( $_POST['pickup_date'] ) ) {
		$val = sanitize_text_field( wp_unslash( $_POST['pickup_date'] ) );
	}
	return trim( $val );
}

function qp_is_rush_from_input( $data = null ) {
	if ( is_array( $data ) && isset( $data['rush'] ) ) {
		return ( $data['rush'] === '1' || $data['rush'] === 'on' );
	}
	if ( isset( $_POST['rush'] ) ) {
		return ( $_POST['rush'] === '1' || $_POST['rush'] === 'on' );
	}
	return false;
}

function qp_validate_pickup_date_server() {
	$pickup_date = qp_pickup_date_from_input();
	$rush = qp_is_rush_from_input();

	if ( empty( $pickup_date ) ) {
		wc_add_notice( __( 'Please choose a pickup date.', 'your-textdomain' ), 'error' );
		return;
	}

	$min_iso = qp_get_min_pickup_iso( $rush );

	try {
		$tz_string = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : get_option( 'timezone_string' );
		$tz = $tz_string ? new DateTimeZone( $tz_string ) : new DateTimeZone( 'UTC' );
		$posted = DateTime::createFromFormat( 'Y-m-d', $pickup_date, $tz );
		if ( ! $posted ) { $posted = new DateTime( $pickup_date, $tz ); }
		$posted_iso = $posted->format( 'Y-m-d' );
	} catch ( Exception $e ) {
		wc_add_notice( __( 'Invalid pickup date.', 'your-textdomain' ), 'error' );
		return;
	}

	if ( $posted_iso < $min_iso ) {
		if ( $rush ) {
			wc_add_notice( sprintf( __( 'Rush booking requires a pickup date of at least tomorrow (%s).', 'your-textdomain' ), $min_iso ), 'error' );
		} else {
			wc_add_notice( sprintf( __( 'Pickup date must be at least 2 days from today (%s).', 'your-textdomain' ), $min_iso ), 'error' );
		}
	}
}

function qp_validate_pickup_date_after( $data, $errors ) {
	$pickup_date = qp_pickup_date_from_input( $data );
	$rush = qp_is_rush_from_input( $data );

	if ( empty( $pickup_date ) ) {
		$errors->add( 'qp_pickup_date', __( 'Please choose a pickup date.', 'your-textdomain' ) );
		return;
	}

	$min_iso = qp_get_min_pickup_iso( $rush );

	try {
		$tz_string = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : get_option( 'timezone_string' );
		$tz = $tz_string ? new DateTimeZone( $tz_string ) : new DateTimeZone( 'UTC' );
		$posted = DateTime::createFromFormat( 'Y-m-d', $pickup_date, $tz );
		if ( ! $posted ) $posted = new DateTime( $pickup_date, $tz );
		$posted_iso = $posted->format( 'Y-m-d' );
	} catch ( Exception $e ) {
		$errors->add( 'qp_pickup_date', __( 'Invalid pickup date.', 'your-textdomain' ) );
		return;
	}

	if ( $posted_iso < $min_iso ) {
		if ( $rush ) {
			$errors->add( 'qp_pickup_date', sprintf( __( 'Rush booking requires a pickup date of at least tomorrow (%s).', 'your-textdomain' ), $min_iso ) );
		} else {
			$errors->add( 'qp_pickup_date', sprintf( __( 'Pickup date must be at least 2 days from today (%s).', 'your-textdomain' ), $min_iso ) );
		}
	}
}

/**
 * -------------------------------------------------------------------------
 * 6) Debug template include and optional forced template for a page ID
 * -------------------------------------------------------------------------
 */
add_filter( 'template_include', function( $template ) {
	if ( ! is_admin() ) {
		echo '<!-- WP_TEMPLATE:' . esc_html( $template ) . ' -->';
	}
	return $template;
}, 100 );

add_filter( 'template_include', function( $template ) {
	if ( is_admin() ) {
		return $template;
	}
	$checkout_page_id = 1955; // change if necessary
	if ( is_page( $checkout_page_id ) ) {
		$child_tpl = get_stylesheet_directory() . '/template-qp-custom-checkout.php';
		if ( file_exists( $child_tpl ) ) {
			return $child_tpl;
		}
	}
	return $template;
}, 9999 );

/**
 * -------------------------------------------------------------------------
 * 7) OPTIONAL: Dequeue third-party scripts by matching src patterns.
 *    Best-effort runtime removal for problematic scripts (e.g. deprecated Protected Audience API).
 * -------------------------------------------------------------------------
 */
add_action( 'wp_print_scripts', 'qp_maybe_deregister_captcha_like_scripts', 1 );
function qp_maybe_deregister_captcha_like_scripts() {
	if ( is_admin() ) return;

	global $wp_scripts;
	if ( ! ( $wp_scripts instanceof WP_Scripts ) ) return;

	$to_remove_handles = array();

	foreach ( $wp_scripts->registered as $handle => $obj ) {
		$src = isset( $obj->src ) ? $obj->src : '';
		if ( empty( $src ) ) continue;
		// Common patterns that indicate recaptcha / protected-audience / captcha loaders
		if ( preg_match( '#api\.js.*captcha|captcha|recaptcha|protectedAudience|protected#i', $src ) || preg_match( '#/recaptcha/#i', $src ) ) {
			$to_remove_handles[] = $handle;
		}
		if ( strpos( $src, 'onload=captchaLoad' ) !== false ) {
			$to_remove_handles[] = $handle;
		}
	}

	// Dequeue/deregister (only front-end)
	foreach ( array_unique( $to_remove_handles ) as $h ) {
		wp_dequeue_script( $h );
		wp_deregister_script( $h );
	}
}

/**
 * -------------------------------------------------------------------------
 * 8) OPTIONAL: AJAX handler to create Stripe PaymentIntent (server-side)
 *    Requires stripe-php and WP options qp_stripe_secret_key / qp_stripe_publishable_key.
 * -------------------------------------------------------------------------
 *
 * Register the AJAX handlers conditionally to avoid colliding with a plugin that
 * may have already registered the same handlers.
 */
if ( ! has_action( 'wp_ajax_nopriv_qp_create_payment_intent' ) ) {
	add_action( 'wp_ajax_nopriv_qp_create_payment_intent', 'qp_create_payment_intent_ajax' );
}
if ( ! has_action( 'wp_ajax_qp_create_payment_intent' ) ) {
	add_action( 'wp_ajax_qp_create_payment_intent', 'qp_create_payment_intent_ajax' );
}

function qp_create_payment_intent_ajax() {
	// Nonce
	if ( empty( $_POST['qp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qp_nonce'] ) ), 'qp_apply_nonce' ) ) {
		status_header( 400 );
		wp_send_json( array( 'success' => false, 'message' => 'Invalid nonce' ) );
	}

	$payload_raw = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '{}';
	$payload = json_decode( $payload_raw, true ) ?: array();

	// Prefer constant (wp-config.php) then option
	$secret_key = '';
	if ( defined( 'QP_STRIPE_SECRET_KEY' ) && QP_STRIPE_SECRET_KEY ) {
		$secret_key = QP_STRIPE_SECRET_KEY;
	} else {
		$secret_key = get_option( 'qp_stripe_secret_key', '' );
	}

	if ( empty( $secret_key ) ) {
		error_log( 'qp_create_payment_intent: Stripe secret key not configured.' );
		status_header( 500 );
		wp_send_json( array( 'success' => false, 'message' => 'Stripe secret key not configured.' ) );
	}

	// Ensure cart is available
	if ( ! isset( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}

	// Compute amount (in cents) - adjust to your logic if necessary
	$amount = 1000; // fallback $10.00 (in cents)
	try {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) ) {
			$raw_total = WC()->cart->get_total( 'raw' );
			$raw_total = floatval( $raw_total );
			if ( $raw_total > 0 ) {
				$amount = (int) round( $raw_total * 100 );
			}
		}
	} catch ( Exception $e ) {
		error_log( 'qp_create_payment_intent: cart total error: ' . $e->getMessage() );
		$amount = 1000;
	}

	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : strtolower( get_option( 'woocommerce_currency', 'USD' ) );

	// If stripe-php is available, use it; otherwise call Stripe REST API via wp_remote_post
	if ( class_exists( '\Stripe\Stripe' ) ) {
		try {
			\Stripe\Stripe::setApiKey( $secret_key );
			$intent = \Stripe\PaymentIntent::create( array(
				'amount' => (int) $amount,
				'currency' => strtolower( $currency ),
				'automatic_payment_methods' => array( 'enabled' => true ),
				'metadata' => array( 'qp_payload' => wp_json_encode( $payload ) ),
			) );
			wp_send_json( array( 'success' => true, 'client_secret' => $intent->client_secret ) );
		} catch ( Exception $e ) {
			error_log( 'qp_create_payment_intent (stripe-php) error: ' . $e->getMessage() );
			status_header( 500 );
			wp_send_json( array( 'success' => false, 'message' => 'Payment provider error' ) );
		}
	} else {
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret_key,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body' => array(
				'amount' => (int) $amount,
				'currency' => strtolower( $currency ),
				'automatic_payment_methods[enabled]' => 'true',
			),
			'timeout' => 20,
		);

		$response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'qp_create_payment_intent: wp_remote_post error: ' . $response->get_error_message() );
			status_header( 500 );
			wp_send_json( array( 'success' => false, 'message' => 'Network error' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 && ! empty( $data['client_secret'] ) ) {
			wp_send_json( array( 'success' => true, 'client_secret' => $data['client_secret'] ) );
		} else {
			error_log( 'qp_create_payment_intent: stripe REST response code=' . $code . ' body=' . $body );
			status_header( 500 );
			wp_send_json( array( 'success' => false, 'message' => 'Payment provider error' ) );
		}
	}
}

/**
 * -------------------------------------------------------------------------
 * 9) Fix label[for] mismatches for form fields
 *    - Server-side: adjust WooCommerce field HTML (woocommerce_form_field)
 *    - Client-side: runtime fallback scoped to checkout
 * -------------------------------------------------------------------------
 */

/* Server-side filter for WooCommerce fields */
add_filter( 'woocommerce_form_field', 'qp_fix_woocommerce_field_label_for', 20, 4 );
function qp_fix_woocommerce_field_label_for( $field_html, $key, $args, $value ) {
	// If there is an input/select/textarea with an id inside the field HTML, use it.
	if ( preg_match( '/<(input|select|textarea)[^>]*id=[\'"]([^\'"]+)[\'"]/i', $field_html, $m ) ) {
		$input_id = $m[2];
		// Replace the first label[for="..."] (if present) with the actual input id
		$field_html = preg_replace( '/(<label[^>]*for=[\'"])([^\'"]*)([\'"])/i', '$1' . $input_id . '$3', $field_html, 1 );
		return $field_html;
	}

	// No id found on the control: try to find a name attribute and inject an id
	if ( preg_match( '/<(input|select|textarea)([^>]*)name=[\'"]([^\'"]+)[\'"]([^>]*)>/i', $field_html, $m ) ) {
		$name = $m[3];
		// Build a safe id from the name
		$inject_id = preg_replace( '/[^a-z0-9_-]+/i', '_', $name );
		$inject_id = 'qp_' . ltrim( $inject_id, '_' );

		// Inject id into the first input/select/textarea tag found
		$field_html = preg_replace_callback( '/<(input|select|textarea)([^>]*)>/i', function( $matches ) use ( $inject_id ) {
			$tag = $matches[1];
			$attrs = $matches[2];
			// If id already exists somehow, leave it
			if ( preg_match( '/\sid=[\'"][^\'"]+[\'"]/', $attrs ) ) {
				return $matches[0];
			}
			return '<' . $tag . $attrs . ' id="' . esc_attr( $inject_id ) . '">';
		}, $field_html, 1 );

		// Replace label[for="..."] (if present) with injected id
		$field_html = preg_replace( '/(<label[^>]*for=[\'"])([^\'"]*)([\'"])/i', '$1' . $inject_id . '$3', $field_html, 1 );

		return $field_html;
	}

	// Nothing to fix in this snippet — return original
	return $field_html;
}

/* Client-side fallback: runtime label->control repair (scoped to checkout) */
add_action( 'wp_footer', 'qp_runtime_fix_label_for', 999 );
function qp_runtime_fix_label_for() {
	// Only on frontend checkout or specific page if desired
	if ( is_admin() ) {
		return;
	}
	if ( function_exists( 'is_checkout' ) && ! is_checkout() ) {
		// adjust to specific page ID if necessary, e.g. if ( ! is_page( 1955 ) ) return;
		return;
	}
	?>
	<script>
	(function(){
		'use strict';
		function fixLabels(root){
			root = root || document;
			Array.from(root.querySelectorAll('label[for]')).forEach(function(label){
				var forId = label.getAttribute('for');
				if (!forId) return;
				if (document.getElementById(forId)) return; // already good
				// Try to find input/select/textarea by name matching the for value
				var candidate = document.querySelector('[name="'+forId+'"]') || document.querySelector('[name="'+forId.replace(/_+/g,' ')+'"]');
				if (candidate) {
					if (!candidate.id) candidate.id = forId + '_auto';
					label.setAttribute('for', candidate.id);
					return;
				}
				// Try to find a nearby control inside same parent
				var parent = label.parentElement;
				if (parent) {
					var nearby = parent.querySelector('input,select,textarea');
					if (nearby) {
						if (!nearby.id) nearby.id = (forId || 'qp_auto_' + Math.random().toString(36).slice(2,8));
						label.setAttribute('for', nearby.id);
						return;
					}
				}
			});
		}

		// Run immediately for main areas
		fixLabels(document.getElementById('qp-checkout-form'));
		fixLabels(document.getElementById('qp-wc-checkout-area'));
		fixLabels(document);

		// Observe dynamic changes (WooCommerce updated_checkout, etc.)
		var area = document.getElementById('qp-wc-checkout-area') || document.querySelector('form.checkout');
		if (area) {
			var mo = new MutationObserver(function(){ fixLabels(area); });
			mo.observe(area, { childList:true, subtree:true, attributes:true });
		}

		// Also run after a short delay to catch late-inserted controls
		setTimeout(function(){ fixLabels(document); }, 800);
	})();
	</script>
	<?php
}

// Temporary: force enqueue Stripe.js on the checkout page for testing.
// Remove after testing.
add_action( 'wp_enqueue_scripts', function() {
    if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_admin() ) {
        if ( ! wp_script_is( 'stripe-js', 'enqueued' ) ) {
            wp_register_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );
            wp_enqueue_script( 'stripe-js' );
        }
    }
}, 20 );

/**
 * -------------------------------------------------------------------------
 * End of functions.php
 * -------------------------------------------------------------------------
 */