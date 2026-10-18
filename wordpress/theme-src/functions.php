<?php
/**
 * Truman Enterprise theme functions.
 *
 * @package Truman_Enterprise
 */

defined( 'ABSPATH' ) || exit;

define( 'TRUMAN_VERSION', '1.3.0' );

require_once get_template_directory() . '/inc/setup.php';

/**
 * Theme supports and menu locations.
 */
function truman_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	register_nav_menus(
		array(
			'primary' => __( 'Main menu (header)', 'truman' ),
			'footer'  => __( 'Footer menu', 'truman' ),
		)
	);
}
add_action( 'after_setup_theme', 'truman_theme_setup' );

/**
 * Styles and scripts.
 */
function truman_assets() {
	// Version by file time so browsers and server caches pick up every change.
	$dir = get_template_directory();
	wp_enqueue_style( 'truman', get_stylesheet_uri(), array(), (string) filemtime( $dir . '/style.css' ) );
	wp_enqueue_script( 'truman', get_template_directory_uri() . '/assets/js/main.js', array(), (string) filemtime( $dir . '/assets/js/main.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'truman_assets' );

/**
 * Show emoji icons (🍺 ⚓ 🌿 …) as normal text, like the original site,
 * instead of WordPress swapping them for images that ignore the design's sizing.
 */
function truman_disable_emoji_images() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'truman_disable_emoji_images' );

/**
 * Browser tab / home-screen icon (the boat from the logo). A Site Icon chosen in
 * Appearance → Customize → Site Identity takes priority.
 */
function truman_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	$base = get_template_directory_uri() . '/assets/images/';
	printf( '<link rel="icon" type="image/png" sizes="32x32" href="%s">' . "\n", esc_url( $base . 'favicon-32.png' ) );
	printf( '<link rel="icon" type="image/png" sizes="16x16" href="%s">' . "\n", esc_url( $base . 'favicon-16.png' ) );
	printf( '<link rel="icon" type="image/png" sizes="512x512" href="%s">' . "\n", esc_url( $base . 'site-icon-512.png' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $base . 'apple-touch-icon.png' ) );
}
add_action( 'wp_head', 'truman_favicon', 5 );
add_action( 'admin_head', 'truman_favicon', 5 );

/**
 * Every page gets the "te-site" class; all site styles are scoped to it.
 */
function truman_body_class( $classes ) {
	$classes[] = 'te-site';
	return $classes;
}
add_filter( 'body_class', 'truman_body_class' );

/**
 * Header menu: the design styles the links themselves, so move the
 * "current page" and "nav-cta" (Book a Trip button) classes onto the <a>.
 */
function truman_menu_link_classes( $atts, $item, $args ) {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $atts;
	}
	$item_classes = (array) $item->classes;
	$add          = array();
	if ( in_array( 'nav-cta', $item_classes, true ) ) {
		$add[] = 'nav-cta';
	}
	if ( array_intersect( array( 'current-menu-item', 'current_page_item' ), $item_classes ) ) {
		$add[] = 'active';
	}
	if ( $add ) {
		$atts['class'] = trim( ( isset( $atts['class'] ) ? $atts['class'] : '' ) . ' ' . implode( ' ', $add ) );
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'truman_menu_link_classes', 10, 3 );

function truman_menu_li_classes( $classes, $item, $args ) {
	if ( ! empty( $args->theme_location ) && 'primary' === $args->theme_location ) {
		$classes = array_diff( $classes, array( 'nav-cta' ) );
	}
	return $classes;
}
add_filter( 'nav_menu_css_class', 'truman_menu_li_classes', 10, 3 );

/**
 * URL of a Truman page by slug (falls back to /slug/ if it does not exist yet).
 */
function truman_page_url( $slug ) {
	if ( 'home' === $slug ) {
		return home_url( '/' );
	}
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
}

/**
 * Menus used until real menus are assigned in Appearance → Menus.
 */
function truman_primary_menu_fallback() {
	$items = array(
		'home'         => __( 'Home', 'truman' ),
		'our-trips'    => __( 'Our Trips', 'truman' ),
		'get-involved' => __( 'Get Involved', 'truman' ),
		'support-us'   => __( 'Support Us', 'truman' ),
		'contact'      => __( 'Contact', 'truman' ),
		'book-a-trip'  => __( 'Book a Trip', 'truman' ),
	);
	echo '<ul class="nav-links">';
	foreach ( $items as $slug => $label ) {
		$classes = array();
		if ( 'book-a-trip' === $slug ) {
			$classes[] = 'nav-cta';
		}
		if ( ( 'home' === $slug && is_front_page() ) || ( 'home' !== $slug && is_page( $slug ) ) ) {
			$classes[] = 'active';
		}
		printf(
			'<li><a href="%s"%s>%s</a></li>',
			esc_url( truman_page_url( $slug ) ),
			$classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '',
			esc_html( $label )
		);
	}
	echo '</ul>';
}

function truman_footer_menu_fallback() {
	$items = array(
		'home'         => __( 'Home', 'truman' ),
		'our-trips'    => __( 'Our Trips', 'truman' ),
		'book-a-trip'  => __( 'Book a Trip', 'truman' ),
		'get-involved' => __( 'Get Involved', 'truman' ),
		'support-us'   => __( 'Support Us', 'truman' ),
		'contact'      => __( 'Contact', 'truman' ),
	);
	echo '<ul class="footer-menu">';
	foreach ( $items as $slug => $label ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( truman_page_url( $slug ) ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * Layout for ordinary (non-Truman) pages, e.g. a privacy policy.
 */
function truman_simple_page() {
	?>
	<div class="page-hero te-simple-hero">
		<div class="page-hero-content">
			<h1><?php the_title(); ?></h1>
		</div>
	</div>
	<section>
		<div class="section-inner te-prose">
			<?php the_content(); ?>
		</div>
	</section>
	<?php
}
