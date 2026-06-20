<?php
/**
 * Configure Yoast SEO defaults for physicsme.ir.
 * Run via: wp eval-file /var/www/html/tools/seo-setup.php
 */

// ─── Title & meta templates ──────────────────────────────────────────────
$titles = get_option('wpseo_titles', []);

// Post types
$titles['title-article']     = '%%title%% — منِ فیزیکی';
$titles['metadesc-article']  = '%%excerpt%%';
$titles['title-video']       = '%%title%% — ویدیوهای منِ فیزیکی';
$titles['metadesc-video']    = '%%excerpt%%';
$titles['title-post']        = '%%title%% — وبلاگ منِ فیزیکی';
$titles['metadesc-post']     = '%%excerpt%%';
$titles['title-page']        = '%%title%% — منِ فیزیکی';

// Taxonomies (archives)
$titles['title-tax-chapter']  = '%%term_title%% — درس‌های فیزیک — منِ فیزیکی';
$titles['title-tax-level']    = 'مقطع %%term_title%% — منِ فیزیکی';
$titles['title-tax-branch']   = 'شاخه‌ی %%term_title%% — منِ فیزیکی';
$titles['title-tax-post_tag'] = 'برچسب: %%term_title%% — منِ فیزیکی';

$titles['metadesc-tax-chapter'] = '%%term_description%%';
$titles['metadesc-tax-level']   = '%%term_description%%';
$titles['metadesc-tax-branch']  = '%%term_description%%';

// Index settings: index articles, videos, posts. noindex tags & search.
$titles['noindex-article']   = false;
$titles['noindex-video']     = false;
$titles['noindex-post']      = false;
$titles['noindex-page']      = false;
$titles['noindex-tax-chapter']  = false;
$titles['noindex-tax-level']    = false;
$titles['noindex-tax-branch']   = false;
$titles['noindex-tax-post_tag'] = true;
$titles['noindex-author-wpseo']  = true;
$titles['noindex-author-noposts-wpseo'] = true;
$titles['disable-author']    = true;     // disable author archives entirely
$titles['disable-attachment'] = true;    // attachment pages redirected to media file

// Breadcrumbs
$titles['breadcrumbs-enable']    = true;
$titles['breadcrumbs-sep']       = '«';
$titles['breadcrumbs-home']      = 'خانه';
$titles['breadcrumbs-prefix']    = '';

update_option('wpseo_titles', $titles);
echo "✓ Yoast title/meta templates set\n";

// ─── Social / Knowledge graph ────────────────────────────────────────────
$wpseo = get_option('wpseo', []);
$wpseo['company_or_person']       = 'person';
$wpseo['company_or_person_user_id'] = 1;          // admin user id
$wpseo['website_name']            = 'منِ فیزیکی';
$wpseo['alternate_website_name']  = 'PhysicalMe';
$wpseo['site_type']               = 'otherEducationalOrganization';
$wpseo['enable_xml_sitemap']      = true;
$wpseo['enable_text_link_counter']= true;
update_option('wpseo', $wpseo);
echo "✓ Yoast general/schema set\n";

// ─── Social images ───────────────────────────────────────────────────────
$social = get_option('wpseo_social', []);
$social['og_default_image']     = 'https://physicsme.ir/wp-content/uploads/2026/05/Logo-main.jpg';
$social['og_default_image_id']  = 8;
$social['twitter_card_type']    = 'summary_large_image';
$social['opengraph']            = true;
$social['twitter']              = true;
update_option('wpseo_social', $social);
echo "✓ Yoast social/OG set\n";

// ─── Person profile (admin user used for schema author) ──────────────────
update_user_meta(1, 'description', 'حسن باقری، معلم فیزیک — آموزش فیزیک به زبان ساده و با اشتیاق.');
update_user_meta(1, 'first_name',  'حسن');
update_user_meta(1, 'last_name',   'باقری');
update_user_meta(1, 'nickname',    'حسن باقری');
wp_update_user(['ID' => 1, 'display_name' => 'حسن باقری']);
echo "✓ admin user profile set for schema authorship\n";

// ─── Backfill existing articles: meta description + focus keyword ────────
$query = new WP_Query([
    'post_type'      => ['article', 'video', 'post'],
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);
foreach ($query->posts as $post_id) {
    $post = get_post($post_id);
    $excerpt = $post->post_excerpt;
    if (!$excerpt) {
        $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 30);
    }
    if (!get_post_meta($post_id, '_yoast_wpseo_metadesc', true)) {
        update_post_meta($post_id, '_yoast_wpseo_metadesc', mb_substr($excerpt, 0, 156));
    }
    // focus kw from first tag
    $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
    if ($tags && !get_post_meta($post_id, '_yoast_wpseo_focuskw', true)) {
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $tags[0]);
    }
    printf("  · post #%d  meta+focuskw set\n", $post_id);
}
echo "✓ existing posts backfilled\n";

// flush rewrite to pick up sitemap routes
flush_rewrite_rules(false);
echo "\nALL DONE\n";
