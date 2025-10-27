<?php
/**
 * Plugin Name: YouTube Latest Video Player
 * Plugin URI: https://github.com/your-username/youtube-latest-video-player
 * Description: Allows users to enter their YouTube channel streams URL and displays a video player that dynamically loads the latest video.
 * Version: 1.3.1
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('YLVP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YLVP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('YLVP_VERSION', '1.3.1');

class YouTubeLatestVideoPlayer {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('youtube_latest_video', array($this, 'shortcode_handler'));

        // AJAX handlers for checking video status
        add_action('wp_ajax_ylvp_check_video_status', array($this, 'ajax_check_video_status'));
        add_action('wp_ajax_nopriv_ylvp_check_video_status', array($this, 'ajax_check_video_status'));

        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        // Plugin initialization
    }

    public function activate() {
        // Set default options
        add_option('ylvp_youtube_channel_url', '');
        add_option('ylvp_api_key', '');
        add_option('ylvp_cache_duration', 300); // 5 minutes default
        add_option('ylvp_show_upcoming', 1); // Show upcoming videos
        add_option('ylvp_countdown_enabled', 1); // Enable countdown
    }

    public function deactivate() {
        // Clean up if needed
        wp_clear_scheduled_hook('ylvp_clear_cache');
    }

    public function add_admin_menu() {
        add_options_page(
            'YouTube Latest Video Player Settings',
            'YouTube Video Player',
            'manage_options',
            'youtube-latest-video-player',
            array($this, 'admin_page')
        );
    }

    public function admin_init() {
        register_setting('ylvp_settings', 'ylvp_youtube_channel_url', array($this, 'sanitize_channel_url'));
        register_setting('ylvp_settings', 'ylvp_api_key', array($this, 'sanitize_api_key'));
        register_setting('ylvp_settings', 'ylvp_cache_duration');
        register_setting('ylvp_settings', 'ylvp_show_upcoming');
        register_setting('ylvp_settings', 'ylvp_countdown_enabled');

        add_settings_section(
            'ylvp_main_section',
            'YouTube Channel Settings',
            array($this, 'section_callback'),
            'ylvp_settings'
        );

        add_settings_field(
            'ylvp_youtube_channel_url',
            'YouTube Channel Streams URL',
            array($this, 'channel_url_callback'),
            'ylvp_settings',
            'ylvp_main_section'
        );

        add_settings_field(
            'ylvp_api_key',
            'YouTube Data API Key',
            array($this, 'api_key_callback'),
            'ylvp_settings',
            'ylvp_main_section'
        );

        add_settings_field(
            'ylvp_cache_duration',
            'Cache Duration (seconds)',
            array($this, 'cache_duration_callback'),
            'ylvp_settings',
            'ylvp_main_section'
        );

        add_settings_field(
            'ylvp_show_upcoming',
            'Show Upcoming Videos',
            array($this, 'show_upcoming_callback'),
            'ylvp_settings',
            'ylvp_main_section'
        );

        add_settings_field(
            'ylvp_countdown_enabled',
            'Enable Countdown Timer',
            array($this, 'countdown_enabled_callback'),
            'ylvp_settings',
            'ylvp_main_section'
        );
    }

    public function section_callback() {
        echo '<p>Configure your YouTube channel settings below. You\'ll need a YouTube Data API key to fetch the latest videos.</p>';
        echo '<p><strong>How to get a YouTube Data API key:</strong></p>';
        echo '<ol>';
        echo '<li>Go to the <a href="https://console.developers.google.com/" target="_blank">Google Developers Console</a></li>';
        echo '<li>Create a new project or select an existing one</li>';
        echo '<li>Enable the YouTube Data API v3</li>';
        echo '<li>Create credentials (API key)</li>';
        echo '<li>Copy the API key and paste it below</li>';
        echo '</ol>';
    }

    public function channel_url_callback() {
        $value = get_option('ylvp_youtube_channel_url', '');
        echo '<input type="url" name="ylvp_youtube_channel_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://www.youtube.com/@channelname/streams" />';
        echo '<p class="description">Enter your YouTube channel streams URL (e.g., https://www.youtube.com/@templechurchpca2042/streams)</p>';
    }

    public function api_key_callback() {
        $value = get_option('ylvp_api_key', '');
        echo '<input type="text" name="ylvp_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your YouTube Data API v3 key</p>';
    }

    public function cache_duration_callback() {
        $value = get_option('ylvp_cache_duration', 300);
        echo '<input type="number" name="ylvp_cache_duration" value="' . esc_attr($value) . '" min="60" max="3600" />';
        echo '<p class="description">How long to cache the latest video data (in seconds). Default: 300 (5 minutes)</p>';
    }

    public function show_upcoming_callback() {
        $value = get_option('ylvp_show_upcoming', 1);
        echo '<input type="checkbox" name="ylvp_show_upcoming" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Check to show upcoming scheduled videos/live streams</p>';
    }

    public function countdown_enabled_callback() {
        $value = get_option('ylvp_countdown_enabled', 1);
        echo '<input type="checkbox" name="ylvp_countdown_enabled" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Enable countdown timer for upcoming videos</p>';
    }

    public function sanitize_channel_url($value) {
        // Clear channel ID cache when URL is changed
        $old_value = get_option('ylvp_youtube_channel_url');
        if ($old_value !== $value) {
            $this->clear_all_caches();
        }
        return sanitize_url($value);
    }

    public function sanitize_api_key($value) {
        // Clear all caches when API key is changed
        $old_value = get_option('ylvp_api_key');
        if ($old_value !== $value) {
            $this->clear_all_caches();
        }
        return sanitize_text_field($value);
    }

    private function clear_all_caches() {
        // Clear video data caches
        delete_transient('ylvp_video_data_upcoming');
        delete_transient('ylvp_video_data_latest');
        delete_transient('ylvp_video_data_upcoming_lock');
        delete_transient('ylvp_video_data_latest_lock');

        // Clear all channel ID caches (they use md5 hash)
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ylvp_channel_id_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ylvp_channel_id_%'");
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>YouTube Latest Video Player Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ylvp_settings');
                do_settings_sections('ylvp_settings');
                submit_button();
                ?>
            </form>

            <div class="ylvp-usage-info">
                <h3>How to Use</h3>
                <p>After configuring the settings above, you can display your latest YouTube video using the shortcode:</p>
                <code>[youtube_latest_video]</code>

                <h4>Shortcode Parameters:</h4>
                <ul>
                    <li><code>width</code> - Video player width (default: 560)</li>
                    <li><code>height</code> - Video player height (default: 315)</li>
                    <li><code>autoplay</code> - Auto-play video (default: 0)</li>
                </ul>

                <p><strong>Example:</strong> <code>[youtube_latest_video width="800" height="450" autoplay="1"]</code></p>
            </div>

            <div class="ylvp-quota-info">
                <h3>API Quota Usage (Last 7 Days)</h3>
                <?php
                $quota_usage = get_option('ylvp_quota_usage', array());
                if (empty($quota_usage)) {
                    echo '<p>No API calls tracked yet.</p>';
                } else {
                    echo '<table class="widefat">';
                    echo '<thead><tr><th>Date</th><th>Quota Units Used</th></tr></thead>';
                    echo '<tbody>';
                    krsort($quota_usage); // Most recent first
                    $total = 0;
                    foreach ($quota_usage as $date => $usage) {
                        echo '<tr><td>' . esc_html($date) . '</td><td>' . esc_html(number_format($usage)) . '</td></tr>';
                        $total += $usage;
                    }
                    echo '<tr style="font-weight: bold;"><td>Total</td><td>' . esc_html(number_format($total)) . '</td></tr>';
                    echo '</tbody></table>';
                    echo '<p class="description" style="margin-top: 10px;">YouTube Data API v3 has a default quota of 10,000 units per day. Search requests cost 100 units each.</p>';
                }
                ?>
            </div>
        </div>

        <style>
        .ylvp-usage-info, .ylvp-quota-info {
            background: #f1f1f1;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #0073aa;
        }
        .ylvp-usage-info code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .ylvp-quota-info {
            border-left-color: #d63638;
        }
        .ylvp-quota-info table {
            margin-top: 10px;
            background: #fff;
        }
        </style>
        <?php
    }

    public function enqueue_scripts() {
        wp_enqueue_style('ylvp-style', YLVP_PLUGIN_URL . 'assets/style.css', array(), time()); // Force timestamp cache bust
        wp_enqueue_script('ylvp-script', YLVP_PLUGIN_URL . 'assets/script.js', array('jquery'), YLVP_VERSION, true);

        // Localize script for AJAX
        wp_localize_script('ylvp-script', 'ylvp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ylvp_check_video_status')
        ));

        // Add isolation CSS to prevent conflicts
        wp_add_inline_style('ylvp-style', '
            .ylvp-isolated-container iframe[src*="youtube.com"] {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                border: none !important;
                z-index: 1001 !important;
            }
            .ylvp-isolated-container {
                position: relative !important;
                z-index: 1000 !important;
                isolation: isolate !important;
            }
        ');
    }

    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'width' => 560,
            'height' => 315,
            'autoplay' => 0,
            'show_upcoming' => get_option('ylvp_show_upcoming', 1),
            'debug' => 0,
            'refresh' => 0
        ), $atts);

        // Force cache refresh if requested
        if ($atts['refresh'] == 1) {
            delete_transient('ylvp_video_data_upcoming');
            delete_transient('ylvp_video_data_latest');
            delete_transient('ylvp_video_data_upcoming_lock');
            delete_transient('ylvp_video_data_latest_lock');
        }

        $video_data = $this->get_video_data($atts['show_upcoming']);
        $debug_info = $this->get_debug_info();

        if (!$video_data) {
            $error_message = 'Unable to load video. Please check your settings.';

            // Show debug info if debug mode is enabled
            if ($atts['debug'] == 1 && current_user_can('manage_options')) {
                $error_message .= '<div class="ylvp-debug-info" style="background: #f1f1f1; padding: 10px; margin-top: 10px; font-size: 12px; font-family: monospace; max-height: 400px; overflow-y: auto;">';
                $error_message .= '<strong>Debug Information:</strong><br>';
                foreach ($debug_info as $key => $value) {
                    if ($key === 'API Debug Messages') {
                        // Show detailed API messages
                        $debug_messages = get_option('ylvp_debug_messages', array());
                        $error_message .= '<strong>' . esc_html($key) . ':</strong><br>';
                        foreach ($debug_messages as $message) {
                            $error_message .= '• ' . esc_html($message) . '<br>';
                        }
                    } else {
                        $error_message .= esc_html($key) . ': ' . esc_html($value) . '<br>';
                    }
                }
                $error_message .= '</div>';
            }

            return '<div class="ylvp-error">' . $error_message . '</div>';
        }

        $video_id = $video_data['video_id'];
        $title = $video_data['title'];
        $is_upcoming = $video_data['is_upcoming'];
        $is_live = isset($video_data['is_live']) ? $video_data['is_live'] : false;
        $scheduled_start = $video_data['scheduled_start_time'];
        $autoplay = $atts['autoplay'] ? '&autoplay=1' : '';

        if ($is_upcoming && !$is_live && get_option('ylvp_countdown_enabled', 1)) {
            // Show countdown for upcoming videos
            $output = '<div class="ylvp-container ylvp-upcoming"';
            if ($scheduled_start) {
                $output .= ' data-scheduled-start="' . esc_attr($scheduled_start) . '"';
            }
            $output .= '>';
            $output .= '<div class="ylvp-countdown-wrapper">';
            $output .= '<div class="ylvp-upcoming-info">';
            $output .= '<h3 class="ylvp-upcoming-title">Upcoming Video</h3>';
            $output .= '<h4 class="ylvp-video-title">' . esc_html($title) . '</h4>';
            $output .= '</div>';
            $output .= '<div class="ylvp-countdown" data-target="' . esc_attr($scheduled_start) . '">';
            $output .= '<div class="ylvp-countdown-display">';
            $output .= '<div class="ylvp-time-unit"><span class="ylvp-days">00</span><label>Days</label></div>';
            $output .= '<div class="ylvp-time-unit"><span class="ylvp-hours">00</span><label>Hours</label></div>';
            $output .= '<div class="ylvp-time-unit"><span class="ylvp-minutes">00</span><label>Minutes</label></div>';
            $output .= '<div class="ylvp-time-unit"><span class="ylvp-seconds">00</span><label>Seconds</label></div>';
            $output .= '</div>';
            $output .= '<div class="ylvp-countdown-message">Until stream starts</div>';
            $output .= '</div>';
            $output .= '<div class="ylvp-video-placeholder">';
            $output .= '<img src="' . esc_url($video_data['thumbnail']) . '" alt="' . esc_attr($title) . '" class="ylvp-thumbnail" />';
            $output .= '<div class="ylvp-play-overlay">⏰</div>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
        } else {
            // Clean video player output
            $output = '<div class="ylvp-container"';
            // Add data attributes for status monitoring
            if ($is_live) {
                $output .= ' data-is-live="true" data-video-id="' . esc_attr($video_id) . '"';
            }
            $output .= '>';
            $output .= '<div style="position: relative; width: 100%; height: 0; padding-bottom: 56.25%; overflow: hidden;">';
            $output .= '<iframe ';
            $output .= 'src="https://www.youtube.com/embed/' . esc_attr($video_id) . '?rel=0' . $autoplay . '" ';
            $output .= 'title="' . esc_attr($title) . '" ';
            $output .= 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" ';
            $output .= 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" ';
            $output .= 'allowfullscreen></iframe>';
            $output .= '</div>';
            $output .= '<div class="ylvp-video-info">';
            $output .= '<h3 class="ylvp-video-title">' . esc_html($title) . '</h3>';
            if ($is_live) {
                $output .= '<div class="ylvp-live-indicator">🔴 LIVE</div>';
            }
            $output .= '</div>';
            $output .= '</div>';
        }

        // Add debug info even when successful if debug mode is enabled
        if ($atts['debug'] == 1 && current_user_can('manage_options')) {
            $output .= '<div class="ylvp-debug-info" style="background: #e8f4f8; padding: 10px; margin-top: 10px; font-size: 12px; font-family: monospace; max-height: 400px; overflow-y: auto; border: 1px solid #bee5eb;">';
            $output .= '<strong>Success Debug Information:</strong><br>';
            $output .= 'Video ID: ' . esc_html($video_id) . '<br>';
            $output .= 'Title: ' . esc_html($title) . '<br>';
            $output .= 'Is Upcoming: ' . ($is_upcoming ? 'Yes' : 'No') . '<br>';
            $output .= 'Is Live: ' . ($is_live ? 'Yes' : 'No') . '<br>';
            $output .= 'Scheduled Start: ' . esc_html($scheduled_start ?? 'None') . '<br>';
            $output .= 'Current Time: ' . esc_html(date('Y-m-d H:i:s')) . '<br>';

            foreach ($debug_info as $key => $value) {
                if ($key === 'API Debug Messages') {
                    $debug_messages = get_option('ylvp_debug_messages', array());
                    $output .= '<strong>' . esc_html($key) . ':</strong><br>';
                    foreach ($debug_messages as $message) {
                        $output .= '• ' . esc_html($message) . '<br>';
                    }
                } else {
                    $output .= esc_html($key) . ': ' . esc_html($value) . '<br>';
                }
            }
            $output .= '</div>';
        }

        return $output;
    }

    private function get_video_data($show_upcoming = true) {
        // Check cache first, but use shorter cache for live events
        $cache_key = $show_upcoming ? 'ylvp_video_data_upcoming' : 'ylvp_video_data_latest';
        $lock_key = $cache_key . '_lock';
        $cached_video = get_transient($cache_key);

        // If we have cached data, check if it's a live stream or upcoming event
        if ($cached_video !== false) {
            // For live streams or upcoming events, use shorter cache
            if (isset($cached_video['is_live']) && $cached_video['is_live']) {
                // Live streams - cache for only 30 seconds
                $cache_time = get_option('ylvp_live_cache_time', time());
                if (time() - $cache_time > 30) {
                    delete_transient($cache_key);
                    $cached_video = false;
                }
            } elseif (isset($cached_video['is_upcoming']) && $cached_video['is_upcoming']) {
                // Upcoming events - cache for only 60 seconds when close to start time
                $scheduled_start = strtotime($cached_video['scheduled_start_time']);
                $time_until_start = $scheduled_start - time();
                if ($time_until_start < 300) { // Within 5 minutes of start
                    $cache_time = get_option('ylvp_upcoming_cache_time', time());
                    if (time() - $cache_time > 60) {
                        delete_transient($cache_key);
                        $cached_video = false;
                    }
                }
            }

            if ($cached_video !== false) {
                return $cached_video;
            }
        }

        // RACE CONDITION PROTECTION: Check if another process is already fetching
        // If locked, wait briefly and return cached data (even if stale)
        if (get_transient($lock_key)) {
            // Another request is already fetching, wait a moment
            usleep(500000); // Wait 500ms

            // Check cache again - the other process may have completed
            $cached_video = get_transient($cache_key);
            if ($cached_video !== false) {
                return $cached_video;
            }

            // If still no cache, return false rather than making duplicate API call
            return false;
        }

        // Set lock to prevent other processes from making concurrent API calls
        // Lock expires after 10 seconds (in case this process crashes)
        set_transient($lock_key, true, 10);

        $channel_url = get_option('ylvp_youtube_channel_url');
        $api_key = get_option('ylvp_api_key');

        if (empty($channel_url) || empty($api_key)) {
            delete_transient($lock_key); // Release lock
            return false;
        }

        // Extract channel ID from URL
        $channel_id = $this->extract_channel_id($channel_url);

        if (!$channel_id) {
            delete_transient($lock_key); // Release lock
            return false;
        }

        $video_data = false;

        if ($show_upcoming) {
            // First try to get upcoming or live video
            $video_data = $this->fetch_upcoming_video_from_api($channel_id, $api_key);

            // If we found an upcoming video, check if it should have started by now
            if ($video_data && isset($video_data['is_upcoming']) && $video_data['is_upcoming'] && isset($video_data['scheduled_start_time'])) {
                $scheduled_start = strtotime($video_data['scheduled_start_time']);
                $current_time = time();

                // If the scheduled time has passed by more than 5 minutes, clear cache and try again
                if ($current_time > ($scheduled_start + 300)) {
                    delete_transient('ylvp_video_data_upcoming');
                    delete_transient('ylvp_video_data_latest');

                    // Try again - this time it should find the live or completed video
                    $video_data = $this->fetch_upcoming_video_from_api($channel_id, $api_key);
                }
            }
        }

        // If no upcoming video found or not requested, get latest published video
        if (!$video_data) {
            $video_data = $this->fetch_latest_video_from_api($channel_id, $api_key);
        }

        if ($video_data) {
            // Cache the result with appropriate duration
            $cache_duration = get_option('ylvp_cache_duration', 300);

            // Longer cache to preserve quota
            if (isset($video_data['is_live']) && $video_data['is_live']) {
                $cache_duration = 120; // 2 minutes for live streams (was 30)
                update_option('ylvp_live_cache_time', time());
            } elseif (isset($video_data['is_upcoming']) && $video_data['is_upcoming']) {
                $scheduled_start = strtotime($video_data['scheduled_start_time']);
                $time_until_start = $scheduled_start - time();
                if ($time_until_start < 300) { // Within 5 minutes of start
                    $cache_duration = 180; // 3 minutes when close to start (was 60)
                    update_option('ylvp_upcoming_cache_time', time());
                } else {
                    $cache_duration = 900; // 15 minutes for distant upcoming events
                }
            } else {
                $cache_duration = 1800; // 30 minutes for regular videos
            }

            set_transient($cache_key, $video_data, $cache_duration);
        }

        // Release lock now that we've completed the API call and caching
        delete_transient($lock_key);

        return $video_data;
    }

    private function extract_channel_id($channel_url) {
        // Handle different YouTube URL formats
        if (preg_match('/@([^\/]+)/', $channel_url, $matches)) {
            $channel_handle = $matches[1];
            return $this->get_channel_id_from_handle($channel_handle);
        }

        if (preg_match('/channel\/([a-zA-Z0-9_-]+)/', $channel_url, $matches)) {
            return $matches[1];
        }

        return false;
    }

    private function get_channel_id_from_handle($handle) {
        // Check cache first - channel IDs don't change
        $cache_key = 'ylvp_channel_id_' . md5($handle);
        $cached_channel_id = get_transient($cache_key);

        if ($cached_channel_id !== false) {
            return $cached_channel_id;
        }

        $api_key = get_option('ylvp_api_key');
        $debug_messages = array();

        // Try the new forHandle parameter first (for @handle format)
        $api_url = "https://www.googleapis.com/youtube/v3/channels?part=id&forHandle=" . urlencode($handle) . "&key=" . urlencode($api_key);
        $debug_messages[] = "Trying forHandle: " . $api_url;

        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            $debug_messages[] = "forHandle error: " . $response->get_error_message();
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $debug_messages[] = "forHandle response: " . substr($body, 0, 200);

            if (isset($data['items'][0]['id'])) {
                $channel_id = $data['items'][0]['id'];
                // Cache for 7 days - will be cleared if settings change
                set_transient($cache_key, $channel_id, 7 * DAY_IN_SECONDS);
                $this->store_debug_messages($debug_messages);
                return $channel_id;
            }
        }

        // Fallback to forUsername for older format
        $api_url = "https://www.googleapis.com/youtube/v3/channels?part=id&forUsername=" . urlencode($handle) . "&key=" . urlencode($api_key);
        $debug_messages[] = "Trying forUsername: " . $api_url;

        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            $debug_messages[] = "forUsername error: " . $response->get_error_message();
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $debug_messages[] = "forUsername response: " . substr($body, 0, 200);

            if (isset($data['items'][0]['id'])) {
                $channel_id = $data['items'][0]['id'];
                // Cache for 7 days - will be cleared if settings change
                set_transient($cache_key, $channel_id, 7 * DAY_IN_SECONDS);
                $this->store_debug_messages($debug_messages);
                return $channel_id;
            }
        }

        // Final fallback - search for the channel
        $api_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=channel&q=" . urlencode($handle) . "&key=" . urlencode($api_key);
        $debug_messages[] = "Trying search: " . $api_url;

        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            $debug_messages[] = "Search error: " . $response->get_error_message();
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $debug_messages[] = "Search response: " . substr($body, 0, 200);

            if (isset($data['items'][0]['snippet']['channelId'])) {
                $channel_id = $data['items'][0]['snippet']['channelId'];
                // Cache for 7 days - will be cleared if settings change
                set_transient($cache_key, $channel_id, 7 * DAY_IN_SECONDS);
                $this->store_debug_messages($debug_messages);
                return $channel_id;
            }
        }

        $this->store_debug_messages($debug_messages);
        return false;
    }

    private function store_debug_messages($messages) {
        update_option('ylvp_debug_messages', $messages);
    }

    private function track_api_call($cost = 100) {
        // Track API calls (default cost is 100 quota units per search call)
        $today = date('Y-m-d');
        $quota_usage = get_option('ylvp_quota_usage', array());

        if (!isset($quota_usage[$today])) {
            $quota_usage[$today] = 0;
        }

        $quota_usage[$today] += $cost;

        // Keep only last 7 days of data
        $cutoff_date = date('Y-m-d', strtotime('-7 days'));
        foreach ($quota_usage as $date => $usage) {
            if ($date < $cutoff_date) {
                unset($quota_usage[$date]);
            }
        }

        update_option('ylvp_quota_usage', $quota_usage);
    }

    private function check_rate_limit() {
        // Rate limit: max 10 API calls per minute
        $rate_limit_key = 'ylvp_rate_limit_' . floor(time() / 60);
        $call_count = get_transient($rate_limit_key);

        if ($call_count === false) {
            set_transient($rate_limit_key, 1, 60);
            return true;
        }

        if ($call_count >= 10) {
            return false; // Rate limit exceeded
        }

        set_transient($rate_limit_key, $call_count + 1, 60);
        return true;
    }

    private function fetch_upcoming_video_from_api($channel_id, $api_key) {
        // Check rate limit before making API call
        if (!$this->check_rate_limit()) {
            // Return cached data if available, even if expired
            $cache_key = 'ylvp_video_data_upcoming';
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
            return false;
        }

        // First check for currently live streams
        $api_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . urlencode($channel_id) . "&eventType=live&type=video&order=date&maxResults=1&key=" . urlencode($api_key);

        $this->track_api_call(100); // Search calls cost 100 units
        $response = wp_remote_get($api_url);

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['items'][0])) {
                $video = $data['items'][0];
                $video_id = $video['id']['videoId'];

                return array(
                    'video_id' => $video_id,
                    'title' => $video['snippet']['title'],
                    'description' => $video['snippet']['description'],
                    'published_at' => $video['snippet']['publishedAt'],
                    'thumbnail' => $video['snippet']['thumbnails']['high']['url'] ?? $video['snippet']['thumbnails']['default']['url'],
                    'is_upcoming' => false,
                    'is_live' => true,
                    'scheduled_start_time' => null
                );
            }
        }

        // If no live stream, check for upcoming streams
        $api_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . urlencode($channel_id) . "&eventType=upcoming&type=video&order=date&maxResults=1&key=" . urlencode($api_key);

        $this->track_api_call(100); // Search calls cost 100 units
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['items'][0])) {
            $video = $data['items'][0];
            $video_id = $video['id']['videoId'];

            // Get detailed video information including scheduled start time
            $video_details = $this->get_video_details($video_id, $api_key);

            if ($video_details && isset($video_details['liveStreamingDetails']['scheduledStartTime'])) {
                return array(
                    'video_id' => $video_id,
                    'title' => $video['snippet']['title'],
                    'description' => $video['snippet']['description'],
                    'published_at' => $video['snippet']['publishedAt'],
                    'thumbnail' => $video['snippet']['thumbnails']['high']['url'] ?? $video['snippet']['thumbnails']['default']['url'],
                    'is_upcoming' => true,
                    'is_live' => false,
                    'scheduled_start_time' => $video_details['liveStreamingDetails']['scheduledStartTime']
                );
            }
        }

        return false;
    }

    private function get_video_details($video_id, $api_key) {
        $api_url = "https://www.googleapis.com/youtube/v3/videos?part=liveStreamingDetails,snippet,contentDetails&id=" . urlencode($video_id) . "&key=" . urlencode($api_key);

        $this->track_api_call(1); // Videos.list calls cost 1 unit
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return isset($data['items'][0]) ? $data['items'][0] : false;
    }

    private function fetch_latest_video_from_api($channel_id, $api_key) {
        // Check rate limit
        if (!$this->check_rate_limit()) {
            $cache_key = 'ylvp_video_data_latest';
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
            return false;
        }

        // First check for recently completed live streams
        $api_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . urlencode($channel_id) . "&eventType=completed&type=video&order=date&maxResults=1&key=" . urlencode($api_key);

        $this->track_api_call(100); // Search calls cost 100 units
        $response = wp_remote_get($api_url);

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['items'][0])) {
                $video = $data['items'][0];
                return array(
                    'video_id' => $video['id']['videoId'],
                    'title' => $video['snippet']['title'],
                    'description' => $video['snippet']['description'],
                    'published_at' => $video['snippet']['publishedAt'],
                    'thumbnail' => $video['snippet']['thumbnails']['high']['url'] ?? $video['snippet']['thumbnails']['default']['url'],
                    'is_upcoming' => false,
                    'is_live' => false,
                    'scheduled_start_time' => null
                );
            }
        }

        // Fallback to regular latest video search (reduced to 2 videos to save quota)
        $api_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . urlencode($channel_id) . "&order=date&type=video&maxResults=2&key=" . urlencode($api_key);

        $this->track_api_call(100); // Search calls cost 100 units
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['items']) && !empty($data['items'])) {
            // Get the most recent video that's not a short (check max 2 videos)
            foreach ($data['items'] as $video) {
                // Get video details to check duration (skip shorts)
                $video_details = $this->get_video_details($video['id']['videoId'], $api_key);
                if ($video_details && isset($video_details['contentDetails']['duration'])) {
                    // Parse duration (PT format like PT1H2M30S)
                    $duration = $video_details['contentDetails']['duration'];
                    preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);
                    $hours = isset($matches[1]) ? (int)$matches[1] : 0;
                    $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
                    $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
                    $total_seconds = $hours * 3600 + $minutes * 60 + $seconds;

                    // Skip videos shorter than 1 minute (likely shorts)
                    if ($total_seconds >= 60) {
                        return array(
                            'video_id' => $video['id']['videoId'],
                            'title' => $video['snippet']['title'],
                            'description' => $video['snippet']['description'],
                            'published_at' => $video['snippet']['publishedAt'],
                            'thumbnail' => $video['snippet']['thumbnails']['high']['url'] ?? $video['snippet']['thumbnails']['default']['url'],
                            'is_upcoming' => false,
                            'is_live' => false,
                            'scheduled_start_time' => null
                        );
                    }
                }
            }

            // If no long videos found, return the first one anyway
            $video = $data['items'][0];
            return array(
                'video_id' => $video['id']['videoId'],
                'title' => $video['snippet']['title'],
                'description' => $video['snippet']['description'],
                'published_at' => $video['snippet']['publishedAt'],
                'thumbnail' => $video['snippet']['thumbnails']['high']['url'] ?? $video['snippet']['thumbnails']['default']['url'],
                'is_upcoming' => false,
                'is_live' => false,
                'scheduled_start_time' => null
            );
        }

        return false;
    }

    private function get_debug_info() {
        $channel_url = get_option('ylvp_youtube_channel_url');
        $api_key = get_option('ylvp_api_key');

        $debug_info = array(
            'Channel URL' => !empty($channel_url) ? $channel_url : 'NOT SET',
            'API Key' => !empty($api_key) ? substr($api_key, 0, 10) . '...' : 'NOT SET',
            'Cache Duration' => get_option('ylvp_cache_duration', 300) . ' seconds',
            'Show Upcoming' => get_option('ylvp_show_upcoming', 1) ? 'Yes' : 'No',
            'Countdown Enabled' => get_option('ylvp_countdown_enabled', 1) ? 'Yes' : 'No'
        );

        if (!empty($channel_url)) {
            $channel_id = $this->extract_channel_id($channel_url);
            $debug_info['Extracted Channel ID'] = $channel_id ? $channel_id : 'FAILED TO EXTRACT';

            // Show API debug messages
            $debug_messages = get_option('ylvp_debug_messages', array());
            if (!empty($debug_messages)) {
                $debug_info['API Debug Messages'] = implode(' | ', array_slice($debug_messages, -3)); // Show last 3 messages
            }

            // Clear cache for debugging
            if (!$channel_id) {
                delete_transient('ylvp_video_data_upcoming');
                delete_transient('ylvp_video_data_latest');
                delete_transient('ylvp_video_data_upcoming_lock');
                delete_transient('ylvp_video_data_latest_lock');
                $debug_info['Cache Status'] = 'CLEARED DUE TO EXTRACTION FAILURE';
            }
        }

        return $debug_info;
    }

    /**
     * AJAX handler to check video status and return HTML
     */
    public function ajax_check_video_status() {
        // Verify nonce for security
        check_ajax_referer('ylvp_check_video_status', 'nonce');

        // Get current video ID if provided (to detect changes)
        $current_video_id = isset($_POST['current_video_id']) ? sanitize_text_field($_POST['current_video_id']) : '';

        // Only clear cache if explicitly requested or if we're close to an upcoming event
        $force_refresh = isset($_POST['force_refresh']) ? (bool)$_POST['force_refresh'] : false;

        if ($force_refresh) {
            delete_transient('ylvp_video_data_upcoming');
            delete_transient('ylvp_video_data_latest');
            delete_transient('ylvp_video_data_upcoming_lock');
            delete_transient('ylvp_video_data_latest_lock');
        }

        // Get video data (will use cache if available and valid)
        $video_data = $this->get_video_data(true);

        if (!$video_data) {
            wp_send_json_error(array(
                'message' => 'Unable to fetch video data'
            ));
            return;
        }

        $video_id = $video_data['video_id'];
        $title = $video_data['title'];
        $is_upcoming = $video_data['is_upcoming'];
        $is_live = isset($video_data['is_live']) ? $video_data['is_live'] : false;

        // Detect if video has changed (live stream ended, new video available)
        $video_changed = !empty($current_video_id) && $current_video_id !== $video_id;

        // If we have an upcoming video, return countdown
        if ($is_upcoming && !$is_live) {
            $scheduled_start = $video_data['scheduled_start_time'];

            // Generate countdown HTML
            $html = '<div class="ylvp-countdown-wrapper">';
            $html .= '<div class="ylvp-upcoming-info">';
            $html .= '<h3 class="ylvp-upcoming-title">Upcoming Video</h3>';
            $html .= '<h4 class="ylvp-video-title">' . esc_html($title) . '</h4>';
            $html .= '</div>';
            $html .= '<div class="ylvp-countdown" data-target="' . esc_attr($scheduled_start) . '">';
            $html .= '<div class="ylvp-countdown-display">';
            $html .= '<div class="ylvp-time-unit"><span class="ylvp-days">00</span><label>Days</label></div>';
            $html .= '<div class="ylvp-time-unit"><span class="ylvp-hours">00</span><label>Hours</label></div>';
            $html .= '<div class="ylvp-time-unit"><span class="ylvp-minutes">00</span><label>Minutes</label></div>';
            $html .= '<div class="ylvp-time-unit"><span class="ylvp-seconds">00</span><label>Seconds</label></div>';
            $html .= '</div>';
            $html .= '<div class="ylvp-countdown-message">Until stream starts</div>';
            $html .= '</div>';
            $html .= '<div class="ylvp-video-placeholder">';
            $html .= '<img src="' . esc_url($video_data['thumbnail']) . '" alt="' . esc_attr($title) . '" class="ylvp-thumbnail" />';
            $html .= '<div class="ylvp-play-overlay">⏰</div>';
            $html .= '</div>';
            $html .= '</div>';

            wp_send_json_success(array(
                'status' => 'upcoming',
                'video_id' => $video_id,
                'is_upcoming' => true,
                'scheduled_start' => $scheduled_start,
                'html' => $html,
                'video_changed' => $video_changed
            ));
        } else {
            // Live or completed video - generate video player HTML
            $html = '<div style="position: relative; width: 100%; height: 0; padding-bottom: 56.25%; overflow: hidden;">';
            $html .= '<iframe ';
            $html .= 'src="https://www.youtube.com/embed/' . esc_attr($video_id) . '?rel=0' . ($video_changed ? '' : '&autoplay=1') . '" ';
            $html .= 'title="' . esc_attr($title) . '" ';
            $html .= 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" ';
            $html .= 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" ';
            $html .= 'allowfullscreen></iframe>';
            $html .= '</div>';
            $html .= '<div class="ylvp-video-info">';
            $html .= '<h3 class="ylvp-video-title">' . esc_html($title) . '</h3>';
            if ($is_live) {
                $html .= '<div class="ylvp-live-indicator">🔴 LIVE</div>';
            }
            $html .= '</div>';

            wp_send_json_success(array(
                'status' => $is_live ? 'live' : 'completed',
                'video_id' => $video_id,
                'is_live' => $is_live,
                'is_upcoming' => false,
                'html' => $html,
                'title' => $title,
                'video_changed' => $video_changed
            ));
        }
    }
}

// Initialize the plugin
new YouTubeLatestVideoPlayer();