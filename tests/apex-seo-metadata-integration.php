<?php
/** PDF follow-up: upgrade previously managed Rank Math fields; reversible localhost fixtures only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Rank_Math_Pages' ) ) { throw new RuntimeException( 'Load the native page SEO module first.' ); }

$checks = 0; $posts = array(); $terms = array(); $http_calls = 0;
$old_user = get_current_user_id(); $old_get = $_GET; $old_post = $_POST; $old_request = $_REQUEST; $old_method = $_SERVER['REQUEST_METHOD'] ?? null;
$old_query = $GLOBALS['wp_query']; $old_main = $GLOBALS['wp_the_query'];
$options = array();
foreach ( array( 'rank-math-options-titles', 'tc_rank_math_archive_backup', 'tc_rank_math_archive_managed', 'timonconfigs_page_seo_state', 'timonconfigs_page_seo_queue', '_tc_page_seo_lock' ) as $key ) { $options[ $key ] = array( 'exists' => false !== get_option( $key, false ), 'value' => get_option( $key, false ) ); }
$old_profiles = json_decode( <<<'JSON'
{
  "pages": {
    "home": {
      "title": "TimonConfigs | XIM Matrix & Cronus Zen Game Configs",
      "focus_keyword": "TimonConfigs",
      "description": "Explore TimonConfigs game configs for XIM Matrix and Cronus Zen. Compare releases, read setup details and choose Free or VIP access for your device."
    },
    "xim-matrix-apex-legends": {
      "title": "XIM Matrix Apex Legends Configs | TimonConfigs",
      "focus_keyword": "XIM Matrix Apex Legends configs",
      "description": "Explore the XIM Matrix Apex Legends configs collection. Check release status, supported input, platform, release version and setup guide before choosing your setup."
    },
    "cronus-zen-apex-legends": {
      "title": "Cronus Zen Apex Legends Configs | TimonConfigs",
      "focus_keyword": "Cronus Zen Apex Legends configs",
      "description": "Explore the Cronus Zen Apex Legends configs collection. Check release status, supported input, platform, release version and setup guide before choosing your setup."
    },
    "vip-monthly": {
      "title": "TimonConfigs VIP Monthly | Monthly Config Access",
      "focus_keyword": "TimonConfigs VIP Monthly",
      "description": "Explore TimonConfigs VIP Monthly configuration access. Review included releases, covered updates, Discord access, recurring billing and cancellation terms."
    }
  },
  "games": {
    "apex-legends": {
      "title": "Apex Legends Configs | XIM Matrix & Cronus Zen",
      "focus_keyword": "Apex Legends configs",
      "description": "Explore Apex Legends configs from TimonConfigs. Compare XIM Matrix and Cronus Zen releases, input methods, supported platforms and setup information."
    }
  }
}
JSON
, true, 512, JSON_THROW_ON_ERROR );
$fields = array( 'title', 'description', 'focus_keyword' );
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Apex SEO upgrade: ' . $message ); } ++$checks; };
$create = static function ( string $title, string $collection = '' ) use ( &$posts ): int {
    $id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'tc-apex-seo-' . wp_generate_uuid4(), 'post_content' => '<p>LOCAL QA authored public page content remains unchanged.</p>', 'meta_input' => $collection ? array( '_tc_collection' => $collection ) : array() ) ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    $posts[] = (int) $id; return (int) $id;
};
$get = static fn( string $type, int $id, string $key ): mixed => 'post' === $type ? get_post_meta( $id, $key, true ) : get_term_meta( $id, $key, true );
$put = static function ( string $type, int $id, string $key, mixed $value ): void { if ( 'post' === $type ) { update_post_meta( $id, $key, wp_slash( $value ) ); } else { update_term_meta( $id, $key, wp_slash( $value ) ); } };
$native = static function ( string $type, int $id ) use ( $get, $fields ): array { $values = array(); foreach ( $fields as $field ) { $values[ 'rank_math_' . $field ] = $get( $type, $id, 'rank_math_' . $field ); } return $values; };
$seed_v1 = static function ( string $type, int $id, array $profile ) use ( $put, $fields ): array {
    $values = array(); $backup = array();
    foreach ( $fields as $field ) {
        $key = 'rank_math_' . $field; $values[ $key ] = $profile[ $field ]; $backup[ $key ] = array( 'exists' => true, 'value' => 'LOCAL QA original pre-v1 ' . $field );
        $put( $type, $id, $key, $profile[ $field ] );
    }
    $put( $type, $id, '_tc_rank_math_page_backup', $backup );
    $put( $type, $id, '_tc_rank_math_page_managed', array( 'version' => '2026-11-06-v1', 'fields' => $values ) );
    $put( $type, $id, 'rank_math_robots', array( 'noindex', 'nofollow' ) );
    $put( $type, $id, 'rank_math_canonical_url', 'https://example.invalid/local-qa-canonical/' );
    $put( $type, $id, 'rank_math_schema_qa', array( '@type' => 'WebPage', 'name' => 'LOCAL QA original schema' ) );
    $put( $type, $id, 'tc_noindex', true );
    $put( $type, $id, '_tc_values_content', 'PRIVATE_VALUES_MUST_NOT_ENTER_METADATA' );
    $put( $type, $id, '_tc_private_files', 'PRIVATE_FILE_PATH_MUST_NOT_ENTER_METADATA' );
    return $backup;
};
$front_id = 0; $front_mode = static fn(): string => 'page'; $front_page = static function () use ( &$front_id ): int { return $front_id; };
$alias_id = 0;
$alias_term = static function ( mixed $term ) use ( &$alias_id ): mixed { if ( $term instanceof WP_Term && $term->term_id === $alias_id ) { $term = clone $term; $term->slug = 'apex-legends'; $term->name = 'Apex Legends'; } return $term; };
$no_http = static function () use ( &$http_calls ): WP_Error { ++$http_calls; return new WP_Error( 'tc_apex_seo_offline', 'No external requests in metadata upgrade tests.' ); };
add_filter( 'pre_http_request', $no_http, 1 ); add_filter( 'pre_option_show_on_front', $front_mode ); add_filter( 'pre_option_page_on_front', $front_page ); add_filter( 'get_term', $alias_term );
try {
    $version = ( new ReflectionClass( TC_Rank_Math_Pages::class ) )->getConstant( 'VERSION' );
    $assert( '2026-11-10-v2' === $version, 'The new release advances the marker so previously applied native fields can be reviewed again.' );
    $data = TC_Rank_Math_Pages::catalogue();
    $targets = array( 'home', 'xim-matrix-apex-legends', 'cronus-zen-apex-legends' );
    $reviewed = array_merge( array_map( static fn( string $key ): array => $data['pages'][ $key ], $targets ), array( $data['games']['apex-legends'] ) );
    foreach ( $reviewed as $profile ) {
        $assert( str_contains( strtolower( $profile['title'] ), strtolower( $profile['focus_keyword'] ) ) && str_contains( strtolower( $profile['description'] ), strtolower( $profile['focus_keyword'] ) ), 'Each revised metadata profile retains its natural primary phrase.' );
        $assert( strlen( $profile['title'] ) <= 65 && strlen( $profile['description'] ) <= 165, 'Revised metadata remains concise enough for a search snippet.' );
        $assert( ! preg_match( '/\b(best|most popular|current season|season\s*\d+|all scripts|regular updates|predator|lethalpanda|s30|s31|2026|instant ranking)\b/i', $profile['title'] . ' ' . $profile['description'] ), 'The PDF update does not invent seasonal, release, popularity or ranking claims.' );
    }
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    $assert( ! empty( $admins ), 'A local administrator is available.' ); wp_set_current_user( (int) $admins[0] );
    foreach ( $targets as $key ) {
        $id = $create( 'LOCAL QA v1 ' . $key, 'home' === $key ? '' : $key ); $front_id = 'home' === $key ? $id : 0;
        $backup = $seed_v1( 'post', $id, $old_profiles['pages'][ $key ] ); $unchanged = get_post( $id )->to_array();
        $result = TC_Rank_Math_Pages::apply_post( $id );
        $expected = array(); foreach ( $fields as $field ) { $expected[ 'rank_math_' . $field ] = $data['pages'][ $key ][ $field ]; }
        $assert( ! is_wp_error( $result ) && $result['changed'] >= 2 && $native( 'post', $id ) === $expected, 'Existing v1 native metadata updates to the revised ' . $key . ' profile.' );
        $assert( $backup === get_post_meta( $id, '_tc_rank_math_page_backup', true ), 'The earliest pre-v1 native backup survives the ' . $key . ' upgrade.' );
        $assert( $unchanged === get_post( $id )->to_array(), 'Metadata does not rewrite the H1 title, body, slug, publication status or dates of ' . $key . '.' );
        $assert( array( 'noindex', 'nofollow' ) === get_post_meta( $id, 'rank_math_robots', true ) && true === (bool) get_post_meta( $id, 'tc_noindex', true ) && 'https://example.invalid/local-qa-canonical/' === get_post_meta( $id, 'rank_math_canonical_url', true ) && array( '@type' => 'WebPage', 'name' => 'LOCAL QA original schema' ) === get_post_meta( $id, 'rank_math_schema_qa', true ), 'The ' . $key . ' update preserves robots, canonical and authored schema.' );
        $assert( ! str_contains( wp_json_encode( $native( 'post', $id ) ), 'PRIVATE_' ) && 'PRIVATE_VALUES_MUST_NOT_ENTER_METADATA' === get_post_meta( $id, '_tc_values_content', true ) && 'PRIVATE_FILE_PATH_MUST_NOT_ENTER_METADATA' === get_post_meta( $id, '_tc_private_files', true ), 'Private values and files remain untouched and absent from ' . $key . ' metadata.' );
        $managed = get_post_meta( $id, '_tc_rank_math_page_managed', true ); $again = TC_Rank_Math_Pages::apply_post( $id );
        $assert( ! is_wp_error( $again ) && 0 === $again['changed'] && $managed === get_post_meta( $id, '_tc_rank_math_page_managed', true ) && $backup === get_post_meta( $id, '_tc_rank_math_page_backup', true ), 'Repeated ' . $key . ' application is idempotent.' );
    }
    $front_id = 0;
    $term = wp_insert_term( 'LOCAL QA Apex metadata upgrade', 'tc_game', array( 'slug' => 'tc-apex-seo-' . wp_generate_uuid4(), 'description' => 'LOCAL QA authored term body remains unchanged.' ) );
    if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
    $alias_id = (int) $term['term_id']; $terms[] = array( $alias_id, 'tc_game' );
    $backup = $seed_v1( 'term', $alias_id, $old_profiles['games']['apex-legends'] ); $before_term = get_term( $alias_id )->to_array();
    $result = TC_Rank_Math_Pages::apply_term( $alias_id ); $expected = array(); foreach ( $fields as $field ) { $expected[ 'rank_math_' . $field ] = $data['games']['apex-legends'][ $field ]; }
    $assert( ! is_wp_error( $result ) && $result['changed'] >= 2 && $expected === $native( 'term', $alias_id ), 'The existing Apex game collection receives revised native title and description.' );
    $assert( $before_term === get_term( $alias_id )->to_array() && $backup === get_term_meta( $alias_id, '_tc_rank_math_page_backup', true ), 'The game collection H1/description and earliest backup remain intact.' );
    $assert( array( 'noindex', 'nofollow' ) === get_term_meta( $alias_id, 'rank_math_robots', true ) && 'https://example.invalid/local-qa-canonical/' === get_term_meta( $alias_id, 'rank_math_canonical_url', true ) && get_term_meta( $alias_id, 'tc_noindex', true ), 'Apex game metadata preserves indexing and canonical directives.' );
    $again = TC_Rank_Math_Pages::apply_term( $alias_id ); $assert( ! is_wp_error( $again ) && 0 === $again['changed'], 'The game collection update is idempotent.' );
    $assert( ! str_contains( wp_json_encode( $native( 'term', $alias_id ) ), 'PRIVATE_' ), 'The term metadata contains no private fixture data.' );

    $manual = $create( 'LOCAL QA edited v1 profile', 'cronus-zen-apex-legends' ); $backup = $seed_v1( 'post', $manual, $old_profiles['pages']['cronus-zen-apex-legends'] );
    update_post_meta( $manual, 'rank_math_title', 'LOCAL QA later manual title stays intact' );
    $result = TC_Rank_Math_Pages::apply_post( $manual );
    $assert( ! is_wp_error( $result ) && 'LOCAL QA later manual title stays intact' === get_post_meta( $manual, 'rank_math_title', true ) && $data['pages']['cronus-zen-apex-legends']['description'] === get_post_meta( $manual, 'rank_math_description', true ), 'An editor correction made after v1 is retained while the unchanged managed description advances.' );
    $assert( $backup === get_post_meta( $manual, '_tc_rank_math_page_backup', true ) && ! TC_Rank_Math_Pages::manages_title( get_post( $manual ) ), 'Later manual titles are not claimed as module-owned and their original backup is kept.' );
    $result = TC_Rank_Math_Pages::restore( 'post', $manual );
    $assert( ! is_wp_error( $result ) && 'LOCAL QA later manual title stays intact' === get_post_meta( $manual, 'rank_math_title', true ) && 'LOCAL QA original pre-v1 description' === get_post_meta( $manual, 'rank_math_description', true ), 'Restore still reaches the pre-v1 source values without overwriting a newer manual title.' );

    $restored = $create( 'LOCAL QA restored v1 page', 'xim-matrix-apex-legends' ); $seed_v1( 'post', $restored, $old_profiles['pages']['xim-matrix-apex-legends'] );
    update_post_meta( $restored, 'rank_math_title', 'LOCAL QA restored original title' ); update_post_meta( $restored, 'rank_math_description', 'LOCAL QA restored original description' ); update_post_meta( $restored, 'rank_math_focus_keyword', 'LOCAL QA restored original phrase' );
    $restored_values = $native( 'post', $restored ); $restored_marker = array( 'version' => '2026-11-06-v1', 'fields' => array(), 'restored' => true ); update_post_meta( $restored, '_tc_rank_math_page_managed', $restored_marker );
    $result = TC_Rank_Math_Pages::apply_post( $restored );
    $assert( ! is_wp_error( $result ) && 0 === $result['changed'] && $restored_values === $native( 'post', $restored ) && $restored_marker === get_post_meta( $restored, '_tc_rank_math_page_managed', true ), 'A v1 restoration remains explicitly restored and is never re-enrolled by the v2 rollout.' );

    $term_restored_values = $native( 'term', $alias_id ); update_term_meta( $alias_id, '_tc_rank_math_page_managed', $restored_marker );
    $result = TC_Rank_Math_Pages::apply_term( $alias_id );
    $assert( ! is_wp_error( $result ) && 0 === $result['changed'] && $term_restored_values === $native( 'term', $alias_id ) && $restored_marker === get_term_meta( $alias_id, '_tc_rank_math_page_managed', true ), 'An explicitly restored term also remains excluded from future automatic profile updates.' );
    $archive_before = get_option( 'rank-math-options-titles', false ); $archive_backup = get_option( 'tc_rank_math_archive_backup', false );
    update_option( 'tc_rank_math_archive_managed', $restored_marker, false ); $result = TC_Rank_Math_Pages::apply_archive();
    $assert( ! is_wp_error( $result ) && 0 === $result['changed'] && $archive_before === get_option( 'rank-math-options-titles', false ) && $archive_backup === get_option( 'tc_rank_math_archive_backup', false ) && $restored_marker === get_option( 'tc_rank_math_archive_managed' ), 'A restored v1 archive marker preserves all native archive settings and its restoration record.' );

    $unrelated = $create( 'LOCAL QA unrelated VIP page', 'vip-monthly' ); $backup = $seed_v1( 'post', $unrelated, $old_profiles['pages']['vip-monthly'] ); $before_native = $native( 'post', $unrelated );
    $result = TC_Rank_Math_Pages::apply_post( $unrelated );
    $assert( ! is_wp_error( $result ) && 0 === $result['changed'] && $before_native === $native( 'post', $unrelated ) && $backup === get_post_meta( $unrelated, '_tc_rank_math_page_backup', true ), 'Unrelated v1 curated copy stays byte-identical during the version review.' );
    $assert( 0 === $http_calls, 'Native metadata migration performs no external requests.' );
    WP_CLI::success( "Apex SEO metadata upgrade: $checks assertions passed; v1-to-v2 native fields and backups verified with reversible localhost fixtures." );
} finally {
    remove_filter( 'pre_http_request', $no_http, 1 ); remove_filter( 'pre_option_show_on_front', $front_mode ); remove_filter( 'pre_option_page_on_front', $front_page ); remove_filter( 'get_term', $alias_term );
    wp_set_current_user( 0 );
    foreach ( array_reverse( $posts ) as $id ) { wp_delete_post( $id, true ); }
    foreach ( array_reverse( $terms ) as $entry ) { wp_delete_term( $entry[0], $entry[1] ); }
    foreach ( $options as $key => $backup ) { $backup['exists'] ? update_option( $key, $backup['value'], false ) : delete_option( $key ); }
    wp_set_current_user( $old_user ); $_GET = $old_get; $_POST = $old_post; $_REQUEST = $old_request; $GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main;
    if ( null === $old_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $old_method; }
}
