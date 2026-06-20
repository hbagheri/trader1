<?php
if(!defined("ABSPATH"))exit;
define("HELLO_ELEMENTOR_VERSION","3.4.9");
define("HELLO_THEME_STYLE_URL",get_template_directory_uri()."/assets/css/");
define("HELLO_THEME_SCRIPTS_URL",get_template_directory_uri()."/assets/js/");
add_action("after_setup_theme",function(){
  register_nav_menus(["menu-1"=>"Header"]);
  add_theme_support("post-thumbnails");
  add_theme_support("title-tag");
  add_theme_support("custom-logo",["height"=>100,"width"=>350]);
});
if(!function_exists('hello_elementor_display_header_footer')){
  function hello_elementor_display_header_footer(){
    return apply_filters('hello_elementor_display_header_footer',!did_action('elementor/loaded'));
  }
}
require get_template_directory().'/includes/elementor-functions.php';
require get_template_directory().'/includes/customizer-functions.php';
add_action("wp_enqueue_scripts",function(){
  wp_enqueue_style("he",HELLO_THEME_STYLE_URL."reset.css",[],HELLO_ELEMENTOR_VERSION);
  wp_enqueue_style("het",HELLO_THEME_STYLE_URL."theme.css",[],HELLO_ELEMENTOR_VERSION);
  wp_enqueue_style("heh",HELLO_THEME_STYLE_URL."header-footer.css",[],HELLO_ELEMENTOR_VERSION);
  wp_enqueue_style("hei",HELLO_THEME_STYLE_URL."inner-pages.css",[],HELLO_ELEMENTOR_VERSION);
  wp_enqueue_style("override",HELLO_THEME_STYLE_URL."style-override-v4.css",[],HELLO_ELEMENTOR_VERSION);
});
?>
