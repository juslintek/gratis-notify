<?php declare(strict_types=1);
namespace Gratis\Notify;

defined('ABSPATH') || exit;

/**
 * Self-hosted browser push notifications with a polling fallback.
 *
 * Stores subscriber endpoints in an option and records the latest published
 * post so the front-end beacon can surface a "new post" notification without a
 * third-party push service. Behaviour (enable + prompt delay) is configurable
 * via {@see Settings}.
 */
final class PushNotifier {

    private const OPTION = 'gratis_notify_subscribers';
    private const LATEST = 'gratis_notify_latest';

    /** Register the subscribe + latest REST routes. */
    public static function registerAPI(): void {
        register_rest_route('gratis-notify/v1', '/subscribe', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => [__CLASS__, 'handleSubscribe'],
        ]);

        register_rest_route('gratis-notify/v1', '/latest', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => [__CLASS__, 'handleLatest'],
        ]);
    }

    /** Persist a push subscription endpoint. */
    public static function handleSubscribe(\WP_REST_Request $req): \WP_REST_Response {
        $sub = $req->get_json_params();
        if (empty($sub['endpoint']) || !is_string($sub['endpoint'])) {
            return new \WP_REST_Response(['error' => 'No endpoint'], 400);
        }

        $endpoint = esc_url_raw($sub['endpoint']);
        $subs     = get_option(self::OPTION, []);
        if (!is_array($subs)) {
            $subs = [];
        }
        $subs[md5($endpoint)] = [
            'endpoint' => $endpoint,
            'type'     => isset($sub['type']) ? sanitize_key((string) $sub['type']) : 'poll',
            'added'    => time(),
        ];
        update_option(self::OPTION, $subs, false);

        return new \WP_REST_Response(['subscribed' => true]);
    }

    /** Return the latest notification payload for polling clients. */
    public static function handleLatest(): \WP_REST_Response {
        return new \WP_REST_Response(self::latest());
    }

    /** Latest stored notification, or an empty payload. */
    public static function latest(): array {
        $latest = get_option(self::LATEST, []);
        return is_array($latest) ? $latest : [];
    }

    /** Record the latest published post as a notification payload. */
    public static function onPublish(int $postId): void {
        if (!Settings::enabled()) {
            return;
        }

        $post = get_post($postId);
        if (!$post || 'post' !== $post->post_type) {
            return;
        }

        update_option(self::LATEST, [
            'title' => $post->post_title,
            'body'  => wp_trim_words($post->post_content, 20),
            'url'   => get_permalink($postId),
            'time'  => time(),
        ], false);
    }

    /** Print the opt-in prompt + subscribe beacon in the footer. */
    public static function serviceWorkerScript(): void {
        if (is_admin() || !Settings::enabled()) {
            return;
        }

        $endpoint = rest_url('gratis-notify/v1/subscribe');
        $delay    = absint(Settings::promptDelay()) * 1000;

        echo '<script>if("Notification"in window&&Notification.permission==="default"){setTimeout(function(){var b=document.createElement("div");b.innerHTML=\'<div style="position:fixed;bottom:20px;right:20px;background:#1a1e2e;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:16px;z-index:99999;color:#fff;font-size:14px;max-width:300px"><p style="margin:0 0 10px">🔔 ' . esc_js(Settings::promptText()) . '</p><button onclick="gratis_subscribe()" style="background:#22C55E;color:#0F172A;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px">Allow</button> <button onclick="this.parentElement.remove()" style="background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:13px">No thanks</button></div>\';document.body.appendChild(b)},' . absint($delay) . ')}function gratis_subscribe(){Notification.requestPermission().then(function(p){if(p==="granted")fetch("' . esc_url($endpoint) . '",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({endpoint:"poll://"+Date.now(),type:"poll"})})})}</script>' . "\n";
    }
}
