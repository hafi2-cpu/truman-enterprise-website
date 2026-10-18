<?php
/**
 * Emails to the bookings team and to groups.
 *
 * @package Truman_Bookings
 */

defined( 'ABSPATH' ) || exit;

function tb_mail_headers( $reply_to = '' ) {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', (string) $host );
	$h    = array(
		'Content-Type: text/plain; charset=UTF-8',
		'From: Truman Enterprise Narrowboat Trust <wordpress@' . $host . '>',
	);
	if ( $reply_to && is_email( $reply_to ) ) {
		$h[] = 'Reply-To: ' . $reply_to;
	}
	return $h;
}

/**
 * Plain-text summary of a booking.
 */
function tb_booking_summary( $b ) {
	$lines = array(
		'Reference:        ' . $b['ref'],
		'Date:             ' . tb_format_date( $b['date'] ),
		'Trip:             ' . $b['trip_name'] . ' (' . tb_format_time( $b['start'] ) . ' – ' . tb_format_time( $b['end'] ) . ')',
		'Price:            £' . $b['price'] . ' for the whole boat',
		'',
		'Group:            ' . $b['group_name'],
		'Contact:          ' . $b['contact_name'],
		'Phone:            ' . $b['phone'],
		'Email:            ' . $b['email'],
		'Address:          ' . preg_replace( '/\s*\n\s*/', ', ', trim( (string) $b['address'] ) ),
	);
	if ( $b['day_contact'] || $b['day_mobile'] ) {
		$lines[] = 'On the day:       ' . trim( $b['day_contact'] . ' ' . $b['day_mobile'] );
	}
	$lines[] = 'Passengers:       ' . $b['passengers'] . ( (int) $b['wheelchairs'] ? ' (' . (int) $b['wheelchairs'] . ' wheelchair user' . ( 1 === (int) $b['wheelchairs'] ? '' : 's' ) . ')' : '' );
	if ( $b['lunch'] ) {
		$lines[] = 'Lunch:            ' . $b['lunch'];
	}
	$lines[] = 'Photo consent:    ' . ( $b['photos'] ? 'Yes' : 'No' );
	if ( trim( (string) $b['needs'] ) ) {
		$lines[] = '';
		$lines[] = 'Notes from the group:';
		$lines[] = trim( $b['needs'] );
	}
	return implode( "\n", $lines );
}

function tb_mail_footer() {
	$s = tb_settings();
	return "\n\n--\nTruman Enterprise Narrowboat Trust\nHatherton Marina, Queens Road, Calf Heath, Wolverhampton WV10 7DT\n" . $s['phone'] . ' · ' . $s['notify_email'] . "\nRegistered Charity No. 1037914";
}

/**
 * New request → bookings team.
 */
function tb_mail_new_request( $id ) {
	$b  = tb_get_booking( $id );
	$to = tb_settings()['notify_email'];
	if ( ! $b || ! is_email( $to ) ) {
		return false;
	}
	$subject = sprintf( 'New boat trip request: %s – %s (%s)', $b['group_name'], tb_format_date( $b['date'] ), $b['trip_name'] );
	$body    = "A new boat trip request has come in through the website. The date is now held as provisional.\n\n"
		. tb_booking_summary( $b )
		. "\n\nConfirm or decline it here:\n" . admin_url( 'admin.php?page=tb-booking&id=' . $id )
		. "\n\nReplying to this email goes straight to the group.";
	return wp_mail( $to, $subject, $body, tb_mail_headers( $b['email'] ) );
}

/**
 * Acknowledgement → group.
 */
function tb_mail_received( $id ) {
	$b = tb_get_booking( $id );
	if ( ! $b || ! is_email( $b['email'] ) ) {
		return false;
	}
	$s       = tb_settings();
	$subject = sprintf( 'We’ve received your boat trip request (%s)', $b['ref'] );
	$body    = 'Dear ' . $b['contact_name'] . ",\n\n"
		. 'Thank you for your request to travel with us on narrowboat Enterprise. Your date is now held as provisional while one of our volunteers checks the details. '
		. "We’ll email you to confirm, together with an invoice.\n\n"
		. tb_booking_summary( $b )
		. "\n\nPayment is due 28 days before the trip, by cheque or BACS (details on the invoice). The price does not include food.\n\n"
		. 'If anything needs changing, just reply to this email or call ' . $s['phone'] . '.'
		. tb_mail_footer();
	return wp_mail( $b['email'], $subject, $body, tb_mail_headers( $s['notify_email'] ) );
}

/**
 * Confirmed / declined / cancelled → group.
 */
function tb_mail_status( $id, $status, $note = '' ) {
	$b = tb_get_booking( $id );
	if ( ! $b || ! is_email( $b['email'] ) ) {
		return false;
	}
	$s    = tb_settings();
	$note = trim( (string) $note );
	$date = tb_format_date( $b['date'] );
	switch ( $status ) {
		case 'confirmed':
			$subject = sprintf( 'Your boat trip is confirmed – %s', $date );
			$intro   = "Good news — your boat trip on narrowboat Enterprise is confirmed.\n\n"
				. "Your invoice will follow. Payment is due 28 days before the trip, by cheque or BACS. Please arrive at Hatherton Marina (WV10 7DT) about 15 minutes before departure.";
			break;
		case 'declined':
			$subject = sprintf( 'About your boat trip request for %s', $date );
			$intro   = "Thank you for your request. Unfortunately we’re not able to offer this trip.\n\nPlease have a look at the calendar on our website for another date: " . tb_booking_page_url();
			break;
		case 'cancelled':
			$subject = sprintf( 'Your boat trip on %s has been cancelled', $date );
			$intro   = 'This is to let you know that your boat trip has been cancelled.';
			break;
		default:
			return false;
	}
	$body = 'Dear ' . $b['contact_name'] . ",\n\n" . $intro
		. ( $note ? "\n\nA message from our team:\n" . $note : '' )
		. "\n\n" . tb_booking_summary( $b )
		. "\n\nAny questions, just reply to this email or call " . $s['phone'] . '.'
		. tb_mail_footer();
	return wp_mail( $b['email'], $subject, $body, tb_mail_headers( $s['notify_email'] ) );
}
