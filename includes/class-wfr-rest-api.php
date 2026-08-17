<?php
/**
 * REST API for Future Revisions.
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST routes.
 */
class WFR_REST_API {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			WFR_REST_NAMESPACE,
			'/public-revisions/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_public_revisions' ),
					'permission_callback' => array( __CLASS__, 'read_public_permissions' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			WFR_REST_NAMESPACE,
			'/public-revisions/(?P<post_id>\d+)/(?P<revision_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'set_public_revision' ),
					'permission_callback' => array( __CLASS__, 'write_public_permissions' ),
					'args'                => array(
						'post_id'     => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'revision_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'public'      => array(
							'required'          => true,
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				),
			)
		);

		register_rest_route(
			WFR_REST_NAMESPACE,
			'/forks',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_fork' ),
					'permission_callback' => array( __CLASS__, 'write_future_permissions' ),
					'args'                => array(
						'post' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_fork_info' ),
					'permission_callback' => array( __CLASS__, 'write_future_permissions' ),
					'args'                => array(
						'post' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			WFR_REST_NAMESPACE,
			'/forks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'trash_fork' ),
					'permission_callback' => array( __CLASS__, 'delete_fork_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Read public revisions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function read_public_permissions( $request ) {
		$post = get_post( (int) $request->get_param( 'post_id' ) );
		if ( ! $post ) {
			return new WP_Error( 'rest_not_found', __( 'Post not found.', 'wp-feature-future-revisions' ), array( 'status' => 404 ) );
		}
		if ( ! wfr_post_type_supports_public_revisions( $post->post_type ) ) {
			return new WP_Error(
				'rest_post_type_not_supported',
				__( 'This post type does not support public revisions.', 'wp-feature-future-revisions' ),
				array( 'status' => 400 )
			);
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', (int) $post->ID ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view revisions for this post.', 'wp-feature-future-revisions' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Write public flag.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function write_public_permissions( $request ) {
		$post = get_post( (int) $request->get_param( 'post_id' ) );
		if ( ! $post ) {
			return new WP_Error( 'rest_not_found', __( 'Post not found.', 'wp-feature-future-revisions' ), array( 'status' => 404 ) );
		}
		if ( ! wfr_post_type_supports_public_revisions( $post->post_type ) ) {
			return new WP_Error(
				'rest_post_type_not_supported',
				__( 'This post type does not support public revisions.', 'wp-feature-future-revisions' ),
				array( 'status' => 400 )
			);
		}
		if ( ! current_user_can( 'edit_post', (int) $post->ID ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to modify this post.', 'wp-feature-future-revisions' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Fork write permission. Uses `post` query/body param.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function write_future_permissions( $request ) {
		$post = get_post( (int) $request->get_param( 'post' ) );
		if ( ! $post ) {
			return new WP_Error( 'rest_not_found', __( 'Post not found.', 'wp-feature-future-revisions' ), array( 'status' => 404 ) );
		}
		if ( ! wfr_post_type_supports_future_revisions( $post->post_type ) ) {
			return new WP_Error(
				'rest_post_type_not_supported',
				__( 'This post type does not support future revisions.', 'wp-feature-future-revisions' ),
				array( 'status' => 400 )
			);
		}
		if ( ! current_user_can( 'edit_post', (int) $post->ID ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to modify this post.', 'wp-feature-future-revisions' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Discard permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function delete_fork_permissions( $request ) {
		$post = get_post( (int) $request->get_param( 'id' ) );
		if ( ! $post ) {
			return new WP_Error( 'rest_not_found', __( 'Post not found.', 'wp-feature-future-revisions' ), array( 'status' => 404 ) );
		}
		$parent_id = absint( get_post_meta( $post->ID, WFR_META_FORK_OF, true ) );
		$check_id  = $parent_id ? $parent_id : (int) $post->ID;
		$check     = get_post( $check_id );
		$is_fork   = $parent_id > 0 || absint( get_post_meta( $post->ID, WFR_META_ACTIVE_FORK, true ) ) > 0;
		if ( $check && ! wfr_post_type_supports_future_revisions( $check->post_type ) && ! $is_fork ) {
			return new WP_Error(
				'rest_post_type_not_supported',
				__( 'This post type does not support future revisions.', 'wp-feature-future-revisions' ),
				array( 'status' => 400 )
			);
		}
		if ( ! current_user_can( 'edit_post', $check_id ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to modify this post.', 'wp-feature-future-revisions' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * List public revisions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_public_revisions( $request ) {
		$data = WFR_Public_Revisions::get_public_revisions( (int) $request->get_param( 'post_id' ) );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Set public flag. Uses update_metadata so the flag stays on the revision.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function set_public_revision( $request ) {
		$post_id     = (int) $request->get_param( 'post_id' );
		$revision_id = (int) $request->get_param( 'revision_id' );
		$revision    = get_post( $revision_id );
		if ( ! $revision || 'revision' !== $revision->post_type || (int) $revision->post_parent !== $post_id ) {
			return new WP_Error(
				'revision_mismatch',
				__( 'The revision does not belong to the specified post.', 'wp-feature-future-revisions' ),
				array( 'status' => 400 )
			);
		}

		$result = WFR_Public_Revisions::set_public( $revision_id, (bool) $request->get_param( 'public' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		$is_public = WFR_Public_Revisions::is_public( $revision_id );
		return new WP_REST_Response(
			array(
				'public' => $is_public,
				'url'    => $is_public ? wfr_get_public_revision_url( $post_id, $revision_id ) : '',
			),
			200
		);
	}

	/**
	 * Create a fork.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_fork( $request ) {
		$fork_id = WFR_Future_Revisions::create_fork( (int) $request->get_param( 'post' ) );
		if ( is_wp_error( $fork_id ) ) {
			$status = 'fork_exists' === $fork_id->get_error_code() ? 409 : 400;
			return new WP_Error(
				$fork_id->get_error_code(),
				$fork_id->get_error_message(),
				array_merge( array( 'status' => $status ), (array) $fork_id->get_error_data() )
			);
		}
		return new WP_REST_Response(
			array(
				'fork_id'  => $fork_id,
				'edit_url' => get_edit_post_link( $fork_id, 'raw' ),
			),
			201
		);
	}

	/**
	 * Fork info.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_fork_info( $request ) {
		return new WP_REST_Response( WFR_Future_Revisions::get_fork_info( (int) $request->get_param( 'post' ) ), 200 );
	}

	/**
	 * Trash a fork.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function trash_fork( $request ) {
		$result = WFR_Future_Revisions::trash_fork( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$status_map = array(
				'invalid_post'         => 404,
				'no_active_fork'       => 404,
				'fork_already_trashed' => 410,
				'fork_already_merged'  => 400,
				'rest_forbidden'       => 403,
				'trash_failed'         => 500,
			);
			$code = $result->get_error_code();
			return new WP_Error(
				$code,
				$result->get_error_message(),
				array( 'status' => isset( $status_map[ $code ] ) ? $status_map[ $code ] : 400 )
			);
		}
		return new WP_REST_Response(
			array(
				'trashed'   => true,
				'fork_id'   => $result['fork_id'],
				'parent_id' => $result['parent_id'],
			),
			200
		);
	}
}
