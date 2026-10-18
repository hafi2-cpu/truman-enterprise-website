<?php
/**
 * Pages. The Truman pages are built with Elementor ("Elementor Full Width"
 * template), which uses this theme's header and footer. If Elementor is ever
 * switched off, the saved page HTML is shown here with the same styling.
 *
 * @package Truman_Enterprise
 */

get_header();
?>
<main id="content" class="te-main">
	<?php
	while ( have_posts() ) :
		the_post();
		if ( get_post_meta( get_the_ID(), '_truman_page', true ) ) {
			the_content();
		} else {
			truman_simple_page();
		}
	endwhile;
	?>
</main>
<?php
get_footer();
