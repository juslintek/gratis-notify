<?php
/**
 * Plugin Name: Gratis Notify
 * Description: Self-hosted push notifications + email digest. Replaces OneSignal/PushEngage ($9-29/mo).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';
add_action('publish_post', [\Gratis\Notify\PushNotifier::class, 'onPublish']);
add_action('wp_footer', [\Gratis\Notify\PushNotifier::class, 'serviceWorkerScript']);
add_action('rest_api_init', [\Gratis\Notify\PushNotifier::class, 'registerAPI']);
