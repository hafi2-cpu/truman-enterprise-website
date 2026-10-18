<?php
/**
 * Site footer.
 *
 * @package Truman_Enterprise
 */
?>
<footer class="site-footer">
	<div class="footer-inner">
		<div class="footer-grid">
			<div class="footer-brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="footer-logo-link">
					<img class="footer-logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/truman-logo-large.png' ); ?>" width="216" height="100" alt="<?php esc_attr_e( 'Truman Enterprise Narrowboat Trust', 'truman' ); ?>" loading="lazy">
				</a>
				<p>A volunteer-run charity providing accessible canal boat day trips for elderly, disabled and community groups across the West Midlands since 1968.</p>
				<div class="footer-badges">
					<span class="footer-badge">Registered Charity No. 1037914</span>
					<span class="footer-badge">🚢 NCBA Member</span>
				</div>
			</div>

			<div class="footer-col">
				<h4>Explore</h4>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'footer-menu',
						'depth'          => 1,
						'fallback_cb'    => 'truman_footer_menu_fallback',
					)
				);
				?>
			</div>

			<div class="footer-col">
				<h4>Contact Us</h4>
				<ul class="footer-contact">
					<li><span class="footer-icon" aria-hidden="true">📞</span><a href="tel:07895053090">07895 053090</a></li>
					<li><span class="footer-icon" aria-hidden="true">✉️</span><a href="mailto:enterprise@truman-enterprise.org.uk">enterprise@truman-enterprise.org.uk</a></li>
					<li><span class="footer-icon" aria-hidden="true">📍</span><span>Hatherton Marina<br>Queens Road, Calf Heath<br>Wolverhampton WV10 7DT</span></li>
				</ul>
			</div>

			<div class="footer-col">
				<h4>Trip Season</h4>
				<p>Trips run <strong>April to October</strong>, 7 days a week, from Hatherton Marina.</p>
				<a class="btn btn-primary btn-sm footer-book" href="<?php echo esc_url( truman_page_url( 'book-a-trip' ) ); ?>"><?php esc_html_e( 'Book a Trip', 'truman' ); ?></a>
			</div>
		</div>
	</div>

	<div class="footer-bottom-bar">
		<div class="footer-inner footer-bottom">
			<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Truman Enterprise Narrowboat Trust · Registered Charity No. 1037914</p>
			<p>Member of the National Community Boats Association</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
