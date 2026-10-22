<?php
/** Run only against disposable local/staging WordPress: wp eval-file tests/editorial-integration.php. */
defined( 'ABSPATH' ) || exit;

$tc_editorial_checks = 0;
$tc_editorial_id = 0;
$tc_editorial_original_user = get_current_user_id();
$tc_editorial_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$tc_editorial_expect = static function ( bool $condition, string $message ) use ( &$tc_editorial_checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$tc_editorial_checks;
};
$tc_editorial_request = static function ( string $method, string $route, array $params = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) { $request->set_param( $key, $value ); }
	return rest_do_request( $request );
};

try {
	$tc_editorial_expect( ! empty( $tc_editorial_admins ), 'An administrator is required in this disposable WordPress.' );
	wp_set_current_user( (int) $tc_editorial_admins[0] );
	$response = $tc_editorial_request( 'OPTIONS', '/wp/v2/tc_review' );
	$schema = $response->get_data()['schema']['properties']['meta']['properties'] ?? array();
	$tc_editorial_expect( 200 === $response->get_status() && isset( $schema['tc_review_rating'], $schema['tc_review_config'], $schema['tc_review_demo'] ), 'Review metadata is available in the real editor REST schema.' );

	$response = $tc_editorial_request( 'POST', '/wp/v2/tc_review', array(
		'title' => 'LOCAL EDITORIAL INTEGRATION FIXTURE',
		'content' => '<!-- wp:paragraph --><p>Disposable editor test, not customer feedback.</p><!-- /wp:paragraph -->',
		'status' => 'draft',
		'meta' => array( 'tc_review_rating' => 4, 'tc_review_config' => 'Apex Legends · XIM Matrix', 'tc_review_demo' => true ),
	) );
	$data = $response->get_data();
	$tc_editorial_id = (int) ( $data['id'] ?? 0 );
	$tc_editorial_expect( 201 === $response->get_status() && $tc_editorial_id > 0, 'An authenticated editor can create a review draft with metadata.' );
	$tc_editorial_expect( 4 === $data['meta']['tc_review_rating'] && true === $data['meta']['tc_review_demo'] && 'Apex Legends · XIM Matrix' === $data['meta']['tc_review_config'], 'Create persists rating, config and demo flag through REST.' );
	$tc_editorial_expect( 'draft' === get_post_status( $tc_editorial_id ), 'The fixture never becomes public customer feedback.' );

	$route = '/wp/v2/tc_review/' . $tc_editorial_id;
	$response = $tc_editorial_request( 'POST', $route, array( 'meta' => array( 'tc_review_rating' => 9, 'tc_review_config' => '<b>Updated</b> · Cronus Zen', 'tc_review_demo' => false ) ) );
	$data = $response->get_data();
	$tc_editorial_expect( 200 === $response->get_status(), 'An authenticated metadata update succeeds.' );
	$tc_editorial_expect( 5 === $data['meta']['tc_review_rating'] && 'Updated · Cronus Zen' === $data['meta']['tc_review_config'] && false === $data['meta']['tc_review_demo'], 'REST applies the existing capped-rating, plain-text and boolean sanitizers.' );
	$response = $tc_editorial_request( 'GET', $route, array( 'context' => 'edit' ) );
	$data = $response->get_data();
	$tc_editorial_expect( 200 === $response->get_status() && 5 === $data['meta']['tc_review_rating'] && false === $data['meta']['tc_review_demo'], 'An editor reads the persisted metadata back from REST.' );

	foreach ( array( array( 'tc_review_rating' => 'not-a-rating' ), array( 'tc_review_rating' => array( 4 ) ), array( 'tc_review_config' => array( 'unexpected' ) ), array( 'tc_review_demo' => array( true ) ) ) as $invalid_meta ) {
		$response = $tc_editorial_request( 'POST', $route, array( 'meta' => $invalid_meta ) );
		$tc_editorial_expect( 400 === $response->get_status(), 'Malformed metadata types are rejected before storage.' );
	}
	$tc_editorial_expect( '5' === get_post_meta( $tc_editorial_id, 'tc_review_rating', true ) && 'Updated · Cronus Zen' === get_post_meta( $tc_editorial_id, 'tc_review_config', true ) && ! get_post_meta( $tc_editorial_id, 'tc_review_demo', true ), 'Rejected requests leave existing review metadata intact.' );

	wp_set_current_user( 0 );
	$response = $tc_editorial_request( 'POST', $route, array( 'meta' => array( 'tc_review_rating' => 1, 'tc_review_demo' => true ) ) );
	$tc_editorial_expect( in_array( $response->get_status(), array( 401, 403 ), true ), 'Anonymous users cannot change a review or its demo status.' );
	$response = $tc_editorial_request( 'POST', '/wp/v2/tc_review', array( 'title' => 'Unauthorized fixture', 'status' => 'draft' ) );
	$tc_editorial_expect( in_array( $response->get_status(), array( 401, 403 ), true ), 'Anonymous users cannot create review entries.' );
	$tc_editorial_expect( '5' === get_post_meta( $tc_editorial_id, 'tc_review_rating', true ) && ! get_post_meta( $tc_editorial_id, 'tc_review_demo', true ), 'Denied anonymous writes leave persisted values unchanged.' );

	wp_set_current_user( (int) $tc_editorial_admins[0] );
	$response = $tc_editorial_request( 'DELETE', $route, array( 'force' => true ) );
	$tc_editorial_expect( 200 === $response->get_status() && null === get_post( $tc_editorial_id ), 'An authorized REST deletion removes the disposable fixture.' );
	WP_CLI::success( sprintf( 'Editorial integration: %d assertions passed.', $tc_editorial_checks ) );
} finally {
	if ( $tc_editorial_id && get_post( $tc_editorial_id ) ) { wp_delete_post( $tc_editorial_id, true ); }
	wp_set_current_user( $tc_editorial_original_user );
}
