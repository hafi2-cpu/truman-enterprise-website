<?php
/**
 * Public REST API used by the calendar and request form.
 *
 *   GET  /wp-json/truman/v1/availability?month=YYYY-MM
 *   POST /wp-json/truman/v1/requests
 *
 * @package Truman_Bookings
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'truman/v1',
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => 'tb_rest_availability',
				'permission_callback' => '__return_true',
				'args'                => array(
					'month' => array(
						'required'          => true,
						'validate_callback' => function ( $v ) {
							return (bool) preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $v );
						},
					),
				),
			)
		);
		register_rest_route(
			'truman/v1',
			'/requests',
			array(
				'methods'             => 'POST',
				'callback'            => 'tb_rest_request',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * Public trip info for the form.
 */
function tb_public_trips() {
	return array_map(
		function ( $t ) {
			return array(
				'id'          => $t['id'],
				'name'        => $t['name'],
				'price'       => (float) $t['price'],
				'start'       => $t['start'],
				'end'         => $t['end'],
				'time'        => tb_format_time( $t['start'] ) . ' – ' . tb_format_time( $t['end'] ),
				'description' => $t['description'],
				'lunch'       => tb_trip_lunch_options( $t ),
			);
		},
		tb_trips( true )
	);
}

function tb_no_cache( $response ) {
	$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	return $response;
}

function tb_rest_availability( WP_REST_Request $req ) {
	$month = $req['month'];
	$from  = $month . '-01';
	$to    = gmdate( 'Y-m-t', strtotime( $from . ' 12:00:00 UTC' ) );
	$s     = tb_settings();
	$today = wp_date( 'Y-m-d' );
	return tb_no_cache(
		rest_ensure_response(
			array(
				'month'    => $month,
				'today'    => $today,
				'maxMonth' => gmdate( 'Y-m', strtotime( '+' . (int) $s['max_months'] . ' months', strtotime( $today . ' 12:00:00 UTC' ) ) ),
				'days'     => tb_availability( $from, $to ),
				'trips'    => tb_public_trips(),
			)
		)
	);
}

function tb_rest_request( WP_REST_Request $req ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $req->get_body_params();
	}
	$s = tb_settings();

	// Spam protection: hidden field, too-fast submissions, and a per-visitor limit.
	if ( ! empty( $p['website'] ) ) {
		return tb_no_cache( rest_ensure_response( array( 'ok' => true, 'reference' => 'TE-' . wp_rand( 100000, 999999 ) ) ) );
	}
	if ( ! empty( $p['started'] ) && ( time() * 1000 - (int) $p['started'] ) < 3000 ) {
		return new WP_Error( 'tb_fast', __( 'Please take a moment to check your details, then try again.', 'truman-bookings' ), array( 'status' => 400 ) );
	}
	$ip_key = 'tb_rl_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 8 ) {
		return new WP_Error( 'tb_limit', __( 'Too many requests from this connection. Please call us instead.', 'truman-bookings' ), array( 'status' => 429 ) );
	}

	$clean = array(
		'date'         => sanitize_text_field( isset( $p['date'] ) ? $p['date'] : '' ),
		'trip'         => sanitize_key( isset( $p['trip'] ) ? $p['trip'] : '' ),
		'group_name'   => sanitize_text_field( isset( $p['group_name'] ) ? $p['group_name'] : '' ),
		'contact_name' => sanitize_text_field( isset( $p['contact_name'] ) ? $p['contact_name'] : '' ),
		'phone'        => sanitize_text_field( isset( $p['phone'] ) ? $p['phone'] : '' ),
		'email'        => sanitize_email( isset( $p['email'] ) ? $p['email'] : '' ),
		'address'      => sanitize_textarea_field( isset( $p['address'] ) ? $p['address'] : '' ),
		'day_contact'  => sanitize_text_field( isset( $p['day_contact'] ) ? $p['day_contact'] : '' ),
		'day_mobile'   => sanitize_text_field( isset( $p['day_mobile'] ) ? $p['day_mobile'] : '' ),
		'passengers'   => absint( isset( $p['passengers'] ) ? $p['passengers'] : 0 ),
		'wheelchairs'  => absint( isset( $p['wheelchairs'] ) ? $p['wheelchairs'] : 0 ),
		'lunch'        => sanitize_text_field( isset( $p['lunch'] ) ? $p['lunch'] : '' ),
		'needs'        => sanitize_textarea_field( isset( $p['needs'] ) ? $p['needs'] : '' ),
		'photos'       => ! empty( $p['photos'] ) ? 1 : 0,
		'status'       => 'pending',
		'source'       => 'website',
	);

	$errors = array();
	$trip   = tb_trip( $clean['trip'] );
	if ( ! $trip || empty( $trip['active'] ) ) {
		$errors['trip'] = __( 'Please choose a trip.', 'truman-bookings' );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $clean['date'] ) ) {
		$errors['date'] = __( 'Please choose a date.', 'truman-bookings' );
	} elseif ( $reason = tb_date_closed_reason( $clean['date'] ) ) { // phpcs:ignore
		$errors['date'] = tb_closed_label( $reason, $clean['date'] );
	}
	foreach ( array( 'group_name' => 'group or organisation name', 'contact_name' => 'contact name', 'phone' => 'phone number', 'address' => 'postal address' ) as $k => $label ) {
		if ( '' === $clean[ $k ] ) {
			/* translators: %s: field */
			$errors[ $k ] = sprintf( __( 'Please enter your %s.', 'truman-bookings' ), $label );
		}
	}
	if ( ! is_email( $clean['email'] ) ) {
		$errors['email'] = __( 'Please enter a valid email address.', 'truman-bookings' );
	}
	if ( $clean['passengers'] < 1 || $clean['passengers'] > (int) $s['max_passengers'] ) {
		/* translators: %d: max */
		$errors['passengers'] = sprintf( __( 'Please enter between 1 and %d passengers.', 'truman-bookings' ), (int) $s['max_passengers'] );
	}
	if ( $clean['wheelchairs'] > (int) $s['max_wheelchair'] ) {
		/* translators: %d: max */
		$errors['wheelchairs'] = sprintf( __( 'We can take up to %d wheelchair users per trip — please call us to discuss.', 'truman-bookings' ), (int) $s['max_wheelchair'] );
	}
	if ( $trip && $clean['lunch'] && ! in_array( $clean['lunch'], tb_trip_lunch_options( $trip ), true ) ) {
		$errors['lunch'] = __( 'Please choose a lunch option.', 'truman-bookings' );
	}
	if ( empty( $p['terms'] ) ) {
		$errors['terms'] = __( 'Please confirm you’ve read the booking terms.', 'truman-bookings' );
	}
	if ( $errors ) {
		return new WP_Error( 'tb_invalid', __( 'Please check the highlighted details.', 'truman-bookings' ), array( 'status' => 400, 'fields' => $errors ) );
	}

	$id = tb_create_booking( $clean );
	if ( is_wp_error( $id ) ) {
		$id->add_data( array( 'status' => 409 ) );
		return $id;
	}
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );
	tb_set_status( $id, 'pending', '' );

	tb_mail_new_request( $id );
	tb_mail_received( $id );

	$b = tb_get_booking( $id );
	return tb_no_cache(
		rest_ensure_response(
			array(
				'ok'        => true,
				'reference' => $b['ref'],
				'date'      => tb_format_date( $b['date'] ),
				'trip'      => $b['trip_name'],
				'email'     => $b['email'],
			)
		)
	);
}
