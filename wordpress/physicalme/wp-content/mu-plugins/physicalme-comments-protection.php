<?php
/**
 * Plugin Name: PhysicalMe — Comments Protection
 * Description: کپچای ریاضی ساده برای ضد اسپم نظرات (بدون نیاز به سرویس بیرونی)
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Insert math captcha field into the comment form (after the comment textarea,
 * before the submit button).
 */
add_action('comment_form_after_fields', 'physicalme_render_captcha', 5);
add_action('comment_form_logged_in_after', 'physicalme_render_captcha', 5);

function physicalme_render_captcha() {
    if (current_user_can('moderate_comments')) return;   // skip for admins
    $a = rand(1, 9);
    $b = rand(1, 9);
    $token = wp_generate_uuid4();
    set_transient('pm_captcha_' . $token, $a + $b, 30 * MINUTE_IN_SECONDS);
    ?>
    <p class="comment-form-captcha" style="background:#FBF6E3;border:1px solid #D4A847;border-radius:8px;padding:14px 18px;margin:18px 0">
        <label for="pm_captcha_answer" style="display:block;font-weight:600;margin-bottom:8px">
            🔒 برای ضد اسپم: <strong style="font-size:18px;color:#5B6E32"><?= $a ?> + <?= $b ?> = ?</strong>
        </label>
        <input type="text" name="pm_captcha_answer" id="pm_captcha_answer" required autocomplete="off"
               inputmode="numeric"
               style="width:120px;padding:8px 12px;border:1px solid #C8C8B0;border-radius:6px;font-size:16px"
               placeholder="جواب…">
        <input type="hidden" name="pm_captcha_token" value="<?= esc_attr($token) ?>">
    </p>
    <?php
}

/**
 * Validate the captcha before the comment is saved.
 */
add_filter('preprocess_comment', 'physicalme_validate_captcha');

function physicalme_validate_captcha($commentdata) {
    if (current_user_can('moderate_comments')) return $commentdata;   // skip for admins

    $token = isset($_POST['pm_captcha_token']) ? sanitize_text_field($_POST['pm_captcha_token']) : '';
    $raw_answer = isset($_POST['pm_captcha_answer']) ? trim(wp_unslash($_POST['pm_captcha_answer'])) : '';
    if (!$token || $raw_answer === '') {
        wp_die(
            'لطفاً پاسخ سؤال ضد اسپم را وارد کنید.<br><a href="javascript:history.back()">بازگشت</a>',
            'پاسخ ضد اسپم نیاز است',
            ['back_link' => true]
        );
    }

    // Normalize Persian digits → Latin
    $answer = strtr($raw_answer, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
        '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
        '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);

    $expected = get_transient('pm_captcha_' . $token);
    if ($expected === false) {
        wp_die(
            'فرم منقضی شده. لطفاً صفحه را تازه‌سازی کنید و دوباره ارسال کنید.<br><a href="javascript:history.back()">بازگشت</a>',
            'فرم منقضی',
            ['back_link' => true]
        );
    }
    if ((int)$answer !== (int)$expected) {
        // keep the transient so user can try again (don't delete on failure)
        wp_die(
            'پاسخ ضد اسپم اشتباه است.<br><a href="javascript:history.back()">بازگشت</a>',
            'پاسخ اشتباه',
            ['back_link' => true]
        );
    }

    // valid — clean up
    delete_transient('pm_captcha_' . $token);
    return $commentdata;
}
