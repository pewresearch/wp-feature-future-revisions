<?php
/**
 * Helpers and constants for Future Revisions.
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WFR_SUPPORT_PUBLIC   = 'public-revisions';
const WFR_SUPPORT_FUTURE   = 'future-revisions';
const WFR_META_IS_PUBLIC   = 'is_revision_public';
const WFR_META_IS_MERGED   = 'is_revision_merged';
const WFR_META_FORK_OF     = '_future_revision_of';
const WFR_META_ACTIVE_FORK = '_active_future_revision';
const WFR_META_FORK_STATUS = '_future_revision_status';
const WFR_REST_NAMESPACE   = 'wp-future-revisions/v1';

/**
 * Whether a post type supports public revisions.
 *
 * @param string $post_type Post type.
 * @return bool
 */
function wfr_post_type_supports_public_revisions( $post_type ) {
	return post_type_supports( $post_type, WFR_SUPPORT_PUBLIC );
}

/**
 * Whether a post type supports future revisions.
 *
 * @param string $post_type Post type.
 * @return bool
 */
function wfr_post_type_supports_future_revisions( $post_type ) {
	return post_type_supports( $post_type, WFR_SUPPORT_FUTURE );
}

/**
 * Parent post type for a revision ID.
 *
 * @param int $revision_id Revision post ID.
 * @return string Empty string when the parent cannot be resolved.
 */
function wfr_get_revision_parent_type( $revision_id ) {
	$revision = get_post( $revision_id );
	if ( ! $revision || 'revision' !== $revision->post_type ) {
		return '';
	}
	$parent = get_post( (int) $revision->post_parent );
	return $parent ? $parent->post_type : '';
}

/**
 * Register default post type supports.
 *
 * @return void
 */
function wfr_register_default_post_type_support() {
	add_post_type_support( 'post', WFR_SUPPORT_PUBLIC );
	add_post_type_support( 'post', WFR_SUPPORT_FUTURE );
	add_post_type_support( 'page', WFR_SUPPORT_PUBLIC );
	add_post_type_support( 'page', WFR_SUPPORT_FUTURE );
}

/**
 * PHP 7.4 string suffix check.
 *
 * @param string $haystack Haystack.
 * @param string $needle   Needle.
 * @return bool
 */
function wfr_ends_with( $haystack, $needle ) {
	if ( '' === $needle ) {
		return true;
	}
	$len = strlen( $needle );
	return substr( $haystack, -$len ) === $needle;
}

/**
 * Latest revision ID for a post, or 0.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function wfr_get_latest_revision_id( $post_id ) {
	$revisions = wp_get_post_revisions(
		$post_id,
		array(
			'numberposts' => 1,
			'order'       => 'DESC',
		)
	);
	if ( empty( $revisions ) ) {
		return 0;
	}
	$revision = reset( $revisions );
	return (int) $revision->ID;
}

/**
 * Public URL for a revision, or empty string.
 *
 * @param int $parent_id   Parent post ID.
 * @param int $revision_id Revision ID.
 * @return string
 */
function wfr_get_public_revision_url( $parent_id, $revision_id ) {
	$parent_url = get_permalink( $parent_id );
	if ( ! $parent_url ) {
		return '';
	}
	return trailingslashit( $parent_url ) . 'revision/' . absint( $revision_id ) . '/';
}

/**
 * Activate: register rewrite then flush.
 *
 * @return void
 */
function wfr_activate() {
	wfr_register_rewrite_endpoint();
	flush_rewrite_rules();
}

/**
 * Deactivate: flush rewrite rules.
 *
 * @return void
 */
function wfr_deactivate() {
	flush_rewrite_rules();
}
