<?php
/**
 * Booking storage and availability.
 *
 * Bookings are a private post type; details are stored as post meta.
 * A pending request or a confirmed booking holds its time slot (plus the
 * turnaround buffer). Two trips can share a day only if their times don't overlap.
 *
 * @package Truman_Bookings
 */

defined( 'ABSPATH' ) || exit;

function tb_register_post_type() {
	register_post_type(
		'tb_booking',
		array(
			'label'               => __( 'Boat bookings', 'truman-bookings' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
		)
	);
}
add_action( 'init', 'tb_register_post_type' );

/**
 * Booking fields saved as meta (key => label).
 */
function tb_fields() {
	return array(
		'date'          => 'Date',
		'trip'          => 'Trip',
		'start'         => 'Start',
		'end'           => 'Finish',
		'price'         => 'Price',
		'group_name'    => 'Group / organisation',
		'contact_name'  => 'Contact name',
		'phone'         => 'Phone',
		'email'         => 'Email',
		'address'       => 'Postal address',
		'day_contact'   => 'Contact on the day',
		'day_mobile'    => 'Mobile on the day',
		'passengers'    => 'Passengers',
		'wheelchairs'   => 'Wheelchair users',
		'lunch'         => 'Lunch option',
		'needs'         => 'Notes from the group',
		'photos'        => 'Photo consent',
		'status'        => 'Status',
		'source'        => 'Source',
		'admin_notes'   => 'Internal notes',
		'status_note'   => 'Last message to group',
	);
}

function tb_statuses() {
	return array(
		'pending'   => __( 'Awaiting confirmation', 'truman-bookings' ),
		'confirmed' => __( 'Confirmed', 'truman-bookings' ),
		'declined'  => __( 'Declined', 'truman-bookings' ),
		'cancelled' => __( 'Cancelled', 'truman-bookings' ),
	);
}

/**
 * All the details of one booking as an array.
 */
function tb_get_booking( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'tb_booking' !== $post->post_type ) {
		return null;
	}
	$b = array(
		'id'       => $post->ID,
		'created'  => $post->post_date,
		'ref'      => get_post_meta( $post->ID, '_tb_ref', true ),
	);
	foreach ( array_keys( tb_fields() ) as $k ) {
		$b[ $k ] = get_post_meta( $post->ID, '_tb_' . $k, true );
	}
	$trip            = tb_trip( $b['trip'] );
	$b['trip_name']  = $trip ? $trip['name'] : $b['trip'];
	return $b;
}

/**
 * Bookings between two dates (inclusive) with the given statuses.
 */
function tb_query_bookings( $from, $to, $statuses = array( 'pending', 'confirmed' ), $order = 'ASC' ) {
	$meta = array(
		'relation' => 'AND',
		array(
			'key'     => '_tb_date',
			'value'   => array( $from, $to ),
			'compare' => 'BETWEEN',
			'type'    => 'CHAR',
		),
	);
	if ( $statuses ) {
		$meta[] = array(
			'key'     => '_tb_status',
			'value'   => (array) $statuses,
			'compare' => 'IN',
		);
	}
	$ids = get_posts(
		array(
			'post_type'      => 'tb_booking',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_key'       => '_tb_date', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value',
			'order'          => $order,
			'no_found_rows'  => true,
		)
	);
	return array_values( array_filter( array_map( 'tb_get_booking', $ids ) ) );
}

/**
 * Does a trip fit on a day, given the bookings already holding slots?
 *
 * @param array    $trip       Trip type.
 * @param array    $bookings   Bookings on that day (pending/confirmed).
 * @param int|null $ignore_id  Booking to ignore (when re-checking itself).
 * @return array|null The booking it clashes with, or null if it fits.
 */
function tb_clash( $trip, $bookings, $ignore_id = null ) {
	$buffer = (int) tb_settings()['buffer'];
	$s      = tb_time_to_min( $trip['start'] );
	$e      = tb_time_to_min( $trip['end'] );
	foreach ( $bookings as $b ) {
		if ( $ignore_id && (int) $b['id'] === (int) $ignore_id ) {
			continue;
		}
		$bs = tb_time_to_min( $b['start'] );
		$be = tb_time_to_min( $b['end'] );
		if ( $s < $be + $buffer && $bs < $e + $buffer ) {
			return $b;
		}
	}
	return null;
}

/**
 * Why a date can't take requests at all (or '' if it can).
 */
function tb_date_closed_reason( $ymd ) {
	$s      = tb_settings();
	$today  = wp_date( 'Y-m-d' );
	$ts     = strtotime( $ymd . ' 12:00:00 UTC' );
	$month  = (int) gmdate( 'n', $ts );
	$closed = tb_closed_dates();

	if ( $ymd < $today ) {
		return 'past';
	}
	$notice = gmdate( 'Y-m-d', strtotime( $today . ' 12:00:00 UTC' ) + DAY_IN_SECONDS * (int) $s['min_notice'] );
	if ( $ymd < $notice ) {
		return 'notice';
	}
	$limit = gmdate( 'Y-m-d', strtotime( '+' . (int) $s['max_months'] . ' months', strtotime( $today . ' 12:00:00 UTC' ) ) );
	if ( $ymd > $limit ) {
		return 'far';
	}
	$a = (int) $s['season_start'];
	$z = (int) $s['season_end'];
	$in_season = $a <= $z ? ( $month >= $a && $month <= $z ) : ( $month >= $a || $month <= $z );
	if ( ! $in_season ) {
		return 'season';
	}
	if ( isset( $closed[ $ymd ] ) ) {
		return 'closed';
	}
	return '';
}

function tb_closed_label( $reason, $ymd = '' ) {
	$closed = tb_closed_dates();
	switch ( $reason ) {
		case 'past':
			return __( 'Date has passed', 'truman-bookings' );
		case 'notice':
			/* translators: %d: days */
			return sprintf( __( 'Requests need %d days’ notice', 'truman-bookings' ), (int) tb_settings()['min_notice'] );
		case 'far':
			return __( 'Not open for requests yet', 'truman-bookings' );
		case 'season':
			return __( 'Outside the trip season', 'truman-bookings' );
		case 'closed':
			return ( $ymd && ! empty( $closed[ $ymd ] ) ) ? $closed[ $ymd ] : __( 'Boat not running', 'truman-bookings' );
	}
	return '';
}

/**
 * Availability for each day between two dates.
 *
 * s: available | limited (some trips still fit) | provisional (held by a request) | booked | closed
 */
function tb_availability( $from, $to ) {
	$trips    = tb_trips( true );
	$bookings = tb_query_bookings( $from, $to );
	$by_day   = array();
	foreach ( $bookings as $b ) {
		$by_day[ $b['date'] ][] = $b;
	}
	$out = array();
	for ( $ts = strtotime( $from . ' 12:00:00 UTC' ); gmdate( 'Y-m-d', $ts ) <= $to; $ts += DAY_IN_SECONDS ) {
		$d      = gmdate( 'Y-m-d', $ts );
		$reason = tb_date_closed_reason( $d );
		if ( $reason ) {
			$out[ $d ] = array( 's' => 'closed', 'r' => tb_closed_label( $reason, $d ), 'trips' => array() );
			continue;
		}
		$day  = isset( $by_day[ $d ] ) ? $by_day[ $d ] : array();
		$fits = array();
		foreach ( $trips as $t ) {
			if ( ! tb_clash( $t, $day ) ) {
				$fits[] = $t['id'];
			}
		}
		if ( ! $day ) {
			$s = 'available';
		} elseif ( $fits ) {
			$s = 'limited';
		} else {
			$confirmed = array_filter( $day, function ( $b ) { return 'confirmed' === $b['status']; } );
			$s         = $confirmed ? 'booked' : 'provisional';
		}
		$out[ $d ] = array( 's' => $s, 'trips' => $fits );
	}
	return $out;
}

/**
 * Create a booking.
 *
 * @param array $data   Field values (see tb_fields()).
 * @param bool  $force  Skip the clash check (admin override).
 * @return int|WP_Error Booking ID.
 */
function tb_create_booking( $data, $force = false ) {
	$trip = tb_trip( isset( $data['trip'] ) ? $data['trip'] : '' );
	if ( ! $trip ) {
		return new WP_Error( 'tb_trip', __( 'Please choose a trip.', 'truman-bookings' ) );
	}
	if ( empty( $data['date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['date'] ) ) {
		return new WP_Error( 'tb_date', __( 'Please choose a date.', 'truman-bookings' ) );
	}
	$data['start'] = $trip['start'];
	$data['end']   = $trip['end'];
	$data['price'] = $trip['price'];
	if ( empty( $data['status'] ) ) {
		$data['status'] = 'pending';
	}

	if ( ! $force ) {
		$clash = tb_clash( $trip, tb_query_bookings( $data['date'], $data['date'] ) );
		if ( $clash ) {
			return new WP_Error( 'tb_clash', __( 'Sorry, that trip is no longer available on this date — someone has just requested it. Please choose another date or trip.', 'truman-bookings' ), array( 'clash' => $clash['id'] ) );
		}
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'tb_booking',
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s – %s – %s', $data['date'], $trip['name'], isset( $data['group_name'] ) ? $data['group_name'] : '' ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	foreach ( array_keys( tb_fields() ) as $k ) {
		if ( isset( $data[ $k ] ) ) {
			update_post_meta( $id, '_tb_' . $k, $data[ $k ] );
		}
	}
	update_post_meta( $id, '_tb_ref', 'TE-' . str_replace( '-', '', substr( $data['date'], 2 ) ) . '-' . $id );
	return $id;
}

function tb_set_status( $id, $status, $note = '' ) {
	if ( ! isset( tb_statuses()[ $status ] ) ) {
		return false;
	}
	update_post_meta( $id, '_tb_status', $status );
	update_post_meta( $id, '_tb_status_note', $note );
	$log   = get_post_meta( $id, '_tb_log', true );
	$log   = is_array( $log ) ? $log : array();
	$user  = wp_get_current_user();
	$log[] = array(
		'time'   => current_time( 'mysql' ),
		'status' => $status,
		'by'     => $user && $user->exists() ? $user->display_name : '',
		'note'   => $note,
	);
	update_post_meta( $id, '_tb_log', $log );
	return true;
}

function tb_count_pending() {
	$q = new WP_Query(
		array(
			'post_type'      => 'tb_booking',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => '_tb_status', 'value' => 'pending' ),
				array( 'key' => '_tb_date', 'value' => wp_date( 'Y-m-d' ), 'compare' => '>=', 'type' => 'CHAR' ),
			),
		)
	);
	return (int) $q->found_posts;
}
