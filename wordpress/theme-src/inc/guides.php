<?php
/**
 * Volunteer Guides: step-by-step help shown inside the WordPress admin
 * (left menu → Volunteer Guides). Only logged-in editors and admins can see it.
 *
 * Content is built from /guides/*.md in the repo (see wordpress/build.py).
 *
 * @package Truman_Enterprise
 */

defined( 'ABSPATH' ) || exit;

function truman_guides() {
	return array(
		'editing'  => __( 'Editing the Website', 'truman' ),
		'bookings' => __( 'Boat Bookings', 'truman' ),
	);
}

function truman_guides_menu() {
	add_menu_page(
		__( 'Volunteer Guides', 'truman' ),
		__( 'Volunteer Guides', 'truman' ),
		'edit_pages',
		'truman-guides',
		'truman_guides_page',
		'dashicons-book-alt',
		3
	);
}
add_action( 'admin_menu', 'truman_guides_menu' );

function truman_guide_url( $key ) {
	return admin_url( 'admin.php?page=truman-guides&guide=' . $key );
}

function truman_guides_page() {
	$guides = truman_guides();
	$key    = isset( $_GET['guide'] ) ? sanitize_key( wp_unslash( $_GET['guide'] ) ) : 'editing';
	if ( ! isset( $guides[ $key ] ) ) {
		$key = 'editing';
	}
	$file = get_template_directory() . '/guides/' . $key . '.html';
	$html = file_exists( $file ) ? file_get_contents( $file ) : '';
	$html = str_replace( '%%GUIDES%%', esc_url( get_template_directory_uri() . '/guides' ), $html );
	$html = preg_replace_callback(
		'/%%GUIDE:([a-z]+)%%/',
		function ( $m ) {
			return esc_url( truman_guide_url( $m[1] ) );
		},
		$html
	);
	?>
	<div class="wrap te-guides">
		<hr class="wp-header-end" style="display:none">
		<nav class="nav-tab-wrapper">
			<?php foreach ( $guides as $k => $label ) : ?>
				<a href="<?php echo esc_url( truman_guide_url( $k ) ); ?>" class="nav-tab<?php echo $k === $key ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
			<a href="#" class="te-print" onclick="window.print();return false;"><?php esc_html_e( 'Print', 'truman' ); ?></a>
		</nav>
		<div class="te-guide">
			<?php echo wp_kses_post( $html ); ?>
		</div>
	</div>
	<style>
		.te-guides .nav-tab-wrapper { margin-bottom: 0; }
		.te-print { float: right; margin-top: 10px; text-decoration: none; }
		.te-guide { background: #fff; border: 1px solid #c3c4c7; border-top: 0; padding: 8px 32px 32px; max-width: 860px; font-size: 14px; line-height: 1.65; }
		.te-guide h1 { font-size: 26px; margin: 24px 0 4px; }
		.te-guide h2 { font-size: 19px; margin: 32px 0 8px; padding-top: 16px; border-top: 1px solid #eee; }
		.te-guide img { max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 6px; margin: 8px 0 16px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
		.te-guide li { margin-bottom: 6px; }
		.te-guide table { border-collapse: collapse; margin: 8px 0 16px; }
		.te-guide th, .te-guide td { border: 1px solid #ddd; padding: 6px 12px; text-align: left; }
		.te-guide th { background: #f6f7f7; }
		.te-guide code { font-size: 12px; word-break: break-all; }
		@media print {
			#adminmenumain, #wpadminbar, .nav-tab-wrapper, #wpfooter, .notice { display: none !important; }
			#wpcontent { margin-left: 0 !important; }
			.te-guide { border: 0; max-width: none; }
			.te-guide img { break-inside: avoid; }
		}
	</style>
	<?php
}

/**
 * Dashboard box pointing volunteers at the guides.
 */
function truman_guides_dashboard_widget() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'truman_guides',
		__( 'Volunteer Guides', 'truman' ),
		function () {
			echo '<p>' . esc_html__( 'Step-by-step help with screenshots:', 'truman' ) . '</p><ul>';
			foreach ( truman_guides() as $k => $label ) {
				printf( '<li><span class="dashicons dashicons-book-alt" style="color:#2f6f63"></span> <a href="%s"><strong>%s</strong></a></li>', esc_url( truman_guide_url( $k ) ), esc_html( $label ) );
			}
			echo '</ul>';
		}
	);
	// Put it at the top of the first column.
	global $wp_meta_boxes;
	$normal = $wp_meta_boxes['dashboard']['normal']['core'];
	$box    = array( 'truman_guides' => $normal['truman_guides'] );
	unset( $normal['truman_guides'] );
	$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $box, $normal );
}
add_action( 'wp_dashboard_setup', 'truman_guides_dashboard_widget' );
