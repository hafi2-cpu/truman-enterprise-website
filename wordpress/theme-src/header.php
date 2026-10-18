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
			<img class="nav-logo-img" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/truman-logo.png' ); ?>" width="173" height="80" alt="<?php esc_attr_e( 'Truman Enterprise Narrowboat Trust', 'truman' ); ?>">
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
