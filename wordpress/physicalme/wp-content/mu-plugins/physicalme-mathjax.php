<?php
/**
 * Plugin Name: PhysicalMe — MathJax dollar delimiters
 * Description: تنظیم MathJax تا $...$ و $$...$$ رو هم بشناسه (نه فقط \(...\) و \[...\])
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * MathJax 3/4 by default ONLY processes \(...\) and \[...\] — not $...$ or $$...$$.
 * Inject a window.MathJax config object BEFORE the MathJax script loads,
 * so it recognizes dollar delimiters too.
 */
add_action('wp_head', function () {
    ?>
<script id="physicalme-mathjax-config">
window.MathJax = {
  tex: {
    inlineMath: [['$', '$'], ['\\(', '\\)']],
    displayMath: [['$$', '$$'], ['\\[', '\\]']],
    processEscapes: true,
    processEnvironments: true
  },
  options: {
    skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
    ignoreHtmlClass: 'tex2jax_ignore',
    processHtmlClass: 'tex2jax_process'
  }
};
</script>
<script async id="mathjax-cdn" src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <?php
}, 1);
