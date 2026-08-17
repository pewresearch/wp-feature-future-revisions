<?php
/**
 * Plugin Name: Future Revisions
 * Plugin URI: https://github.com/WordPress/wp-feature-future-revisions
 * Description: Public historical revisions and future-revision fork/merge for WordPress.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 0.1.0
 * Author: WordPress Contributors
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: wp-feature-future-revisions
 *
 * @package wp-feature-future-revisions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_FUTURE_REVISIONS_VERSION' ) ) {
	return;
}

define( 'WP_FUTURE_REVISIONS_VERSION', '0.1.0' );
define( 'WP_FUTURE_REVISIONS_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-wfr-public-revisions.php';
require_once __DIR__ . '/includes/class-wfr-future-revisions.php';
require_once __DIR__ . '/includes/class-wfr-rewrite.php';
require_once __DIR__ . '/includes/class-wfr-rest-api.php';
require_once __DIR__ . '/includes/class-wfr-admin.php';
require_once __DIR__ . '/hooks.php';

register_activation_hook( __FILE__, 'wfr_activate' );
register_deactivation_hook( __FILE__, 'wfr_deactivate' );
