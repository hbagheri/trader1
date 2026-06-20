<?php
/**
 * Plugin Name: PhysicalMe — Schema (LearningResource)
 * Description: افزودن JSON-LD LearningResource و Article عمیق‌تر به پست‌های article / video
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Inject a LearningResource into Yoast's JSON-LD graph for single article/video posts.
 */
add_filter('wpseo_schema_graph', function ($graph, $context) {
    if (!is_singular(['article', 'video'])) return $graph;

    $post_id = get_the_ID();
    if (!$post_id) return $graph;

    $post = get_post($post_id);
    if (!$post) return $graph;

    // Pull related taxonomies (chapter, level)
    $chapter_terms = wp_get_post_terms($post_id, 'chapter', ['fields' => 'all']);
    $level_terms   = wp_get_post_terms($post_id, 'level',   ['fields' => 'names']);
    $branch_terms  = wp_get_post_terms($post_id, 'branch',  ['fields' => 'names']);
    $tags          = wp_get_post_terms($post_id, 'post_tag','fields' === 'names' ? ['fields' => 'names'] : ['fields' => 'names']);

    $educational_level = $level_terms[0] ?? 'دبیرستان';
    $section_meta      = get_post_meta($post_id, 'section', true);
    $chapter_meta      = get_post_meta($post_id, 'chapter', true);
    $reading_time      = get_post_meta($post_id, 'reading_time', true);

    $about = [];
    foreach ($chapter_terms as $t) {
        $about[] = [
            '@type' => 'Thing',
            'name'  => $t->name,
            'url'   => get_term_link($t),
        ];
    }
    foreach ($branch_terms as $name) {
        $about[] = ['@type' => 'Thing', 'name' => $name];
    }

    $teaches = $tags ? array_slice((array)$tags, 0, 6) : [];

    $is_video = (get_post_type($post_id) === 'video');

    $node = [
        '@type'            => $is_video ? ['VideoObject', 'LearningResource'] : ['Article', 'LearningResource'],
        '@id'              => get_permalink($post_id) . '#learning-resource',
        'name'             => get_the_title($post_id),
        'headline'         => get_the_title($post_id),
        'description'      => wp_strip_all_tags($post->post_excerpt) ?: wp_trim_words(wp_strip_all_tags($post->post_content), 30),
        'url'              => get_permalink($post_id),
        'inLanguage'       => 'fa-IR',
        'datePublished'    => get_the_date('c', $post_id),
        'dateModified'     => get_the_modified_date('c', $post_id),
        'author'  => [
            '@type' => 'Person',
            'name'  => 'حسن باقری',
            'url'   => home_url('/about/'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name'  => 'منِ فیزیکی',
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => home_url('/wp-content/uploads/2026/05/Logo-main.jpg'),
            ],
        ],
        'educationalLevel'     => $educational_level,
        'learningResourceType' => $is_video ? 'video lesson' : 'lesson article',
        'inDefinedTermSet'     => $chapter_meta ?: 'فیزیک دبیرستان',
        'about'                => $about,
        'isFamilyFriendly'     => true,
        'isAccessibleForFree'  => true,
    ];

    if (!empty($teaches)) {
        $node['teaches'] = array_map(fn($k) => ['@type' => 'DefinedTerm', 'name' => $k], $teaches);
    }
    if ($reading_time) {
        $node['timeRequired'] = $reading_time;
    }
    if ($section_meta) {
        $node['educationalUse'] = $section_meta;
    }

    $graph[] = $node;
    return $graph;
}, 11, 2);
