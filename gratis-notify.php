<?php
/**
 * Plugin Name: Gratis Notify
 * Plugin URI: https://github.com/juslintek/gratis-notify
 * Description: Self-hosted browser push notifications with a polling fallback. Replaces OneSignal/PushEngage ($9-29/mo).
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-notify
 */

defined('ABSPATH') || exit;

define('GRATIS_NOTIFY_VERSION', '1.0.0');
define('GRATIS_NOTIFY_FILE', __FILE__);
define('GRATIS_NOTIFY_DIR', plugin_dir_path(__FILE__));
define('GRATIS_NOTIFY_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('publish_post', [\Gratis\Notify\PushNotifier::class, 'onPublish']);
add_action('wp_footer', [\Gratis\Notify\PushNotifier::class, 'serviceWorkerScript']);
add_action('rest_api_init', [\Gratis\Notify\PushNotifier::class, 'registerAPI']);

// Admin settings page.
add_action('admin_menu', [\Gratis\Notify\Settings::class, 'register_menu']);
add_action('admin_init', [\Gratis\Notify\Settings::class, 'register_settings']);
