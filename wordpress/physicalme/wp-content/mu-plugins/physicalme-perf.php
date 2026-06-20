<?php
/**
 * Plugin Name: PhysicalMe — Performance trims (LCP/CLS/TBT)
 * Description: Removes MathJax/heavy scripts on pages that don't need them
 *              (home, /chapter/*, /about/, etc.), defers low-priority CSS,
 *              and adds resource hints to lift Core Web Vitals.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * MathJax is ~1MB of JS+CSS. Only article pages have $math$, so dequeue it
 * everywhere else. We detect article post type via WP's conditional tags.
 */
function pm_perf_needs_mathjax(): bool {
    if (is_singular('article')) return true;
    // any single post with $math$ in content (catch-all)
    if (is_singular() && has_shortcode(get_post()->post_content ?? '', 'pm_articles_carousel') === false) {
        $body = get_post()->post_content ?? '';
        if (preg_match('/\$[^$\n]+\$|\\\\\(|\\\\\[/', $body)) return true;
    }
    return false;
}

add_action('wp_enqueue_scripts', function () {
    if (pm_perf_needs_mathjax()) return;

    // simple-mathjax registers the script as 'mathjax' (line 104 of the plugin).
    wp_dequeue_script('mathjax');
    wp_deregister_script('mathjax');

    // Drop simple-mathjax's window.MathJax config block (injected on wp_head)
    remove_action('wp_head', ['SimpleMathJax', 'configure_mathjax'], 1);
}, 100);

/**
 * Buffer the head and remove our own physicalme-mathjax config block from
 * pages that don't load MathJax — it's dead weight on the homepage.
 */
add_action('wp_head', function () {
    if (pm_perf_needs_mathjax()) return;
    ob_start(function ($html) {
        return preg_replace(
            '#<script id="physicalme-mathjax-config">.*?</script>#s',
            '', $html
        );
    });
}, 0);
add_action('wp_head', function () {
    if (pm_perf_needs_mathjax()) return;
    if (ob_get_level() > 0) ob_end_flush();
}, 999);

/**
 * NOTE: do NOT dequeue elementor-frontend / elementor-pro-frontend on the
 * front page — the site's header and footer ARE Elementor templates and
 * need those CSS/JS bundles to render. Only safe to drop the per-page
 * Elementor stylesheet for the home page itself, since the home content
 * is rendered by our shortcodes, not by Elementor's own widgets.
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_front_page()) return;
    wp_dequeue_style('elementor-post-6');
    wp_deregister_style('elementor-post-6');
}, 200);

/**
 * Defer all non-critical scripts (everything except jQuery itself and our
 * own pm-pwa-register which we keep inline). This pushes work past LCP.
 */
add_filter('script_loader_tag', function ($tag, $handle) {
    if (is_admin()) return $tag;
    // Keep jQuery synchronous for legacy plugin compat
    if (in_array($handle, ['jquery-core', 'jquery-migrate'], true)) return $tag;
    // Skip the SW registration script (inline) — it has no src
    if (strpos($tag, ' src=') === false) return $tag;
    // Add defer if not already present
    if (strpos($tag, ' defer') === false && strpos($tag, ' async') === false) {
        $tag = preg_replace('/<script /', '<script defer ', $tag, 1);
    }
    return $tag;
}, 10, 2);

/**
 * Resource hints for Cloudflare-served assets the page references.
 */
add_filter('wp_resource_hints', function ($hints, $relation_type) {
    if ($relation_type === 'preconnect') {
        $hints[] = ['href' => 'https://cdn.jsdelivr.net', 'crossorigin' => 'anonymous'];
    }
    return $hints;
}, 10, 2);

/**
 * Strip wp-emoji (saves an extra HTTP request + small JS bundle).
 */
remove_action('wp_head',         'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');
add_filter('emoji_svg_url', '__return_false');
