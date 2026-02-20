<?php
/**
 * File: includes/seo/class-tbfnmi-sitemaps-admin.php
 * Version: 4.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Sitemaps_Admin {
  public static function init() {
    if ( is_multisite() ) add_action('network_admin_menu', [__CLASS__, 'menu']);
    else add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu() {
    $cap = is_multisite() ? 'manage_network_options' : 'manage_options';
    $parent = is_multisite() ? 'settings.php' : 'options-general.php';
    add_submenu_page($parent, 'Photofall Sitemaps', 'Photofall Sitemaps', $cap, 'tbf-photofall-sitemaps', [__CLASS__, 'render']);
  }

  public static function render() {
    if ( ! current_user_can(is_multisite() ? 'manage_network_options' : 'manage_options') ) wp_die('Permission denied');
    $photoIndex = home_url('/photo-sitemap-index.xml');
    $videoIndex = home_url('/video-sitemap-index.xml');
    echo '<div class="wrap"><h1>Photofall Sitemaps</h1>';
    echo '<p>Submit these sitemap index URLs to Google Search Console:</p>';
    echo '<ul style="font-size:14px; line-height:1.8;">';
    echo '<li><a href="' . esc_url($photoIndex) . '" target="_blank" rel="noopener">' . esc_html($photoIndex) . '</a></li>';
    echo '<li><a href="' . esc_url($videoIndex) . '" target="_blank" rel="noopener">' . esc_html($videoIndex) . '</a></li>';
    echo '</ul>';
    echo '<p class="description">If these return 404, flush permalinks once (Settings → Permalinks → Save).</p>';
    echo '</div>';
  }
}
