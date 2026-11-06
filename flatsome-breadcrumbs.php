<?php
/**
 * Plugin Name: Flatsome Breadcrumbs
 * Plugin URI: https://github.com/bli-synlig-plugins/flatsome-breadcrumbs
 * Description: UX Builder breadcrumbs with a responsive ellipsis menu. Builds the trail for pages, posts, archives, and WooCommerce.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Benjamin Pedersen
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flatsome-breadcrumbs
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FBC_VERSION', '1.0.0');
define('FBC_FILE', __FILE__);
define('FBC_DIR', plugin_dir_path(__FILE__));
define('FBC_URL', plugin_dir_url(__FILE__));

require_once FBC_DIR . 'includes/class-fbc-plugin.php';
require_once FBC_DIR . 'includes/class-fbc-trail.php';
require_once FBC_DIR . 'includes/class-fbc-element.php';

add_action('plugins_loaded', array('FBC_Plugin', 'init'));
