<?php
/**
 * Plugin Name: PhysicalMe — Widget iframe auto-resize
 * Description: ارتفاع iframe ویجت‌ها رو خودکار با محتوای داخلی تنظیم می‌کنه (بدون scroll داخلی)
 * Version: 1.2
 */

if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (!is_singular(['article', 'video', 'post'])) return;
    ?>
<script id="physicalme-iframe-autosize">
(function () {
  var SAFETY_PAD = 24;
  var MAX_HEIGHT = 3000;     // sane cap — no widget should exceed this
  var MIN_HEIGHT = 200;      // ignore measurements below this (likely body-not-loaded)

  function measure(doc) {
    if (!doc || !doc.body) return 0;
    // 1) Sum bottoms of body's element children. This ignores body's own
    //    min-height/100vh stretching tricks and gives the true content extent.
    var h = 0;
    var kids = doc.body.children;
    for (var i = 0; i < kids.length; i++) {
      var el = kids[i];
      if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE') continue;
      var bottom = (el.offsetTop || 0) + el.offsetHeight;
      if (bottom > h) h = bottom;
    }
    // 2) Fallback: body.scrollHeight (rarely reliable for our widgets)
    if (h < MIN_HEIGHT) {
      var bs = doc.body.scrollHeight;
      if (bs >= MIN_HEIGHT && bs < MAX_HEIGHT) h = bs;
    }
    return h;
  }

  function fit(iframe) {
    try {
      var doc = iframe.contentDocument || iframe.contentWindow.document;
      var h = measure(doc);
      if (h < MIN_HEIGHT || h > MAX_HEIGHT) return; // skip nonsense measurements
      h += SAFETY_PAD;
      var cur = parseInt(iframe.style.height, 10) || 0;
      if (Math.abs(h - cur) > 4) iframe.style.height = h + 'px';
    } catch (e) { /* cross-origin or not loaded */ }
  }

  function setupOne(iframe) {
    if (iframe.dataset.pmAutosize === 'on') return;
    iframe.dataset.pmAutosize = 'on';

    var attached = false;
    function onLoad() {
      // Run fit a few times after load — once for initial layout,
      // again to catch JS-driven async content (canvas, charts, etc.)
      [50, 400, 1200, 2500].forEach(function (d) {
        setTimeout(function () { fit(iframe); }, d);
      });
      if (attached) return;
      attached = true;
      // Observe body resize for ongoing changes (font load, image load, JS layout)
      try {
        var doc = iframe.contentDocument || iframe.contentWindow.document;
        if (doc && doc.body && typeof ResizeObserver !== 'undefined') {
          var debounceTimer = null;
          var ro = new ResizeObserver(function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { fit(iframe); }, 80);
          });
          ro.observe(doc.body);
          // Observe first real child too — many widgets use a wrapper div
          var firstKid = [].find.call(doc.body.children, function (c) {
            return c.tagName !== 'SCRIPT' && c.tagName !== 'STYLE';
          });
          if (firstKid) ro.observe(firstKid);
        }
      } catch (e) {}
    }

    // If iframe already loaded (cached), fit immediately
    try {
      if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
        onLoad();
      }
    } catch (e) {}

    iframe.addEventListener('load', onLoad);
  }

  function setupAll() {
    document.querySelectorAll('iframe[src*="/physics-content/"]').forEach(setupOne);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupAll);
  } else {
    setupAll();
  }
  // Re-fit on window resize (responsive layout reflow)
  var resizeTimer = null;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      document.querySelectorAll('iframe[src*="/physics-content/"]').forEach(fit);
    }, 120);
  });
  // Catch iframes added later (rare, but safe)
  if (typeof MutationObserver !== 'undefined') {
    new MutationObserver(setupAll).observe(document.body, {childList: true, subtree: true});
  }
})();
</script>
    <?php
}, 50);
