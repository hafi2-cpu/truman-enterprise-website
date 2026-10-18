<?php
/**
 * Fallback template (blog posts, archives, search).
 *
 * @package Truman_Enterprise
 */

get_header();
?>
<main id="content" class="te-main">
	<section>
		<div class="section-inner te-prose">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class(); ?>>
						<?php if ( is_singular() ) : ?>
							<h1 class="section-title"><?php the_title(); ?></h1>
							<div class="title-line"></div>
							<?php the_content(); ?>
						<?php else : ?>
							<h2 class="section-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<?php the_excerpt(); ?>
						<?php endif; ?>
					</article>
				<?php endwhile; ?>
				<?php the_posts_pagination(); ?>
			<?php else : ?>
				<h1 class="section-title"><?php esc_html_e( 'Page not found', 'truman' ); ?></h1>
				<div class="title-line"></div>
				<p><?php esc_html_e( 'Sorry, we couldn’t find that page.', 'truman' ); ?></p>
				<p style="margin-top:24px;"><a class="btn btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the homepage', 'truman' ); ?></a></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
