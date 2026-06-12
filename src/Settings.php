<?php declare(strict_types=1);

namespace Gratis\Notify;

defined('ABSPATH') || exit;

/**
 * Admin settings for Gratis Notify.
 *
 * Toggles the opt-in prompt, configures its delay + copy, and shows the current
 * subscriber count plus the latest recorded notification.
 */
final class Settings {

    private const GROUP = 'gratis_notify_settings_group';
    private const PAGE  = 'gratis-notify';

    private const OPT_ENABLED = 'gratis_notify_enabled';
    private const OPT_DELAY   = 'gratis_notify_prompt_delay';
    private const OPT_PROMPT  = 'gratis_notify_prompt_text';

    /** Register the settings submenu page. */
    public static function register_menu(): void {
        add_options_page(
            __('Gratis Notify', 'gratis-notify'),
            __('Gratis Notify', 'gratis-notify'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /** Register options with typed sanitisers. */
    public static function register_settings(): void {
        register_setting(self::GROUP, self::OPT_ENABLED, [
            'type'              => 'boolean',
            'sanitize_callback' => [__CLASS__, 'sanitize_bool'],
            'default'           => true,
        ]);
        register_setting(self::GROUP, self::OPT_DELAY, [
            'type'              => 'integer',
            'sanitize_callback' => [__CLASS__, 'sanitize_delay'],
            'default'           => 5,
        ]);
        register_setting(self::GROUP, self::OPT_PROMPT, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Get notified of new posts?',
        ]);
    }

    /** Coerce a checkbox to a boolean. */
    public static function sanitize_bool($value): bool {
        return (bool) $value;
    }

    /** Clamp the prompt delay to a sensible range (seconds). */
    public static function sanitize_delay($value): int {
        $value = absint($value);
        if ($value < 1) {
            $value = 1;
        }
        if ($value > 120) {
            $value = 120;
        }
        return $value;
    }

    /** Whether push prompts + publish notifications are active. */
    public static function enabled(): bool {
        return (bool) get_option(self::OPT_ENABLED, true);
    }

    /** Opt-in prompt delay in seconds. */
    public static function promptDelay(): int {
        return self::sanitize_delay(get_option(self::OPT_DELAY, 5));
    }

    /** Opt-in prompt copy. */
    public static function promptText(): string {
        return (string) get_option(self::OPT_PROMPT, 'Get notified of new posts?');
    }

    /** Render the admin screen. */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-notify'));
        }

        $subs   = get_option(self::OPTION_SUBSCRIBERS, []);
        $count  = is_array($subs) ? count($subs) : 0;
        $latest = PushNotifier::latest();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gratis Notify', 'gratis-notify') . '</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Enable notifications', 'gratis-notify') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_ENABLED) . '" value="1"' . checked(self::enabled(), true, false) . '> ' . esc_html__('Show the opt-in prompt and notify on new posts', 'gratis-notify') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gn_delay">' . esc_html__('Prompt delay (seconds)', 'gratis-notify') . '</label></th><td>';
        echo '<input type="number" min="1" max="120" id="gn_delay" class="small-text" name="' . esc_attr(self::OPT_DELAY) . '" value="' . esc_attr((string) self::promptDelay()) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gn_prompt">' . esc_html__('Prompt text', 'gratis-notify') . '</label></th><td>';
        echo '<input type="text" id="gn_prompt" class="large-text" name="' . esc_attr(self::OPT_PROMPT) . '" value="' . esc_attr(self::promptText()) . '">';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save settings', 'gratis-notify'));
        echo '</form>';

        echo '<h2>' . esc_html__('Status', 'gratis-notify') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('Subscribers', 'gratis-notify') . '</th><td>' . absint($count) . '</td></tr>';
        if (!empty($latest['title'])) {
            echo '<tr><th scope="row">' . esc_html__('Latest notification', 'gratis-notify') . '</th><td>' . esc_html((string) $latest['title']) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '</div>';
    }

    /** Option name for stored subscribers (mirrors PushNotifier). */
    private const OPTION_SUBSCRIBERS = 'gratis_notify_subscribers';
}
