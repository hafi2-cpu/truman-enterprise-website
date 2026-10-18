<?php
/**
 * [truman_booking]      – calendar + trip choice + request form.
 * [truman_availability] – compact read-only calendar linking to the booking page.
 *
 * @package Truman_Bookings
 */

defined( 'ABSPATH' ) || exit;

function tb_register_assets() {
	wp_register_style( 'truman-bookings', TB_URL . 'assets/booking.css', array(), (string) filemtime( TB_DIR . 'assets/booking.css' ) );
	wp_register_script( 'truman-bookings', TB_URL . 'assets/booking.js', array(), (string) filemtime( TB_DIR . 'assets/booking.js' ), true );
	$s = tb_settings();
	wp_localize_script(
		'truman-bookings',
		'TB_CONFIG',
		array(
			'api'           => esc_url_raw( rest_url( 'truman/v1/' ) ),
			'bookingUrl'    => tb_booking_page_url(),
			'phone'         => $s['phone'],
			'termsUrl'      => $s['terms_url'],
			'maxPassengers' => (int) $s['max_passengers'],
			'maxWheelchair' => (int) $s['max_wheelchair'],
		)
	);
}
add_action( 'wp_enqueue_scripts', 'tb_register_assets', 5 );

function tb_enqueue_assets() {
	if ( ! wp_script_is( 'truman-bookings', 'registered' ) ) {
		tb_register_assets();
	}
	wp_enqueue_style( 'truman-bookings' );
	wp_enqueue_script( 'truman-bookings' );
}

// Load the styles in the <head> on pages that use the calendar.
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_page( array( 'book-a-trip', 'our-trips' ) ) ) {
			tb_enqueue_assets();
		}
	},
	20
);

function tb_sc_booking() {
	tb_enqueue_assets();
	$phone = tb_settings()['phone'];
	return '<div class="tb-app" id="tb-booking" data-tb="booking">'
		. '<noscript><p>Please turn on JavaScript to use the booking calendar, or call us on ' . esc_html( $phone ) . '.</p></noscript>'
		. '<p class="tb-loading">' . esc_html__( 'Loading availability…', 'truman-bookings' ) . '</p></div>';
}
add_shortcode( 'truman_booking', 'tb_sc_booking' );

function tb_sc_availability() {
	tb_enqueue_assets();
	return '<div class="tb-app tb-app--mini" data-tb="availability"><p class="tb-loading">' . esc_html__( 'Loading availability…', 'truman-bookings' ) . '</p></div>';
}
add_shortcode( 'truman_availability', 'tb_sc_availability' );

/**
 * Let the shortcodes work inside Elementor's HTML widget too.
 */
add_filter(
	'elementor/widget/render_content',
	function ( $content, $widget ) {
		if ( false !== strpos( (string) $content, '[truman_' ) && in_array( $widget->get_name(), array( 'html', 'text-editor' ), true ) ) {
			$content = do_shortcode( $content );
		}
		return $content;
	},
	10,
	2
);
