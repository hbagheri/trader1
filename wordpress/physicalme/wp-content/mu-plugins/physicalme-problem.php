<?php
/**
 * Plugin Name: PhysicalMe — Step-by-Step Problems
 * Description: استایل و JS برای بلوک‌های «تمرین» با حل گام‌به‌گام (همه گام‌ها بسته‌اند تا دانش‌آموز خودش کلیک کند)
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
    ?>
<style id="physicalme-problem-style">
/* ─── Problem container ─── */
.pm-problem {
    background: #FBF6E3;
    border: 2px solid #D4A847;
    border-radius: 14px;
    padding: 24px 26px 18px;
    margin: 32px 0;
    box-shadow: 0 4px 14px rgba(212,168,71,0.12);
}
.pm-problem-title {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-size: 20px;
    font-weight: 800;
    color: #3D4548;
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pm-problem-title::before { content: "📝"; font-size: 22px; }
.pm-problem-statement {
    color: #2A2E30;
    font-size: 17px;
    line-height: 1.95;
    margin: 0 0 18px;
}

/* ─── Step / hint / answer blocks ─── */
.pm-problem details {
    background: #FFFFFF;
    border: 1px solid #E0DCC8;
    border-radius: 8px;
    margin: 10px 0;
    overflow: hidden;
}
.pm-problem details[open] {
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.pm-problem summary {
    cursor: pointer;
    padding: 13px 18px;
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #3D4548;
    display: flex;
    align-items: center;
    gap: 10px;
    list-style: none;
    user-select: none;
}
.pm-problem summary::-webkit-details-marker { display: none; }
.pm-problem summary::after {
    content: "▾";
    margin-right: auto;
    color: #9CAB52;
    transition: transform .2s;
}
.pm-problem details[open] summary::after { transform: rotate(180deg); }
.pm-problem details > div {
    padding: 4px 22px 18px;
    color: #2A2E30;
    line-height: 1.95;
    border-top: 1px dashed #E0DCC8;
}

/* Hint variant */
.pm-problem .pm-hint {
    background: #FFF8E1;
    border-color: #F0CE6B;
}
.pm-problem .pm-hint summary { color: #8A6E2E; }
.pm-problem .pm-hint summary::before { content: "💡"; font-size: 18px; }

/* Step variant */
.pm-problem .pm-step summary::before {
    content: counter(pm-step);
    counter-increment: pm-step;
    background: #5B6E32;
    color: #FFFFFF;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    flex-shrink: 0;
}
.pm-problem { counter-reset: pm-step; }
.pm-problem .pm-step .pm-next-step {
    margin-top: 14px;
    display: inline-block;
    padding: 8px 16px;
    background: #5B6E32;
    color: #FFFFFF;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    font-family: inherit;
}
.pm-problem .pm-step .pm-next-step:hover { background: #9CAB52; }

/* Answer variant */
.pm-problem .pm-answer {
    background: linear-gradient(135deg, #E8F0D9 0%, #C8E0B0 100%);
    border-color: #5B6E32;
    margin-top: 16px;
}
.pm-problem .pm-answer summary { color: #1F4D2E; font-weight: 700; }
.pm-problem .pm-answer summary::before { content: "✅"; font-size: 18px; }
.pm-problem .pm-answer details > div,
.pm-problem .pm-answer > div { border-top-color: #9CAB52; }
</style>

<script id="physicalme-problem-script">
document.addEventListener('DOMContentLoaded', function () {
  // wire up «گام بعدی» buttons inside problem blocks
  document.querySelectorAll('.pm-problem .pm-step .pm-next-step').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var current = btn.closest('details.pm-step');
      if (!current) return;
      var next = current.nextElementSibling;
      while (next && !(next.tagName === 'DETAILS' && (next.classList.contains('pm-step') || next.classList.contains('pm-answer')))) {
        next = next.nextElementSibling;
      }
      if (next) {
        next.open = true;
        next.scrollIntoView({behavior: 'smooth', block: 'center'});
      }
    });
  });
});
</script>
    <?php
}, 110);
