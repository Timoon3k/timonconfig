<?php
/** Site header. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="tc-skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'timonconfigs' ); ?></a>
<header class="tc-header" data-header>
	<div class="tc-shell tc-header__inner">
		<a class="tc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr__( 'TimonConfigs home', 'timonconfigs' ); ?>">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="tc-brand__mark">T</span><span>TIMON<span>CONFIGS</span></span>
			<?php endif; ?>
		</a>
		<button class="tc-menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu" data-menu-toggle>
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle navigation', 'timonconfigs' ); ?></span>
			<?php echo timonconfigs_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
		<nav class="tc-nav" aria-label="<?php echo esc_attr__( 'Primary navigation', 'timonconfigs' ); ?>" data-menu>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_id'        => 'primary-menu',
					'menu_class'     => 'tc-nav__list',
					'fallback_cb'    => false,
				)
			);
			?>
			<a class="tc-button tc-button--small" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>"><?php esc_html_e( 'Get VIP', 'timonconfigs' ); ?></a>
		</nav>
	</div>
</header>

