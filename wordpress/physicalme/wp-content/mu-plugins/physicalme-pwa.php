<?php
/**
 * Plugin Name: PhysicalMe — PWA (manifest + service worker + install)
 * Description: Turns the site into an installable PWA. Serves /manifest.webmanifest
 *              and /sw.js from the WP root via virtual routes, emits meta tags,
 *              registers SW, exposes install/notify hooks, and provides the
 *              [pm_app_download] shortcode used by /app/.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

define('PM_PWA_VERSION', '2026.06.04');
define('PM_PWA_ICON_BASE', content_url('uploads/pwa'));

/* ─────────── 1. Virtual routes for /manifest.webmanifest and /sw.js ───────────
 * Hook into `init` at priority 0 — earlier than canonical_redirect — so the
 * .js / .webmanifest paths don't get bounced by WP's URL normalization. */
add_action('init', function () {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($path === '/manifest.webmanifest') { pm_pwa_serve('manifest'); }
    if ($path === '/sw.js')                { pm_pwa_serve('sw'); }
}, 0);

function pm_pwa_serve(string $what): void {
    nocache_headers();
    if ($what === 'manifest') {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo wp_json_encode([
            'name'             => 'منِ فیزیکی — آموزش فیزیک',
            'short_name'       => 'منِ فیزیکی',
            'description'      => 'آموزش فیزیک به زبان ساده — مقاله‌ها، حلِ مسئله، فلش‌کارت و ویجت‌های تعاملی',
            'lang'             => 'fa-IR',
            'dir'              => 'rtl',
            'start_url'        => '/?utm_source=pwa',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#FBF6E3',
            'theme_color'      => '#5B6E32',
            'categories'       => ['education', 'science', 'books'],
            'id'               => '/?utm_source=pwa',
            'icons'            => [
                ['src' => PM_PWA_ICON_BASE.'/icon-192.png',         'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => PM_PWA_ICON_BASE.'/icon-512.png',         'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => PM_PWA_ICON_BASE.'/icon-512-maskable.png','sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'دروس فیزیک',  'url' => '/chapter/dahom-ram/',          'icons' => [['src' => PM_PWA_ICON_BASE.'/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'همه‌ی مقالات', 'url' => '/articles/',                   'icons' => [['src' => PM_PWA_ICON_BASE.'/icon-192.png', 'sizes' => '192x192']]],
            ],
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($what === 'sw') {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
        echo pm_pwa_sw_script();
        exit;
    }
}

/* ─────────── 2. Service worker source ─────────── */
function pm_pwa_sw_script(): string {
    $v   = PM_PWA_VERSION;
    $ico = esc_url(PM_PWA_ICON_BASE.'/icon-192.png');
    return <<<JS
// منِ فیزیکی — service worker v{$v}
const VERSION = '{$v}';
const SHELL_CACHE  = 'pm-shell-'  + VERSION;
const PAGE_CACHE   = 'pm-pages-'  + VERSION;
const ASSET_CACHE  = 'pm-assets-' + VERSION;
const IMAGE_CACHE  = 'pm-images-' + VERSION;

const SHELL = ['/?utm_source=pwa', '/manifest.webmanifest', '/app/'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(SHELL_CACHE).then((c) => c.addAll(SHELL)).catch(() => null));
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((k) => !k.endsWith(VERSION)).map((k) => caches.delete(k)));
        await self.clients.claim();
        const clients = await self.clients.matchAll({type: 'window'});
        clients.forEach((c) => c.postMessage({type: 'pm:updated', version: VERSION}));
    })());
});

self.addEventListener('message', (e) => {
    if (!e.data) return;
    if (e.data.type === 'SKIP_WAITING') self.skipWaiting();
    if (e.data.type === 'CLEAR_CACHE') {
        e.waitUntil(caches.keys().then((ks) => Promise.all(ks.map((k) => caches.delete(k)))));
    }
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
        e.respondWith((async () => {
            try {
                const fresh = await fetch(req);
                const cache = await caches.open(PAGE_CACHE);
                cache.put(req, fresh.clone());
                return fresh;
            } catch (err) {
                const cached = await caches.match(req);
                return cached || caches.match('/?utm_source=pwa');
            }
        })());
        return;
    }

    if (req.destination === 'image') {
        e.respondWith((async () => {
            const cached = await caches.match(req);
            if (cached) return cached;
            try {
                const fresh = await fetch(req);
                const cache = await caches.open(IMAGE_CACHE);
                cache.put(req, fresh.clone());
                return fresh;
            } catch (err) { return cached; }
        })());
        return;
    }

    if (['style','script','font'].includes(req.destination)) {
        e.respondWith((async () => {
            const cache = await caches.open(ASSET_CACHE);
            const cached = await cache.match(req);
            const fresh = fetch(req).then((res) => {
                if (res && res.ok) cache.put(req, res.clone());
                return res;
            }).catch(() => cached);
            return cached || fresh;
        })());
        return;
    }
});

self.addEventListener('push', (e) => {
    let data = { title: 'منِ فیزیکی', body: 'محتوای جدیدی منتشر شد', url: '/' };
    if (e.data) {
        try { data = Object.assign(data, e.data.json()); } catch (err) { data.body = e.data.text(); }
    }
    e.waitUntil(self.registration.showNotification(data.title, {
        body: data.body,
        icon: '{$ico}',
        badge: '{$ico}',
        lang: 'fa-IR',
        dir: 'rtl',
        data: { url: data.url || '/' },
    }));
});

self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const target = (e.notification.data && e.notification.data.url) || '/';
    e.waitUntil(clients.matchAll({type: 'window'}).then((wins) => {
        for (const w of wins) { if (w.url.includes(target) && 'focus' in w) return w.focus(); }
        return clients.openWindow(target);
    }));
});
JS;
}

/* ─────────── 3. Inject PWA meta tags + SW registration script ─────────── */
add_action('wp_head', function () {
    $ico = PM_PWA_ICON_BASE;
    ?>
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#5B6E32">
<meta name="application-name" content="منِ فیزیکی">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="منِ فیزیکی">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" href="<?php echo esc_url($ico); ?>/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url($ico); ?>/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="<?php echo esc_url($ico); ?>/icon-512.png">
    <?php
}, 6);

add_action('wp_footer', function () {
    ?>
<script id="pm-pwa-register">
(function () {
    if (!('serviceWorker' in navigator)) return;
    let deferredPrompt = null;
    let registration   = null;

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        document.documentElement.classList.add('pm-pwa-installable');
        window.dispatchEvent(new CustomEvent('pm:installable'));
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        document.documentElement.classList.remove('pm-pwa-installable');
        document.documentElement.classList.add('pm-pwa-installed');
        window.dispatchEvent(new CustomEvent('pm:installed'));
    });

    window.pmPwa = {
        canInstall: function () { return !!deferredPrompt; },
        install: async function () {
            if (!deferredPrompt) return { ok: false, reason: 'not-available' };
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            deferredPrompt = null;
            return { ok: choice.outcome === 'accepted', outcome: choice.outcome };
        },
        isStandalone: function () {
            return window.matchMedia('(display-mode: standalone)').matches ||
                   window.navigator.standalone === true;
        },
        notifyPermission: function () { return Notification.permission; },
        requestPush: async function () {
            if (!('PushManager' in window) || !registration) return { ok: false, reason: 'unsupported' };
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') return { ok: false, reason: 'denied' };
            const vapid = document.querySelector('meta[name="pm-vapid-key"]');
            if (!vapid || !vapid.content) return { ok: true, perm: 'granted', subscribed: false };
            try {
                const sub = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: vapid.content,
                });
                await fetch('/wp-json/pm/v1/push/subscribe', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(sub),
                });
                return { ok: true, perm: 'granted', subscribed: true };
            } catch (err) { return { ok: false, reason: err.message }; }
        },
        forceUpdate: async function () {
            if (!registration) return false;
            await registration.update();
            if (registration.waiting) {
                registration.waiting.postMessage({type: 'SKIP_WAITING'});
            }
            return true;
        },
    };

    navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(function (reg) {
            registration = reg;
            if (reg.waiting) showUpdateToast();
            reg.addEventListener('updatefound', function () {
                const nw = reg.installing;
                if (!nw) return;
                nw.addEventListener('statechange', function () {
                    if (nw.state === 'installed' && navigator.serviceWorker.controller) showUpdateToast();
                });
            });
            navigator.serviceWorker.addEventListener('message', function (e) {
                if (e.data && e.data.type === 'pm:updated') {
                    if (sessionStorage.getItem('pm-reloading') !== '1') {
                        sessionStorage.setItem('pm-reloading', '1');
                        window.location.reload();
                    }
                }
            });
        })
        .catch(function () { /* silent */ });

    function showUpdateToast() {
        if (document.getElementById('pm-update-toast')) return;
        const t = document.createElement('div');
        t.id = 'pm-update-toast';
        t.innerHTML = '<span>🆕 نسخه‌ی جدیدِ سایت در دسترسه</span>' +
                      '<button type="button" id="pm-update-btn">به‌روزرسانی</button>' +
                      '<button type="button" id="pm-update-dismiss" aria-label="بستن">×</button>';
        document.body.appendChild(t);
        document.getElementById('pm-update-btn').addEventListener('click', function () {
            window.pmPwa.forceUpdate();
        });
        document.getElementById('pm-update-dismiss').addEventListener('click', function () {
            t.remove();
        });
    }
})();
</script>
<style id="pm-pwa-style">
#pm-update-toast {
    position: fixed; bottom: 20px; right: 20px; z-index: 9999;
    background: #2A2E30; color: #FFFFFF;
    padding: 14px 18px; border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    display: flex; gap: 12px; align-items: center;
    font-family: 'Vazirmatn', Tahoma, sans-serif; font-size: 14px;
    max-width: calc(100vw - 40px); animation: pmSlideUp 0.4s ease-out;
}
#pm-update-toast button {
    background: #5B6E32; color: #FFF; border: none;
    padding: 6px 14px; border-radius: 6px; cursor: pointer;
    font-family: inherit; font-size: 13px; font-weight: 700;
}
#pm-update-btn:hover { background: #6B7E42; }
#pm-update-dismiss {
    background: transparent !important; padding: 2px 8px !important;
    font-size: 20px !important; font-weight: 400 !important; opacity: 0.7;
}
#pm-update-dismiss:hover { opacity: 1; }
@keyframes pmSlideUp { from {opacity: 0; transform: translateY(20px);} to {opacity: 1; transform: translateY(0);} }
@media (max-width: 480px) {
    #pm-update-toast { right: 12px; left: 12px; bottom: 12px; }
}
</style>
    <?php
}, 30);

/* ─────────── 4. [pm_app_download] — the app download / install page ─────────── */
add_shortcode('pm_app_download', function () {
    ob_start();
    ?>
<div class="pm-app-download">
    <div class="pm-app-hero">
        <div class="pm-app-icon-wrap">
            <img src="<?php echo esc_url(PM_PWA_ICON_BASE.'/icon-512.png'); ?>" alt="آیکون منِ فیزیکی" class="pm-app-icon" width="180" height="180" />
        </div>
        <h2 class="pm-app-title">اپ موبایلِ منِ فیزیکی</h2>
        <p class="pm-app-subtitle">آفلاین بخوان، روی صفحه‌ی اصلی موبایلت نصب کن — بدون نیاز به Google Play یا App Store.</p>
    </div>

    <div class="pm-install-grid">
        <div class="pm-install-card pm-card-android">
            <div class="pm-card-head"><span class="pm-card-emoji">🤖</span><h3>اندروید</h3></div>
            <p>روی موبایلِ اندرویدی، دکمه‌ی زیر رو بزن. مرورگر اپ رو روی صفحه‌ی اصلی موبایلت نصب می‌کنه.</p>
            <button type="button" class="pm-btn pm-btn-primary" id="pm-install-btn" disabled>نصب اپ</button>
            <div class="pm-install-hint" id="pm-install-hint">
                <small>اگه دکمه‌ی نصب فعال نیست:<br>از منوی Chrome (سه‌نقطه‌ی بالا-راست) → <strong>«افزودن به صفحه‌ی اصلی»</strong></small>
            </div>
        </div>

        <div class="pm-install-card pm-card-ios">
            <div class="pm-card-head"><span class="pm-card-emoji">🍎</span><h3>iOS / آیفون</h3></div>
            <p>روی Safari، دکمه‌ی «اشتراک‌گذاری» (مربع با فلش بالا) رو بزن، بعد «<strong>Add to Home Screen</strong>» رو انتخاب کن.</p>
            <ol class="pm-ios-steps">
                <li>Safari → دکمه‌ی Share ⬆️</li>
                <li>پایین بکش → "Add to Home Screen"</li>
                <li>اسم رو تأیید کن → "Add"</li>
            </ol>
        </div>

        <div class="pm-install-card pm-card-apk">
            <div class="pm-card-head"><span class="pm-card-emoji">📦</span><h3>دانلودِ مستقیم APK</h3></div>
            <p>اگه می‌خوای فایلِ APK رو مستقیم دانلود کنی و نصب کنی (بدون مرورگر):</p>
            <a class="pm-btn pm-btn-secondary pm-btn-disabled" href="#" aria-disabled="true">دانلود APK <small>(به‌زودی)</small></a>
            <div class="pm-install-hint">
                <small>برای نصبِ APK باید «منابعِ ناشناس» (Unknown Sources) در تنظیمات گوشی اجازه داشته باشه.</small>
            </div>
        </div>

        <div class="pm-install-card pm-card-notify">
            <div class="pm-card-head"><span class="pm-card-emoji">🔔</span><h3>اعلانِ مقالاتِ جدید</h3></div>
            <p>وقتی مقاله‌ی جدیدی منتشر می‌شه یا آپدیتِ مهمی هست، خبردارت می‌کنیم.</p>
            <button type="button" class="pm-btn pm-btn-outline" id="pm-notify-btn">فعالسازیِ اعلان</button>
            <div class="pm-install-hint" id="pm-notify-status"></div>
        </div>
    </div>

    <div class="pm-app-features">
        <h3>چی به‌دست می‌آری؟ 🎁</h3>
        <ul>
            <li>📚 <strong>آفلاین خواندن</strong> — مقاله رو یک‌بار باز کن، بعداً بدون اینترنت بخون</li>
            <li>⚡ <strong>سریع‌تر</strong> — صفحه‌ها cached می‌شن، تجربه‌ی native</li>
            <li>🔔 <strong>اعلانِ مقاله‌ی جدید</strong> — جا نمونی از آپدیت‌ها</li>
            <li>🏠 <strong>آیکونِ مستقل روی صفحه‌ی اصلی</strong> — بدون باز کردنِ مرورگر</li>
            <li>🆕 <strong>آپدیتِ خودکار</strong> — همیشه آخرین نسخه</li>
        </ul>
    </div>
</div>

<style>
.pm-app-download { max-width: 1100px; margin: 0 auto; padding: 24px 18px 48px; font-family: 'Vazirmatn', Tahoma, sans-serif; }
.pm-app-hero { text-align: center; padding: 32px 16px; background: linear-gradient(180deg, #FBF6E3 0%, #F8F0CC 100%); border-radius: 18px; margin-bottom: 28px; border: 1px solid rgba(180,140,40,0.20); }
.pm-app-icon-wrap { display: inline-block; padding: 12px; background: #FFFFFF; border-radius: 28px; box-shadow: 0 10px 24px rgba(60,50,30,0.18); margin-bottom: 16px; }
.pm-app-icon { width: 110px; height: 110px; border-radius: 18px; display: block; }
.pm-app-title { font-family: 'Aref Ruqaa', 'Vazirmatn', Tahoma, sans-serif !important; font-size: 44px !important; font-weight: 400 !important; color: #1F2421 !important; margin: 0 0 8px !important; line-height: 1.2 !important; }
.pm-app-subtitle { font-size: 16px; color: #3D4548; max-width: 600px; margin: 0 auto; line-height: 1.9; }
.pm-install-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-bottom: 28px; }
.pm-install-card { background: #FFFFFF; border: 1px solid #E8E2CC; border-radius: 14px; padding: 22px 24px; box-shadow: 0 4px 14px rgba(60,50,30,0.08); }
.pm-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.pm-card-emoji { font-size: 32px; line-height: 1; }
.pm-install-card h3 { margin: 0 !important; font-size: 22px !important; color: #1F2421 !important; font-weight: 800 !important; font-family: 'Vazirmatn', Tahoma, sans-serif !important; }
.pm-install-card p { font-size: 14px; line-height: 1.9; color: #3D4548; margin: 0 0 14px; }
.pm-btn { display: inline-block; padding: 12px 20px; border-radius: 8px; font-family: 'Vazirmatn', Tahoma, sans-serif; font-size: 15px; font-weight: 700; text-decoration: none; cursor: pointer; border: 2px solid transparent; transition: all .15s; }
.pm-btn-primary { background: #5B6E32 !important; color: #FFFFFF !important; border-color: #5B6E32 !important; }
.pm-btn-primary:hover:not(:disabled) { background: #4A5828 !important; }
.pm-btn-primary:disabled { background: #B8B8B8 !important; border-color: #B8B8B8 !important; cursor: not-allowed; opacity: 0.7; }
.pm-btn-secondary { background: #D4A847 !important; color: #1F2421 !important; border-color: #D4A847 !important; }
.pm-btn-disabled { opacity: 0.55; pointer-events: none; }
.pm-btn-outline { background: transparent !important; color: #5B6E32 !important; border-color: #5B6E32 !important; }
.pm-btn-outline:hover { background: #5B6E32 !important; color: #FFFFFF !important; }
.pm-install-hint { margin-top: 10px; color: #6B6B6B; font-size: 12px; line-height: 1.7; }
.pm-ios-steps { list-style: none; padding: 0; margin: 8px 0 0; counter-reset: pm-ios; }
.pm-ios-steps li { counter-increment: pm-ios; padding: 8px 8px 8px 38px; position: relative; font-size: 14px; color: #2A2E30; background: #FBF6E3; border-radius: 8px; margin-bottom: 6px; }
.pm-ios-steps li::before { content: counter(pm-ios); position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 22px; height: 22px; background: #5B6E32; color: #FFF; border-radius: 50%; text-align: center; line-height: 22px; font-size: 12px; font-weight: 700; }
.pm-app-features { background: #F8F6F0; border-radius: 14px; padding: 22px 26px; border: 1px solid #E8E2CC; }
.pm-app-features h3 { font-size: 22px !important; color: #1F2421 !important; margin: 0 0 12px !important; font-weight: 800 !important; font-family: 'Vazirmatn', Tahoma, sans-serif !important; }
.pm-app-features ul { list-style: none; padding: 0; margin: 0; }
.pm-app-features li { padding: 8px 0; font-size: 15px; line-height: 1.9; color: #2A2E30; border-bottom: 1px dashed #E8E2CC; }
.pm-app-features li:last-child { border-bottom: none; }
@media (max-width: 720px) {
    .pm-install-grid { grid-template-columns: 1fr; }
    .pm-app-title { font-size: 32px !important; }
    .pm-app-icon { width: 92px; height: 92px; }
    .pm-app-subtitle { font-size: 14px; }
}
</style>

<script>
(function () {
    var installBtn = document.getElementById('pm-install-btn');
    var hintBox    = document.getElementById('pm-install-hint');
    var notifyBtn  = document.getElementById('pm-notify-btn');
    var notifyMsg  = document.getElementById('pm-notify-status');

    function setNotifyStatus(text, ok) {
        if (!notifyMsg) return;
        notifyMsg.innerHTML = '<small style="color:' + (ok ? '#1F4D2E' : '#8A4040') + '">' + text + '</small>';
    }

    function refreshInstallBtn() {
        if (!installBtn) return;
        if (window.pmPwa && window.pmPwa.isStandalone()) {
            installBtn.textContent = 'اپ از قبل نصبه ✓';
            installBtn.disabled = true;
            if (hintBox) hintBox.innerHTML = '<small style="color:#1F4D2E">اپ روی این دستگاه از قبل نصب شده. می‌تونی از صفحه‌ی اصلی بازش کنی.</small>';
            return;
        }
        if (window.pmPwa && window.pmPwa.canInstall()) {
            installBtn.disabled = false;
            installBtn.textContent = 'نصبِ اپ روی این دستگاه';
        } else {
            installBtn.disabled = true;
        }
    }

    if (installBtn) {
        installBtn.addEventListener('click', async function () {
            if (!window.pmPwa) return;
            installBtn.textContent = 'در حالِ نصب…';
            const r = await window.pmPwa.install();
            if (r.ok) { installBtn.textContent = 'نصب موفق ✓'; }
            else { installBtn.textContent = 'نصب اپ'; refreshInstallBtn(); }
        });
        window.addEventListener('pm:installable', refreshInstallBtn);
        window.addEventListener('pm:installed',   refreshInstallBtn);
        setTimeout(refreshInstallBtn, 800);
    }

    if (notifyBtn) {
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                notifyBtn.textContent = 'اعلان فعاله ✓';
                setNotifyStatus('اعلان‌ها برای این دستگاه فعال‌ـن. وقتی محتوای جدید منتشر بشه، خبردارت می‌کنیم.', true);
            } else if (Notification.permission === 'denied') {
                notifyBtn.textContent = 'اعلان مسدوده';
                notifyBtn.disabled = true;
                setNotifyStatus('در تنظیمات مرورگر، اعلانِ این سایت رو مسدود کردی.', false);
            }
        } else {
            notifyBtn.disabled = true;
            setNotifyStatus('این مرورگر اعلان رو پشتیبانی نمی‌کنه.', false);
        }
        notifyBtn.addEventListener('click', async function () {
            if (!window.pmPwa) return;
            notifyBtn.textContent = 'در حال درخواست…';
            const r = await window.pmPwa.requestPush();
            if (r.perm === 'granted') {
                notifyBtn.textContent = 'اعلان فعال ✓';
                setNotifyStatus(r.subscribed ? 'با موفقیت ثبت شد!' : 'اجازه‌ی اعلان فعال شد. به‌محضِ راه‌اندازیِ سرورِ اعلان، خبرها برات می‌اد.', true);
            } else if (r.reason === 'denied') {
                notifyBtn.textContent = 'مسدود شد';
                setNotifyStatus('درخواست رد شد. از تنظیمات مرورگر می‌تونی فعالش کنی.', false);
            } else {
                notifyBtn.textContent = 'دوباره تلاش';
                setNotifyStatus('پشتیبانی نشد یا مشکل پیش اومد: ' + (r.reason || ''), false);
            }
        });
    }
})();
</script>
    <?php
    return ob_get_clean();
});

register_activation_hook(__FILE__, 'flush_rewrite_rules');
add_action('admin_init', function () {
    if (get_option('pm_pwa_flushed') !== PM_PWA_VERSION) {
        flush_rewrite_rules(false);
        update_option('pm_pwa_flushed', PM_PWA_VERSION);
    }
});
