<?php
/**
 * Hook callbacks for Future Revisions.
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'wfr_register_default_post_type_support', 5 );
add_action( 'init', 'wfr_register_rewrite_endpoint' );
add_action( 'init', array( 'WFR_Public_Revisions', 'register_meta' ) );
add_action( 'init', array( 'WFR_Future_Revisions', 'register_meta' ), 20 );
add_action( 'parse_request', array( 'WFR_Rewrite', 'maybe_intercept_request' ), 1 );
add_filter( 'redirect_canonical', array( 'WFR_Rewrite', 'prevent_canonical_redirect' ), 1, 2 );
add_action( 'template_redirect', array( 'WFR_Rewrite', 'handle_endpoint' ) );
add_filter( 'pre_delete_post', array( 'WFR_Public_Revisions', 'protect_public_revision' ), 10, 3 );
add_action( 'transition_post_status', array( 'WFR_Future_Revisions', 'on_transition_post_status' ), 1, 3 );
add_action( 'before_delete_post', array( 'WFR_Future_Revisions', 'cleanup_fork_reference' ) );
add_action( 'wp_trash_post', array( 'WFR_Future_Revisions', 'cleanup_fork_reference' ) );
add_filter( 'display_post_states', array( 'WFR_Future_Revisions', 'add_post_states' ), 10, 2 );
add_action( 'wp_body_open', array( 'WFR_Future_Revisions', 'render_fork_banner' ) );
add_action( 'rest_api_init', array( 'WFR_REST_API', 'register_routes' ) );
add_action( 'enqueue_block_editor_assets', array( 'WFR_Admin', 'enqueue_editor_assets' ) );
