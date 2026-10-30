<?php
/** Device-specific setup orientation on established game URLs, without invented firmware claims. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$tc_intent_term = get_queried_object();
if ( ! $tc_intent_term instanceof WP_Term || 'tc_game' !== $tc_intent_term->taxonomy || ! isset( TC_Catalog::game_definitions()[ $tc_intent_term->slug ] ) ) { return; }
$tc_intent_is_apex = 'apex-legends' === $tc_intent_term->slug;
$tc_intent_game = $tc_intent_term->name;
$tc_intent_collection = get_term_link( $tc_intent_term );
if ( is_wp_error( $tc_intent_collection ) ) { return; }
?>
<section class="tc-collection-intent" aria-label="<?php echo esc_attr( $tc_intent_game . ' device setup guidance' ); ?>"><div class="tc-shell">
	<div class="tc-intent-grid">
		<article class="tc-intent-card" id="xim-matrix">
			<h2>XIM Matrix <?php echo esc_html( $tc_intent_game ); ?> Configs</h2>
			<p><?php echo $tc_intent_is_apex ? 'Choose a release for your actual input: controller and mouse &amp; keyboard configurations are separate setups. Do not assume that a controller release includes MnK values.' : esc_html( 'Check the specific ' . $tc_intent_game . ' edition and mode before choosing a XIM Matrix setup. Settings for another title, mode or input are not automatically compatible.' ); ?></p>
			<ol><li>Confirm the listed platform, input and game version on the config details page.</li><li>Compare the release requirements with your installed XIM firmware and Manager version. No firmware compatibility is implied where a version is not documented.</li><li>Follow the release-specific Guide and Values, then keep a backup before changing your existing setup.</li></ol>
			<div class="tc-actions"><a class="tc-text-link" href="<?php echo esc_url( timonconfigs_collection_url( $tc_intent_term->slug, 'xim-matrix' ) ); ?>">Browse XIM Matrix releases <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-text-link" href="<?php echo esc_url( home_url( '/xim-matrix-configs/' ) ); ?>">All XIM Matrix configs</a></div>
		</article>
		<article class="tc-intent-card" id="cronus-zen">
			<h2>Cronus Zen <?php echo esc_html( $tc_intent_game ); ?> Configs</h2>
			<p><?php echo $tc_intent_is_apex ? 'Apex Legends releases can depend on the season, platform and controller setup. Use the requirements on the individual release, rather than treating every Apex script as interchangeable.' : esc_html( 'A Cronus Zen release must match the named ' . $tc_intent_game . ' edition and supported controller setup. Confirm the documented mode and platform before applying a configuration.' ); ?></p>
			<ol><li>Check the published config version, supported platform and release status.</li><li>Verify the documented Zen Studio and device firmware requirements before importing a file.</li><li>Apply the instructions for that release and retain a backup. If a requirement is missing, ask support before installing.</li></ol>
			<div class="tc-actions"><a class="tc-text-link" href="<?php echo esc_url( timonconfigs_collection_url( $tc_intent_term->slug, 'cronus-zen' ) ); ?>">Browse Cronus Zen releases <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-text-link" href="<?php echo esc_url( home_url( '/cronus-zen-configs/' ) ); ?>">All Cronus Zen configs</a></div>
		</article>
	</div>
	<p class="tc-intent-note">Only published releases establish availability. Game updates and platform rules can change compatibility; competitive results vary by setup and player input. Guides, Values and version history belong to each release. <?php if ( $tc_intent_is_apex ) : ?><a class="tc-text-link" href="<?php echo esc_url( home_url( '/video-showcase/' ) ); ?>">View first-party Apex footage</a> — recording labels must identify the setup before being used as compatibility evidence. <?php endif; ?><a class="tc-text-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Ask about a missing requirement</a>.</p>
</div></section>
