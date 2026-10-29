<?php
/** Tiny synthetic gettext fixtures for a reproducible PL -> EN regression test, not language packs. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
    throw new RuntimeException( 'Synthetic translations may be installed only on disposable localhost WordPress.' );
}
require_once ABSPATH . WPINC . '/pomo/mo.php';
$targets = array(
    WP_LANG_DIR . '/pl_PL.mo' => array( 'November' => 'listopad', 'October' => 'październik' ),
    WP_LANG_DIR . '/plugins/woocommerce-pl_PL.mo' => array(
        '%s is a required field.' => '%s jest wymaganym polem.',
        'First name' => 'Imię', 'Last name' => 'Nazwisko', 'Subtotal' => 'Kwota', 'Total' => 'Łącznie',
    ),
);
$created = 0;
foreach ( $targets as $path => $messages ) {
    // A genuine language pack, including a partially installed pair, always wins.
    if ( file_exists( $path ) ) { continue; }
    if ( ! wp_mkdir_p( dirname( $path ) ) ) { throw new RuntimeException( 'Cannot prepare the local translation fixture directory.' ); }
    $catalog = new MO();
    $catalog->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
    $catalog->set_header( 'Language', 'pl_PL' );
    $catalog->set_header( 'Project-Id-Version', 'TimonConfigs synthetic locale test fixture' );
    $catalog->set_header( 'Plural-Forms', 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);' );
    foreach ( $messages as $source => $translated ) {
        $catalog->add_entry( new Translation_Entry( array( 'singular' => $source, 'translations' => array( $translated ) ) ) );
    }
    if ( ! $catalog->export_to_file( $path ) ) { throw new RuntimeException( 'Cannot write the local translation fixture.' ); }
    ++$created;
}
WP_CLI::success( "Created $created missing synthetic translation catalogs for local regression testing; existing catalogs were preserved. These are not complete official translations." );
