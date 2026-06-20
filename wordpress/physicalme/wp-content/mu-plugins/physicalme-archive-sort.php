<?php
/**
 * Plugin Name: PhysicalMe — Archive Sort
 * Description: ترتیب بندها در آرشیو فصل بر اساس lesson_order (صعودی) به‌جای تاریخ
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Sort a WP_Query in BOOK ORDER:
 *   global_order ASC  (chapter_order × 100 + lesson_order)
 *   posts without global_order slide to the end, then by date ASC.
 */
function physicalme_sort_book_order(WP_Query $query): void {
    $query->set('meta_query', [
        'relation' => 'OR',
        'has_order' => [
            'key'     => 'global_order',
            'compare' => 'EXISTS',
            'type'    => 'NUMERIC',
        ],
        'no_order' => [
            'key'     => 'global_order',
            'compare' => 'NOT EXISTS',
        ],
    ]);
    $query->set('orderby', [
        'has_order' => 'ASC',
        'date'      => 'ASC',
    ]);
}

// 1) Taxonomy archive pages (main query): chapter / level / branch.
//    For chapter archives, also include posts in child chapters
//    (so /chapter/dahom-ram/ lists everything across فصل ۱، ۲، ...).
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query()) return;

    if ($query->is_tax(['chapter', 'level', 'branch'])) {
        physicalme_sort_book_order($query);

        // include child terms when viewing a parent chapter archive
        if ($query->is_tax('chapter')) {
            $term = $query->get_queried_object();
            if ($term && empty($term->parent)) {
                // parent term — pull in its children
                $children = get_term_children($term->term_id, 'chapter');
                if (!empty($children)) {
                    $query->set('tax_query', [[
                        'taxonomy' => 'chapter',
                        'field'    => 'term_id',
                        'terms'    => array_merge([$term->term_id], $children),
                    ]]);
                }
            }
        }
    }
});

// 2) Elementor Posts widgets on Home (query_id = home_articles / home_videos)
foreach (['home_articles', 'home_videos'] as $qid) {
    add_action("elementor/query/{$qid}", 'physicalme_sort_book_order');
}
