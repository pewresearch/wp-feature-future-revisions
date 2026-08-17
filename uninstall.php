<?php
/**
 * Uninstall Future Revisions.
 *
 * Meta on posts and revisions is left in place so content is not destroyed
 * when the plugin is removed.
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
