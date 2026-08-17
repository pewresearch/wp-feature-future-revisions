<?php
/**
 * Public revision permalink endpoint.
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite endpoint `/revision/{id}/`.
 */
class WFR_Rewrite {

	const ENDPOINT = 'revision';

	/**
	 * Whether this request is a public revision URL.
	 *
	 * @var bool
	 */
	private static $is_revision_request = false;

	/**
	 * Parent post ID resolved from the path.
	 *
	 * @var int|null
	 */
	private static $parent_id = null;

	/**
	 * Resolve /post-slug/revision/{id}/ to the parent post.
	 *
	 * @param WP $wp WordPress environment.
	 * @return void
	 */
	public static function maybe_intercept_request( $wp ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! $path ) {
			return;
		}
		if ( ! preg_match( '#/revision/(\d+)/?$#', $path, $matches ) ) {
			return;
		}

		$revision_id = absint( $matches[1] );
		$parent_path = preg_replace( '#/revision/\d+/?$#', '', $path );
		$parent_path = trim( $parent_path, '/' );

		$home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home_path && 0 === strpos( $parent_path, $home_path ) ) {
			$parent_path = trim( substr( $parent_path, strlen( $home_path ) ), '/' );
		}

		if ( '' === $parent_path ) {
			return;
		}

		$parent_url = home_url( '/' . $parent_path . '/' );
		$post_id    = (int) url_to_postid( $parent_url );
		if ( ! $post_id ) {
			$post_id = (int) url_to_postid( home_url( '/' . $parent_path ) );
		}
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		self::$is_revision_request = true;
		self::$parent_id           = $post_id;

		$wp->query_vars = array(
			'p'            => $post_id,
			'post_type'    => $post->post_type,
			self::ENDPOINT => (string) $revision_id,
		);
	}

	/**
	 * Keep the revision segment on the URL.
	 *
	 * @param string|false $redirect_url  Redirect URL.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function prevent_canonical_redirect( $redirect_url, $requested_url ) {
		unset( $requested_url );
		if ( self::$is_revision_request ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Serve a public revision or 404.
	 *
	 * @return void
	 */
	public static function handle_endpoint() {
		$revision_id = get_query_var( self::ENDPOINT, false );
		if ( false === $revision_id || '' === $revision_id ) {
			return;
		}
		if ( ! preg_match( '/^\d+$/', (string) $revision_id ) ) {
			self::send_404();
			return;
		}

		$revision_id = absint( $revision_id );
		$post_id     = null !== self::$parent_id ? (int) self::$parent_id : (int) get_queried_object_id();
		$post        = get_post( $post_id );
		if ( ! $post || ! wfr_post_type_supports_public_revisions( $post->post_type ) ) {
			self::send_404();
			return;
		}

		$revision = get_post( $revision_id );
		if ( ! $revision || 'revision' !== $revision->post_type || (int) $revision->post_parent !== $post_id ) {
			self::send_404();
			return;
		}

		if ( ! WFR_Public_Revisions::is_public( $revision_id ) ) {
			self::send_404();
			return;
		}

		WFR_Public_Revisions::set_current_context( $post_id, $revision_id );
		add_filter( 'the_content', array( 'WFR_Public_Revisions', 'substitute_content' ), 1 );
		add_filter( 'the_content', array( 'WFR_Public_Revisions', 'prepend_newer_notice' ), 5 );
		add_filter( 'the_title', array( 'WFR_Public_Revisions', 'filter_title' ), 10, 2 );
		add_filter( 'get_the_date', array( 'WFR_Public_Revisions', 'filter_date' ), 10, 3 );
		add_filter( 'get_the_modified_date', array( 'WFR_Public_Revisions', 'filter_modified_date' ), 10, 3 );
		add_filter( 'the_author', array( 'WFR_Public_Revisions', 'filter_author' ) );
		add_filter( 'document_title_parts', array( 'WFR_Public_Revisions', 'filter_document_title' ) );
		add_action( 'wp_enqueue_scripts', array( 'WFR_Public_Revisions', 'enqueue_notice_styles' ) );
	}

	/**
	 * Mark the request as 404.
	 *
	 * @return void
	 */
	private static function send_404() {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}
}

/**
 * Register the revision rewrite endpoint.
 *
 * @return void
 */
function wfr_register_rewrite_endpoint() {
	add_rewrite_endpoint( WFR_Rewrite::ENDPOINT, EP_PERMALINK );
}
