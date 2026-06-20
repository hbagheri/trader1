<?php
/**
 * Contact Form Handler
 */

// Add contact form shortcode
add_shortcode('physicalme_contact_form', function() {
  if (!is_admin()) {
    wp_enqueue_script('physicalme-contact-form', get_template_directory_uri() . '/../../../mu-plugins/contact-form.js', [], time(), true);
    wp_localize_script('physicalme-contact-form', 'physicalmeContact', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('physicalme_contact_nonce'),
      'maxSize' => 20 * 1024 * 1024,
      'maxSizeText' => '20 MB'
    ]);
  }
  
  return physicalme_render_contact_form();
});

// Handle form submission
add_action('wp_ajax_nopriv_physicalme_contact_submit', 'physicalme_contact_submit');
add_action('wp_ajax_physicalme_contact_submit', 'physicalme_contact_submit');

function physicalme_contact_submit() {
  check_ajax_referer('physicalme_contact_nonce', 'nonce');
  
  $email = sanitize_email($_POST['email'] ?? '');
  $subject = sanitize_text_field($_POST['subject'] ?? '');
  $message = sanitize_textarea_field($_POST['message'] ?? '');
  $captcha = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
  
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
  
  // Handle file upload
  $attachments = [];
  if (!empty($_FILES['file'])) {
    $upload = physicalme_handle_contact_upload($_FILES['file']);
    if (is_wp_error($upload)) {
      wp_send_json_error($upload->get_error_message());
    }
    $attachments = $upload;
  }
  
  // Send email
  $admin_email = get_option('admin_email');
  $headers = ['Content-Type: text/html; charset=UTF-8', "From: {$email}"];
  
  $body = "
    <h2>پیام تماس جدید</h2>
    <p><strong>ایمیل:</strong> {$email}</p>
    <p><strong>موضوع:</strong> {$subject}</p>
    <p><strong>پیام:</strong></p>
    <p>" . nl2br(esc_html($message)) . "</p>
  ";
  
  $sent = wp_mail($admin_email, "پیام تماس: {$subject}", $body, $headers, $attachments);
  
  // Clean up attachment files
  foreach ($attachments as $attachment) {
    @unlink($attachment);
  }
  
  if ($sent) {
    wp_send_json_success('پیام شما با موفقیت ارسال شد');
  } else {
    wp_send_json_error('خطا در ارسال پیام');
  }
}

function physicalme_handle_contact_upload($file) {
  $max_size = 20 * 1024 * 1024; // 20MB
  $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'application/pdf', 'text/plain'];
  
  if ($file['size'] > $max_size) {
    return new WP_Error('file_too_large', 'حجم فایل بیش از 20 مگابایت است');
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
  
  return [$upload['file']];
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
        <label for="contact-file">فایل پیوست (اختیاری - حداکثر 20 MB)</label>
        <input type="file" id="contact-file" name="file" accept="image/*,video/*,.pdf,.txt">
      </div>
      
      <div class="form-group">
        <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></div>
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
    }
    .contact-submit-btn:hover {
      background: #3d4620;
      transform: translateY(-2px);
    }
    .contact-submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .contact-message-status {
      margin-top: 15px;
      padding: 12px;
      border-radius: 4px;
      display: none;
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
  </style>
  <?php
  return ob_get_clean();
}
