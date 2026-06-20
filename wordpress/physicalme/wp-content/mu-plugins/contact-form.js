document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('physicalme-contact-form');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = form.querySelector('.contact-submit-btn');
    const statusDiv = document.getElementById('contact-message-status');

    submitBtn.disabled = true;
    statusDiv.className = 'contact-message-status';
    statusDiv.textContent = '⏳ در حال ارسال...';

    // Get reCAPTCHA token
    grecaptcha.ready(function() {
      grecaptcha.execute(physicalmeContact.siteKey, { action: 'contact_form' })
        .then(function(token) {
          const formData = new FormData(form);
          formData.append('action', 'physicalme_contact_submit');
          formData.append('nonce', physicalmeContact.nonce);
          formData.append('g-recaptcha-response', token);

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
        })
        .catch(function(error) {
          statusDiv.className = 'contact-message-status error';
          statusDiv.textContent = '❌ خطا در reCAPTCHA: ' + error;
          submitBtn.disabled = false;
        });
    });
  });
});
