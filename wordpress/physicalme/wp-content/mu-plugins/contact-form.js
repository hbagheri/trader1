document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('physicalme-contact-form');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = form.querySelector('.contact-submit-btn');
    const statusDiv = document.getElementById('contact-message-status');
    const recaptchaResponse = document.querySelector('[name="g-recaptcha-response"]');

    submitBtn.disabled = true;
    statusDiv.className = 'contact-message-status';
    statusDiv.textContent = '';

    // Check if reCAPTCHA is completed
    if (!recaptchaResponse || !recaptchaResponse.value) {
      statusDiv.className = 'contact-message-status error';
      statusDiv.textContent = '❌ لطفا reCAPTCHA را تکمیل کنید';
      submitBtn.disabled = false;
      return;
    }

    statusDiv.textContent = '⏳ در حال ارسال...';

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
        // Reset reCAPTCHA
        if (window.grecaptcha) {
          window.grecaptcha.reset();
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
