<?php
/**
 * REST route tests.
 *
 * @package wp-feature-future-revisions
 */

class WFR_Test_REST_API extends WP_UnitTestCase {

	public function test_create_fork_and_conflict() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'body',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp-future-revisions/v1/forks' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertGreaterThan( 0, $data['fork_id'] );

		$conflict = rest_get_server()->dispatch( $request );
		$this->assertSame( 409, $conflict->get_status() );
	}

	public function test_set_public_revision_flag() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'one',
			)
		);
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'two',
			)
		);
		$revisions   = wp_get_post_revisions( $post_id );
		$revision    = end( $revisions );
		$revision_id = (int) $revision->ID;

		$request = new WP_REST_Request(
			'POST',
			'/wp-future-revisions/v1/public-revisions/' . $post_id . '/' . $revision_id
		);
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'revision_id', $revision_id );
		$request->set_param( 'public', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['public'] );
		$this->assertStringContainsString( '/revision/' . $revision_id, $data['url'] );
	}
}
