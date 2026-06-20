<?php
/**
 * Plugin Name: PhysicalMe — Typography & Reading Polish
 * Description: استایل خوانش برای محتوای مقالات و ویدیوها (justify, line-height, و ...)
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
    $fonts_url = content_url('mu-plugins/fonts/vazirmatn');
    ?>
<style id="physicalme-vazirmatn">
/* ---------- Vazirmatn (self-hosted) — works under Iran filter ---------- */
@font-face {
  font-family: 'Vazirmatn';
  src: url('<?php echo $fonts_url; ?>/Vazirmatn-400.woff2') format('woff2');
  font-weight: 400; font-style: normal; font-display: swap;
}
@font-face {
  font-family: 'Vazirmatn';
  src: url('<?php echo $fonts_url; ?>/Vazirmatn-500.woff2') format('woff2');
  font-weight: 500; font-style: normal; font-display: swap;
}
@font-face {
  font-family: 'Vazirmatn';
  src: url('<?php echo $fonts_url; ?>/Vazirmatn-600.woff2') format('woff2');
  font-weight: 600; font-style: normal; font-display: swap;
}
@font-face {
  font-family: 'Vazirmatn';
  src: url('<?php echo $fonts_url; ?>/Vazirmatn-700.woff2') format('woff2');
  font-weight: 700; font-style: normal; font-display: swap;
}
@font-face {
  font-family: 'Vazirmatn';
  src: url('<?php echo $fonts_url; ?>/Vazirmatn-800.woff2') format('woff2');
  font-weight: 800; font-style: normal; font-display: swap;
}

/* Site-wide default: Vazirmatn before falling back to Tahoma. We need !important
   to win against hello-elementor's hard-coded font-family on body. */
html, body, body * {
  font-family: 'Vazirmatn', 'Tahoma', 'Segoe UI', system-ui, sans-serif;
}
/* Don't fight the icon fonts (FontAwesome, dashicons, elementor icons) */
i[class^="fa"], i[class*=" fa"],
[class^="eicon-"], [class*=" eicon-"],
.dashicons, .dashicons-before:before,
.elementor-icon, .elementor-icon i, .elementor-icon svg {
  font-family: revert !important;
}
</style>
<style id="physicalme-reading-polish">
/* ---------- Single article / video / post body ---------- */
.single-article .page-content,
.single-video   .page-content,
.single-post    .page-content {
    max-width: 760px;
    margin: 0 auto;
    font-size: 17px;
    line-height: 2;
    color: #2A2E30;
}

.single-article .page-content p,
.single-video   .page-content p,
.single-post    .page-content p {
    text-align: justify;
    text-justify: inter-word;
    line-height: 2;
    margin-bottom: 1.2em;
}

/* Headings — keep them flush right (Persian RTL) */
.single-article .page-content h1,
.single-article .page-content h2,
.single-article .page-content h3,
.single-video   .page-content h2,
.single-video   .page-content h3,
.single-post    .page-content h2,
.single-post    .page-content h3 {
    text-align: right;
    color: #3D4548;
    font-weight: 700;
    margin-top: 2em;
    margin-bottom: 0.6em;
    line-height: 1.4;
}

/* Lists — natural alignment (not justified) */
.single-article .page-content ul,
.single-article .page-content ol,
.single-video   .page-content ul,
.single-video   .page-content ol,
.single-post    .page-content ul,
.single-post    .page-content ol {
    text-align: right;
    line-height: 1.9;
}

/* Blockquotes */
.single-article .page-content blockquote,
.single-video   .page-content blockquote,
.single-post    .page-content blockquote {
    background: #F8F6F0;
    border-right: 4px solid #9CAB52;
    padding: 14px 22px;
    margin: 22px 0;
    color: #2A2E30;
    border-radius: 6px;
}
.single-article .page-content blockquote p,
.single-video   .page-content blockquote p,
.single-post    .page-content blockquote p {
    text-align: right;     /* quotes look better right-aligned, not justified */
    margin-bottom: 0;
}

/* Tables — clean look */
.single-article .page-content table,
.single-video   .page-content table,
.single-post    .page-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 26px 0;
    font-size: 16px;
}
.single-article .page-content th,
.single-article .page-content td,
.single-video   .page-content th,
.single-video   .page-content td,
.single-post    .page-content th,
.single-post    .page-content td {
    border: 1px solid #E0DCC8;
    padding: 12px 14px;
    text-align: right;
    vertical-align: middle;
}
.single-article .page-content th,
.single-video   .page-content th,
.single-post    .page-content th {
    background: #3D4548;
    color: #F8F6F0;
    font-weight: 600;
}
.single-article .page-content tr:nth-child(even) td,
.single-video   .page-content tr:nth-child(even) td,
.single-post    .page-content tr:nth-child(even) td {
    background: #FAFAF5;
}

/* Inline code (NOT inside <pre>) */
.single-article .page-content :not(pre) > code,
.single-video   .page-content :not(pre) > code,
.single-post    .page-content :not(pre) > code {
    background: #F1ECDB;
    color: #4A3B1F;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.92em;
    direction: ltr;
    display: inline-block;
    font-family: "JetBrains Mono", Consolas, "Courier New", monospace;
}

/* Code block — high contrast, copy button friendly */
.single-article .page-content pre,
.single-video   .page-content pre,
.single-post    .page-content pre {
    background: #1E1E2E;
    color: #EAE8DF;
    padding: 22px 18px 18px;
    border-radius: 8px;
    overflow-x: auto;
    direction: ltr;
    text-align: left;
    position: relative;
    line-height: 1.65;
    font-size: 14.5px;
    font-family: "JetBrains Mono", Consolas, "Courier New", monospace;
    border: 1px solid #2D2D44;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.single-article .page-content pre code,
.single-video   .page-content pre code,
.single-post    .page-content pre code {
    background: transparent;
    color: inherit;
    padding: 0;
    border-radius: 0;
    font-size: inherit;
    display: block;
    direction: ltr;
    font-family: inherit;
}

/* Lightweight Python-ish highlight via spans the copy script can leave in place */
.single-article .page-content pre .tok-kw,
.single-video   .page-content pre .tok-kw,
.single-post    .page-content pre .tok-kw { color: #C792EA; }
.single-article .page-content pre .tok-str,
.single-video   .page-content pre .tok-str,
.single-post    .page-content pre .tok-str { color: #C3E88D; }
.single-article .page-content pre .tok-num,
.single-video   .page-content pre .tok-num,
.single-post    .page-content pre .tok-num { color: #F78C6C; }
.single-article .page-content pre .tok-com,
.single-video   .page-content pre .tok-com,
.single-post    .page-content pre .tok-com { color: #898C9F; font-style: italic; }
.single-article .page-content pre .tok-fn,
.single-video   .page-content pre .tok-fn,
.single-post    .page-content pre .tok-fn { color: #82AAFF; }

/* Copy button injected by physicalme-code-copy.php */
.pm-copy-btn {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(255,255,255,0.10);
    color: #EAE8DF;
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 6px;
    padding: 4px 10px;
    font-family: Vazirmatn, Tahoma, sans-serif;
    font-size: 12px;
    cursor: pointer;
    direction: rtl;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background 0.15s, color 0.15s;
    z-index: 2;
}
.pm-copy-btn:hover {
    background: rgba(255,255,255,0.18);
    color: #FFFFFF;
}
.pm-copy-btn.pm-copied {
    background: #1D9E75;
    color: #FFFFFF;
    border-color: #1D9E75;
}
.pm-copy-btn svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
}

/* Links inside body */
.single-article .page-content a,
.single-video   .page-content a,
.single-post    .page-content a {
    color: #5B6E32;
    text-decoration: underline;
    text-decoration-color: #D4A847;
    text-underline-offset: 3px;
}
.single-article .page-content a:hover,
.single-video   .page-content a:hover,
.single-post    .page-content a:hover {
    color: #9CAB52;
}

/* Mobile tweaks */
@media (max-width: 720px) {
    .single-article .page-content,
    .single-video   .page-content,
    .single-post    .page-content {
        font-size: 16px;
        padding: 0 16px;
    }
}

/* ─────────── Chapter cards (Home grid, Khan-style) ─────────── */
.pm-chapter-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    max-width: 1280px;
    margin: 0 auto;
    padding: 8px 0;
}
@media (max-width: 1180px) {
    .pm-chapter-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 720px) {
    .pm-chapter-grid { grid-template-columns: 1fr; }
}
.pm-chapter-card {
    position: relative;
    display: block;
    padding: 28px 24px 26px;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--c1) 0%, var(--c2) 100%);
    color: #FFFFFF;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    transition: transform .25s ease, box-shadow .25s ease;
    overflow: hidden;
    min-height: 240px;
}
.pm-chapter-card::before {
    content: "";
    position: absolute;
    top: -40px; right: -40px;
    width: 140px; height: 140px;
    background: var(--accent, #D4A847);
    opacity: 0.15;
    border-radius: 50%;
}
.pm-chapter-card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 18px 36px rgba(0,0,0,0.18);
    color: #FFFFFF;
}
.pm-chap-num {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 2px;
    color: var(--accent, #D4A847);
    opacity: 0.95;
    margin-bottom: 10px;
}
.pm-chap-emoji {
    font-size: 40px;
    line-height: 1;
    margin-bottom: 12px;
}
.pm-chapter-card h3 {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-size: 21px;
    font-weight: 800;
    color: #FFFFFF;
    margin: 0 0 8px;
    line-height: 1.5;
}
.pm-chapter-card p {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-size: 14px;
    line-height: 1.85;
    color: rgba(255,255,255,0.85);
    margin: 0 0 16px;
}
.pm-chap-count {
    display: inline-block;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 100px;
    padding: 4px 12px;
    font-size: 13px;
    font-weight: 600;
}

/* ─────────── Quanta-style single article tweaks ─────────── */
.single-article .page-content {
    max-width: 820px;          /* default reading width — slightly wider */
    font-size: 18px;
    line-height: 2.05;
    color: #1F2421;
}
/* Posts that embed a problem-solving widget need much more horizontal room
   so the iframe can render its sidebar + main column without going mobile-mode. */
.single-article.pm-wide-layout .page-content {
    max-width: 1180px;
}
.single-article .page-content > h1,
.single-article .entry-title,
.single-article h1.entry-title {
    font-family: 'Vazirmatn', Tahoma, sans-serif !important;
    font-weight: 800 !important;
    font-size: 44px !important;
    line-height: 1.5 !important;
    color: #1F2421 !important;
    text-align: center !important;
    margin: 30px auto 24px !important;
    max-width: 820px !important;
}
.single-article .page-content h2 {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-weight: 800;
    font-size: 28px;
    margin-top: 2.5em;
    color: #1F2421;
    border-bottom: 1px solid #E0DCC8;
    padding-bottom: 8px;
    line-height: 1.5;
}
.single-article .page-content h3 {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-weight: 700;
    font-size: 22px;
    color: #3D4548;
    line-height: 1.5;
}
.single-article .page-content > p:first-of-type {
    font-size: 20px;
    line-height: 1.9;
    color: #3D4548;
    font-weight: 500;
}

/* Drop-cap on first paragraph (Quanta-style) — Vazirmatn (Persian-friendly) */
.single-article .page-content > p:first-of-type::first-letter {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-size: 58px;
    font-weight: 800;
    float: right;
    line-height: 1;
    margin: 6px 8px 0 12px;
    color: #5B6E32;
}

/* Article container — white card with soft shadow so content sits in a defined box.
   max-width keeps body background showing as a visible margin on both sides so the
   shadow has something to contrast against (otherwise the card spans edge-to-edge
   and the shadow has nowhere to fall). A faint border further defines the edge. */
.single-article main#content,
.post-type-archive-article main#content,
.tax-chapter main#content,
.tax-branch main#content,
.tax-level main#content {
    background: #FFFFFF;
    border-radius: 14px;
    border: 1px solid rgba(60, 50, 30, 0.06);
    box-shadow: 0 10px 30px rgba(60, 50, 30, 0.14),
                0 2px 6px rgba(60, 50, 30, 0.08);
    padding: 36px 42px;
    margin: 32px auto 48px;
    max-width: 980px;
}
/* Wide-layout articles (problem widgets) still need extra horizontal room */
.single-article.pm-wide-layout main#content {
    max-width: 1200px;
}
@media (max-width: 720px) {
    .single-article main#content,
    .post-type-archive-article main#content,
    .tax-chapter main#content,
    .tax-branch main#content,
    .tax-level main#content {
        padding: 22px 18px;
        margin: 14px 12px 24px;
        border-radius: 10px;
    }
}

/* ─────────── Generic main-content card (blog, search, 404, regular post) ───────────
   These page types used to render their content bare against the page background.
   Same card treatment as article/chapter pages, but a slightly narrower max-width
   since the content tends to be lists / short bodies. */
body.blog main.site-main,
body.search main.site-main,
body.error404 main.site-main,
body.single-post main.site-main,
body.single-video main.site-main,
body.post-type-archive-video main.site-main {
    background: #FFFFFF;
    border-radius: 14px;
    box-shadow: 0 8px 28px rgba(60, 50, 30, 0.12),
                0 2px 6px rgba(60, 50, 30, 0.06);
    padding: 36px 42px;
    margin: 24px auto 40px;
    max-width: 920px;
}
body.blog main.site-main .page-content,
body.search main.site-main .page-content,
body.error404 main.site-main .page-content,
body.single-post main.site-main .page-content,
body.single-video main.site-main .page-content,
body.post-type-archive-video main.site-main .page-content {
    max-width: 760px;
    margin: 0 auto;
}
/* Page header (the H1 like «نتایج جستجوی...» / «صفحه پیدا نشد» / «بایگانی‌ها»)
   was hugging the right edge. Center it and give it the same Vazirmatn weight as articles. */
body.blog main.site-main .page-header h1,
body.search main.site-main .page-header h1,
body.error404 main.site-main .page-header h1 {
    font-family: 'Vazirmatn', Tahoma, sans-serif !important;
    font-weight: 800;
    font-size: 36px;
    color: #1F2421;
    text-align: center;
    margin: 8px auto 24px;
    max-width: 760px;
    line-height: 1.5;
}
/* Search-result list items — give each its own card so they don't look like raw links */
body.search main.site-main article.post,
body.search main.site-main article.article {
    background: #FAFAF5;
    border: 1px solid #E8E2CC;
    border-radius: 10px;
    padding: 18px 22px;
    margin: 14px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
body.search main.site-main article h2 a {
    color: #1F2421;
    text-decoration: none;
}
body.search main.site-main article h2 a:hover {
    color: #5B6E32;
    text-decoration: underline;
}
@media (max-width: 720px) {
    body.blog main.site-main,
    body.search main.site-main,
    body.error404 main.site-main,
    body.single-post main.site-main,
    body.single-video main.site-main {
        padding: 22px 18px;
        margin: 14px 12px 24px;
        border-radius: 10px;
    }
    body.blog main.site-main .page-header h1,
    body.search main.site-main .page-header h1,
    body.error404 main.site-main .page-header h1 {
        font-size: 26px;
    }
}

/* ─────────── MathJax overflow & clipping fix ─────────── */
/* Long display-math (e.g. $$ B = (4πk×10⁻⁷)(I)(L) / ... $$) was overflowing the article column
   on mobile widths and creating horizontal page scroll. We let each math block scroll
   horizontally inside itself instead of stretching the page. */
mjx-container[display="true"] {
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    padding-block: 0.5em !important;        /* room for both top exponents and bottom descenders */
}
/* Inline math: line-height was being clipped by tight surroundings (table cells, styled spans).
   Use vertical-align:baseline so the math sits on the text baseline (no center-shift clipping),
   and add a hair of padding for the "0" in 10^n not to lose its bottom curve. */
mjx-container {
    line-height: 1.6 !important;
    vertical-align: baseline;
    padding-bottom: 1px;
    /* never let an INLINE math span overflow its parent and create a horizontal page scrollbar
       (this was the #1 cause of 411px body width on 375px mobile screens). */
    max-width: 100% !important;
    overflow-x: auto !important;
}
/* For inline math specifically, give the container intrinsic block-ish handling so
   overflow-x actually works (overflow on inline elements is otherwise ignored). */
mjx-container:not([display="true"]) {
    display: inline-block !important;
    vertical-align: middle;
}
/* Tables: cells often have constrained heights — guarantee math has room */
.single-article .page-content td mjx-container,
.single-article .page-content th mjx-container {
    line-height: 1.8 !important;
}
/* HTML <sup>/<sub> in styled spans (e.g. scale-bar in 3-2 article) — prevent bottom clip
   of the base digit (the "0" in 10^n) by giving the span enough line-height. */
.single-article .page-content sup,
.single-article .page-content sub {
    line-height: 0;                          /* don't push line-box bigger, but … */
    vertical-align: baseline;
    position: relative;
    font-size: 0.78em;
}
.single-article .page-content sup { top: -0.55em; }
.single-article .page-content sub { bottom: -0.25em; }
/* Any span containing <sup>/<sub> needs explicit line-height so the base isn't clipped */
.single-article .page-content span:has(sup),
.single-article .page-content span:has(sub) {
    line-height: 1.6 !important;
}
/* Tighten on very small screens, scale down display math slightly */
@media (max-width: 480px) {
    mjx-container[display="true"] {
        font-size: 0.92em !important;
    }
}
/* Make sure article container itself never overflows its parent on mobile */
.single-article .page-content, .single-post .page-content {
    overflow-wrap: break-word;
    word-wrap: break-word;
}
/* Final safety net: never allow horizontal page scroll. Any content wider than viewport
   either wraps, breaks, or scrolls internally (math via mjx-container above). */
html, body {
    overflow-x: hidden;
    max-width: 100%;
}
/* Comment form textarea + inputs must respect their column on mobile (was 381px on 375px vp) */
textarea, input[type="text"], input[type="email"], input[type="url"], input[type="search"] {
    max-width: 100%;
    box-sizing: border-box;
}
#commentform textarea, #commentform input { width: 100% !important; }

/* Defensive: any image, iframe, video, embed should never overflow its column on mobile */
img, video, embed, object, svg { max-width: 100%; height: auto; }
iframe { max-width: 100%; }  /* width-only — height is managed by iframe-autosize JS */

/* ─────────── WP Pages (About, Contact, etc.) — mirror article styling ─────────── */
body.page main#content {
    background: #FFFFFF;
    border-radius: 14px;
    box-shadow: 0 8px 28px rgba(60, 50, 30, 0.12),
                0 2px 6px rgba(60, 50, 30, 0.06);
    padding: 36px 42px;
    margin: 24px auto 40px;
    max-width: 920px;
}
/* ─────────── Front page: full-bleed, no card, no title ─────────── */
/* No padding/margin on main + content wrappers so hero hugs the header */
body.home main#content.site-main,
body.page-id-9 main#content.site-main {
    background: transparent !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
    max-width: none !important;
    width: auto !important;
}
/* Hide the WP page title (=‌ "خانه") and its wrapper */
body.home main#content > .page-header,
body.home main#content .entry-header,
body.home main#content h1.entry-title,
body.home main#content header.entry-header,
body.page-id-9 main#content > .page-header,
body.page-id-9 main#content .entry-header,
body.page-id-9 main#content h1.entry-title,
body.page-id-9 main#content header.entry-header {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}
/* Strip all reading-polish styles from .page-content on home */
body.home main#content .page-content,
body.page-id-9 main#content .page-content {
    max-width: none !important;
    width: 100% !important;
    font-size: inherit !important;
    line-height: inherit !important;
    padding: 0 !important;
    margin: 0 !important;
    color: inherit !important;
}
body.home main#content .page-content > p:first-of-type,
body.page-id-9 main#content .page-content > p:first-of-type {
    font-size: inherit !important;
    line-height: inherit !important;
    color: inherit !important;
    font-weight: inherit !important;
    text-align: inherit !important;
}
body.home main#content .page-content > p:first-of-type::first-letter,
body.page-id-9 main#content .page-content > p:first-of-type::first-letter {
    font-size: inherit !important;
    float: none !important;
    margin: 0 !important;
    color: inherit !important;
    font-weight: inherit !important;
}
/* Eliminate any gap between header and the hero (theme often adds top padding to main) */
body.home .site,
body.home .site-content,
body.page-id-9 .site,
body.page-id-9 .site-content {
    padding-top: 0 !important;
    margin-top: 0 !important;
}
/* Hero already breaks out of containers, but make sure top edge actually touches the header */
body.home .pm-hero,
body.page-id-9 .pm-hero { margin-top: 0 !important; }
/* Books section should sit right under the hero */
body.home .pm-books-section,
body.page-id-9 .pm-books-section { margin-top: 0 !important; }
body.page .page-content {
    max-width: 760px;
    margin: 0 auto;
    font-size: 18px;
    line-height: 2.05;
    color: #1F2421;
}
body.page .page-content p {
    text-align: justify;
    text-justify: inter-word;
    line-height: 2;
    margin-bottom: 1.2em;
}
body.page .page-header h1,
body.page .entry-title {
    font-family: 'Vazirmatn', Tahoma, sans-serif !important;
    font-weight: 800;
    font-size: 42px;
    line-height: 1.5;
    color: #1F2421;
    text-align: center;
    margin: 30px auto 24px;
    max-width: 760px;
}
body.page .page-content h2 {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-weight: 800;
    font-size: 26px;
    margin-top: 2.2em;
    color: #1F2421;
    border-bottom: 1px solid #E0DCC8;
    padding-bottom: 8px;
    line-height: 1.5;
    text-align: right;
}
body.page .page-content h3 {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-weight: 700;
    font-size: 21px;
    color: #3D4548;
    text-align: right;
    line-height: 1.5;
    margin-top: 1.8em;
}
body.page .page-content ul,
body.page .page-content ol {
    text-align: right;
    line-height: 2.1;
    padding-right: 1.6em;
}
body.page .page-content blockquote {
    background: #F8F6F0;
    border-right: 4px solid #9CAB52;
    padding: 14px 22px;
    margin: 22px auto;
    color: #2A2E30;
    border-radius: 6px;
    max-width: 720px;
}
body.page .page-content a {
    color: #5B6E32;
    text-decoration: underline;
    text-decoration-color: #D4A847;
    text-underline-offset: 3px;
}
body.page .page-content a:hover {
    color: #9CAB52;
}
@media (max-width: 720px) {
    body.page main#content {
        padding: 22px 18px;
        margin: 14px 12px 24px;
        border-radius: 10px;
    }
    body.page .page-content { font-size: 16px; }
    body.page .page-header h1, body.page .entry-title { font-size: 30px; }
    body.page .page-content h2 { font-size: 22px; }
    body.page .page-content h3 { font-size: 19px; }
}
</style>
    <?php
}, 100);
