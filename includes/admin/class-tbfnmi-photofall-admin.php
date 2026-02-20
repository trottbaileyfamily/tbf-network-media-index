<?php
/**
 * File: includes/admin/class-tbfnmi-photofall-admin.php
 * Version: 4.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBFNMI_PhotoFall_Admin {

  public static function init() {
    if ( is_multisite() ) add_action('network_admin_menu', [__CLASS__, 'menu']);
    else add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
  }

  public static function menu() {
    $cap = is_multisite() ? 'manage_network_options' : 'manage_options';
    $parent = is_multisite() ? 'settings.php' : 'options-general.php';
    add_submenu_page($parent, 'Photofall', 'Photofall', $cap, 'tbf-photofall', [__CLASS__, 'render']);
  }

  public static function assets($hook) {
    if ( strpos((string)$hook, 'tbf-photofall') === false ) return;
    wp_enqueue_script('tbf-photofall-admin', TBFNMI_URL . 'assets/js/photofall-admin.js', ['jquery'], TBFNMI_VER, true);
  }

  private static function get_settings() {
    $raw = is_multisite() ? get_site_option('tbfnmi_settings', []) : get_option('tbfnmi_settings', []);
    return is_array($raw) ? $raw : [];
  }

  private static function save_settings(array $s) {
    if ( is_multisite() ) update_site_option('tbfnmi_settings', $s);
    else update_option('tbfnmi_settings', $s);
  }

  public static function render() {
    if ( ! current_user_can(is_multisite() ? 'manage_network_options' : 'manage_options') ) wp_die('Permission denied');

    $settings = class_exists('TBFNMI_Plugin') ? TBFNMI_Plugin::instance()->get_settings() : self::get_settings();

    if ( isset($_POST['tbf_pf_save']) ) {
      check_admin_referer('tbf_pf_save');
      $settings['photofall_enabled'] = ! empty($_POST['photofall_enabled']) ? 1 : 0;
      $settings['photofall_public']  = ! empty($_POST['photofall_public']) ? 1 : 0;
      $settings['photofall_page_size'] = max(24, min(200, (int)($_POST['photofall_page_size'] ?? 96)));
      $settings['photofall_cache_ttl'] = max(0, min(3600, (int)($_POST['photofall_cache_ttl'] ?? 300)));
      $settings['photofall_sitemap_chunk'] = max(200, min(5000, (int)($_POST['photofall_sitemap_chunk'] ?? 1000)));
      self::save_settings($settings);
      echo '<div class="notice notice-success"><p>Saved.</p></div>';
    }

    $photoUrl = home_url('/' . trim(TBFNMI_PHOTOFALL_BASE, '/') . '/');
    $photoIndex = home_url('/photo-sitemap-index.xml');
    $videoIndex = home_url('/video-sitemap-index.xml');

    echo '<div class="wrap"><h1>Photofall</h1>';
    echo '<p><strong>Public URL:</strong> <a href="' . esc_url($photoUrl) . '" target="_blank" rel="noopener">' . esc_html($photoUrl) . '</a></p>';
    echo '<p><strong>Photo sitemap index:</strong> <a href="' . esc_url($photoIndex) . '" target="_blank" rel="noopener">' . esc_html($photoIndex) . '</a></p>';
    echo '<p><strong>Video sitemap index:</strong> <a href="' . esc_url($videoIndex) . '" target="_blank" rel="noopener">' . esc_html($videoIndex) . '</a></p>';

    echo '<hr/><form method="post">';
    wp_nonce_field('tbf_pf_save');

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">Enable Photofall</th><td><label><input type="checkbox" name="photofall_enabled" value="1" ' . checked(1, (int)$settings['photofall_enabled'], false) . '> Enable</label></td></tr>';
    echo '<tr><th scope="row">Public access</th><td><label><input type="checkbox" name="photofall_public" value="1" ' . checked(1, (int)$settings['photofall_public'], false) . '> Public to the world</label></td></tr>';

    echo '<tr><th scope="row">Page size</th><td><input type="number" name="photofall_page_size" value="' . esc_attr((int)$settings['photofall_page_size']) . '" min="24" max="200"></td></tr>';
    echo '<tr><th scope="row">Cache TTL (seconds)</th><td><input type="number" name="photofall_cache_ttl" value="' . esc_attr((int)$settings['photofall_cache_ttl']) . '" min="0" max="3600"></td></tr>';
    echo '<tr><th scope="row">Sitemap chunk size</th><td><input type="number" name="photofall_sitemap_chunk" value="' . esc_attr((int)$settings['photofall_sitemap_chunk']) . '" min="200" max="5000"></td></tr>';

    echo '</tbody></table>';

    echo '<p><button type="submit" class="button button-primary" name="tbf_pf_save" value="1">Save Settings</button></p>';
    echo '</form>';

    echo '<hr/><ol style="line-height:1.8;">';
    echo '<li>Flush permalinks once: <strong>Settings → Permalinks → Save</strong>.</li>';
    echo '<li>Build index: <strong>Settings → Photofall Index</strong>.</li>';
    echo '<li>Submit sitemap index URLs in Google Search Console.</li>';
    echo '</ol>';

    echo '</div>';
  }
}
