<?php
/**
 * Future revision fork and merge.
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fork/merge workflow.
 */
class WFR_Future_Revisions {

	/**
	 * Meta keys not copied between parent and fork.
	 *
	 * @var string[]
	 */
	const META_COPY_BLOCKLIST = array(
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_old_date',
		WFR_META_FORK_OF,
		WFR_META_FORK_STATUS,
		WFR_META_ACTIVE_FORK,
		WFR_META_IS_PUBLIC,
		WFR_META_IS_MERGED,
	);

	/**
	 * Register fork meta on supported post types.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			'revision',
			WFR_META_IS_MERGED,
			array(
				'single'        => true,
				'type'          => 'boolean',
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => '__return_false',
			)
		);
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			if ( ! wfr_post_type_supports_future_revisions( $post_type ) ) {
				continue;
			}
			self::register_meta_for_type( $post_type );
		}
	}

	/**
	 * Register fork meta for one type.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	private static function register_meta_for_type( $post_type ) {
		register_post_meta(
			$post_type,
			WFR_META_FORK_OF,
			array(
				'single'        => true,
				'type'          => 'integer',
				'default'       => 0,
				'show_in_rest'  => true,
				'auth_callback' => array( __CLASS__, 'auth_edit_posts' ),
			)
		);
		register_post_meta(
			$post_type,
			WFR_META_FORK_STATUS,
			array(
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_fork_status' ),
				'auth_callback'     => array( __CLASS__, 'auth_edit_posts' ),
			)
		);
		register_post_meta(
			$post_type,
			WFR_META_ACTIVE_FORK,
			array(
				'single'        => true,
				'type'          => 'integer',
				'default'       => 0,
				'show_in_rest'  => true,
				'auth_callback' => array( __CLASS__, 'auth_edit_posts' ),
			)
		);
	}

	/**
	 * Capability gate for fork meta REST writes.
	 *
	 * @return bool
	 */
	public static function auth_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Sanitize fork status.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function sanitize_fork_status( $value ) {
		return in_array( $value, array( 'draft', 'merged' ), true ) ? $value : '';
	}

	/**
	 * Create a fork of a published post.
	 *
	 * @param int $parent_post_id Parent post ID.
	 * @return int|WP_Error Fork ID.
	 */
	public static function create_fork( $parent_post_id ) {
		$parent = get_post( $parent_post_id );
		if ( ! $parent ) {
			return new WP_Error( 'invalid_parent', __( 'Parent post does not exist.', 'wp-feature-future-revisions' ) );
		}

		if ( ! wfr_post_type_supports_future_revisions( $parent->post_type ) ) {
			return new WP_Error(
				'rest_post_type_not_supported',
				__( 'This post type does not support future revisions.', 'wp-feature-future-revisions' )
			);
		}

		if ( 'publish' !== $parent->post_status ) {
			return new WP_Error( 'not_published', __( 'Only published posts can be forked.', 'wp-feature-future-revisions' ) );
		}

		if ( ! current_user_can( 'edit_post', (int) $parent->ID ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to modify this post.', 'wp-feature-future-revisions' )
			);
		}

		$existing = self::get_active_fork_id( (int) $parent->ID );
		if ( $existing ) {
			return new WP_Error(
				'fork_exists',
				__( 'An active fork already exists for this post.', 'wp-feature-future-revisions' ),
				array( 'fork_id' => $existing )
			);
		}

		$fork_id = wp_insert_post(
			array(
				'post_type'    => $parent->post_type,
				'post_status'  => 'draft',
				'post_title'   => $parent->post_title,
				'post_name'    => $parent->post_name . '__fork',
				'post_content' => $parent->post_content,
				'post_excerpt' => $parent->post_excerpt,
				'post_author'  => get_current_user_id(),
				'post_parent'  => 0,
			),
			true
		);
		if ( is_wp_error( $fork_id ) ) {
			return $fork_id;
		}

		update_post_meta( $fork_id, WFR_META_FORK_OF, (int) $parent->ID );
		update_post_meta( $fork_id, WFR_META_FORK_STATUS, 'draft' );
		update_post_meta( (int) $parent->ID, WFR_META_ACTIVE_FORK, (int) $fork_id );

		self::copy_taxonomy_terms( (int) $parent->ID, (int) $fork_id );
		self::copy_post_meta( (int) $parent->ID, (int) $fork_id );

		return (int) $fork_id;
	}

	/**
	 * Active non-merged, non-trashed fork ID for a parent, or 0.
	 *
	 * @param int $parent_id Parent post ID.
	 * @return int
	 */
	public static function get_active_fork_id( $parent_id ) {
		$existing_fork = absint( get_post_meta( $parent_id, WFR_META_ACTIVE_FORK, true ) );
		if ( ! $existing_fork ) {
			return 0;
		}
		$existing_post = get_post( $existing_fork );
		if (
			! $existing_post
			|| 'trash' === $existing_post->post_status
			|| absint( get_post_meta( $existing_fork, WFR_META_FORK_OF, true ) ) !== absint( $parent_id )
		) {
			return 0;
		}
		if ( 'merged' === get_post_meta( $existing_fork, WFR_META_FORK_STATUS, true ) ) {
			delete_post_meta( $parent_id, WFR_META_ACTIVE_FORK );
			return 0;
		}
		return $existing_fork;
	}

	/**
	 * Copy taxonomy terms.
	 *
	 * @param int $source_id      Source.
	 * @param int $destination_id Destination.
	 * @return void
	 */
	private static function copy_taxonomy_terms( $source_id, $destination_id ) {
		$post_type  = get_post_type( $source_id );
		$taxonomies = get_object_taxonomies( $post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $source_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $destination_id, $terms, $taxonomy );
			}
		}
	}

	/**
	 * Copy post meta, skipping the blocklist.
	 *
	 * @param int $source_id      Source.
	 * @param int $destination_id Destination.
	 * @return void
	 */
	private static function copy_post_meta( $source_id, $destination_id ) {
		$meta = get_post_meta( $source_id );
		if ( ! $meta ) {
			return;
		}
		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, self::META_COPY_BLOCKLIST, true ) ) {
				continue;
			}
			delete_post_meta( $destination_id, $key );
			foreach ( $values as $value ) {
				add_post_meta( $destination_id, $key, maybe_unserialize( $value ) );
			}
		}
	}

	/**
	 * Merge a fork that is transitioning to publish.
	 *
	 * Runs even if future-revisions support was later removed, so a leftover
	 * fork cannot publish as a sibling.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public static function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$parent_id = absint( get_post_meta( $post->ID, WFR_META_FORK_OF, true ) );
		if ( ! $parent_id ) {
			return;
		}
		if ( 'merged' === get_post_meta( $post->ID, WFR_META_FORK_STATUS, true ) ) {
			return;
		}

		$result = self::merge_fork( (int) $post->ID, $parent_id );
		if ( is_wp_error( $result ) ) {
			remove_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 1 );
			wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'draft',
				)
			);
			add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 1, 3 );
		}
	}

	/**
	 * Merge fork into parent. Keeps the original publish date.
	 *
	 * @param int $fork_id   Fork ID.
	 * @param int $parent_id Parent ID. Looked up from fork meta when 0.
	 * @return true|WP_Error
	 */
	public static function merge_fork( $fork_id, $parent_id = 0 ) {
		$fork_id   = absint( $fork_id );
		$parent_id = absint( $parent_id );
		if ( $parent_id <= 0 ) {
			$parent_id = absint( get_post_meta( $fork_id, WFR_META_FORK_OF, true ) );
		}
		$fork   = get_post( $fork_id );
		$parent = get_post( $parent_id );
		if ( ! $fork || ! $parent ) {
			return new WP_Error( 'invalid_posts', __( 'Fork or parent post not found.', 'wp-feature-future-revisions' ) );
		}

		self::copy_post_meta( $fork_id, $parent_id );
		self::copy_taxonomy_terms( $fork_id, $parent_id );

		$fork_suffix   = '__fork';
		$resolved_slug = wfr_ends_with( $fork->post_name, $fork_suffix )
			? $parent->post_name
			: $fork->post_name;

		$before_revision = wfr_get_latest_revision_id( $parent_id );

		$update_result = wp_update_post(
			array(
				'ID'           => $parent_id,
				'post_title'   => $fork->post_title,
				'post_name'    => $resolved_slug,
				'post_content' => $fork->post_content,
				'post_excerpt' => $fork->post_excerpt,
			),
			true
		);
		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

		$after_revision = wfr_get_latest_revision_id( $parent_id );
		if ( $after_revision && $after_revision !== $before_revision ) {
			update_metadata( 'post', $after_revision, WFR_META_IS_MERGED, true );
		}
		delete_post_meta( $parent_id, WFR_META_IS_MERGED );

		$attachments = get_children(
			array(
				'post_parent' => $fork_id,
				'post_type'   => 'attachment',
				'numberposts' => -1,
			)
		);
		foreach ( $attachments as $attachment ) {
			wp_update_post(
				array(
					'ID'          => $attachment->ID,
					'post_parent' => $parent_id,
				)
			);
		}

		$children = get_children(
			array(
				'post_parent' => $fork_id,
				'post_type'   => 'any',
				'numberposts' => -1,
				'exclude'     => array_keys( $attachments ),
			)
		);
		foreach ( $children as $child ) {
			wp_update_post(
				array(
					'ID'          => $child->ID,
					'post_parent' => $parent_id,
				)
			);
		}

		$latest_revision = wp_get_post_revisions(
			$parent_id,
			array(
				'numberposts' => 1,
				'order'       => 'DESC',
			)
		);
		if ( ! empty( $latest_revision ) ) {
			do_action( 'revision_applied', $parent_id, reset( $latest_revision ) );
		}

		update_post_meta( $fork_id, WFR_META_FORK_STATUS, 'merged' );
		delete_post_meta( $parent_id, WFR_META_ACTIVE_FORK );

		if ( 'publish' === get_post_status( $fork_id ) ) {
			remove_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 1 );
			wp_update_post(
				array(
					'ID'          => $fork_id,
					'post_status' => 'draft',
				)
			);
			add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 1, 3 );
		}
		wp_trash_post( $fork_id );

		return true;
	}

	/**
	 * Clear the parent pointer when a fork is trashed or deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function cleanup_fork_reference( $post_id ) {
		$parent_id = get_post_meta( $post_id, WFR_META_FORK_OF, true );
		if ( ! $parent_id ) {
			return;
		}
		$active_fork = get_post_meta( $parent_id, WFR_META_ACTIVE_FORK, true );
		if ( (int) $active_fork === (int) $post_id ) {
			delete_post_meta( $parent_id, WFR_META_ACTIVE_FORK );
		}
	}

	/**
	 * List table states.
	 *
	 * @param array   $post_states States.
	 * @param WP_Post $post        Post.
	 * @return array
	 */
	public static function add_post_states( $post_states, $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return $post_states;
		}
		if ( ! wfr_post_type_supports_future_revisions( $post->post_type ) ) {
			return $post_states;
		}
		$parent_id = (int) get_post_meta( $post->ID, WFR_META_FORK_OF, true );
		if ( $parent_id ) {
			$post_states['wfr_future_revision'] = __( 'Future Revision', 'wp-feature-future-revisions' );
			return $post_states;
		}
		$active_fork_id = self::get_active_fork_id( (int) $post->ID );
		if ( $active_fork_id ) {
			$post_states['wfr_has_future_revision'] = __( 'Has Future Revision', 'wp-feature-future-revisions' );
		}
		return $post_states;
	}

	/**
	 * Admin-bar fork preview banner.
	 *
	 * @return void
	 */
	public static function render_fork_banner() {
		if ( is_admin() || ! is_singular() || ! is_admin_bar_showing() ) {
			return;
		}
		$queried_post = get_queried_object();
		if ( ! ( $queried_post instanceof WP_Post ) ) {
			return;
		}
		if ( ! wfr_post_type_supports_future_revisions( $queried_post->post_type ) ) {
			return;
		}
		$parent_id = (int) get_post_meta( $queried_post->ID, WFR_META_FORK_OF, true );
		if ( ! $parent_id ) {
			return;
		}
		$parent = get_post( $parent_id );
		if ( ! $parent ) {
			return;
		}
		$parent_url   = get_permalink( $parent );
		$parent_title = get_the_title( $parent );
		if ( ! $parent_url || ! $parent_title ) {
			return;
		}
		echo self::get_banner_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'label'        => __( 'This is a future revision of:', 'wp-feature-future-revisions' ),
				'parent_url'   => $parent_url,
				'parent_title' => $parent_title,
			)
		);
	}

	/**
	 * Fork banner markup.
	 *
	 * @param array $args Args.
	 * @return string
	 */
	public static function get_banner_html( $args ) {
		$defaults = array(
			'label'        => __( 'This is a future revision of:', 'wp-feature-future-revisions' ),
			'parent_url'   => '',
			'parent_title' => '',
		);
		$args     = wp_parse_args( $args, $defaults );
		$label    = trim( $args['label'] );
		if ( '' === $label ) {
			return '';
		}
		$parent_markup = '';
		if ( ! empty( $args['parent_url'] ) && ! empty( $args['parent_title'] ) ) {
			$parent_markup = ' <a href="' . esc_url( $args['parent_url'] ) . '">' . esc_html( $args['parent_title'] ) . '</a>';
		}
		return '<style>
			.wfr-future-revision-banner {
				background-color: #ffd84d;
				background-image: repeating-linear-gradient(-45deg, rgba(0,0,0,.14) 0, rgba(0,0,0,.14) 12px, rgba(0,0,0,.05) 12px, rgba(0,0,0,.05) 24px);
				color: #1d2327;
				border-bottom: 1px solid rgba(0,0,0,.25);
				padding: 10px 16px;
				font-size: 13px;
				font-weight: 600;
				line-height: 1.3;
				text-align: center;
			}
			.wfr-future-revision-banner a { color: #1d2327; text-decoration: underline; }
		</style>
		<div class="wfr-future-revision-banner" role="status">' . esc_html( $label ) . $parent_markup . '</div>';
	}

	/**
	 * Reject a REST-writable merged fork without trashing it.
	 *
	 * @param int $fork_id   Fork ID.
	 * @param int $parent_id Parent ID.
	 * @return WP_Error
	 */
	private static function reject_merged_fork( $fork_id, $parent_id ) {
		$fork_id   = absint( $fork_id );
		$parent_id = absint( $parent_id );
		$active    = absint( get_post_meta( $parent_id, WFR_META_ACTIVE_FORK, true ) );
		if ( $active === $fork_id ) {
			delete_post_meta( $parent_id, WFR_META_ACTIVE_FORK );
		}

		return new WP_Error(
			'fork_already_merged',
			__( 'This future revision has already been merged.', 'wp-feature-future-revisions' )
		);
	}

	/**
	 * Trash a pending fork. Accepts parent or fork ID.
	 *
	 * @param int $post_id Post ID.
	 * @return array|WP_Error
	 */
	public static function trash_fork( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Post does not exist.', 'wp-feature-future-revisions' ) );
		}

		$fork_id   = 0;
		$parent_id = 0;

		$active_fork_id = absint( get_post_meta( $post_id, WFR_META_ACTIVE_FORK, true ) );
		if ( $active_fork_id ) {
			$fork = get_post( $active_fork_id );
			if ( $fork && 'trash' !== $fork->post_status ) {
				$fork_parent_of_active = absint( get_post_meta( $active_fork_id, WFR_META_FORK_OF, true ) );
				if ( $fork_parent_of_active === $post_id ) {
					if ( 'merged' === get_post_meta( $active_fork_id, WFR_META_FORK_STATUS, true ) ) {
						return self::reject_merged_fork( $active_fork_id, $post_id );
					}
					$fork_id   = $active_fork_id;
					$parent_id = $post_id;
				}
			}
		}

		if ( ! $fork_id ) {
			$fork_parent_id = absint( get_post_meta( $post_id, WFR_META_FORK_OF, true ) );
			if ( $fork_parent_id ) {
				if ( 'trash' === $post->post_status ) {
					return new WP_Error( 'fork_already_trashed', __( 'This future revision is already in the trash.', 'wp-feature-future-revisions' ) );
				}
				if ( 'merged' === get_post_meta( $post_id, WFR_META_FORK_STATUS, true ) ) {
					return self::reject_merged_fork( $post_id, $fork_parent_id );
				}
				$fork_id   = $post_id;
				$parent_id = $fork_parent_id;
			}
		}

		if ( ! $fork_id || ! $parent_id ) {
			return new WP_Error( 'no_active_fork', __( 'No pending future revision found for this post.', 'wp-feature-future-revisions' ) );
		}

		if ( ! current_user_can( 'delete_post', $fork_id ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to delete this future revision.', 'wp-feature-future-revisions' ) );
		}

		$result = wp_trash_post( $fork_id );
		if ( ! $result ) {
			return new WP_Error( 'trash_failed', __( 'Could not trash the future revision.', 'wp-feature-future-revisions' ) );
		}

		return array(
			'fork_id'   => $fork_id,
			'parent_id' => $parent_id,
		);
	}

	/**
	 * Fork relationship for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_fork_info( $post_id ) {
		$post_id        = absint( $post_id );
		$active_fork_id = self::get_active_fork_id( $post_id );
		if ( $active_fork_id ) {
			return array(
				'role'          => 'parent',
				'fork_id'       => $active_fork_id,
				'fork_status'   => get_post_meta( $active_fork_id, WFR_META_FORK_STATUS, true ),
				'fork_edit_url' => get_edit_post_link( $active_fork_id, 'raw' ),
			);
		}

		$fork_parent_id = absint( get_post_meta( $post_id, WFR_META_FORK_OF, true ) );
		if ( $fork_parent_id ) {
			return array(
				'role'            => 'fork',
				'parent_id'       => $fork_parent_id,
				'parent_title'    => get_the_title( $fork_parent_id ),
				'parent_edit_url' => get_edit_post_link( $fork_parent_id, 'raw' ),
				'fork_status'     => get_post_meta( $post_id, WFR_META_FORK_STATUS, true ),
			);
		}

		return array(
			'role' => 'none',
		);
	}
}
