<?php
/**
 * Editor assets.
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block editor enqueue.
 */
class WFR_Admin {

	const HANDLE = 'wp-feature-future-revisions';

	/**
	 * Enqueue editor script when either feature is supported.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets() {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		$post_type = $screen->post_type;
		$public    = wfr_post_type_supports_public_revisions( $post_type );
		$future    = wfr_post_type_supports_future_revisions( $post_type );
		if ( ! $public && ! $future ) {
			return;
		}

		$asset_path = plugin_dir_path( WP_FUTURE_REVISIONS_MAIN_FILE ) . 'build/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = include $asset_path;
		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'build/index.js', WP_FUTURE_REVISIONS_MAIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.wpFutureRevisions = ' . wp_json_encode(
				array(
					'restNamespace' => WFR_REST_NAMESPACE,
					'supports'      => array(
						'publicRevisions' => $public,
						'futureRevisions' => $future,
					),
				)
			) . ';',
			'before'
		);
	}
}
