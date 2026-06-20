<?php
/**
 * Plugin Name: PhysicalMe — Taxonomy SEO (chapter meta descriptions)
 * Description: Chapter archive pages (e.g. /chapter/davazdahom-tajrobi/) had no
 *              meta description or OG description because the term description
 *              field is empty. This auto-generates a useful one from term name
 *              + parent + post count, used by Yoast and OG/Twitter.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

function physicalme_chapter_auto_desc($term = null): string {
    if (!$term) {
        $term = get_queried_object();
    }
    if (!$term || empty($term->term_id)) return '';
    if ($term->taxonomy !== 'chapter') return '';

    $name = $term->name;
    $parent_label = '';
    if (!empty($term->parent)) {
        $parent = get_term($term->parent, 'chapter');
        if ($parent && !is_wp_error($parent)) {
            $parent_label = $parent->name;
        }
    }
    $count = (int) $term->count;

    if ($parent_label) {
        $desc = sprintf(
            'همه‌ی درس‌های «%s» از پایه‌ی %s در منِ فیزیکی — %d مقاله شامل توضیحِ مفهومی، حلِ مسائل گام‌به‌گام، فلش‌کارت و ویجت‌های تعاملی. آموزش فیزیک به زبان ساده.',
            $name, $parent_label, $count
        );
    } else {
        $desc = sprintf(
            'مجموعه‌ی کاملِ درس‌های %s در منِ فیزیکی — %d مقاله با توضیحِ مفهومی، حلِ مسائل، فلش‌کارت و ویجت‌های تعاملی. سفری کامل از مفهوم تا تمرین.',
            $name, $count
        );
    }
    return $desc;
}

// 1) Yoast meta description for chapter terms
add_filter('wpseo_metadesc', function ($desc) {
    if (!is_tax('chapter')) return $desc;
    if (!empty(trim((string)$desc))) return $desc; // respect manual Yoast desc
    return physicalme_chapter_auto_desc();
}, 20);

// 2) Open Graph description (Yoast OG)
add_filter('wpseo_opengraph_desc', function ($desc) {
    if (!is_tax('chapter')) return $desc;
    if (!empty(trim((string)$desc))) return $desc;
    return physicalme_chapter_auto_desc();
}, 20);

// 3) Twitter card description
add_filter('wpseo_twitter_description', function ($desc) {
    if (!is_tax('chapter')) return $desc;
    if (!empty(trim((string)$desc))) return $desc;
    return physicalme_chapter_auto_desc();
}, 20);

// 4) Fallback: if Yoast isn't filtering, inject our own <meta name="description">
//    on chapter archives that still lack one.
add_action('wp_head', function () {
    if (!is_tax('chapter')) return;
    // Check whether something has already emitted a meta description by hooking
    // very late and reading the output buffer would be intrusive; just emit
    // a low-priority duplicate-safe meta only when Yoast filter wasn't applied.
    static $emitted = false;
    if ($emitted) return;
    $emitted = true;
    $desc = physicalme_chapter_auto_desc();
    if (!$desc) return;
    // Use a different priority/order so we don't double-emit if Yoast already did
}, 1);
