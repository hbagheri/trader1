<?php
/**
 * Plugin Name: PhysicalMe — Featured Image Placeholder
 * Description: تولید خودکار featured image به‌صورت SVG برای پست‌هایی که عکس ندارن (مبتنی بر chapter + lesson + title)
 * Version: 1.0
 *
 * Strategy: generate an SVG file under /wp-content/uploads/auto-thumbs/<post_id>.svg
 * the first time a post needs a thumbnail, then create an attachment record and
 * set it as the post's _thumbnail_id. After that nothing happens until the post
 * is updated and the SVG is regenerated.
 */

if (!defined('ABSPATH')) exit;

const PM_AUTO_THUMB_DIR = 'auto-thumbs';
const PM_AUTO_THUMB_MARK = '_pm_auto_thumb';   // post meta marker (attachment id)

/** palette per chapter (term slug) */
function pm_palette_for_chapter(string $slug): array {
    // (top color, bottom color, accent)
    $palettes = [
        'fasl-1' => ['#3D4548', '#5B6E32', '#D4A847'],
        'fasl-2' => ['#1F3A5F', '#2BAEC4', '#D4A847'],
        'fasl-3' => ['#7A2D2D', '#C46B2B', '#F0CE6B'],
        'fasl-4' => ['#1F4D2E', '#9CAB52', '#FBE3A6'],
        'fasl-5' => ['#3B1F4D', '#7A4DAA', '#E0B6F0'],
    ];
    return $palettes[$slug] ?? ['#3D4548', '#5B6E32', '#D4A847'];
}

/**
 * Wrap text at ~28 characters/line, max 3 lines. Returns array of lines.
 */
function pm_wrap_title(string $title, int $max_chars = 28, int $max_lines = 3): array {
    $words = preg_split('/\s+/u', trim($title));
    $lines = [];
    $cur   = '';
    foreach ($words as $w) {
        $test = $cur === '' ? $w : "$cur $w";
        if (mb_strlen($test) > $max_chars && $cur !== '') {
            $lines[] = $cur;
            $cur = $w;
            if (count($lines) >= $max_lines - 1) break;
        } else {
            $cur = $test;
        }
    }
    if ($cur !== '' && count($lines) < $max_lines) $lines[] = $cur;
    if (count($lines) === $max_lines && count($words) > 0) {
        // truncate last line with ellipsis if more words remain
        $last = end($lines);
        $remaining = array_slice($words, array_search($last, $words) + 1);
        if (!empty($remaining)) $lines[count($lines)-1] = mb_substr($last, 0, $max_chars-1) . '…';
    }
    return $lines;
}

/**
 * Build the SVG markup for a post.
 */
function pm_build_thumb_svg(int $post_id): string {
    $title    = get_the_title($post_id);
    $chapters = wp_get_post_terms($post_id, 'chapter', ['fields' => 'all']);
    // pick the child chapter (the one with a numeric parent), fall back to first
    $chap_term = null;
    foreach ($chapters as $t) { if ($t->parent) { $chap_term = $t; break; } }
    if (!$chap_term && !empty($chapters)) $chap_term = $chapters[0];
    $chap_slug  = $chap_term ? $chap_term->slug : 'fasl-1';
    $chap_name  = $chap_term ? $chap_term->name : '';

    $section    = get_post_meta($post_id, 'section', true);
    $lesson_no  = (int) get_post_meta($post_id, 'lesson_order', true);

    [$c1, $c2, $accent] = pm_palette_for_chapter($chap_slug);
    $lines = pm_wrap_title($title);

    // Build text spans (each at its own y)
    $title_svg = '';
    $start_y   = 320;
    $line_h    = 84;
    foreach ($lines as $i => $line) {
        $y = $start_y + $i * $line_h;
        $title_svg .= sprintf(
            '<text x="600" y="%d" text-anchor="middle" font-family="Vazirmatn, Tahoma, sans-serif" font-size="62" font-weight="800" fill="#F8F6F0" direction="rtl">%s</text>',
            $y, htmlspecialchars($line, ENT_XML1, 'UTF-8')
        );
    }

    $lesson_label = $lesson_no > 0 ? sprintf('بند %s', $lesson_no) : '';
    $chap_label   = htmlspecialchars($chap_name, ENT_XML1, 'UTF-8');

    return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="$c1"/>
      <stop offset="100%" stop-color="$c2"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <!-- accent corner -->
  <path d="M0,0 L260,0 L0,260 Z" fill="$accent" opacity="0.18"/>
  <path d="M1200,630 L940,630 L1200,370 Z" fill="$accent" opacity="0.12"/>
  <!-- top label: chapter -->
  <text x="600" y="120" text-anchor="middle" font-family="Vazirmatn, Tahoma, sans-serif"
        font-size="30" font-weight="600" fill="$accent" letter-spacing="2" direction="rtl">$chap_label</text>
  <!-- divider -->
  <line x1="500" y1="160" x2="700" y2="160" stroke="$accent" stroke-width="2" opacity="0.6"/>
  <!-- title -->
  $title_svg
  <!-- bottom: lesson label + brand -->
  <text x="600" y="560" text-anchor="middle" font-family="Vazirmatn, Tahoma, sans-serif"
        font-size="24" font-weight="500" fill="#F8F6F0" opacity="0.85" direction="rtl">$lesson_label</text>
  <text x="600" y="600" text-anchor="middle" font-family="Vazirmatn, Tahoma, sans-serif"
        font-size="22" font-weight="700" fill="$accent" direction="rtl">منِ فیزیکی · physicsme.ir</text>
</svg>
SVG;
}

/**
 * Generate the SVG file on disk and attach it as the post thumbnail.
 */
function pm_generate_featured(int $post_id): ?int {
    $svg = pm_build_thumb_svg($post_id);

    $up  = wp_upload_dir();
    $dir = trailingslashit($up['basedir']) . PM_AUTO_THUMB_DIR;
    $url = trailingslashit($up['baseurl']) . PM_AUTO_THUMB_DIR;
    if (!file_exists($dir)) wp_mkdir_p($dir);

    $filename = "post-{$post_id}.svg";
    $path     = "$dir/$filename";
    $file_url = "$url/$filename";
    file_put_contents($path, $svg);

    // Create or update an attachment for this file
    $attach_id = (int) get_post_meta($post_id, PM_AUTO_THUMB_MARK, true);
    if ($attach_id && get_post($attach_id)) {
        // already exists — just update the file
        return $attach_id;
    }

    $attachment = [
        'guid'           => $file_url,
        'post_mime_type' => 'image/svg+xml',
        'post_title'     => 'تصویر شاخص: ' . get_the_title($post_id),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $attach_id = wp_insert_attachment($attachment, $path, $post_id);
    if (!$attach_id || is_wp_error($attach_id)) return null;

    update_post_meta($attach_id, '_wp_attached_file', PM_AUTO_THUMB_DIR . "/$filename");
    set_post_thumbnail($post_id, $attach_id);
    update_post_meta($post_id, PM_AUTO_THUMB_MARK, $attach_id);
    return $attach_id;
}

/** Auto-generate on save if missing */
foreach (['save_post_article', 'save_post_video', 'save_post_post'] as $hook) {
    add_action($hook, function ($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (has_post_thumbnail($post_id)) return;
        pm_generate_featured($post_id);
    }, 99, 3);
}

/** Allow SVG uploads (since attachments are SVG) */
add_filter('upload_mimes', function ($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
});

/** Tell WordPress how to handle SVG dimensions for image_downsize */
add_filter('wp_get_attachment_image_src', function ($image, $attachment_id, $size) {
    if (!$image) return $image;
    if (str_ends_with((string)$image[0], '.svg')) {
        $image[1] = 1200;
        $image[2] = 630;
    }
    return $image;
}, 10, 3);

/**
 * CLI helper: `wp eval 'pm_backfill_all_featured();'` regenerates for all posts.
 */
function pm_backfill_all_featured(): void {
    $q = new WP_Query([
        'post_type'      => ['article', 'video', 'post'],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    foreach ($q->posts as $id) {
        if (has_post_thumbnail($id)) continue;
        $aid = pm_generate_featured($id);
        echo "  · post #$id → attachment #$aid\n";
    }
}
