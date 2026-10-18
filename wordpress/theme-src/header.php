<?php
/**
 * Site header: logo + main menu (Appearance → Menus → "Main menu" location).
 *
 * @package Truman_Enterprise
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<nav>
	<div class="nav-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="nav-logo">
			<svg class="nav-logo-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<rect x="4" y="28" width="40" height="10" rx="2" fill="#1a6e62"/>
				<rect x="8" y="22" width="28" height="8" rx="1" fill="#b8852a"/>
				<rect x="16" y="14" width="16" height="10" rx="1" fill="#b8852a"/>
				<rect x="10" y="24" width="4" height="4" rx="1" fill="rgba(255,255,255,0.7)"/>
				<rect x="18" y="24" width="4" height="4" rx="1" fill="rgba(255,255,255,0.7)"/>
				<rect x="26" y="24" width="4" height="4" rx="1" fill="rgba(255,255,255,0.7)"/>
				<circle cx="12" cy="38" r="4" fill="#0d2137"/>
				<circle cx="12" cy="38" r="2" fill="#4a6080"/>
				<circle cx="36" cy="38" r="4" fill="#0d2137"/>
				<circle cx="36" cy="38" r="2" fill="#4a6080"/>
				<line x1="4" y1="28" x2="44" y2="28" stroke="#1a6e62" stroke-width="1.5"/>
			</svg>
			<div class="nav-logo-text">
				<span>Truman Enterprise</span>
				<span>Narrowboat Trust</span>
			</div>
		</a>
		<button class="nav-hamburger" aria-label="<?php esc_attr_e( 'Menu', 'truman' ); ?>">
			<span></span><span></span><span></span>
		</button>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'nav-links',
				'depth'          => 1,
				'fallback_cb'    => 'truman_primary_menu_fallback',
			)
		);
		?>
	</div>
</nav>
