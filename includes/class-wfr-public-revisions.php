<?php
/**
 * Public revision flag and content substitution.
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public revisions.
 */
class WFR_Public_Revisions {

	/**
	 * Request context while serving a public revision URL.
	 *
	 * @var array|null
	 */
	private static $current_context = null;

	/**
	 * Register revision meta.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			'revision',
			WFR_META_IS_PUBLIC,
			array(
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( __CLASS__, 'auth_public_meta' ),
			)
		);
	}

	/**
	 * REST auth for is_revision_public.
	 *
	 * @param bool   $allowed    Whether allowed.
	 * @param string $meta_key   Meta key.
	 * @param int    $object_id  Object ID.
	 * @return bool
	 */
	public static function auth_public_meta( $allowed, $meta_key, $object_id ) {
		unset( $allowed, $meta_key );
		$parent_type = wfr_get_revision_parent_type( $object_id );
		if ( '' === $parent_type || ! wfr_post_type_supports_public_revisions( $parent_type ) ) {
			return false;
		}
		$revision = get_post( $object_id );
		return $revision && current_user_can( 'edit_post', (int) $revision->post_parent );
	}

	/**
	 * Whether a revision is public.
	 *
	 * @param int $revision_id Revision ID.
	 * @return bool
	 */
	public static function is_public( $revision_id ) {
		return (bool) get_metadata( 'post', (int) $revision_id, WFR_META_IS_PUBLIC, true );
	}

	/**
	 * Set public flag on a revision.
	 *
	 * @param int  $revision_id Revision ID.
	 * @param bool $public      Whether public.
	 * @return true|WP_Error
	 */
	public static function set_public( $revision_id, $public ) {
		$revision = get_post( $revision_id );
		if ( ! $revision || 'revision' !== $revision->post_type ) {
			return new WP_Error( 'invalid_revision', __( 'The specified revision does not exist.', 'wp-feature-future-revisions' ) );
		}

		$parent = get_post( (int) $revision->post_parent );
		if ( ! $parent ) {
			return new WP_Error( 'invalid_parent', __( 'The revision parent does not exist.', 'wp-feature-future-revisions' ) );
		}

		if ( ! wfr_post_type_supports_public_revisions( $parent->post_type ) ) {
			return new WP_Error(
				'rest_post_type_not_supported',
				__( 'This post type does not support public revisions.', 'wp-feature-future-revisions' )
			);
		}

		if ( ! current_user_can( 'edit_post', (int) $parent->ID ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to modify this post.', 'wp-feature-future-revisions' )
			);
		}

		update_metadata( 'post', (int) $revision_id, WFR_META_IS_PUBLIC, (bool) $public );
		return true;
	}

	/**
	 * Public revisions for a parent post.
	 *
	 * @param int $post_id Parent post ID.
	 * @return array
	 */
	public static function get_public_revisions( $post_id ) {
		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'numberposts' => -1,
				'order'       => 'DESC',
			)
		);
		$out = array();
		foreach ( $revisions as $revision ) {
			if ( ! self::is_public( $revision->ID ) ) {
				continue;
			}
			$out[] = array(
				'id'     => (int) $revision->ID,
				'date'   => $revision->post_date,
				'author' => get_the_author_meta( 'display_name', (int) $revision->post_author ),
				'url'    => wfr_get_public_revision_url( $post_id, $revision->ID ),
			);
		}
		return $out;
	}

	/**
	 * Block deletion of public revisions.
	 *
	 * Follows the flag, not current post type support.
	 *
	 * @param bool|null $delete       Whether to delete.
	 * @param WP_Post   $post         Post.
	 * @param bool      $force_delete Bypass trash.
	 * @return bool|null
	 */
	public static function protect_public_revision( $delete, $post, $force_delete ) {
		unset( $force_delete );
		if ( ! $post instanceof WP_Post || 'revision' !== $post->post_type ) {
			return $delete;
		}
		if ( self::is_public( $post->ID ) ) {
			return false;
		}
		return $delete;
	}

	/**
	 * Set request context.
	 *
	 * @param int $parent_id   Parent ID.
	 * @param int $revision_id Revision ID.
	 * @return void
	 */
	public static function set_current_context( $parent_id, $revision_id ) {
		self::$current_context = array(
			'parent_id'   => (int) $parent_id,
			'revision_id' => (int) $revision_id,
		);
	}

	/**
	 * Current request context.
	 *
	 * @return array|null
	 */
	public static function get_current_context() {
		return self::$current_context;
	}

	/**
	 * Substitute revision content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function substitute_content( $content ) {
		if ( null === self::$current_context ) {
			return $content;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$revision = get_post( self::$current_context['revision_id'] );
		if ( ! $revision ) {
			return $content;
		}
		remove_filter( 'the_content', array( __CLASS__, 'substitute_content' ), 1 );
		return $revision->post_content;
	}

	/**
	 * Use the revision title.
	 *
	 * @param string $title   Title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function filter_title( $title, $post_id = 0 ) {
		if ( null === self::$current_context ) {
			return $title;
		}
		if ( (int) $post_id !== self::$current_context['parent_id'] ) {
			return $title;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}
		$revision = get_post( self::$current_context['revision_id'] );
		return $revision ? $revision->post_title : $title;
	}

	/**
	 * Revision date.
	 *
	 * @param string|false $the_date Formatted date.
	 * @param string       $format   Format.
	 * @param WP_Post      $post     Post.
	 * @return string|false
	 */
	public static function filter_date( $the_date, $format, $post ) {
		if ( null === self::$current_context || ! $post instanceof WP_Post ) {
			return $the_date;
		}
		if ( (int) $post->ID !== self::$current_context['parent_id'] ) {
			return $the_date;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $the_date;
		}
		$revision = get_post( self::$current_context['revision_id'] );
		if ( ! $revision ) {
			return $the_date;
		}
		$time = get_post_time( 'U', true, $revision );
		if ( false === $time ) {
			$time = get_post_time( 'U', false, $revision );
		}
		if ( false === $time ) {
			return $the_date;
		}
		if ( '' === $format ) {
			$format = get_option( 'date_format' );
		}
		return wp_date( $format, $time );
	}

	/**
	 * Revision modified date.
	 *
	 * @param string|false $the_date Formatted date.
	 * @param string       $format   Format.
	 * @param WP_Post      $post     Post.
	 * @return string|false
	 */
	public static function filter_modified_date( $the_date, $format, $post ) {
		if ( null === self::$current_context || ! $post instanceof WP_Post ) {
			return $the_date;
		}
		if ( (int) $post->ID !== self::$current_context['parent_id'] ) {
			return $the_date;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $the_date;
		}
		$revision = get_post( self::$current_context['revision_id'] );
		if ( ! $revision ) {
			return $the_date;
		}
		$time = get_post_modified_time( 'U', true, $revision );
		if ( false === $time ) {
			$time = get_post_modified_time( 'U', false, $revision );
		}
		if ( false === $time ) {
			$time = get_post_time( 'U', true, $revision );
		}
		if ( false === $time ) {
			return $the_date;
		}
		if ( '' === $format ) {
			$format = get_option( 'date_format' );
		}
		return wp_date( $format, $time );
	}

	/**
	 * Revision author display name.
	 *
	 * @param string $display_name Display name.
	 * @return string
	 */
	public static function filter_author( $display_name ) {
		if ( null === self::$current_context ) {
			return $display_name;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $display_name;
		}
		$revision = get_post( self::$current_context['revision_id'] );
		if ( ! $revision ) {
			return $display_name;
		}
		$author = get_the_author_meta( 'display_name', (int) $revision->post_author );
		return '' !== $author ? $author : $display_name;
	}

	/**
	 * Newer-version notice.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function prepend_newer_notice( $content ) {
		if ( null === self::$current_context ) {
			return $content;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$parent   = get_post( self::$current_context['parent_id'] );
		$revision = get_post( self::$current_context['revision_id'] );
		if ( ! $parent || ! $revision ) {
			return $content;
		}
		$parent_ts   = strtotime( $parent->post_modified_gmt ? $parent->post_modified_gmt : $parent->post_modified );
		$revision_ts = strtotime( $revision->post_date_gmt ? $revision->post_date_gmt : $revision->post_date );
		if ( false === $parent_ts || false === $revision_ts || $parent_ts <= $revision_ts ) {
			return $content;
		}
		$message = sprintf(
			/* translators: %s: URL of the current post */
			__( 'A <a href="%s">newer version</a> of this content is available.', 'wp-feature-future-revisions' ),
			esc_url( get_permalink( $parent ) )
		);
		$notice = '<div class="wfr-public-revision-notice" role="status">' . wp_kses_post( $message ) . '</div>';
		return $notice . $content;
	}

	/**
	 * Notice styles.
	 *
	 * @return void
	 */
	public static function enqueue_notice_styles() {
		if ( null === self::$current_context ) {
			return;
		}
		$css = '.wfr-public-revision-notice{border:1px solid #ccc;background:#f6f7f7;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:0.875rem;line-height:1.5;}';
		wp_register_style( 'wfr-public-revision-notice', false, array(), WP_FUTURE_REVISIONS_VERSION );
		wp_enqueue_style( 'wfr-public-revision-notice' );
		wp_add_inline_style( 'wfr-public-revision-notice', $css );
	}

	/**
	 * Document title from the revision.
	 *
	 * @param array $title Title parts.
	 * @return array
	 */
	public static function filter_document_title( $title ) {
		if ( null === self::$current_context || ! is_array( $title ) ) {
			return $title;
		}
		$revision = get_post( self::$current_context['revision_id'] );
		if ( ! $revision ) {
			return $title;
		}
		$title['title'] = $revision->post_title;
		return $title;
	}
}
