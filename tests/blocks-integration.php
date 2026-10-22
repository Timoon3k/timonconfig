<?php
/** Native block content round trip and safe rendering. Run inside local WordPress. */
defined( 'ABSPATH' ) || exit;
$checks = 0;
$fixture = 0;
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
};
try {
	$type = WP_Block_Type_Registry::get_instance()->get_registered( 'timonconfigs/section' );
	$assert( $type instanceof WP_Block_Type && is_callable( $type->render_callback ), 'The native section block has a server renderer.' );
	$sections = array_filter( parse_blocks( timonconfigs_default_home_content() ), static fn( array $block ): bool => 'timonconfigs/section' === $block['blockName'] );
	$assert( 10 === count( $sections ), 'The default Home contains ten reorderable native sections, ignoring whitespace blocks.' );
	$fixture = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Block integration fixture', 'post_content' => '<!-- wp:timonconfigs/section {"section":"hero","title":"Editor round-trip heading","description":"An authored description."} /-->' ), true );
	if ( is_wp_error( $fixture ) ) { throw new RuntimeException( $fixture->get_error_message() ); }
	$content = (string) get_post_field( 'post_content', $fixture );
	$assert( has_block( 'timonconfigs/section', $content ), 'Block syntax survives a WordPress content save.' );
	$rendered = do_blocks( $content );
	$assert( str_contains( $rendered, 'Editor round-trip heading' ), 'The saved heading controls the actual frontend renderer.' );
	$assert( str_contains( $rendered, 'An authored description.' ), 'The saved description controls the renderer.' );
	$assert( 1 === substr_count( $rendered, '<h1 ' ), 'A Hero produces one primary heading.' );
	$invalid = timonconfigs_render_section( array( 'section' => '../../functions' ) );
	$assert( '' === $invalid, 'Unknown template parts cannot be included through block attributes.' );
	$escaped = timonconfigs_render_section( array( 'section' => 'hero', 'title' => '<script>alert(1)</script>Safe heading', 'image' => 'javascript:alert(1)' ) );
	$assert( ! str_contains( $escaped, '<script>' ) && ! str_contains( $escaped, 'javascript:' ), 'Text and image attributes are sanitized at render time.' );
	$benefits = timonconfigs_render_section( array( 'section' => 'benefits', 'items' => array( array( 'shield', 'Edited benefit', 'Edited supporting text.' ) ) ) );
	$assert( str_contains( $benefits, 'Edited benefit' ) && str_contains( $benefits, 'Edited supporting text.' ), 'Benefit cards have editable content.' );
	$home_override = static fn() => $fixture;
	add_filter( 'pre_option_page_on_front', $home_override );
	try {
		$assert( timonconfigs_home_has_section( 'hero' ), 'Schema sees a present Hero block.' );
		$assert( ! timonconfigs_home_has_section( 'faq' ), 'A removed FAQ cannot produce invisible schema content.' );
		$assert( ! timonconfigs_home_has_section( 'videos' ), 'A removed video section is absent from schema visibility.' );
	} finally { remove_filter( 'pre_option_page_on_front', $home_override ); }
	WP_CLI::success( sprintf( 'Gutenberg block integration: %d assertions passed.', $checks ) );
} finally {
	if ( $fixture && ! is_wp_error( $fixture ) ) { wp_delete_post( $fixture, true ); }
}
