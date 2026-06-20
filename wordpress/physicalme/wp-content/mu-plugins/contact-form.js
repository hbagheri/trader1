document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('physicalme-contact-form');
  if (!form) return;
  
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = form.querySelector('.contact-submit-btn');
    const statusDiv = document.getElementById('contact-message-status');
    const fileInput = document.getElementById('contact-file');
    
    submitBtn.disabled = true;
    statusDiv.className = 'contact-message-status';
    statusDiv.textContent = '';
    
    // Check file size
    if (fileInput.files.length > 0) {
      const file = fileInput.files[0];
      if (file.size > physicalmeContact.maxSize) {
        statusDiv.className = 'contact-message-status error';
        statusDiv.textContent = 'حجم فایل بیش از 20 مگابایت است';
        submitBtn.disabled = false;
        return;
      }
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
        statusDiv.textContent = data.data;
        form.reset();
      } else {
        statusDiv.className = 'contact-message-status error';
        statusDiv.textContent = data.data || 'خطا در ارسال پیام';
      }
      submitBtn.disabled = false;
    })
    .catch(error => {
      statusDiv.className = 'contact-message-status error';
      statusDiv.textContent = 'خطا در ارسال پیام: ' + error.message;
      submitBtn.disabled = false;
    });
  });
});
