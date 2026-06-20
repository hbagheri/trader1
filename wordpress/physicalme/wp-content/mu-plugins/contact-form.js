document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('physicalme-contact-form');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = form.querySelector('.contact-submit-btn');
    const statusDiv = document.getElementById('contact-message-status');

    submitBtn.disabled = true;
    statusDiv.className = 'contact-message-status';
    statusDiv.textContent = '';

    // Check CAPTCHA - REQUIRED!
    const captchaValue = document.querySelector('textarea[name="h-captcha-response"]');
    if (!captchaValue || !captchaValue.value) {
      statusDiv.className = 'contact-message-status error';
      statusDiv.textContent = '⚠️ لطفا hCaptcha را تکمیل کنید';
      submitBtn.disabled = false;
      return;
    }

    const formData = new FormData(form);
    formData.append('action', 'physicalme_contact_submit');
    formData.append('nonce', physicalmeContact.nonce);

    fetch(physicalmeContact.ajaxUrl, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        statusDiv.className = 'contact-message-status success';
        statusDiv.textContent = '✅ ' + data.data;
        form.reset();
        if (window.hcaptcha) {
          window.hcaptcha.reset();
        }
      } else {
        statusDiv.className = 'contact-message-status error';
        statusDiv.textContent = '❌ ' + (data.data || 'خطا در ارسال پیام');
      }
      submitBtn.disabled = false;
    })
    .catch(error => {
      statusDiv.className = 'contact-message-status error';
      statusDiv.textContent = '❌ خطا: ' + error.message;
      submitBtn.disabled = false;
    });
  });
});
