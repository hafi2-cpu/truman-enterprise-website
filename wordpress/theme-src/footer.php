<?php
/**
 * Site footer.
 *
 * @package Truman_Enterprise
 */
?>
<footer>
	<div class="footer-inner">
		<div class="footer-cta-strip">
			<div class="footer-cta-strip-text">
				<h3>Truman Enterprise Narrowboat Trust</h3>
				<p>Making waterways accessible to everyone since 1968 — Registered Charity No. 1037914</p>
			</div>
		</div>
		<div class="footer-grid">
			<div class="footer-brand">
				<img class="footer-logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/truman-logo-large.png' ); ?>" width="216" height="100" alt="<?php esc_attr_e( 'Truman Enterprise Narrowboat Trust', 'truman' ); ?>" loading="lazy">
				<p>A volunteer-run charity providing accessible canal boat day trips for elderly, disabled and community groups across the West Midlands since 1968.</p>
				<span class="footer-charity-no">Registered Charity No. 1037914</span>
			</div>
			<div class="footer-col">
				<h4>Navigate</h4>
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
				<a href="tel:07895053090">07895 053090</a>
				<a href="mailto:enterprise@truman-enterprise.org.uk">enterprise@truman-enterprise.org.uk</a>
				<p class="footer-address" style="margin-top:14px;">Hatherton Marina<br>Queens Road, Calf Heath<br>Wolverhampton WV10 7DT</p>
			</div>
			<div class="footer-col">
				<h4>Season</h4>
				<p>Trips run <strong style="color:rgba(255,255,255,0.7);">April to October</strong>, 7 days a week, from Hatherton Marina.</p>
				<p style="margin-top:16px;"><span class="ncba-badge">🚢 NCBA Member</span></p>
			</div>
		</div>
		<div class="footer-bottom">
			<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Truman Enterprise Narrowboat Trust. Registered Charity No. 1037914.</p>
			<p>Member of the National Community Boats Association</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
