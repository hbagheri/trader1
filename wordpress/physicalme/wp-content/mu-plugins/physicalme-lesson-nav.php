<?php
/**
 * Plugin Name: PhysicalMe — Lesson Navigation
 * Description: لینک «درس قبلی / درس بعدی» در پایان مقالات و ویدیوها بر اساس global_order
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Find the adjacent lesson within the same chapter, by global_order.
 *
 * @param int  $post_id
 * @param bool $next   true → next lesson, false → previous
 * @return WP_Post|null
 */
function physicalme_adjacent_lesson(int $post_id, bool $next): ?WP_Post {
    $current_go = (int)get_post_meta($post_id, 'global_order', true);
    if ($current_go === 0 && get_post_meta($post_id, 'global_order', true) === '') {
        return null; // post has no order — skip
    }

    $chapter_terms = wp_get_post_terms($post_id, 'chapter', ['fields' => 'ids']);
    if (empty($chapter_terms)) return null;

    $args = [
        'post_type'      => get_post_type($post_id),
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'post__not_in'   => [$post_id],
        'tax_query'      => [[
            'taxonomy' => 'chapter',
            'field'    => 'term_id',
            'terms'    => $chapter_terms,
        ]],
        'meta_query'     => [[
            'key'     => 'global_order',
            'value'   => $current_go,
            'compare' => $next ? '>' : '<',
            'type'    => 'NUMERIC',
        ]],
        'orderby'        => 'meta_value_num',
        'meta_key'       => 'global_order',
        'order'          => $next ? 'ASC' : 'DESC',
    ];

    $q = new WP_Query($args);
    return $q->have_posts() ? $q->posts[0] : null;
}

/**
 * Append nav block to the end of single article/video content.
 */
add_filter('the_content', function ($content) {
    if (!is_singular(['article', 'video']) || !is_main_query() || !in_the_loop()) return $content;

    $pid  = get_the_ID();
    $prev = physicalme_adjacent_lesson($pid, false);
    $next = physicalme_adjacent_lesson($pid, true);

    if (!$prev && !$next) return $content;

    $card = function (?WP_Post $p, string $side) {
        if (!$p) {
            // empty placeholder so the row stays balanced
            return '<div style="flex:1"></div>';
        }
        $section = get_post_meta($p->ID, 'section', true);
        $title   = get_the_title($p->ID);
        $url     = get_permalink($p->ID);
        $is_prev = ($side === 'prev');
        $label   = $is_prev ? '→ درس قبلی' : 'درس بعدی ←';
        $align   = $is_prev ? 'right' : 'left';
        $border  = $is_prev ? 'border-right' : 'border-left';
        return sprintf(
            '<a href="%s" style="flex:1;display:block;padding:18px 22px;background:#F8F6F0;%s:4px solid #9CAB52;border-radius:10px;text-decoration:none;color:#2A2E30;transition:all .2s">
                <div style="color:#5B6E32;font-size:13px;font-weight:600;text-align:%s;margin-bottom:6px">%s</div>
                <div style="font-size:16px;font-weight:700;text-align:%s;line-height:1.5;color:#3D4548">%s</div>
                %s
            </a>',
            esc_url($url),
            $border,
            $align,
            esc_html($label),
            $align,
            esc_html($title),
            $section ? '<div style="color:#888;font-size:12px;text-align:'.$align.';margin-top:4px">'.esc_html($section).'</div>' : ''
        );
    };

    $nav = '<nav style="display:flex;gap:16px;margin:32px 0 16px;flex-wrap:wrap" aria-label="پیمایش بین درس‌ها">'
         . $card($prev, 'prev')
         . $card($next, 'next')
         . '</nav>';

    return $content . $nav;
}, 15);  // before the CTA (which runs at 20)
