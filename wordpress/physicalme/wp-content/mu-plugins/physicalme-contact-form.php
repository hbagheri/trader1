<?php
/**
 * Contact Form Handler with Database Storage & Google reCAPTCHA v3
 */

// Google reCAPTCHA v3 Keys
define('RECAPTCHA_SITE_KEY', '6Lc0XiotAAAAACi7cW-1tt27lFq7sLFM43fLIULe');
define('RECAPTCHA_SECRET_KEY', '6Lc0XiotAAAAAJrUE5aCNY2ta0oE8DhBpKz7-tqn');

// Create table on plugin activation
register_activation_hook(__FILE__, 'physicalme_create_contact_table');

function physicalme_create_contact_table() {
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $table_name = $wpdb->prefix . 'physicalme_contacts';

  $sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    email varchar(255) NOT NULL,
    subject varchar(255) NOT NULL,
    message longtext NOT NULL,
    file_url varchar(500),
    file_name varchar(255),
    ip_address varchar(45),
    captcha_score float,
    status varchar(20) DEFAULT 'new',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY status (status),
    KEY created_at (created_at)
  ) $charset_collate;";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}

// Create table on plugin load
physicalme_create_contact_table();

// Add admin menu for contact submissions
add_action('admin_menu', function() {
  add_submenu_page(
    'tools.php',
    'پیام‌های تماس',
    'پیام‌های تماس',
    'manage_options',
    'physicalme-contacts',
    'physicalme_render_contact_list'
  );
});

// Render contact list in admin
function physicalme_render_contact_list() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'physicalme_contacts';

  // Handle delete action
  if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_admin_referer('delete_contact_' . $_GET['id']);
    $wpdb->delete($table_name, ['id' => intval($_GET['id'])]);
    echo '<div class="notice notice-success"><p>پیام حذف شد</p></div>';
  }

  // Get contacts
  $contacts = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100");

  ?>
  <div class="wrap">
    <h1>پیام‌های تماس</h1>
    <table class="wp-list-table widefat fixed striped">
      <thead>
        <tr>
          <th>ایمیل</th>
          <th>موضوع</th>
          <th>تاریخ</th>
          <th>فایل</th>
          <th>امتیاز</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contacts as $contact): ?>
          <tr>
            <td><?php echo esc_html($contact->email); ?></td>
            <td>
              <strong><?php echo esc_html($contact->subject); ?></strong>
              <br><small><?php echo esc_html(substr($contact->message, 0, 100)); ?>...</small>
            </td>
            <td><?php echo date_i18n('Y-m-d H:i', strtotime($contact->created_at)); ?></td>
            <td>
              <?php if ($contact->file_url): ?>
                <a href="<?php echo esc_url($contact->file_url); ?>" target="_blank">
                  <?php echo esc_html($contact->file_name); ?>
                </a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td>
              <?php if ($contact->captcha_score): ?>
                <strong><?php echo number_format($contact->captcha_score, 2); ?>/1.0</strong>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td>
              <a href="<?php echo wp_nonce_url('?page=physicalme-contacts&action=delete&id=' . $contact->id, 'delete_contact_' . $contact->id); ?>"
                 onclick="return confirm('آیا می‌خواهید این پیام را حذف کنید؟')">حذف</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <style>
    .wp-list-table { max-width: 100%; }
    .wp-list-table th { text-align: right; }
    .wp-list-table td { text-align: right; }
  </style>
  <?php
}

// Add contact form shortcode
add_shortcode('physicalme_contact_form', function() {
  if (!is_admin()) {
    wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
    wp_enqueue_script('physicalme-contact-form', get_template_directory_uri() . '/../../../mu-plugins/contact-form.js', [], time(), true);
    wp_localize_script('physicalme-contact-form', 'physicalmeContact', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('physicalme_contact_nonce'),
      'siteKey' => RECAPTCHA_SITE_KEY,
    ]);
  }

  return physicalme_render_contact_form();
});

// Handle form submission
add_action('wp_ajax_nopriv_physicalme_contact_submit', 'physicalme_contact_submit');
add_action('wp_ajax_physicalme_contact_submit', 'physicalme_contact_submit');

function physicalme_contact_submit() {
  check_ajax_referer('physicalme_contact_nonce', 'nonce');

  // Check if keys are set
  if (empty(RECAPTCHA_SECRET_KEY)) {
    wp_send_json_error('⚠️ reCAPTCHA لم یتم تشکیل. لطفا با مدیر سایت تماس بگیرید');
  }

  $email = sanitize_email($_POST['email'] ?? '');
  $subject = sanitize_text_field($_POST['subject'] ?? '');
  $message = sanitize_textarea_field($_POST['message'] ?? '');
  $captcha_token = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');

  // Validation
  if (empty($email) || !is_email($email)) {
    wp_send_json_error('ایمیل نامعتبر است');
  }
  if (empty($subject) || strlen($subject) < 3) {
    wp_send_json_error('موضوع باید حداقل 3 حرف باشد');
  }
  if (empty($message) || strlen($message) < 10) {
    wp_send_json_error('متن پیام باید حداقل 10 حرف باشد');
  }

  // Verify reCAPTCHA token
  if (empty($captcha_token)) {
    wp_send_json_error('خطا در تحقق reCAPTCHA');
  }

  $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
    'body' => [
      'secret' => RECAPTCHA_SECRET_KEY,
      'response' => $captcha_token,
    ]
  ]);

  if (is_wp_error($verify_response)) {
    wp_send_json_error('خطا در تحقق reCAPTCHA');
  }

  $body = json_decode(wp_remote_retrieve_body($verify_response));

  // Check score (0.0 = bot, 1.0 = human) - accept if score >= 0.5
  if (!isset($body->success) || !$body->success || ($body->score < 0.5)) {
    wp_send_json_error('تحقق reCAPTCHA ناموفق. شما ممکن است ربات باشید');
  }

  $captcha_score = isset($body->score) ? $body->score : 0;

  // Handle file upload
  $file_url = null;
  $file_name = null;
  if (!empty($_FILES['file'])) {
    $upload = physicalme_handle_contact_upload($_FILES['file']);
    if (is_wp_error($upload)) {
      wp_send_json_error($upload->get_error_message());
    }
    $file_url = $upload['url'];
    $file_name = $upload['name'];
  }

  // Save to database
  global $wpdb;
  $table_name = $wpdb->prefix . 'physicalme_contacts';

  $inserted = $wpdb->insert($table_name, [
    'email' => $email,
    'subject' => $subject,
    'message' => $message,
    'file_url' => $file_url,
    'file_name' => $file_name,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'captcha_score' => $captcha_score,
  ]);

  if (!$inserted) {
    wp_send_json_error('خطا در ذخیره پیام در دیتابیس');
  }

  // Send email to admin
  $admin_email = get_option('admin_email');
  $headers = ['Content-Type: text/html; charset=UTF-8', "From: {$email}"];

  $body = "
    <h2>پیام تماس جدید</h2>
    <p><strong>ایمیل:</strong> {$email}</p>
    <p><strong>موضوع:</strong> {$subject}</p>
    <p><strong>پیام:</strong></p>
    <p>" . nl2br(esc_html($message)) . "</p>
    <p><small>امتیاز reCAPTCHA: " . number_format($captcha_score, 2) . "/1.0</small></p>
  ";

  if ($file_url) {
    $body .= "<p><strong>فایل پیوست:</strong> <a href='{$file_url}'>{$file_name}</a></p>";
  }

  wp_mail($admin_email, "پیام تماس: {$subject}", $body, $headers);

  wp_send_json_success('پیام شما با موفقیت ارسال شد');
}

function physicalme_handle_contact_upload($file) {
  $max_size = 20 * 1024 * 1024; // 20MB
  $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'application/pdf', 'text/plain'];

  if ($file['size'] > $max_size) {
    return new WP_Error('file_too_large', 'حجم فایل خیلی بزرگ است');
  }

  if (!in_array($file['type'], $allowed_types)) {
    return new WP_Error('invalid_type', 'نوع فایل مجاز نیست');
  }

  if (!function_exists('wp_handle_upload')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
  }

  $upload = wp_handle_upload($file, ['test_form' => false]);

  if (isset($upload['error'])) {
    return new WP_Error('upload_error', $upload['error']);
  }

  return [
    'url' => $upload['url'],
    'name' => $file['name']
  ];
}

function physicalme_render_contact_form() {
  ob_start();
  ?>
  <div class="physicalme-contact-form-wrapper">
    <form id="physicalme-contact-form" class="physicalme-contact-form">
      <div class="form-group">
        <label for="contact-email">ایمیل *</label>
        <input type="email" id="contact-email" name="email" required>
      </div>

      <div class="form-group">
        <label for="contact-subject">موضوع *</label>
        <input type="text" id="contact-subject" name="subject" required>
      </div>

      <div class="form-group">
        <label for="contact-message">متن پیام *</label>
        <textarea id="contact-message" name="message" rows="6" required></textarea>
      </div>

      <div class="form-group">
        <label for="contact-file">فایل پیوست (اختیاری)</label>
        <input type="file" id="contact-file" name="file" accept="image/*,video/*,.pdf,.txt">
      </div>

      <div class="form-group">
        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(RECAPTCHA_SITE_KEY); ?>" data-action="contact_form"></div>
      </div>

      <div class="form-group">
        <button type="submit" class="contact-submit-btn">ارسال پیام</button>
      </div>

      <div id="contact-message-status" class="contact-message-status"></div>
    </form>
  </div>

  <style>
    .physicalme-contact-form-wrapper {
      max-width: 600px;
      margin: 30px auto;
    }
    .physicalme-contact-form {
      background: #f9f9f9;
      padding: 30px;
      border-radius: 8px;
      border: 1px solid #ddd;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #333;
    }
    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-family: inherit;
      font-size: 14px;
      box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #5B6E32;
      box-shadow: 0 0 0 2px rgba(91, 110, 50, 0.1);
    }
    .contact-submit-btn {
      background: #5B6E32;
      color: white;
      padding: 12px 30px;
      border: none;
      border-radius: 4px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      width: 100%;
    }
    .contact-submit-btn:hover {
      background: #3d4620;
      transform: translateY(-2px);
    }
    .contact-submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }
    .contact-message-status {
      margin-top: 15px;
      padding: 12px;
      border-radius: 4px;
      display: none;
      text-align: right;
    }
    .contact-message-status.success {
      display: block;
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .contact-message-status.error {
      display: block;
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    .g-recaptcha {
      display: flex;
      justify-content: center;
    }
  </style>
  <?php
  return ob_get_clean();
}
