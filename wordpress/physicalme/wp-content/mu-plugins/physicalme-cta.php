<?php
/**
 * Plugin Name: PhysicalMe — Article CTA
 * Description: ضمیمه‌ی خودکار «جواب بهتری دارید؟» به پایان مقالات و ویدیوها
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_filter('the_content', function ($content) {
    // Only on single article/video/post pages, in the main loop
    if (!is_singular(['article', 'video', 'post']) || !is_main_query() || !in_the_loop()) {
        return $content;
    }

    $cta = <<<HTML
<div style="background:linear-gradient(135deg,#3D4548 0%,#5B6E32 100%);color:#F8F6F0;border-radius:14px;padding:28px 30px;margin:40px 0 24px;box-shadow:0 6px 20px rgba(61,69,72,0.25)">
    <h3 style="color:#F8F6F0;font-size:22px;margin:0 0 14px;font-weight:700">💬 جواب بهتری داری؟ یا یه سؤال جدید؟</h3>
    <p style="color:#F0EDD5;font-size:16px;line-height:1.9;margin:0">
        اگه به سؤالای بالا پاسخی داری که فکر می‌کنی <strong>روشن‌تر</strong> یا <strong>کامل‌تر</strong> از مال منه، یا یه سؤال جدید برای دانش‌آموزای دیگه داری —
        تو بخش نظرات پایین صفحه ارسال کن. هر پیامی رو می‌خونم، تأیید می‌کنم و منتشر می‌شه. این‌جوری همه از تجربه‌ی همدیگه استفاده می‌کنیم. 🌱
    </p>
</div>
HTML;

    return $content . $cta;
}, 20);
