<?php
/**
 * Plugin Name: PhysicalMe — String Overrides
 * Description: ترجمه‌ی فارسی برای استرینگ‌های Elementor Pro که با تنظیمات قابل override نیستن
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

$pm_string_map = [
    'Read More'    => 'شروع درس',
    'Read More »'  => 'شروع درس ←',
    'No Results Found' => 'هیچ پستی پیدا نشد',
    'The page you requested could not be found. Try refining your search, or use the navigation above to locate the post.' =>
        'متأسفانه چیزی پیدا نشد. می‌تونی از منوی بالا به فصل‌های دیگه سر بزنی.',
];

add_filter('gettext', function ($translated, $original, $domain) use ($pm_string_map) {
    if (in_array($domain, ['elementor', 'elementor-pro', 'hello-elementor', 'default'], true)
        && isset($pm_string_map[$original])) {
        return $pm_string_map[$original];
    }
    return $translated;
}, 20, 3);
