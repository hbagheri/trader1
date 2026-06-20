<?php
/**
 * Plugin Name: PhysicalMe — Code Copy Button
 * Description: Adds a clipboard copy button to every <pre><code> block on article/video/post pages. No code execution.
 * Version: 1.0
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (!is_singular(['article', 'video', 'post'])) return;
    ?>
<script>
(function () {
  if (window.__pmCopyInit) return;
  window.__pmCopyInit = true;

  function btnHtml(label) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span>' + label + '</span>';
  }

  function attach(pre) {
    if (pre.dataset.pmCopy === 'on') return;
    pre.dataset.pmCopy = 'on';
    var code = pre.querySelector('code');
    if (!code) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pm-copy-btn';
    btn.setAttribute('aria-label', 'کپی کد');
    btn.innerHTML = btnHtml('کپی');
    btn.addEventListener('click', function () {
      var text = code.innerText;
      var done = function (ok) {
        btn.classList.add('pm-copied');
        btn.innerHTML = btnHtml(ok ? 'کپی شد ✓' : 'خطا');
        setTimeout(function () {
          btn.classList.remove('pm-copied');
          btn.innerHTML = btnHtml('کپی');
        }, 1600);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
      } else {
        try {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          done(true);
        } catch (e) { done(false); }
      }
    });
    pre.appendChild(btn);
  }

  function scan(root) {
    (root || document).querySelectorAll('.page-content pre').forEach(attach);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(document); });
  } else {
    scan(document);
  }
})();
</script>
    <?php
});
