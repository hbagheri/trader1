<?php
/*
Plugin Name: physicalme — multi-level menu styling + accordion (single open)
Description: Styles level-1/2/3 menu items distinctly + ensures only one sibling submenu is open at a time.
*/

if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
    ?>
<style id="physicalme-menu-acc">
/* ── استایلِ سطح‌بندی منو (روی هر دو نسخه: desktop & SmartMenus mobile) ── */

/* سطح ۱: ریاضی / تجربی */
li.menu-level-1 > a {
    font-weight: 800 !important;
    font-size: 1.05em !important;
    color: #0f3d6e !important;
    background: linear-gradient(90deg, #e8f1fb 0%, transparent 100%) !important;
    border-right: 4px solid #185fa5 !important;
}
li.menu-branch-tajrobi > a {
    border-right-color: #1d9e75 !important;
    background: linear-gradient(90deg, #e3f5ed 0%, transparent 100%) !important;
    color: #0d6b48 !important;
}
li.menu-branch-tajrobi {
    margin-top: 6px;
    padding-top: 4px;
    border-top: 2px dashed #c8c8c8 !important;
}

/* سطح ۲: دهم/یازدهم/دوازدهم */
li.menu-level-2 > a {
    font-weight: 700 !important;
    color: #1a4a6e !important;
    background: #f4f8fc !important;
    border-right: 3px solid #6b9bc7 !important;
}
li.menu-branch-tajrobi li.menu-level-2 > a {
    color: #0d6b48 !important;
    background: #ecf6f1 !important;
    border-right-color: #5dcaa5 !important;
}

/* سطح ۳: فصل‌ها */
li.menu-level-3 > a {
    font-weight: 500 !important;
    color: #2a2926 !important;
    font-size: 0.93em !important;
    background: transparent !important;
}

/* فلش آکاردئون */
li.menu-item-has-children > a::after {
    content: "▾";
    margin-right: 6px;
    font-size: 0.8em;
    display: inline-block;
    transition: transform 0.25s ease;
    opacity: 0.7;
}
li.menu-item-has-children.is-open > a::after,
li.menu-item-has-children > a[aria-expanded="true"]::after {
    transform: rotate(180deg);
    opacity: 1;
}

@media (prefers-color-scheme: dark) {
    li.menu-level-1 > a {
        background: linear-gradient(90deg, #0c2c4a 0%, transparent 100%) !important;
        color: #c8dcef !important;
    }
    li.menu-branch-tajrobi > a {
        background: linear-gradient(90deg, #0d3a2a 0%, transparent 100%) !important;
        color: #7fdcb6 !important;
    }
    li.menu-level-2 > a {
        background: #1b2a3a !important;
        color: #b8d4ed !important;
    }
    li.menu-branch-tajrobi li.menu-level-2 > a {
        background: #15302a !important;
        color: #9adfc1 !important;
    }
    li.menu-level-3 > a {
        color: #d6d4cc !important;
    }
}
</style>

<script id="physicalme-menu-acc-js">
(function(){
  function init() {
    // SmartMenus (Elementor) خودش submenuها رو با کلیک باز/بسته می‌کنه و aria-expanded می‌ذاره.
    // ما فقط می‌خوایم وقتی یه آیتم باز می‌شه، خواهرها بسته بشن (accordion: only one open per level).
    // برای آیتم‌های placeholder (#) که SmartMenus shouldn't navigate به جای navigate کردن preventDefault می‌کنیم.

    // بستنِ مستقیمِ یک submenu بدون trigger کلیک (که می‌تونه navigate رو فعال کنه)
    function closeSubmenu(link) {
      link.setAttribute('aria-expanded', 'false');
      var li = link.parentElement;
      if (!li) return;
      var ul = li.querySelector(':scope > ul.sub-menu, :scope > .sub-menu');
      if (ul) {
        ul.style.display = 'none';
        ul.classList.add('sm-nowrap');
      }
      // بستنِ همه‌ی فرزندانش (recursive)
      li.querySelectorAll('a[aria-expanded="true"]').forEach(function(child){
        child.setAttribute('aria-expanded', 'false');
        var childLi = child.parentElement;
        if (childLi) {
          var childUl = childLi.querySelector(':scope > ul.sub-menu, :scope > .sub-menu');
          if (childUl) childUl.style.display = 'none';
        }
      });
    }

    // MutationObserver: وقتی یه آیتم open می‌شه، خواهرهاش رو close کن
    var closing = false;
    var obs = new MutationObserver(function(muts){
      if (closing) return;
      muts.forEach(function(m){
        if (m.type !== 'attributes' || m.attributeName !== 'aria-expanded') return;
        var link = m.target;
        if (!link || link.tagName !== 'A') return;
        if (link.getAttribute('aria-expanded') !== 'true') return;
        var li = link.closest('li');
        if (!li || !li.parentElement) return;
        var siblings = Array.from(li.parentElement.children).filter(function(s){
          return s !== li && s.tagName === 'LI';
        });
        var toClose = [];
        siblings.forEach(function(sib){
          var sibLink = sib.querySelector(':scope > a[aria-expanded="true"]');
          if (sibLink) toClose.push(sibLink);
        });
        if (toClose.length) {
          closing = true;
          toClose.forEach(closeSubmenu);
          setTimeout(function(){ closing = false; }, 100);
        }
      });
    });
    obs.observe(document.documentElement, {
      subtree:true, attributes:true, attributeFilter:['aria-expanded']
    });

    // متد ۲: روی لینک‌های placeholder (#riazi, #tajrobi) preventDefault کنیم چون SmartMenus احتمالاً برای آنکورها navigation رو فعال نمی‌کنه ولی برای اطمینان
    document.querySelectorAll('a[href^="#riazi"], a[href^="#tajrobi"]').forEach(function(a){
      a.addEventListener('click', function(e){
        e.preventDefault();
        // SmartMenus خودش submenu رو toggle می‌کنه — ما کاری نمی‌کنیم اضافه
      });
    });
  }

  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init);
  }
})();
</script>
    <?php
});
