<?php
/**
 * Plugin Name: PhysicalMe — Content Types
 * Description: Custom Post Types (مقاله، ویدیو) و Taxonomies (مقطع، شاخه) برای سایت آموزش فیزیک
 * Version: 1.0
 * Author: hassan
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {

    // ───────── CPT: مقالات ─────────
    register_post_type('article', [
        'labels' => [
            'name'               => 'مقالات',
            'singular_name'      => 'مقاله',
            'menu_name'          => 'مقالات',
            'add_new'            => 'افزودن مقاله',
            'add_new_item'       => 'افزودن مقاله جدید',
            'edit_item'          => 'ویرایش مقاله',
            'new_item'           => 'مقاله جدید',
            'view_item'          => 'مشاهده مقاله',
            'search_items'       => 'جستجوی مقالات',
            'not_found'          => 'مقاله‌ای یافت نشد',
            'not_found_in_trash' => 'مقاله‌ای در زباله‌دان نیست',
        ],
        'public'        => true,
        'has_archive'   => true,
        'rewrite'       => ['slug' => 'articles', 'with_front' => false],
        'menu_icon'     => 'dashicons-media-document',
        'menu_position' => 5,
        'show_in_rest'  => true,
        'supports'      => ['title', 'editor', 'thumbnail', 'excerpt', 'comments', 'revisions', 'author', 'custom-fields'],
        'taxonomies'    => ['level', 'branch', 'post_tag'],
    ]);

    // ───────── CPT: ویدیوها ─────────
    register_post_type('video', [
        'labels' => [
            'name'               => 'ویدیوها',
            'singular_name'      => 'ویدیو',
            'menu_name'          => 'ویدیوها',
            'add_new'            => 'افزودن ویدیو',
            'add_new_item'       => 'افزودن ویدیوی جدید',
            'edit_item'          => 'ویرایش ویدیو',
            'new_item'           => 'ویدیوی جدید',
            'view_item'          => 'مشاهده ویدیو',
            'search_items'       => 'جستجوی ویدیوها',
            'not_found'          => 'ویدیویی یافت نشد',
            'not_found_in_trash' => 'ویدیویی در زباله‌دان نیست',
        ],
        'public'        => true,
        'has_archive'   => true,
        'rewrite'       => ['slug' => 'videos', 'with_front' => false],
        'menu_icon'     => 'dashicons-video-alt2',
        'menu_position' => 6,
        'show_in_rest'  => true,
        'supports'      => ['title', 'editor', 'thumbnail', 'excerpt', 'comments', 'revisions', 'author', 'custom-fields'],
        'taxonomies'    => ['level', 'branch', 'post_tag'],
    ]);

    // ───────── Taxonomy: مقطع تحصیلی ─────────
    register_taxonomy('level', ['post', 'article', 'video'], [
        'labels' => [
            'name'              => 'مقاطع',
            'singular_name'     => 'مقطع',
            'menu_name'         => 'مقاطع',
            'all_items'         => 'همه مقاطع',
            'edit_item'         => 'ویرایش مقطع',
            'add_new_item'      => 'افزودن مقطع',
            'new_item_name'     => 'نام مقطع جدید',
            'search_items'      => 'جستجوی مقاطع',
            'parent_item'       => 'مقطع والد',
            'parent_item_colon' => 'مقطع والد:',
        ],
        'hierarchical'      => true,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'level', 'with_front' => false],
    ]);

    // ───────── Taxonomy: فصل (سلسله‌مراتبی: مقطع > فصل) ─────────
    register_taxonomy('chapter', ['post', 'article', 'video'], [
        'labels' => [
            'name'              => 'فصل‌ها',
            'singular_name'     => 'فصل',
            'menu_name'         => 'فصل‌ها',
            'all_items'         => 'همه فصل‌ها',
            'edit_item'         => 'ویرایش فصل',
            'add_new_item'      => 'افزودن فصل',
            'new_item_name'     => 'نام فصل جدید',
            'search_items'      => 'جستجوی فصل‌ها',
            'parent_item'       => 'مقطع/فصل والد',
            'parent_item_colon' => 'مقطع/فصل والد:',
        ],
        'hierarchical'      => true,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'chapter', 'with_front' => false, 'hierarchical' => true],
    ]);

    // ───────── Taxonomy: شاخه فیزیک ─────────
    register_taxonomy('branch', ['post', 'article', 'video'], [
        'labels' => [
            'name'              => 'شاخه‌ها',
            'singular_name'     => 'شاخه',
            'menu_name'         => 'شاخه‌های فیزیک',
            'all_items'         => 'همه شاخه‌ها',
            'edit_item'         => 'ویرایش شاخه',
            'add_new_item'      => 'افزودن شاخه',
            'new_item_name'     => 'نام شاخه جدید',
            'search_items'      => 'جستجوی شاخه‌ها',
            'parent_item'       => 'شاخه والد',
            'parent_item_colon' => 'شاخه والد:',
        ],
        'hierarchical'      => true,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'branch', 'with_front' => false],
    ]);

}, 0);

// ───────── auto-register CPTs/taxonomies with Polylang for translation ─────────
add_filter('pll_get_post_types', function ($types) {
    $types['article'] = 'article';
    $types['video']   = 'video';
    return $types;
});

add_filter('pll_get_taxonomies', function ($taxes) {
    $taxes['level']   = 'level';
    $taxes['branch']  = 'branch';
    $taxes['chapter'] = 'chapter';
    return $taxes;
});
