<?php
/**
 * Volunteer Guides: step-by-step help.
 *
 * - Inside the admin: left menu → Volunteer Guides (logged-in editors and admins).
 * - A private share link (/volunteer-guides/<secret>/) that works without logging in,
 *   for anyone who has the link. It is not linked from the site, is hidden from search
 *   engines, and an administrator can replace it with a new one at any time.
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

function truman_guide_url( $key ) {
	return admin_url( 'admin.php?page=truman-guides&guide=' . $key );
}

/* ------------------------------------------------------------ share link */

function truman_guides_secret( $renew = false ) {
	$secret = get_option( 'truman_guides_secret' );
	if ( $renew || ! $secret ) {
		$secret = strtolower( wp_generate_password( 16, false, false ) );
		update_option( 'truman_guides_secret', $secret, false );
	}
	return $secret;
}

function truman_guide_share_url( $key = '' ) {
	return home_url( '/volunteer-guides/' . truman_guides_secret() . '/' . ( $key && 'editing' !== $key ? $key . '/' : '' ) );
}

/**
 * The guide's HTML with image and cross-guide links filled in.
 */
function truman_guide_html( $key, $link ) {
	$file = get_template_directory() . '/guides/' . $key . '.html';
	$html = file_exists( $file ) ? file_get_contents( $file ) : '';
	$html = str_replace( '%%GUIDES%%', esc_url( get_template_directory_uri() . '/guides' ), $html );
	return preg_replace_callback(
		'/%%GUIDE:([a-z]+)%%/',
		function ( $m ) use ( $link ) {
			return esc_url( call_user_func( $link, $m[1] ) );
		},
		$html
	);
}

function truman_guides_css() {
	return '
	.te-print { float: right; margin-top: 10px; text-decoration: none; }
	.te-guide { background: #fff; border: 1px solid #c3c4c7; border-top: 0; padding: 8px 32px 32px; max-width: 860px; font-size: 14px; line-height: 1.65; box-sizing: border-box; }
	.te-guide h1 { font-size: 26px; line-height: 1.25; margin: 24px 0 4px; }
	.te-guide h2 { font-size: 19px; margin: 32px 0 8px; padding-top: 16px; border-top: 1px solid #eee; }
	.te-guide img { max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 6px; margin: 8px 0 16px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
	.te-guide li { margin-bottom: 6px; }
	.te-guide table { border-collapse: collapse; margin: 8px 0 16px; }
	.te-guide th, .te-guide td { border: 1px solid #ddd; padding: 6px 12px; text-align: left; }
	.te-guide th { background: #f6f7f7; }
	.te-guide code { font-size: 12px; word-break: break-all; }
	@media (max-width: 600px) { .te-guide { padding: 4px 16px 24px; } }
	@media print {
		#adminmenumain, #wpadminbar, .nav-tab-wrapper, .te-tabs, .te-share, #wpfooter, .notice { display: none !important; }
		#wpcontent { margin-left: 0 !important; }
		.te-guide { border: 0; max-width: none; padding: 0; }
		.te-guide img { break-inside: avoid; }
	}';
}

/**
 * Serve /volunteer-guides/<secret>/[bookings/] to anyone with the link.
 */
function truman_guides_public() {
	$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
	$base = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$path = '/' . ltrim( substr( (string) $path, strlen( rtrim( (string) $base, '/' ) ) ), '/' );
	if ( ! preg_match( '#^/volunteer-guides/([a-z0-9]+)/(?:([a-z]+)/?)?$#', $path, $m ) ) {
		return;
	}
	$secret = get_option( 'truman_guides_secret' );
	if ( ! $secret || ! hash_equals( $secret, $m[1] ) ) {
		return; // Wrong or old link: WordPress shows its normal "not found" page.
	}
	$guides = truman_guides();
	$key    = isset( $m[2] ) && isset( $guides[ $m[2] ] ) ? $m[2] : 'editing';
	$html   = truman_guide_html( $key, 'truman_guide_share_url' );

	status_header( 200 );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Referrer-Policy: no-referrer' );
	header( 'Content-Type: text/html; charset=utf-8' );
	$logo = get_template_directory_uri() . '/assets/images/';
	?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $guides[ $key ] . ' – Volunteer Guide – Truman Enterprise' ); ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( $logo . 'favicon-32.png' ); ?>">
<style>
	body { margin: 0; background: #f6f3ec; color: #1d2327; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
	.te-wrap { max-width: 860px; margin: 0 auto; padding: 24px 16px 48px; }
	.te-head { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; color: #1e3a4c; font-weight: 600; }
	.te-head span { font-size: 15px; }
	.te-tabs { display: flex; gap: 6px; border-bottom: 1px solid #c3c4c7; }
	.te-tabs a { padding: 9px 14px; border: 1px solid #c3c4c7; border-bottom: 0; background: #e8e5dd; color: #50575e; text-decoration: none; font-weight: 600; font-size: 14px; border-radius: 4px 4px 0 0; margin-bottom: -1px; }
	.te-tabs a.on { background: #fff; color: #1d2327; }
	.te-tabs .te-print { margin-left: auto; border: 0; background: none; color: #2f6f63; }
	.te-guide a { color: #2f6f63; }
	<?php echo truman_guides_css(); // phpcs:ignore ?>
</style>
</head>
<body>
<div class="te-wrap">
	<div class="te-head"><span>Truman Enterprise Narrowboat Trust · Volunteer Guides</span></div>
	<nav class="te-tabs">
		<?php foreach ( $guides as $k => $label ) : ?>
			<a href="<?php echo esc_url( truman_guide_share_url( $k ) ); ?>"<?php echo $k === $key ? ' class="on"' : ''; ?>><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
		<a href="#" class="te-print" onclick="window.print();return false;">Print</a>
	</nav>
	<div class="te-guide"><?php echo wp_kses_post( $html ); ?></div>
</div>
</body>
</html>
	<?php
	exit;
}
add_action( 'init', 'truman_guides_public', 1 );

/**
 * Administrators: make a new link (the old one stops working).
 */
function truman_guides_renew() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'truman_guides_renew' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'truman' ) );
	}
	truman_guides_secret( true );
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
	}
	wp_safe_redirect( admin_url( 'admin.php?page=truman-guides&renewed=1' ) );
	exit;
}
add_action( 'admin_post_truman_guides_renew', 'truman_guides_renew' );

/* ------------------------------------------------------------ admin page */

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

function truman_guides_page() {
	$guides = truman_guides();
	$key    = isset( $_GET['guide'] ) ? sanitize_key( wp_unslash( $_GET['guide'] ) ) : 'editing';
	if ( ! isset( $guides[ $key ] ) ) {
		$key = 'editing';
	}
	$html  = truman_guide_html( $key, 'truman_guide_url' );
	$share = truman_guide_share_url();
	?>
	<div class="wrap te-guides">
		<hr class="wp-header-end" style="display:none">
		<?php if ( ! empty( $_GET['renewed'] ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'New share link made. The old link no longer works.', 'truman' ); ?></p></div>
		<?php endif; ?>
		<div class="te-share">
			<strong><?php esc_html_e( 'Share link', 'truman' ); ?></strong>
			<span class="description"><?php esc_html_e( '– anyone with this link can read the guides without logging in. It is not shown anywhere on the website or in Google.', 'truman' ); ?></span>
			<div class="te-share-row">
				<input type="text" readonly value="<?php echo esc_attr( $share ); ?>" onclick="this.select()" id="te-share-url">
				<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('te-share-url').value);this.textContent='Copied';"><?php esc_html_e( 'Copy', 'truman' ); ?></button>
				<a class="button" href="<?php echo esc_url( $share ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open', 'truman' ); ?></a>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Make a new link? The current link will stop working for everyone who has it.', 'truman' ) ); ?>');">
						<input type="hidden" name="action" value="truman_guides_renew">
						<?php wp_nonce_field( 'truman_guides_renew' ); ?>
						<button class="button-link te-renew"><?php esc_html_e( 'Make a new link', 'truman' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>
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
		.te-share { background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2f6f63; padding: 12px 16px; margin: 10px 0 18px; max-width: 828px; }
		.te-share-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 8px; }
		.te-share-row input { flex: 1 1 320px; font-family: monospace; font-size: 12px; }
		.te-share-row form { margin: 0 0 0 8px; }
		.te-renew { color: #b32d2e !important; }
		<?php echo truman_guides_css(); // phpcs:ignore ?>
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
