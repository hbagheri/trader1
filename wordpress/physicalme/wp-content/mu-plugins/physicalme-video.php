<?php
/**
 * Plugin Name: PhysicalMe — Video (YouTube) Support
 * Description: پشتیبانی از پست‌های ویدیو با لینک یوتیوب (embed خودکار، thumbnail، privacy)
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

const PM_VIDEO_META = 'youtube_id';
const PM_THUMB_DONE = '_pm_video_thumb_fetched';

/**
 * Extract a YouTube video ID from various URL formats.
 * Accepts:
 *   - raw 11-char ID
 *   - https://youtube.com/watch?v=XYZ
 *   - https://youtu.be/XYZ
 *   - https://www.youtube.com/embed/XYZ
 */
function pm_normalize_youtube_id(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    // bare ID
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $value)) return $value;
    // any URL containing the ID
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $value, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Build a responsive nocookie iframe for a YouTube ID.
 */
function pm_youtube_embed_html(string $vid, string $title = ''): string {
    $title = $title ?: 'ویدیو';
    return sprintf(
        '<div class="pm-video-wrap" style="position:relative;padding-bottom:56.25%%;height:0;overflow:hidden;border-radius:12px;background:#000;margin:24px 0">
            <iframe src="https://www.youtube-nocookie.com/embed/%s?rel=0&modestbranding=1"
                    title="%s"
                    style="position:absolute;top:0;left:0;width:100%%;height:100%%;border:0;border-radius:12px"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    loading="lazy"
                    allowfullscreen></iframe>
        </div>',
        esc_attr($vid),
        esc_attr($title)
    );
}

/**
 * On single video posts, prepend the YouTube embed to the post content.
 */
add_filter('the_content', function ($content) {
    if (!is_singular('video') || !is_main_query() || !in_the_loop()) return $content;
    $vid = get_post_meta(get_the_ID(), PM_VIDEO_META, true);
    $vid = pm_normalize_youtube_id((string)$vid);
    if (!$vid) return $content;
    return pm_youtube_embed_html($vid, get_the_title()) . $content;
}, 5);

/**
 * When a video post is saved with a youtube_id, fetch the YouTube thumbnail
 * (maxresdefault.jpg) once and set it as the featured image.
 */
add_action('save_post_video', function ($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (has_post_thumbnail($post_id))                                   return;
    if (get_post_meta($post_id, PM_THUMB_DONE, true))                   return;

    $vid = get_post_meta($post_id, PM_VIDEO_META, true);
    $vid = pm_normalize_youtube_id((string)$vid);
    if (!$vid) return;

    // try the high-res first; fall back to standard if missing
    $candidates = [
        "https://i.ytimg.com/vi/{$vid}/maxresdefault.jpg",
        "https://i.ytimg.com/vi/{$vid}/hqdefault.jpg",
    ];

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    foreach ($candidates as $url) {
        $tmp = download_url($url, 15);
        if (is_wp_error($tmp)) continue;
        $file = [
            'name'     => "youtube-{$vid}.jpg",
            'tmp_name' => $tmp,
        ];
        $attach_id = media_handle_sideload($file, $post_id, get_the_title($post_id));
        if (is_wp_error($attach_id)) {
            @unlink($tmp);
            continue;
        }
        set_post_thumbnail($post_id, $attach_id);
        update_post_meta($post_id, PM_THUMB_DONE, 1);
        break;
    }
}, 20, 3);

/**
 * Simple meta box so it's easy to set the YouTube ID from the admin UI too.
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'pm_video_meta',
        '🎬 ویدیو یوتیوب',
        function ($post) {
            $val = get_post_meta($post->ID, PM_VIDEO_META, true);
            wp_nonce_field('pm_video_meta', 'pm_video_meta_nonce');
            echo '<p><label for="pm_youtube_id"><strong>شناسه‌ی ویدیو یا لینک کامل یوتیوب:</strong></label></p>';
            echo '<input type="text" id="pm_youtube_id" name="pm_youtube_id" value="' . esc_attr($val) . '" style="width:100%;padding:8px" placeholder="مثلاً dQw4w9WgXcQ یا https://youtu.be/dQw4w9WgXcQ">';
            echo '<p style="color:#666;margin-top:8px">پس از ذخیره، embed خودکار بالای محتوا اضافه می‌شه و thumbnail هم به‌صورت featured image دانلود می‌شه.</p>';
        },
        'video',
        'side',
        'high'
    );
});

add_action('save_post_video', function ($post_id) {
    if (!isset($_POST['pm_video_meta_nonce']) || !wp_verify_nonce($_POST['pm_video_meta_nonce'], 'pm_video_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['pm_youtube_id'])) {
        update_post_meta($post_id, PM_VIDEO_META, sanitize_text_field(wp_unslash($_POST['pm_youtube_id'])));
    }
}, 10, 1);
