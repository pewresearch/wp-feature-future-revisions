<?php
/**
 * Fork and merge tests.
 *
 * @package wp-feature-future-revisions
 */

class WFR_Test_Future_Revisions extends WP_UnitTestCase {

	public function test_create_fork_and_reject_second() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Parent',
				'post_content' => 'body',
			)
		);

		$fork_id = WFR_Future_Revisions::create_fork( $post_id );
		$this->assertIsInt( $fork_id );
		$this->assertSame( $post_id, (int) get_post_meta( $fork_id, '_future_revision_of', true ) );
		$this->assertSame( $fork_id, (int) get_post_meta( $post_id, '_active_future_revision', true ) );

		$second = WFR_Future_Revisions::create_fork( $post_id );
		$this->assertWPError( $second );
		$this->assertSame( 'fork_exists', $second->get_error_code() );
	}

	public function test_merge_on_publish_and_leftover_fork_without_support() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Parent',
				'post_content' => 'original',
			)
		);
		$fork_id = WFR_Future_Revisions::create_fork( $post_id );
		wp_update_post(
			array(
				'ID'           => $fork_id,
				'post_content' => 'from leftover fork',
			)
		);

		remove_post_type_support( 'post', 'future-revisions' );

		wp_publish_post( $fork_id );

		$parent = get_post( $post_id );
		$this->assertSame( 'from leftover fork', $parent->post_content );
		$this->assertSame( 'trash', get_post_status( $fork_id ) );
		$this->assertStringEndsNotWith( '__fork', $parent->post_name );
	}

	public function test_trash_rejects_merged_status_without_deleting_fork() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'original',
			)
		);
		$fork_id = WFR_Future_Revisions::create_fork( $post_id );
		update_post_meta( $fork_id, '_future_revision_status', 'merged' );

		$result = WFR_Future_Revisions::trash_fork( $post_id );
		$this->assertWPError( $result );
		$this->assertSame( 'fork_already_merged', $result->get_error_code() );
		$this->assertSame( 'draft', get_post_status( $fork_id ) );
		$this->assertSame( 0, (int) get_post_meta( $post_id, '_active_future_revision', true ) );
	}

	public function test_cannot_fork_without_support() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		register_post_type(
			'wfr_no_future',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor', 'revisions', 'public-revisions' ),
			)
		);
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wfr_no_future',
				'post_status' => 'publish',
			)
		);
		$result = WFR_Future_Revisions::create_fork( $post_id );
		$this->assertWPError( $result );
		$this->assertSame( 'rest_post_type_not_supported', $result->get_error_code() );
	}

	public function test_merge_copies_content_and_isolates_merged_flag() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Parent',
				'post_content' => 'original',
				'post_date'    => '2020-01-01 00:00:00',
			)
		);
		$original_date = get_post( $post_id )->post_date;

		$fork_id = WFR_Future_Revisions::create_fork( $post_id );
		wp_update_post(
			array(
				'ID'           => $fork_id,
				'post_content' => 'updated from fork',
				'post_title'   => 'Fork title',
			)
		);

		$result = WFR_Future_Revisions::merge_fork( $fork_id, $post_id );
		$this->assertTrue( $result );

		$parent = get_post( $post_id );
		$this->assertSame( 'updated from fork', $parent->post_content );
		$this->assertSame( 'Fork title', $parent->post_title );
		$this->assertSame( $original_date, $parent->post_date );
		$this->assertSame( 'trash', get_post_status( $fork_id ) );
		$this->assertSame( 0, (int) get_post_meta( $post_id, '_active_future_revision', true ) );

		$latest = wfr_get_latest_revision_id( $post_id );
		$this->assertTrue( (bool) get_metadata( 'post', $latest, 'is_revision_merged', true ) );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'later save',
			)
		);
		$newer = wfr_get_latest_revision_id( $post_id );
		$this->assertNotSame( $latest, $newer );
		$this->assertFalse( (bool) get_metadata( 'post', $newer, 'is_revision_merged', true ) );
		$this->assertTrue( (bool) get_metadata( 'post', $latest, 'is_revision_merged', true ) );
	}
}
