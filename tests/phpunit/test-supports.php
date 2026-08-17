<?php
/**
 * Post type support tests.
 *
 * @package wp-feature-future-revisions
 */

class WFR_Test_Supports extends WP_UnitTestCase {

	public function test_post_and_page_support_both_features() {
		$this->assertTrue( post_type_supports( 'post', 'public-revisions' ) );
		$this->assertTrue( post_type_supports( 'post', 'future-revisions' ) );
		$this->assertTrue( post_type_supports( 'page', 'public-revisions' ) );
		$this->assertTrue( post_type_supports( 'page', 'future-revisions' ) );
	}

	public function test_supports_are_independent() {
		register_post_type(
			'wfr_public_only',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor', 'revisions' ),
			)
		);
		add_post_type_support( 'wfr_public_only', 'public-revisions' );
		$this->assertTrue( wfr_post_type_supports_public_revisions( 'wfr_public_only' ) );
		$this->assertFalse( wfr_post_type_supports_future_revisions( 'wfr_public_only' ) );

		register_post_type(
			'wfr_future_only',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor', 'revisions' ),
			)
		);
		add_post_type_support( 'wfr_future_only', 'future-revisions' );
		$this->assertFalse( wfr_post_type_supports_public_revisions( 'wfr_future_only' ) );
		$this->assertTrue( wfr_post_type_supports_future_revisions( 'wfr_future_only' ) );
	}
}
