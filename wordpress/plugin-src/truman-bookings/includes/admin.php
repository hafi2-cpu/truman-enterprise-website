<?php
/**
 * Admin screens: Boat Bookings → Calendar, Requests, Add booking, Settings.
 *
 * @package Truman_Bookings
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		$cap     = tb_cap();
		$pending = tb_count_pending();
		$bubble  = $pending ? ' <span class="awaiting-mod count-' . $pending . '"><span class="pending-count">' . $pending . '</span></span>' : '';
		add_menu_page( __( 'Boat Bookings', 'truman-bookings' ), __( 'Boat Bookings', 'truman-bookings' ) . $bubble, $cap, 'tb-bookings', 'tb_admin_calendar_page', 'dashicons-calendar-alt', 26 );
		add_submenu_page( 'tb-bookings', __( 'Booking calendar', 'truman-bookings' ), __( 'Calendar', 'truman-bookings' ), $cap, 'tb-bookings', 'tb_admin_calendar_page' );
		add_submenu_page( 'tb-bookings', __( 'Booking requests', 'truman-bookings' ), __( 'Requests', 'truman-bookings' ) . $bubble, $cap, 'tb-requests', 'tb_admin_list_page' );
		add_submenu_page( 'tb-bookings', __( 'Add a booking', 'truman-bookings' ), __( 'Add booking', 'truman-bookings' ), $cap, 'tb-add', 'tb_admin_add_page' );
		add_submenu_page( 'tb-bookings', __( 'Booking settings', 'truman-bookings' ), __( 'Settings', 'truman-bookings' ), 'manage_options', 'tb-settings', 'tb_admin_settings_page' );
		add_submenu_page( '', __( 'Booking', 'truman-bookings' ), '', $cap, 'tb-booking', 'tb_admin_detail_page' );
	}
);

// Keep "Boat Bookings" highlighted on the booking detail screen.
add_filter(
	'parent_file',
	function ( $parent ) {
		global $plugin_page, $submenu_file;
		if ( 'tb-booking' === $plugin_page ) {
			$submenu_file = 'tb-requests'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			return 'tb-bookings';
		}
		return $parent;
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false === strpos( (string) $hook, 'tb-' ) ) {
			return;
		}
		wp_enqueue_style( 'truman-bookings-admin', TB_URL . 'assets/admin.css', array(), (string) filemtime( TB_DIR . 'assets/admin.css' ) );
	}
);

/* ------------------------------------------------------------------ helpers */

function tb_admin_url( $page, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

function tb_status_pill( $status ) {
	$labels = tb_statuses();
	return '<span class="tb-pill tb-pill--' . esc_attr( $status ) . '">' . esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status ) . '</span>';
}

function tb_admin_notice() {
	$msgs = array(
		'confirmed' => __( 'Booking confirmed.', 'truman-bookings' ),
		'declined'  => __( 'Request declined.', 'truman-bookings' ),
		'cancelled' => __( 'Booking cancelled.', 'truman-bookings' ),
		'pending'   => __( 'Booking re-opened as awaiting confirmation.', 'truman-bookings' ),
		'notes'     => __( 'Notes saved.', 'truman-bookings' ),
		'added'     => __( 'Booking added.', 'truman-bookings' ),
		'saved'     => __( 'Settings saved.', 'truman-bookings' ),
		'blocked'   => __( 'Date marked as not running.', 'truman-bookings' ),
		'unblocked' => __( 'Date opened again.', 'truman-bookings' ),
	);
	$key = isset( $_GET['tb_msg'] ) ? sanitize_key( wp_unslash( $_GET['tb_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $msgs[ $key ] ) ) {
		$mail = isset( $_GET['tb_mail'] ) ? sanitize_key( wp_unslash( $_GET['tb_mail'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$extra = 'sent' === $mail ? ' ' . __( 'The group has been emailed.', 'truman-bookings' ) : ( 'failed' === $mail ? ' ' . __( 'The email to the group could not be sent — please contact them directly.', 'truman-bookings' ) : '' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $key ] . $extra ) . '</p></div>';
	}
	$err = isset( $_GET['tb_err'] ) ? sanitize_text_field( wp_unslash( $_GET['tb_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $err ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
	}
}

/* ------------------------------------------------------------------ calendar */

function tb_admin_calendar_page() {
	$m = isset( $_GET['m'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['m'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : wp_date( 'Y-m' ); // phpcs:ignore WordPress.Security.NonceVerification
	$from   = $m . '-01';
	$first  = strtotime( $from . ' 12:00:00 UTC' );
	$to     = gmdate( 'Y-m-t', $first );
	$prev   = gmdate( 'Y-m', strtotime( '-1 month', $first ) );
	$next   = gmdate( 'Y-m', strtotime( '+1 month', $first ) );
	$today  = wp_date( 'Y-m-d' );
	$closed = tb_closed_dates();
	$list   = tb_query_bookings( $from, $to, array( 'pending', 'confirmed' ) );
	$by_day = array();
	foreach ( $list as $b ) {
		$by_day[ $b['date'] ][] = $b;
	}
	usort( $list, function ( $a, $b ) { return strcmp( $a['date'] . $a['start'], $b['date'] . $b['start'] ); } );
	$pending = tb_count_pending();
	?>
	<div class="wrap tb-admin">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Boat Bookings', 'truman-bookings' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( tb_admin_url( 'tb-add' ) ); ?>"><?php esc_html_e( 'Add booking', 'truman-bookings' ); ?></a>
		<hr class="wp-header-end">
		<?php tb_admin_notice(); ?>
		<?php if ( $pending ) : ?>
			<div class="tb-callout">
				<strong><?php echo esc_html( sprintf( _n( '%d request is waiting for confirmation.', '%d requests are waiting for confirmation.', $pending, 'truman-bookings' ), $pending ) ); ?></strong>
				<a class="button button-primary" href="<?php echo esc_url( tb_admin_url( 'tb-requests', array( 'view' => 'pending' ) ) ); ?>"><?php esc_html_e( 'Review requests', 'truman-bookings' ); ?></a>
			</div>
		<?php endif; ?>

		<div class="tb-cal-head">
			<a class="button" href="<?php echo esc_url( tb_admin_url( 'tb-bookings', array( 'm' => $prev ) ) ); ?>">&larr; <?php echo esc_html( wp_date( 'F', strtotime( $prev . '-15 12:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></a>
			<h2><?php echo esc_html( wp_date( 'F Y', $first, new DateTimeZone( 'UTC' ) ) ); ?></h2>
			<a class="button" href="<?php echo esc_url( tb_admin_url( 'tb-bookings', array( 'm' => $next ) ) ); ?>"><?php echo esc_html( wp_date( 'F', strtotime( $next . '-15 12:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?> &rarr;</a>
		</div>

		<div class="tb-legend">
			<span><i class="tb-key tb-key--pending"></i><?php esc_html_e( 'Awaiting confirmation', 'truman-bookings' ); ?></span>
			<span><i class="tb-key tb-key--confirmed"></i><?php esc_html_e( 'Confirmed', 'truman-bookings' ); ?></span>
			<span><i class="tb-key tb-key--closed"></i><?php esc_html_e( 'Not running / out of season', 'truman-bookings' ); ?></span>
		</div>

		<div class="tb-month">
			<?php foreach ( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ) as $d ) : ?>
				<div class="tb-dow"><?php echo esc_html( $d ); ?></div>
			<?php endforeach; ?>
			<?php
			$offset = ( (int) gmdate( 'N', $first ) ) - 1;
			for ( $i = 0; $i < $offset; $i++ ) {
				echo '<div class="tb-cell tb-cell--blank"></div>';
			}
			for ( $ts = $first; gmdate( 'Y-m-d', $ts ) <= $to; $ts += DAY_IN_SECONDS ) :
				$d       = gmdate( 'Y-m-d', $ts );
				$reason  = tb_date_closed_reason( $d );
				$blocked = isset( $closed[ $d ] );
				$off     = in_array( $reason, array( 'season', 'closed' ), true );
				$classes = 'tb-cell' . ( $off ? ' is-off' : '' ) . ( $d < $today ? ' is-past' : '' ) . ( $d === $today ? ' is-today' : '' );
				?>
				<div class="<?php echo esc_attr( $classes ); ?>">
					<div class="tb-cell-top">
						<span class="tb-num"><?php echo esc_html( gmdate( 'j', $ts ) ); ?></span>
						<?php if ( $d >= $today ) : ?>
							<span class="tb-cell-actions">
								<a href="<?php echo esc_url( tb_admin_url( 'tb-add', array( 'date' => $d ) ) ); ?>" title="<?php esc_attr_e( 'Add a booking on this date', 'truman-bookings' ); ?>">+ <?php esc_html_e( 'Add', 'truman-bookings' ); ?></a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tb_toggle_closed&date=' . $d . '&m=' . $m ), 'tb_toggle_' . $d ) ); ?>"><?php echo $blocked ? esc_html__( 'Open', 'truman-bookings' ) : esc_html__( 'Close', 'truman-bookings' ); ?></a>
							</span>
						<?php endif; ?>
					</div>
					<?php if ( $blocked ) : ?>
						<div class="tb-closed-note"><?php echo esc_html( $closed[ $d ] ? $closed[ $d ] : __( 'Not running', 'truman-bookings' ) ); ?></div>
					<?php elseif ( 'season' === $reason ) : ?>
						<div class="tb-closed-note tb-closed-note--soft"><?php esc_html_e( 'Out of season', 'truman-bookings' ); ?></div>
					<?php endif; ?>
					<?php foreach ( isset( $by_day[ $d ] ) ? $by_day[ $d ] : array() as $b ) : ?>
						<a class="tb-chip tb-chip--<?php echo esc_attr( $b['status'] ); ?>" href="<?php echo esc_url( tb_admin_url( 'tb-booking', array( 'id' => $b['id'] ) ) ); ?>">
							<span class="tb-chip-time"><?php echo esc_html( tb_format_time( $b['start'] ) . ' ' . $b['trip_name'] ); ?></span>
							<span class="tb-chip-group"><?php echo esc_html( $b['group_name'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endfor; ?>
		</div>

		<h2 class="tb-list-title"><?php echo esc_html( sprintf( __( 'Trips in %s', 'truman-bookings' ), wp_date( 'F', $first, new DateTimeZone( 'UTC' ) ) ) ); ?></h2>
		<?php tb_admin_table( $list, __( 'No trips booked or requested this month yet.', 'truman-bookings' ) ); ?>
		<p class="description"><?php esc_html_e( 'Use “Close” on a day the boat isn’t running (maintenance, no crew) so it can’t be requested. Out-of-season months are closed automatically — change the season in Settings.', 'truman-bookings' ); ?></p>
	</div>
	<?php
}

function tb_admin_table( $list, $empty ) {
	if ( ! $list ) {
		echo '<p class="tb-empty">' . esc_html( $empty ) . '</p>';
		return;
	}
	?>
	<table class="widefat striped tb-table">
		<thead><tr>
			<th><?php esc_html_e( 'Date', 'truman-bookings' ); ?></th>
			<th><?php esc_html_e( 'Trip', 'truman-bookings' ); ?></th>
			<th><?php esc_html_e( 'Group', 'truman-bookings' ); ?></th>
			<th><?php esc_html_e( 'Contact', 'truman-bookings' ); ?></th>
			<th><?php esc_html_e( 'Passengers', 'truman-bookings' ); ?></th>
			<th><?php esc_html_e( 'Status', 'truman-bookings' ); ?></th>
			<th><?php esc_html_e( 'Received', 'truman-bookings' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $list as $b ) : ?>
			<tr>
				<td><a class="row-title" href="<?php echo esc_url( tb_admin_url( 'tb-booking', array( 'id' => $b['id'] ) ) ); ?>"><?php echo esc_html( wp_date( 'D j M Y', strtotime( $b['date'] . ' 12:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></a><div class="tb-ref"><?php echo esc_html( $b['ref'] ); ?></div></td>
				<td><?php echo esc_html( $b['trip_name'] ); ?><div class="tb-ref"><?php echo esc_html( tb_format_time( $b['start'] ) . '–' . tb_format_time( $b['end'] ) ); ?></div></td>
				<td><?php echo esc_html( $b['group_name'] ); ?></td>
				<td><?php echo esc_html( $b['contact_name'] ); ?><div class="tb-ref"><?php echo esc_html( $b['phone'] ); ?></div></td>
				<td><?php echo esc_html( $b['passengers'] ); ?><?php echo (int) $b['wheelchairs'] ? ' <span class="tb-ref">(' . esc_html( (int) $b['wheelchairs'] ) . ' ♿)</span>' : ''; ?></td>
				<td><?php echo tb_status_pill( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
				<td><?php echo esc_html( mysql2date( 'j M, H:i', $b['created'] ) ); ?><div class="tb-ref"><?php echo 'website' === $b['source'] ? esc_html__( 'Website', 'truman-bookings' ) : esc_html__( 'Added by team', 'truman-bookings' ); ?></div></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/* ------------------------------------------------------------------ list */

function tb_admin_list_page() {
	$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'upcoming'; // phpcs:ignore WordPress.Security.NonceVerification
	$today = wp_date( 'Y-m-d' );
	$views = array(
		'upcoming'  => array( __( 'Upcoming', 'truman-bookings' ), $today, '9999-12-31', array( 'pending', 'confirmed' ), 'ASC' ),
		'pending'   => array( __( 'Awaiting confirmation', 'truman-bookings' ), $today, '9999-12-31', array( 'pending' ), 'ASC' ),
		'confirmed' => array( __( 'Confirmed', 'truman-bookings' ), $today, '9999-12-31', array( 'confirmed' ), 'ASC' ),
		'past'      => array( __( 'Past trips', 'truman-bookings' ), '0000-01-01', gmdate( 'Y-m-d', strtotime( $today . ' 12:00 UTC' ) - DAY_IN_SECONDS ), array( 'confirmed' ), 'DESC' ),
		'closed'    => array( __( 'Declined & cancelled', 'truman-bookings' ), '0000-01-01', '9999-12-31', array( 'declined', 'cancelled' ), 'DESC' ),
	);
	if ( ! isset( $views[ $view ] ) ) {
		$view = 'upcoming';
	}
	?>
	<div class="wrap tb-admin">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Booking requests', 'truman-bookings' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( tb_admin_url( 'tb-add' ) ); ?>"><?php esc_html_e( 'Add booking', 'truman-bookings' ); ?></a>
		<hr class="wp-header-end">
		<?php tb_admin_notice(); ?>
		<ul class="subsubsub">
			<?php
			$links = array();
			foreach ( $views as $key => $v ) {
				$count   = count( tb_query_bookings( $v[1], $v[2], $v[3] ) );
				$links[] = '<li><a href="' . esc_url( tb_admin_url( 'tb-requests', array( 'view' => $key ) ) ) . '"' . ( $key === $view ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $v[0] ) . ' <span class="count">(' . $count . ')</span></a>';
			}
			echo implode( ' |</li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</ul>
		<div class="clear"></div>
		<?php
		$v = $views[ $view ];
		tb_admin_table( tb_query_bookings( $v[1], $v[2], $v[3], $v[4] ), __( 'Nothing here.', 'truman-bookings' ) );
		?>
	</div>
	<?php
}

/* ------------------------------------------------------------------ detail */

function tb_admin_detail_page() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$b  = $id ? tb_get_booking( $id ) : null;
	if ( ! $b ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Booking not found', 'truman-bookings' ) . '</h1></div>';
		return;
	}
	$fields = tb_fields();
	$log    = get_post_meta( $id, '_tb_log', true );
	$clash  = null;
	if ( in_array( $b['status'], array( 'pending', 'declined', 'cancelled' ), true ) ) {
		$trip = tb_trip( $b['trip'] );
		if ( $trip ) {
			$others = array_filter( tb_query_bookings( $b['date'], $b['date'], array( 'confirmed', 'pending' ) ), function ( $o ) use ( $id ) { return (int) $o['id'] !== $id; } );
			$clash  = tb_clash( $trip, $others );
		}
	}
	?>
	<div class="wrap tb-admin">
		<p><a href="<?php echo esc_url( tb_admin_url( 'tb-requests' ) ); ?>">&larr; <?php esc_html_e( 'All requests', 'truman-bookings' ); ?></a></p>
		<h1><?php echo esc_html( $b['group_name'] ); ?> <?php echo tb_status_pill( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
		<p class="tb-lead"><strong><?php echo esc_html( tb_format_date( $b['date'] ) ); ?></strong> · <?php echo esc_html( $b['trip_name'] . ' · ' . tb_format_time( $b['start'] ) . '–' . tb_format_time( $b['end'] ) . ' · £' . $b['price'] ); ?> · <?php esc_html_e( 'Ref', 'truman-bookings' ); ?> <?php echo esc_html( $b['ref'] ); ?></p>
		<?php tb_admin_notice(); ?>

		<div class="tb-detail">
			<div class="tb-card">
				<h2><?php esc_html_e( 'Details', 'truman-bookings' ); ?></h2>
				<table class="tb-dl">
					<?php
					foreach ( array( 'contact_name', 'phone', 'email', 'address', 'day_contact', 'day_mobile', 'passengers', 'wheelchairs', 'lunch', 'needs', 'photos' ) as $k ) :
						$v = $b[ $k ];
						if ( 'photos' === $k ) {
							$v = $v ? __( 'Yes', 'truman-bookings' ) : __( 'No', 'truman-bookings' );
						}
						if ( '' === (string) $v ) {
							continue;
						}
						?>
						<tr><th><?php echo esc_html( $fields[ $k ] ); ?></th><td>
							<?php
							if ( 'email' === $k ) {
								echo '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
							} elseif ( 'phone' === $k || 'day_mobile' === $k ) {
								echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $v ) ) . '">' . esc_html( $v ) . '</a>';
							} else {
								echo nl2br( esc_html( $v ) );
							}
							?>
						</td></tr>
					<?php endforeach; ?>
					<tr><th><?php esc_html_e( 'Received', 'truman-bookings' ); ?></th><td><?php echo esc_html( mysql2date( 'j F Y, H:i', $b['created'] ) . ' · ' . ( 'website' === $b['source'] ? __( 'via the website', 'truman-bookings' ) : __( 'added by the team', 'truman-bookings' ) ) ); ?></td></tr>
				</table>
			</div>

			<div class="tb-side">
				<div class="tb-card">
					<h2><?php esc_html_e( 'Actions', 'truman-bookings' ); ?></h2>
					<?php if ( 'pending' === $b['status'] ) : ?>
						<?php tb_status_form( $id, 'confirmed', __( 'Confirm booking', 'truman-bookings' ), 'button-primary', __( 'Message to include (optional) — e.g. invoice details or arrival time', 'truman-bookings' ), $b['email'] ); ?>
						<?php tb_status_form( $id, 'declined', __( 'Decline request', 'truman-bookings' ), '', __( 'Reason / alternative dates (optional)', 'truman-bookings' ), $b['email'] ); ?>
					<?php elseif ( 'confirmed' === $b['status'] ) : ?>
						<p><?php esc_html_e( 'This trip is confirmed and the date is shown as booked on the website.', 'truman-bookings' ); ?></p>
						<?php tb_status_form( $id, 'cancelled', __( 'Cancel booking', 'truman-bookings' ), 'tb-danger', __( 'Message to the group (optional)', 'truman-bookings' ), $b['email'] ); ?>
					<?php else : ?>
						<?php if ( $clash ) : ?>
							<p class="tb-warn"><?php echo esc_html( sprintf( __( 'This slot is now taken by %s, so it can’t be re-opened.', 'truman-bookings' ), $clash['group_name'] ) ); ?></p>
						<?php else : ?>
							<?php tb_status_form( $id, 'pending', __( 'Re-open as awaiting confirmation', 'truman-bookings' ), '', '', '' ); ?>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<div class="tb-card">
					<h2><?php esc_html_e( 'Internal notes', 'truman-bookings' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'tb_notes_' . $id ); ?>
						<input type="hidden" name="action" value="tb_save_notes">
						<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
						<textarea name="admin_notes" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Only visible to the team — e.g. invoice number, skipper, payment received', 'truman-bookings' ); ?>"><?php echo esc_textarea( $b['admin_notes'] ); ?></textarea>
						<p><button class="button"><?php esc_html_e( 'Save notes', 'truman-bookings' ); ?></button></p>
					</form>
				</div>

				<?php if ( is_array( $log ) && $log ) : ?>
					<div class="tb-card">
						<h2><?php esc_html_e( 'History', 'truman-bookings' ); ?></h2>
						<ul class="tb-log">
							<?php foreach ( array_reverse( $log ) as $l ) : ?>
								<li><strong><?php echo esc_html( isset( tb_statuses()[ $l['status'] ] ) ? tb_statuses()[ $l['status'] ] : $l['status'] ); ?></strong> · <?php echo esc_html( mysql2date( 'j M Y, H:i', $l['time'] ) ); ?><?php echo $l['by'] ? ' · ' . esc_html( $l['by'] ) : ''; ?><?php echo $l['note'] ? '<br><em>' . esc_html( $l['note'] ) . '</em>' : ''; ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

function tb_status_form( $id, $status, $label, $class, $placeholder, $email ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tb-status-form">
		<?php wp_nonce_field( 'tb_status_' . $id ); ?>
		<input type="hidden" name="action" value="tb_status">
		<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
		<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
		<?php if ( $placeholder ) : ?>
			<textarea name="note" rows="2" class="large-text" placeholder="<?php echo esc_attr( $placeholder ); ?>"></textarea>
		<?php endif; ?>
		<?php if ( $email && is_email( $email ) ) : ?>
			<label class="tb-mail-toggle"><input type="checkbox" name="send_email" value="1" checked> <?php echo esc_html( sprintf( __( 'Email %s', 'truman-bookings' ), $email ) ); ?></label>
		<?php endif; ?>
		<button class="button <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

/* ------------------------------------------------------------------ actions */

function tb_check( $nonce_action ) {
	if ( ! current_user_can( tb_cap() ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'truman-bookings' ) );
	}
	check_admin_referer( $nonce_action );
}

add_action(
	'admin_post_tb_status',
	function () {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		tb_check( 'tb_status_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$b      = tb_get_booking( $id );
		if ( ! $b ) {
			wp_die( esc_html__( 'Booking not found.', 'truman-bookings' ) );
		}
		if ( in_array( $status, array( 'pending', 'confirmed' ), true ) && ! in_array( $b['status'], array( 'pending', 'confirmed' ), true ) ) {
			$trip   = tb_trip( $b['trip'] );
			$others = array_filter( tb_query_bookings( $b['date'], $b['date'] ), function ( $o ) use ( $id ) { return (int) $o['id'] !== $id; } );
			if ( $trip && tb_clash( $trip, $others ) ) {
				wp_safe_redirect( tb_admin_url( 'tb-booking', array( 'id' => $id, 'tb_err' => __( 'That slot is now taken by another booking.', 'truman-bookings' ) ) ) );
				exit;
			}
		}
		tb_set_status( $id, $status, $note );
		$mail = '';
		if ( ! empty( $_POST['send_email'] ) && in_array( $status, array( 'confirmed', 'declined', 'cancelled' ), true ) ) {
			$mail = tb_mail_status( $id, $status, $note ) ? 'sent' : 'failed';
		}
		wp_safe_redirect( tb_admin_url( 'tb-booking', array( 'id' => $id, 'tb_msg' => $status, 'tb_mail' => $mail ) ) );
		exit;
	}
);

add_action(
	'admin_post_tb_save_notes',
	function () {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		tb_check( 'tb_notes_' . $id );
		update_post_meta( $id, '_tb_admin_notes', isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '' );
		wp_safe_redirect( tb_admin_url( 'tb-booking', array( 'id' => $id, 'tb_msg' => 'notes' ) ) );
		exit;
	}
);

add_action(
	'admin_post_tb_toggle_closed',
	function () {
		$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_die( 'Bad date' );
		}
		tb_check( 'tb_toggle_' . $date );
		$closed = tb_closed_dates();
		if ( isset( $closed[ $date ] ) ) {
			unset( $closed[ $date ] );
			$msg = 'unblocked';
		} else {
			$closed[ $date ] = __( 'Boat not running', 'truman-bookings' );
			$msg             = 'blocked';
		}
		ksort( $closed );
		update_option( 'tb_closed', $closed, false );
		$m = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : substr( $date, 0, 7 );
		wp_safe_redirect( tb_admin_url( 'tb-bookings', array( 'm' => $m, 'tb_msg' => $msg ) ) );
		exit;
	}
);

/* ------------------------------------------------------------------ add */

function tb_admin_add_page() {
	$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$s    = tb_settings();
	?>
	<div class="wrap tb-admin">
		<h1><?php esc_html_e( 'Add a booking', 'truman-bookings' ); ?></h1>
		<p class="description"><?php esc_html_e( 'For bookings taken by phone or email. The date will show on the website calendar straight away.', 'truman-bookings' ); ?></p>
		<?php tb_admin_notice(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tb-card tb-add-form">
			<?php wp_nonce_field( 'tb_add' ); ?>
			<input type="hidden" name="action" value="tb_add">
			<table class="form-table" role="presentation">
				<tr><th><label for="tb-date"><?php esc_html_e( 'Date', 'truman-bookings' ); ?> *</label></th><td><input type="date" id="tb-date" name="date" value="<?php echo esc_attr( $date ); ?>" required></td></tr>
				<tr><th><label for="tb-trip"><?php esc_html_e( 'Trip', 'truman-bookings' ); ?> *</label></th><td><select id="tb-trip" name="trip" required>
					<?php foreach ( tb_trips() as $t ) : ?>
						<option value="<?php echo esc_attr( $t['id'] ); ?>"><?php echo esc_html( $t['name'] . ' (' . tb_format_time( $t['start'] ) . '–' . tb_format_time( $t['end'] ) . ', £' . $t['price'] . ')' . ( empty( $t['active'] ) ? ' – hidden from website' : '' ) ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
				<tr><th><label for="tb-status"><?php esc_html_e( 'Status', 'truman-bookings' ); ?></label></th><td><select id="tb-status" name="status"><option value="confirmed"><?php esc_html_e( 'Confirmed', 'truman-bookings' ); ?></option><option value="pending"><?php esc_html_e( 'Awaiting confirmation', 'truman-bookings' ); ?></option></select></td></tr>
				<tr><th><label for="tb-group"><?php esc_html_e( 'Group / organisation', 'truman-bookings' ); ?> *</label></th><td><input type="text" id="tb-group" name="group_name" class="regular-text" required></td></tr>
				<tr><th><label for="tb-contact"><?php esc_html_e( 'Contact name', 'truman-bookings' ); ?></label></th><td><input type="text" id="tb-contact" name="contact_name" class="regular-text"></td></tr>
				<tr><th><label for="tb-phone"><?php esc_html_e( 'Phone', 'truman-bookings' ); ?></label></th><td><input type="tel" id="tb-phone" name="phone" class="regular-text"></td></tr>
				<tr><th><label for="tb-email"><?php esc_html_e( 'Email', 'truman-bookings' ); ?></label></th><td><input type="email" id="tb-email" name="email" class="regular-text"></td></tr>
				<tr><th><label for="tb-address"><?php esc_html_e( 'Postal address', 'truman-bookings' ); ?></label></th><td><textarea id="tb-address" name="address" rows="3" class="regular-text"></textarea></td></tr>
				<tr><th><label for="tb-pax"><?php esc_html_e( 'Passengers', 'truman-bookings' ); ?></label></th><td><input type="number" id="tb-pax" name="passengers" min="1" max="<?php echo esc_attr( $s['max_passengers'] ); ?>" class="small-text"> &nbsp; <label><?php esc_html_e( 'Wheelchair users', 'truman-bookings' ); ?> <input type="number" name="wheelchairs" min="0" max="<?php echo esc_attr( $s['max_wheelchair'] ); ?>" value="0" class="small-text"></label></td></tr>
				<tr><th><label for="tb-lunch"><?php esc_html_e( 'Lunch', 'truman-bookings' ); ?></label></th><td><input type="text" id="tb-lunch" name="lunch" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Own food', 'truman-bookings' ); ?>"></td></tr>
				<tr><th><label for="tb-needs"><?php esc_html_e( 'Notes', 'truman-bookings' ); ?></label></th><td><textarea id="tb-needs" name="needs" rows="3" class="large-text"></textarea></td></tr>
				<tr><th></th><td>
					<label><input type="checkbox" name="send_email" value="1"> <?php esc_html_e( 'Email a confirmation to the group (needs an email address)', 'truman-bookings' ); ?></label><br>
					<label><input type="checkbox" name="force" value="1"> <?php esc_html_e( 'Add even if it overlaps another booking', 'truman-bookings' ); ?></label>
				</td></tr>
			</table>
			<p><button class="button button-primary"><?php esc_html_e( 'Add booking', 'truman-bookings' ); ?></button></p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_tb_add',
	function () {
		tb_check( 'tb_add' );
		$f    = wp_unslash( $_POST );
		$data = array(
			'date'         => sanitize_text_field( isset( $f['date'] ) ? $f['date'] : '' ),
			'trip'         => sanitize_key( isset( $f['trip'] ) ? $f['trip'] : '' ),
			'status'       => ( isset( $f['status'] ) && 'pending' === $f['status'] ) ? 'pending' : 'confirmed',
			'group_name'   => sanitize_text_field( isset( $f['group_name'] ) ? $f['group_name'] : '' ),
			'contact_name' => sanitize_text_field( isset( $f['contact_name'] ) ? $f['contact_name'] : '' ),
			'phone'        => sanitize_text_field( isset( $f['phone'] ) ? $f['phone'] : '' ),
			'email'        => sanitize_email( isset( $f['email'] ) ? $f['email'] : '' ),
			'address'      => sanitize_textarea_field( isset( $f['address'] ) ? $f['address'] : '' ),
			'passengers'   => absint( isset( $f['passengers'] ) ? $f['passengers'] : 0 ),
			'wheelchairs'  => absint( isset( $f['wheelchairs'] ) ? $f['wheelchairs'] : 0 ),
			'lunch'        => sanitize_text_field( isset( $f['lunch'] ) ? $f['lunch'] : '' ),
			'needs'        => sanitize_textarea_field( isset( $f['needs'] ) ? $f['needs'] : '' ),
			'photos'       => 0,
			'source'       => 'admin',
		);
		if ( '' === $data['group_name'] ) {
			wp_safe_redirect( tb_admin_url( 'tb-add', array( 'date' => $data['date'], 'tb_err' => __( 'Please enter the group name.', 'truman-bookings' ) ) ) );
			exit;
		}
		$id = tb_create_booking( $data, ! empty( $f['force'] ) );
		if ( is_wp_error( $id ) ) {
			$msg = 'tb_clash' === $id->get_error_code() ? __( 'That trip overlaps another booking on this date. Tick “Add even if it overlaps” to add it anyway.', 'truman-bookings' ) : $id->get_error_message();
			wp_safe_redirect( tb_admin_url( 'tb-add', array( 'date' => $data['date'], 'tb_err' => $msg ) ) );
			exit;
		}
		tb_set_status( $id, $data['status'], __( 'Added by the team', 'truman-bookings' ) );
		$mail = '';
		if ( ! empty( $f['send_email'] ) && 'confirmed' === $data['status'] && is_email( $data['email'] ) ) {
			$mail = tb_mail_status( $id, 'confirmed', '' ) ? 'sent' : 'failed';
		}
		wp_safe_redirect( tb_admin_url( 'tb-booking', array( 'id' => $id, 'tb_msg' => 'added', 'tb_mail' => $mail ) ) );
		exit;
	}
);

/* ------------------------------------------------------------------ settings */

function tb_admin_settings_page() {
	$s      = tb_settings();
	$trips  = tb_trips();
	$months = array();
	for ( $i = 1; $i <= 12; $i++ ) {
		$months[ $i ] = wp_date( 'F', gmmktime( 12, 0, 0, $i, 15, 2026 ), new DateTimeZone( 'UTC' ) );
	}
	$rows = array_merge( $trips, array( array( 'id' => '', 'name' => '', 'price' => '', 'start' => '', 'end' => '', 'description' => '', 'lunch' => '', 'active' => 1 ) ) );
	?>
	<div class="wrap tb-admin">
		<h1><?php esc_html_e( 'Booking settings', 'truman-bookings' ); ?></h1>
		<?php tb_admin_notice(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'tb_settings' ); ?>
			<input type="hidden" name="action" value="tb_save_settings">

			<div class="tb-card">
				<h2><?php esc_html_e( 'General', 'truman-bookings' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="tb-notify"><?php esc_html_e( 'Send new requests to', 'truman-bookings' ); ?></label></th><td><input type="email" id="tb-notify" name="s[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="tb-phone"><?php esc_html_e( 'Bookings phone number', 'truman-bookings' ); ?></label></th><td><input type="text" id="tb-phone" name="s[phone]" value="<?php echo esc_attr( $s['phone'] ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Trip season', 'truman-bookings' ); ?></th><td>
						<select name="s[season_start]"><?php foreach ( $months as $n => $l ) : ?><option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $s['season_start'], $n ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
						<?php esc_html_e( 'to', 'truman-bookings' ); ?>
						<select name="s[season_end]"><?php foreach ( $months as $n => $l ) : ?><option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $s['season_end'], $n ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
					</td></tr>
					<tr><th><label for="tb-notice"><?php esc_html_e( 'Notice needed', 'truman-bookings' ); ?></label></th><td><input type="number" id="tb-notice" name="s[min_notice]" value="<?php echo esc_attr( $s['min_notice'] ); ?>" min="0" class="small-text"> <?php esc_html_e( 'days', 'truman-bookings' ); ?></td></tr>
					<tr><th><label for="tb-ahead"><?php esc_html_e( 'Open for requests up to', 'truman-bookings' ); ?></label></th><td><input type="number" id="tb-ahead" name="s[max_months]" value="<?php echo esc_attr( $s['max_months'] ); ?>" min="1" max="36" class="small-text"> <?php esc_html_e( 'months ahead', 'truman-bookings' ); ?></td></tr>
					<tr><th><label for="tb-buffer"><?php esc_html_e( 'Gap needed between trips', 'truman-bookings' ); ?></label></th><td><input type="number" id="tb-buffer" name="s[buffer]" value="<?php echo esc_attr( $s['buffer'] ); ?>" min="0" class="small-text"> <?php esc_html_e( 'minutes', 'truman-bookings' ); ?><p class="description"><?php esc_html_e( 'Two trips can share a day only if their times don’t overlap, allowing this gap for turnaround.', 'truman-bookings' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Capacity', 'truman-bookings' ); ?></th><td><input type="number" name="s[max_passengers]" value="<?php echo esc_attr( $s['max_passengers'] ); ?>" min="1" class="small-text"> <?php esc_html_e( 'passengers', 'truman-bookings' ); ?> &nbsp; <input type="number" name="s[max_wheelchair]" value="<?php echo esc_attr( $s['max_wheelchair'] ); ?>" min="0" class="small-text"> <?php esc_html_e( 'wheelchair users', 'truman-bookings' ); ?></td></tr>
					<tr><th><label for="tb-terms"><?php esc_html_e( 'Booking terms page (optional)', 'truman-bookings' ); ?></label></th><td><input type="url" id="tb-terms" name="s[terms_url]" value="<?php echo esc_attr( $s['terms_url'] ); ?>" class="regular-text" placeholder="https://"></td></tr>
				</table>
			</div>

			<div class="tb-card">
				<h2><?php esc_html_e( 'Trips', 'truman-bookings' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Untick “Show” to stop a trip being offered on the website (existing bookings are kept). Fill in the blank row to add a new trip. Lunch options: one per line — options containing “not Sundays” are hidden on Sundays.', 'truman-bookings' ); ?></p>
				<table class="widefat tb-trips-table">
					<thead><tr><th><?php esc_html_e( 'Show', 'truman-bookings' ); ?></th><th><?php esc_html_e( 'Trip name', 'truman-bookings' ); ?></th><th><?php esc_html_e( 'Price £', 'truman-bookings' ); ?></th><th><?php esc_html_e( 'Times', 'truman-bookings' ); ?></th><th><?php esc_html_e( 'Description', 'truman-bookings' ); ?></th><th><?php esc_html_e( 'Lunch options', 'truman-bookings' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $i => $t ) : ?>
						<tr>
							<td><input type="hidden" name="t[<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $t['id'] ); ?>"><input type="checkbox" name="t[<?php echo esc_attr( $i ); ?>][active]" value="1" <?php checked( ! empty( $t['active'] ) ); ?>></td>
							<td><input type="text" name="t[<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $t['name'] ); ?>" placeholder="<?php echo $t['id'] ? '' : esc_attr__( 'New trip…', 'truman-bookings' ); ?>"></td>
							<td><input type="number" step="0.01" min="0" name="t[<?php echo esc_attr( $i ); ?>][price]" value="<?php echo esc_attr( $t['price'] ); ?>" class="small-text"></td>
							<td class="tb-times"><input type="time" name="t[<?php echo esc_attr( $i ); ?>][start]" value="<?php echo esc_attr( $t['start'] ); ?>"> – <input type="time" name="t[<?php echo esc_attr( $i ); ?>][end]" value="<?php echo esc_attr( $t['end'] ); ?>"></td>
							<td><textarea name="t[<?php echo esc_attr( $i ); ?>][description]" rows="3"><?php echo esc_textarea( $t['description'] ); ?></textarea></td>
							<td><textarea name="t[<?php echo esc_attr( $i ); ?>][lunch]" rows="3"><?php echo esc_textarea( $t['lunch'] ); ?></textarea></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p><button class="button button-primary"><?php esc_html_e( 'Save settings', 'truman-bookings' ); ?></button></p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_tb_save_settings',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'truman-bookings' ) );
		}
		check_admin_referer( 'tb_settings' );
		$in  = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$def = tb_default_settings();
		$s   = array(
			'notify_email'   => is_email( isset( $in['notify_email'] ) ? $in['notify_email'] : '' ) ? sanitize_email( $in['notify_email'] ) : $def['notify_email'],
			'phone'          => sanitize_text_field( isset( $in['phone'] ) ? $in['phone'] : $def['phone'] ),
			'season_start'   => min( 12, max( 1, absint( isset( $in['season_start'] ) ? $in['season_start'] : 4 ) ) ),
			'season_end'     => min( 12, max( 1, absint( isset( $in['season_end'] ) ? $in['season_end'] : 10 ) ) ),
			'min_notice'     => absint( isset( $in['min_notice'] ) ? $in['min_notice'] : 7 ),
			'max_months'     => min( 36, max( 1, absint( isset( $in['max_months'] ) ? $in['max_months'] : 12 ) ) ),
			'buffer'         => absint( isset( $in['buffer'] ) ? $in['buffer'] : 30 ),
			'max_passengers' => max( 1, absint( isset( $in['max_passengers'] ) ? $in['max_passengers'] : 12 ) ),
			'max_wheelchair' => absint( isset( $in['max_wheelchair'] ) ? $in['max_wheelchair'] : 2 ),
			'terms_url'      => esc_url_raw( isset( $in['terms_url'] ) ? $in['terms_url'] : '' ),
		);
		update_option( 'tb_settings', $s );

		$rows  = isset( $_POST['t'] ) && is_array( $_POST['t'] ) ? wp_unslash( $_POST['t'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$trips = array();
		$ids   = array();
		foreach ( $rows as $r ) {
			$name = sanitize_text_field( isset( $r['name'] ) ? $r['name'] : '' );
			$id   = sanitize_key( isset( $r['id'] ) ? $r['id'] : '' );
			if ( '' === $name ) {
				if ( $id ) { // keep existing trips even if the name was cleared.
					$old = tb_trip( $id );
					if ( $old ) {
						$trips[] = $old;
						$ids[]   = $id;
					}
				}
				continue;
			}
			if ( ! $id ) {
				$base     = sanitize_key( sanitize_title( $name ) );
				$base     = $base ? $base : 'trip';
				$existing = wp_list_pluck( tb_trips(), 'id' );
				$id       = $base;
				$n        = 2;
				while ( in_array( $id, $ids, true ) || in_array( $id, $existing, true ) ) {
					$id = $base . '-' . $n++;
				}
			}
			$start = preg_match( '/^\d{2}:\d{2}$/', isset( $r['start'] ) ? $r['start'] : '' ) ? $r['start'] : '10:00';
			$end   = preg_match( '/^\d{2}:\d{2}$/', isset( $r['end'] ) ? $r['end'] : '' ) ? $r['end'] : '16:00';
			if ( tb_time_to_min( $end ) <= tb_time_to_min( $start ) ) {
				$end = gmdate( 'H:i', ( tb_time_to_min( $start ) + 60 ) * 60 );
			}
			$trips[] = array(
				'id'          => $id,
				'name'        => $name,
				'price'       => (float) ( isset( $r['price'] ) ? $r['price'] : 0 ),
				'start'       => $start,
				'end'         => $end,
				'description' => sanitize_textarea_field( isset( $r['description'] ) ? $r['description'] : '' ),
				'lunch'       => sanitize_textarea_field( isset( $r['lunch'] ) ? $r['lunch'] : '' ),
				'active'      => ! empty( $r['active'] ) ? 1 : 0,
			);
			$ids[] = $id;
		}
		if ( $trips ) {
			update_option( 'tb_trips', $trips );
		}
		wp_safe_redirect( tb_admin_url( 'tb-settings', array( 'tb_msg' => 'saved' ) ) );
		exit;
	}
);
