<?php
/**
 * Public revision flag tests.
 *
 * @package wp-feature-future-revisions
 */

class WFR_Test_Public_Revisions extends WP_UnitTestCase {

	public function test_flag_lives_on_revision_not_parent() {
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
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertNotEmpty( $revisions );
		$revision    = end( $revisions );
		$revision_id = (int) $revision->ID;

		$result = WFR_Public_Revisions::set_public( $revision_id, true );
		$this->assertTrue( $result );
		$this->assertTrue( WFR_Public_Revisions::is_public( $revision_id ) );
		$this->assertFalse( (bool) get_post_meta( $post_id, 'is_revision_public', true ) );
		$this->assertTrue( (bool) get_metadata( 'post', $revision_id, 'is_revision_public', true ) );
	}

	public function test_cannot_set_public_without_support() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		register_post_type(
			'wfr_no_public',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor', 'revisions', 'future-revisions' ),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'wfr_no_public',
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

		$result = WFR_Public_Revisions::set_public( $revision_id, true );
		$this->assertWPError( $result );
		$this->assertSame( 'rest_post_type_not_supported', $result->get_error_code() );
	}

	public function test_public_revision_cannot_be_deleted() {
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
		WFR_Public_Revisions::set_public( $revision_id, true );

		wp_delete_post( $revision_id, true );
		$this->assertNotNull( get_post( $revision_id ) );
	}

	public function test_public_revision_stays_protected_after_support_removed() {
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
		WFR_Public_Revisions::set_public( $revision_id, true );

		remove_post_type_support( 'post', 'public-revisions' );

		wp_delete_post( $revision_id, true );
		$this->assertNotNull( get_post( $revision_id ) );
	}
}
