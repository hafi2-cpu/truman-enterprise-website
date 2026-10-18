<?php
/**
 * Plugin Name:       Truman Boat Bookings
 * Description:       Live availability calendar and boat trip booking requests for the Truman Enterprise Narrowboat Trust. Add [truman_booking] to a page for the calendar and request form, or [truman_availability] for a compact calendar.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Truman Enterprise Narrowboat Trust
 * License:           GPL-2.0-or-later
 * Text Domain:       truman-bookings
 *
 * @package Truman_Bookings
 */

defined( 'ABSPATH' ) || exit;

define( 'TB_VERSION', '1.0.0' );
define( 'TB_FILE', __FILE__ );
define( 'TB_DIR', plugin_dir_path( __FILE__ ) );
define( 'TB_URL', plugin_dir_url( __FILE__ ) );

require_once TB_DIR . 'includes/settings.php';
require_once TB_DIR . 'includes/bookings.php';
require_once TB_DIR . 'includes/emails.php';
require_once TB_DIR . 'includes/rest.php';
require_once TB_DIR . 'includes/shortcodes.php';
if ( is_admin() ) {
	require_once TB_DIR . 'includes/admin.php';
}

register_activation_hook( __FILE__, 'tb_activate' );

/**
 * Store default settings and trips on first activation.
 */
function tb_activate() {
	add_option( 'tb_settings', tb_default_settings() );
	add_option( 'tb_trips', tb_default_trips() );
	add_option( 'tb_closed', array() );
	tb_register_post_type();
}

/**
 * "Settings" link on the Plugins screen.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=tb-bookings' ) ) . '">' . esc_html__( 'Bookings', 'truman-bookings' ) . '</a>' );
		return $links;
	}
);
