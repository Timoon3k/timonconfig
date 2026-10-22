<?php
/** Contact page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
$discord_url = get_theme_mod( 'timonconfigs_discord_url', 'https://discord.gg/RyvzSqrF6' );
$email = sanitize_email( (string) get_option( 'admin_email' ) );
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>CONTACT</p><h1><?php the_title(); ?></h1><div class="tc-lead"><?php if ( trim( (string) get_post_field( 'post_content', get_queried_object_id() ) ) ) { echo apply_filters( 'the_content', get_post_field( 'post_content', get_queried_object_id() ) ); } else { echo '<p>For a faster answer, include the config name, device, platform, input method, and version you are using.</p>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div></section>
	<section class="tc-section"><div class="tc-shell tc-contact-grid">
		<article id="community"><span class="tc-number">01 / COMMUNITY & SUPPORT</span><h2>Discord</h2><p>Use the official TimonConfigs Discord for community help and the support channels available to your current role.</p><?php if ( $discord_url ) : ?><a class="tc-button" href="<?php echo esc_url( $discord_url ); ?>" rel="noopener noreferrer">Open Discord <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><?php else : ?><p class="tc-status-note">The official Discord invite is not available yet. Please check back soon or contact us using the support details on this page.</p><?php endif; ?></article>
		<article><span class="tc-number">02 / ACCOUNT & POLICY</span><h2>Email</h2><p>Use email for privacy requests, policy questions, or an issue that should not be posted in a community channel.</p><?php if ( $email && 'local@example.test' !== $email ) : ?><a class="tc-text-link" href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><?php else : ?><p class="tc-status-note">The production support email will appear here after configuration.</p><?php endif; ?></article>
	</div></section>
</main>
<?php get_footer(); ?>
