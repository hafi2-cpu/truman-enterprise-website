<?php
/**
 * Settings, trip types and closed dates.
 *
 * @package Truman_Bookings
 */

defined( 'ABSPATH' ) || exit;

function tb_default_settings() {
	return array(
		'notify_email'   => 'enterprise@truman-enterprise.org.uk',
		'season_start'   => 4,   // April.
		'season_end'     => 10,  // October.
		'min_notice'     => 7,   // Days' notice needed for a request.
		'max_months'     => 12,  // How far ahead people can request.
		'buffer'         => 30,  // Minutes needed between two trips on the same day.
		'max_passengers' => 12,
		'max_wheelchair' => 2,
		'terms_url'      => '',
		'phone'          => '07895 053090',
	);
}

function tb_default_trips() {
	return array(
		array(
			'id'          => 'coven',
			'name'        => 'Coven Trip',
			'price'       => 110,
			'start'       => '10:30',
			'end'         => '16:00',
			'description' => 'Round trip to the Anchor pub at Coven with a 1–2 hour stop for lunch.',
			'lunch'       => "Own food\nFish & chips (cash, not Sundays)\nPub meal at the Anchor",
			'active'      => 1,
		),
		array(
			'id'          => 'autherley',
			'name'        => 'Autherley Trip',
			'price'       => 140,
			'start'       => '10:30',
			'end'         => '16:30',
			'description' => 'Cruise to Autherley Junction with the Shropshire Union Canal, with a short stop to stretch your legs.',
			'lunch'       => "Own food\nFish & chips (cash, not Sundays)",
			'active'      => 1,
		),
		array(
			'id'          => 'penkridge',
			'name'        => 'Penkridge Trip',
			'price'       => 170,
			'start'       => '09:30',
			'end'         => '17:30',
			'description' => 'The full adventure — 12 locks through the Staffordshire countryside.',
			'lunch'       => "Own food\nTake-away from the Cross Keys",
			'active'      => 1,
		),
	);
}

function tb_settings() {
	$saved = get_option( 'tb_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), tb_default_settings() );
}

/**
 * Trip types. Each: id, name, price, start, end (HH:MM), description, lunch (one option per line), active.
 */
function tb_trips( $active_only = false ) {
	$trips = get_option( 'tb_trips' );
	if ( ! is_array( $trips ) || ! $trips ) {
		$trips = tb_default_trips();
	}
	if ( $active_only ) {
		$trips = array_values( array_filter( $trips, function ( $t ) { return ! empty( $t['active'] ); } ) );
	}
	return $trips;
}

function tb_trip( $id ) {
	foreach ( tb_trips() as $t ) {
		if ( $t['id'] === $id ) {
			return $t;
		}
	}
	return null;
}

function tb_trip_lunch_options( $trip ) {
	return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $trip['lunch'] ) ) ) );
}

/**
 * Dates the boat is not running, as [ 'Y-m-d' => 'reason' ].
 */
function tb_closed_dates() {
	$c = get_option( 'tb_closed', array() );
	return is_array( $c ) ? $c : array();
}

/**
 * Who can manage bookings (editors and admins by default).
 */
function tb_cap() {
	return apply_filters( 'tb_manage_capability', 'edit_pages' );
}

function tb_time_to_min( $hhmm ) {
	$p = explode( ':', (string) $hhmm );
	return ( (int) $p[0] ) * 60 + ( isset( $p[1] ) ? (int) $p[1] : 0 );
}

function tb_format_time( $hhmm ) {
	$m = tb_time_to_min( $hhmm );
	$h = intdiv( $m, 60 );
	$i = $m % 60;
	$suffix = $h >= 12 ? 'pm' : 'am';
	$h12 = $h % 12 ? $h % 12 : 12;
	return $h12 . ( $i ? ':' . str_pad( (string) $i, 2, '0', STR_PAD_LEFT ) : '' ) . $suffix;
}

function tb_format_date( $ymd ) {
	$ts = strtotime( $ymd . ' 12:00:00' );
	return $ts ? wp_date( 'l j F Y', $ts, new DateTimeZone( 'UTC' ) ) : $ymd;
}

/**
 * The page holding the booking form (for links from the compact calendar).
 */
function tb_booking_page_url() {
	$page = get_page_by_path( 'book-a-trip' );
	return $page ? get_permalink( $page ) : home_url( '/book-a-trip/' );
}
