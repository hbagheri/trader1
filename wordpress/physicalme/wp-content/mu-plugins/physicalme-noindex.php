<?php
/**
 * Plugin Name: PhysicalMe — Conditional Noindex
 * Description: noindex برای پست‌های فصل ۲ تا ۵ (تا وقتی محتوای کامل سؤال/مسأله اضافه بشه)
 * Version: 1.0
 *
 * Strategy: hook into Yoast's robots filter directly so it doesn't depend on
 * indexable rebuild / cache state.
 */

if (!defined('ABSPATH')) exit;

/** Chapters whose posts should currently be noindexed (incomplete content). */
const PM_NOINDEX_CHAPTERS = [];   // all chapters now have full content + problems + flashcards

/** Specific post slugs to always noindex (demos, drafts, etc.) */
const PM_NOINDEX_SLUGS = ['test-problem-demo'];

function physicalme_should_noindex($post_id): bool {
    $slug = get_post_field('post_name', $post_id);
    if (in_array($slug, PM_NOINDEX_SLUGS, true)) return true;

    $terms = wp_get_post_terms($post_id, 'chapter', ['fields' => 'slugs']);
    if (!is_array($terms)) return false;
    foreach ($terms as $t) {
        if (in_array($t, PM_NOINDEX_CHAPTERS, true)) return true;
    }
    return false;
}

/* 1) Force Yoast to declare noindex in its <meta name="robots"> tag */
add_filter('wpseo_robots_array', function ($robots) {
    if (!is_singular(['article', 'video', 'post'])) return $robots;
    if (physicalme_should_noindex(get_the_ID())) {
        $robots['index']  = 'noindex';
        $robots['follow'] = 'follow';   // links still followed
    }
    return $robots;
});

/* 2) Backup: Yoast still emits <meta name=robots> via this string filter sometimes */
add_filter('wpseo_robots', function ($value) {
    if (!is_singular(['article', 'video', 'post'])) return $value;
    if (physicalme_should_noindex(get_the_ID())) {
        return 'noindex, follow';
    }
    return $value;
});

/* 3) Remove these URLs from Yoast's XML sitemap entirely */
add_filter('wpseo_exclude_from_sitemap_by_post_ids', function ($excluded) {
    $q = new WP_Query([
        'post_type'      => ['article', 'video', 'post'],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => 'chapter',
            'field'    => 'slug',
            'terms'    => PM_NOINDEX_CHAPTERS,
        ]],
    ]);
    $excluded = array_merge($excluded, $q->posts);

    // also exclude specific slugs
    foreach (PM_NOINDEX_SLUGS as $slug) {
        $p = get_page_by_path($slug, OBJECT, ['article', 'video', 'post']);
        if ($p) $excluded[] = $p->ID;
    }
    return $excluded;
});

/* 4) Belt-and-suspenders: also use WP's core wp_robots filter so search engines see noindex
       even if Yoast is bypassed in some context. */
add_filter('wp_robots', function ($robots) {
    if (!is_singular(['article', 'video', 'post'])) return $robots;
    if (physicalme_should_noindex(get_the_ID())) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
});

/* 5) Last-resort: print our own robots meta directly in <head> at high priority,
      so even if Yoast/theme suppress it, the tag is in the HTML. */
add_action('wp_head', function () {
    if (!is_singular(['article', 'video', 'post'])) return;
    if (physicalme_should_noindex(get_the_ID())) {
        echo "\n" . '<meta name="robots" content="noindex, follow"><!-- physicalme-noindex -->' . "\n";
    }
}, 1);
