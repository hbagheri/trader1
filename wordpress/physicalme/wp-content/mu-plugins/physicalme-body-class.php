<?php
/**
 * Plugin Name: PhysicalMe — Body Classes
 * Description: اضافه‌کردن class «pm-wide-layout» به پست‌هایی که iframe ویجت بزرگ دارن
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_filter('body_class', function ($classes) {
    if (!is_singular(['article', 'video', 'post'])) return $classes;
    $post = get_post();
    if (!$post) return $classes;
    // any post that embeds the master-detail problem widget gets a wider layout
    if (preg_match('~/widgets/(hal-masael|problems-)[\w-]+\.html~', $post->post_content)) {
        $classes[] = 'pm-wide-layout';
    }
    return $classes;
});
