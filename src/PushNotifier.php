<?php declare(strict_types=1);
namespace Gratis\Notify;

final class PushNotifier
{
    private const OPTION = 'gratis_notify_subscribers';

    public static function registerAPI(): void
    {
        register_rest_route('gratis-notify/v1', '/subscribe', [
            'methods' => 'POST', 'permission_callback' => '__return_true',
            'callback' => function ($req) {
                $sub = $req->get_json_params();
                if (empty($sub['endpoint'])) return new \WP_REST_Response(['error' => 'No endpoint'], 400);
                $subs = get_option(self::OPTION, []);
                $subs[md5($sub['endpoint'])] = $sub;
                update_option(self::OPTION, $subs, false);
                return new \WP_REST_Response(['subscribed' => true]);
            },
        ]);
    }

    public static function onPublish(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'post') return;

        $subs = get_option(self::OPTION, []);
        if (empty($subs)) return;

        $payload = json_encode([
            'title' => $post->post_title,
            'body'  => wp_trim_words($post->post_content, 20),
            'url'   => get_permalink($postId),
        ]);

        // Web Push requires VAPID keys - store notification for polling fallback
        update_option('gratis_notify_latest', [
            'title' => $post->post_title,
            'body'  => wp_trim_words($post->post_content, 20),
            'url'   => get_permalink($postId),
            'time'  => time(),
        ]);
    }

    public static function serviceWorkerScript(): void
    {
        if (is_admin()) return;
        $endpoint = rest_url('gratis-notify/v1/subscribe');
        echo '<script>if("Notification"in window&&Notification.permission==="default"){setTimeout(function(){var b=document.createElement("div");b.innerHTML=\'<div style="position:fixed;bottom:20px;right:20px;background:#1a1e2e;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:16px;z-index:99999;color:#fff;font-size:14px;max-width:300px"><p style="margin:0 0 10px">🔔 Get notified of new posts?</p><button onclick="gratis_subscribe()" style="background:#22C55E;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px">Allow</button> <button onclick="this.parentElement.remove()" style="background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:13px">No thanks</button></div>\';document.body.appendChild(b)},5000)}function gratis_subscribe(){Notification.requestPermission().then(function(p){if(p==="granted")fetch("' . esc_url($endpoint) . '",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({endpoint:"poll://"+Date.now(),type:"poll"})})})}</script>' . "\n";
    }
}
