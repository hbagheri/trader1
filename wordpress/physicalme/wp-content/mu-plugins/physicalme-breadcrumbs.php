<?php
/**
 * Plugin Name: PhysicalMe — Breadcrumbs
 * Description: نمایش breadcrumbs Yoast بالای محتوای تک‌پست‌ها و آرشیوها
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Prepend breadcrumbs to single articles, videos, posts, and tax archives.
 */
add_filter('the_content', function ($content) {
    if (!function_exists('yoast_breadcrumb')) return $content;
    if (!is_singular(['article', 'video', 'post']) || !is_main_query() || !in_the_loop()) return $content;

    ob_start();
    yoast_breadcrumb(
        '<nav class="pm-breadcrumbs" style="background:#F8F6F0;border:1px solid #E0DCC8;border-radius:8px;padding:10px 16px;margin:0 0 24px;font-size:14px;color:#5B6E32" aria-label="مسیر صفحه">',
        '</nav>'
    );
    $crumbs = ob_get_clean();

    return $crumbs . $content;
}, 4);

/**
 * Also show on taxonomy archive pages (chapter, level, branch).
 * Hook into the Elementor archive template via a header injection.
 */
add_action('elementor/page_templates/header-footer/before_content', function () {
    if (!function_exists('yoast_breadcrumb')) return;
    if (is_tax(['chapter', 'level', 'branch'])) {
        echo '<div style="max-width:1140px;margin:16px auto 0;padding:0 20px">';
        yoast_breadcrumb(
            '<nav class="pm-breadcrumbs" style="background:#F8F6F0;border:1px solid #E0DCC8;border-radius:8px;padding:10px 16px;font-size:14px;color:#5B6E32" aria-label="مسیر صفحه">',
            '</nav></div>'
        );
    }
});

/**
 * Customize Yoast breadcrumb separator and home label
 */
add_filter('wpseo_breadcrumb_separator', fn() => ' <span style="opacity:.5;margin:0 6px">›</span> ');
add_filter('wpseo_breadcrumb_links', function ($links) {
    // Translate "Home" if Yoast missed it
    foreach ($links as &$link) {
        if (isset($link['text']) && in_array($link['text'], ['Home', 'home'], true)) {
            $link['text'] = 'خانه';
        }
    }
    return $links;
});
