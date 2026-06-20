<?php
/**
 * Plugin Name: PhysicalMe — Force Index Parent Chapter Terms
 * Description: Parent chapter terms (دهم/یازدهم/دوازدهم) are valuable archive pages
 *              listing all lessons of a grade. WP core's wp_robots default flags
 *              hierarchical parent terms as noindex; this plugin reverses that.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

function physicalme_is_parent_chapter_archive(): bool {
    if (!is_tax('chapter')) return false;
    $term = get_queried_object();
    return $term && empty($term->parent);  // parent term has parent=0
}

// 1) Strip 'noindex' from Yoast's robots array
add_filter('wpseo_robots_array', function ($robots) {
    if (physicalme_is_parent_chapter_archive() && isset($robots['index'])) {
        $robots['index'] = 'index';
    }
    return $robots;
}, 99);

// 2) Strip 'noindex' from Yoast's string-form robots
add_filter('wpseo_robots', function ($value) {
    if (physicalme_is_parent_chapter_archive()) {
        return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    }
    return $value;
}, 99);

// 3) Strip from WP core's wp_robots filter
add_filter('wp_robots', function ($robots) {
    if (physicalme_is_parent_chapter_archive()) {
        unset($robots['noindex']);
        $robots['index']  = true;
        $robots['follow'] = true;
    }
    return $robots;
}, 99);
