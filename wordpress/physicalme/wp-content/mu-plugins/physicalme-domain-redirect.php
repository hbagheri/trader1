<?php
/**
 * Plugin Name: PhysicalMe — Canonical Domain Redirect
 * Description: 301 every request that does NOT arrive on the canonical host
 *              to the same path on the canonical host. Fires before WP core
 *              so it catches admin, REST, sitemap, robots — paths WP's own
 *              canonical_redirect skips.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * The single source of truth for the canonical host.
 * Pulled from WP_HOME so flipping `wp option update home …` is the only switch.
 */
function pm_canonical_host(): string {
    static $host = null;
    if ($host !== null) return $host;
    $home = function_exists('home_url') ? home_url('/') : get_option('home');
    $host = parse_url($home, PHP_URL_HOST) ?: '';
    return $host;
}

function pm_redirect_to_canonical(): void {
    // CLI / cron — never redirect.
    if (defined('WP_CLI') && WP_CLI) return;
    if (php_sapi_name() === 'cli') return;
    if (defined('DOING_CRON') && DOING_CRON) return;

    $canonical = pm_canonical_host();
    if (!$canonical) return;

    $req_host = $_SERVER['HTTP_HOST'] ?? '';
    // Strip port for comparison.
    $req_host = strtolower(preg_replace('/:\d+$/', '', $req_host));
    if ($req_host === '' || $req_host === strtolower($canonical)) return;

    // Allow staging / preview hostnames to coexist if explicitly whitelisted.
    $whitelist = defined('PM_HOST_WHITELIST') ? (array) PM_HOST_WHITELIST : [];
    if (in_array($req_host, array_map('strtolower', $whitelist), true)) return;

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https' : 'http';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $target = $scheme . '://' . $canonical . $uri;

    // Cache-friendly: this redirect is permanent and identical for all visitors.
    header('Cache-Control: public, max-age=86400');
    header('Location: ' . $target, true, 301);
    exit;
}

// Run at the earliest available WordPress hook — before routing, before themes,
// before plugins that might short-circuit the request.
add_action('muplugins_loaded', 'pm_redirect_to_canonical', 1);
