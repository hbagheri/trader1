<?php
/**
 * Plugin Name: PhysicalMe — REST API (pm/v1)
 * Description: Typed REST endpoints under /wp-json/pm/v1/ consumed by the
 *              Capacitor mobile app. Response shapes match shared/src/types.ts.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) exit;

define('PM_API_NAMESPACE', 'pm/v1');
define('PM_API_VERSION',   '0.1.0');

/* ─────────── CORS — allow web + Capacitor + Vite dev ─────────── */
add_action('rest_api_init', function () {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function ($value) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $site_origin = untrailingslashit(home_url());
        $allow = $origin === ''
            || $origin === $site_origin
            || $origin === 'https://physicalme.ir'  // legacy domain, redirected but kept for old clients
            || $origin === 'https://localhost'
            || $origin === 'capacitor://localhost'
            || $origin === 'ionic://localhost'
            || (bool)preg_match('#^https?://localhost(:\d+)?$#i', $origin)
            || (bool)preg_match('#^https?://127\.0\.0\.1(:\d+)?$#i', $origin);
        if ($allow) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }
        return $value;
    });
}, 15);

/* ─────────── Route registration ─────────── */
add_action('rest_api_init', function () {
    register_rest_route(PM_API_NAMESPACE, '/version', [
        'methods'  => 'GET',
        'callback' => function () {
            return [
                'name'    => 'physicalme-api',
                'version' => PM_API_VERSION,
                'wp'      => get_bloginfo('version'),
                'php'     => PHP_VERSION,
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(PM_API_NAMESPACE, '/books', [
        'methods'  => 'GET',
        'callback' => 'pm_api_books',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(PM_API_NAMESPACE, '/books/(?P<slug>[a-z0-9-]+)/chapters', [
        'methods'  => 'GET',
        'callback' => 'pm_api_book_chapters',
        'permission_callback' => '__return_true',
        'args' => [
            'slug' => ['sanitize_callback' => 'sanitize_title'],
        ],
    ]);

    register_rest_route(PM_API_NAMESPACE, '/recent', [
        'methods'  => 'GET',
        'callback' => 'pm_api_recent',
        'permission_callback' => '__return_true',
        'args' => [
            'limit' => ['sanitize_callback' => 'absint', 'default' => 12],
        ],
    ]);

    register_rest_route(PM_API_NAMESPACE, '/chapters/(?P<slug>[a-z0-9-]+)/articles', [
        'methods'  => 'GET',
        'callback' => 'pm_api_chapter_articles',
        'permission_callback' => '__return_true',
        'args' => [
            'slug' => ['sanitize_callback' => 'sanitize_title'],
        ],
    ]);

    register_rest_route(PM_API_NAMESPACE, '/articles/(?P<slug>[a-z0-9-]+)', [
        'methods'  => 'GET',
        'callback' => 'pm_api_article',
        'permission_callback' => '__return_true',
        'args' => [
            'slug' => ['sanitize_callback' => 'sanitize_title'],
        ],
    ]);
});

/* ─────────── /chapters/{slug}/articles — ordered article list ─────────── */
function pm_api_chapter_articles(WP_REST_Request $req): WP_REST_Response {
    $chapter_slug = (string)$req->get_param('slug');
    $term = get_term_by('slug', $chapter_slug, 'chapter');
    if (!$term || is_wp_error($term)) {
        return new WP_REST_Response(['error' => ['code' => 'unknown_chapter', 'message' => 'Unknown chapter slug']], 404);
    }

    $q = new WP_Query([
        'post_type'      => 'article',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value_num',
        'meta_key'       => '_pm_order',
        'order'          => 'ASC',
        'tax_query'      => [[
            'taxonomy' => 'chapter',
            'field'    => 'slug',
            'terms'    => $chapter_slug,
        ]],
    ]);

    // If _pm_order isn't set on most posts, fall back to date asc
    if (!$q->have_posts()) {
        $q = new WP_Query([
            'post_type'      => 'article',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'tax_query'      => [[
                'taxonomy' => 'chapter',
                'field'    => 'slug',
                'terms'    => $chapter_slug,
            ]],
        ]);
    }

    $out = [];
    foreach ($q->posts as $p) {
        $book_slug = pm_api_book_of_post($p->ID);
        $thumb_id  = get_post_thumbnail_id($p->ID);
        $thumb     = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : null;
        $out[] = [
            'slug'        => $p->post_name,
            'title'       => get_the_title($p),
            'chapterSlug' => $chapter_slug,
            'bookSlug'    => $book_slug,
            'excerpt'     => wp_strip_all_tags(get_the_excerpt($p)),
            'readingTime' => (string)get_post_meta($p->ID, 'reading_time', true),
            'publishedAt' => mysql2date('c', $p->post_date_gmt, false),
            'thumbUrl'    => $thumb ?: null,
        ];
    }

    $response = new WP_REST_Response($out, 200);
    $response->set_headers(['Cache-Control' => 'public, max-age=300']);
    return $response;
}

/* ─────────── /articles/{slug} — full article body ─────────── */
function pm_api_article(WP_REST_Request $req): WP_REST_Response {
    $slug = (string)$req->get_param('slug');

    $posts = get_posts([
        'name'           => $slug,
        'post_type'      => 'article',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
    ]);
    if (empty($posts)) {
        return new WP_REST_Response(['error' => ['code' => 'not_found', 'message' => 'Article not found']], 404);
    }
    $p = $posts[0];

    $chapter_slug = '';
    $book_slug    = '';
    $terms = wp_get_post_terms($p->ID, 'chapter', ['fields' => 'all']);
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $t) {
            if (in_array($t->slug, ['dahom-ram','yazdahom-ram','davazdahom-ram','dahom-tajrobi','yazdahom-tajrobi','davazdahom-tajrobi'], true)) {
                $book_slug = $t->slug;
            } else {
                $chapter_slug = $t->slug;
            }
        }
    }

    // Run the content through WP filters so shortcodes, oEmbed, etc. resolve
    $html = apply_filters('the_content', $p->post_content);

    // Rewrite path-only URLs (src="/wp-content/...", href="/wp-content/...") to
    // absolute site URLs. In a browser these resolve against the WP
    // host automatically, but the mobile WebView loads from https://localhost/
    // so /wp-content/.../widgets/*.html (iframes, images) would 404 without
    // this. Skips protocol-relative URLs (//host/path) by requiring [^/] after /.
    $site_url = rtrim(site_url(), '/');
    $html = preg_replace_callback(
        '#\b(src|href)="(/[^/][^"]*)"#i',
        function ($m) use ($site_url) {
            return $m[1] . '="' . $site_url . $m[2] . '"';
        },
        $html
    );

    // Find prev/next sibling article in the same chapter
    [$prev, $next] = pm_api_siblings($p, $chapter_slug);

    $thumb_id = get_post_thumbnail_id($p->ID);
    $thumb    = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : null;

    $payload = [
        'slug'        => $p->post_name,
        'title'       => get_the_title($p),
        'chapterSlug' => $chapter_slug,
        'bookSlug'    => $book_slug,
        'excerpt'     => wp_strip_all_tags(get_the_excerpt($p)),
        'readingTime' => (string)get_post_meta($p->ID, 'reading_time', true),
        'publishedAt' => mysql2date('c', $p->post_date_gmt, false),
        'thumbUrl'    => $thumb ?: null,
        'html'        => $html,
        'prev'        => $prev,
        'next'        => $next,
    ];

    $response = new WP_REST_Response($payload, 200);
    $response->set_headers(['Cache-Control' => 'public, max-age=600']);
    return $response;
}

/** Find the chapter parent (book) of a post by slug pattern. */
function pm_api_book_of_post(int $post_id): string {
    $terms = wp_get_post_terms($post_id, 'chapter', ['fields' => 'all']);
    if (!$terms || is_wp_error($terms)) return '';
    foreach ($terms as $t) {
        if (in_array($t->slug, ['dahom-ram','yazdahom-ram','davazdahom-ram','dahom-tajrobi','yazdahom-tajrobi','davazdahom-tajrobi'], true)) {
            return $t->slug;
        }
    }
    return '';
}

/** Return [prev, next] {slug,title} pair for navigation inside a chapter. */
function pm_api_siblings(WP_Post $post, string $chapter_slug): array {
    if (!$chapter_slug) return [null, null];
    $q = new WP_Query([
        'post_type'      => 'article',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => 'chapter',
            'field'    => 'slug',
            'terms'    => $chapter_slug,
        ]],
    ]);
    $ids = $q->posts;
    $i = array_search($post->ID, $ids, true);
    if ($i === false) return [null, null];
    $prev = $i > 0 ? $ids[$i - 1] : null;
    $next = $i < count($ids) - 1 ? $ids[$i + 1] : null;
    $fmt = function ($id) {
        if (!$id) return null;
        $p = get_post($id);
        return $p ? ['slug' => $p->post_name, 'title' => get_the_title($p)] : null;
    };
    return [$fmt($prev), $fmt($next)];
}

/* ─────────── /books — mirrors the shortcode logic in physicalme-homepage.php ─────────── */
function pm_api_books(): WP_REST_Response {
    // Same 6-book curated list + colors as [pm_books_grid]
    $books = [
        ['dahom-ram',          'دهم ریاضی و فیزیک',    '📐', '#2C5F8D', '#4A82B5', '#D4A847', 'fasl-'],
        ['yazdahom-ram',       'یازدهم ریاضی و فیزیک', '⚡', '#6B4E8C', '#8B6FB0', '#F0CE6B', 'y11-fasl-'],
        ['davazdahom-ram',     'دوازدهم ریاضی و فیزیک','🌌', '#8C3A3A', '#A75252', '#FFD27A', 'y12-fasl-'],
        ['dahom-tajrobi',      'دهم تجربی',             '🧪', '#4A7A47', '#6B9E68', '#FFE08A', 'y10t-fasl-'],
        ['yazdahom-tajrobi',   'یازدهم تجربی',          '🩺', '#3A7A78', '#589C9A', '#FFD89E', 'y11t-fasl-'],
        ['davazdahom-tajrobi', 'دوازدهم تجربی',         '☢️', '#C4622D', '#DC8052', '#FFE0B0', 'y12t-fasl-'],
    ];

    $cache_key = 'pm_api_books_v1';
    $payload = get_transient($cache_key);
    if (!$payload) {
        $all_terms = get_terms([
            'taxonomy'   => 'chapter',
            'hide_empty' => false,
        ]);
        $by_slug = [];
        if (is_array($all_terms)) {
            foreach ($all_terms as $t) $by_slug[$t->slug] = $t;
        }
        $payload = [];
        foreach ($books as $b) {
            if (!isset($by_slug[$b[0]])) continue;
            $term   = $by_slug[$b[0]];
            $prefix = $b[6];
            $chapter_count = 0;
            $lesson_count  = (int)$term->count;
            foreach ($by_slug as $slug => $t) {
                if ($slug === $b[0]) continue;
                if ($prefix === 'fasl-') {
                    if (preg_match('/^fasl-\d+$/', $slug)) {
                        $chapter_count++;
                        $lesson_count += (int)$t->count;
                    }
                } elseif (strpos($slug, $prefix) === 0) {
                    $chapter_count++;
                    $lesson_count += (int)$t->count;
                }
            }
            $payload[] = [
                'slug'          => $b[0],
                'label'         => $b[1],
                'emoji'         => $b[2],
                'color1'        => $b[3],
                'color2'        => $b[4],
                'accent'        => $b[5],
                'chapterCount'  => $chapter_count,
                'lessonCount'   => $lesson_count,
                'url'           => get_term_link($term),
            ];
        }
        set_transient($cache_key, $payload, HOUR_IN_SECONDS);
    }

    $response = new WP_REST_Response($payload, 200);
    $response->set_headers(['Cache-Control' => 'public, max-age=300']);
    return $response;
}

/* ─────────── /books/{slug}/chapters ─────────── */
function pm_api_book_chapters(WP_REST_Request $req): WP_REST_Response {
    $slug = (string)$req->get_param('slug');
    // Map book slug → child fasl prefix (mirrors the [pm_books_grid] table).
    $prefix_map = [
        'dahom-ram'          => 'fasl-',
        'yazdahom-ram'       => 'y11-fasl-',
        'davazdahom-ram'     => 'y12-fasl-',
        'dahom-tajrobi'      => 'y10t-fasl-',
        'yazdahom-tajrobi'   => 'y11t-fasl-',
        'davazdahom-tajrobi' => 'y12t-fasl-',
    ];
    if (!isset($prefix_map[$slug])) {
        return new WP_REST_Response(['error' => ['code' => 'unknown_book', 'message' => 'Unknown book slug']], 404);
    }
    $prefix = $prefix_map[$slug];
    $all = get_terms(['taxonomy' => 'chapter', 'hide_empty' => false]);
    $out = [];
    if (is_array($all)) {
        foreach ($all as $t) {
            $is_match = ($prefix === 'fasl-')
                ? (bool)preg_match('/^fasl-\d+$/', $t->slug)
                : (strpos($t->slug, $prefix) === 0);
            if (!$is_match) continue;
            $out[] = [
                'slug'        => $t->slug,
                'bookSlug'    => $slug,
                'title'       => $t->name,
                'order'       => (int)preg_replace('/\D/', '', $t->slug),
                'lessonCount' => (int)$t->count,
            ];
        }
    }
    usort($out, fn($a, $b) => $a['order'] <=> $b['order']);
    $response = new WP_REST_Response($out, 200);
    $response->set_headers(['Cache-Control' => 'public, max-age=300']);
    return $response;
}

/* ─────────── /recent — N most-recent published articles ─────────── */
function pm_api_recent(WP_REST_Request $req): WP_REST_Response {
    $limit = max(1, min(50, (int)$req->get_param('limit')));
    $q = new WP_Query([
        'post_type'      => 'article',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => '_yoast_wpseo_meta-robots-noindex', 'value' => '0', 'compare' => '='],
            ['key' => '_yoast_wpseo_meta-robots-noindex', 'compare' => 'NOT EXISTS'],
        ],
    ]);
    $out = [];
    foreach ($q->posts as $p) {
        $chapter_terms = wp_get_post_terms($p->ID, 'chapter', ['fields' => 'all']);
        $chapter_slug = ''; $book_slug = '';
        if ($chapter_terms && !is_wp_error($chapter_terms)) {
            foreach ($chapter_terms as $t) {
                if (in_array($t->slug, ['dahom-ram','yazdahom-ram','davazdahom-ram','dahom-tajrobi','yazdahom-tajrobi','davazdahom-tajrobi'], true)) {
                    $book_slug = $t->slug;
                } else {
                    $chapter_slug = $t->slug;
                }
            }
        }
        $thumb_id = get_post_thumbnail_id($p->ID);
        $thumb = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : null;
        $out[] = [
            'slug'        => $p->post_name,
            'title'       => get_the_title($p),
            'chapterSlug' => $chapter_slug,
            'bookSlug'    => $book_slug,
            'excerpt'     => wp_strip_all_tags(get_the_excerpt($p)),
            'readingTime' => (string)get_post_meta($p->ID, 'reading_time', true),
            'publishedAt' => mysql2date('c', $p->post_date_gmt, false),
            'thumbUrl'    => $thumb ?: null,
        ];
    }
    $response = new WP_REST_Response($out, 200);
    $response->set_headers(['Cache-Control' => 'public, max-age=300']);
    return $response;
}

/* ─────────── Cache invalidation ─────────── */
add_action('save_post_article', function () { delete_transient('pm_api_books_v1'); });
add_action('edited_chapter',    function () { delete_transient('pm_api_books_v1'); });
add_action('created_chapter',   function () { delete_transient('pm_api_books_v1'); });
