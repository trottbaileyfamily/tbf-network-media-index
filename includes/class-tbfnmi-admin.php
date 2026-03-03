<?php
/*
 * File: includes/class-tbfnmi-admin.php
 * Version: 6.6.25 (Single Site Admin Menu)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Admin {

  public static function init() {
    if ( is_multisite() ) {
        add_action('network_admin_menu', [__CLASS__, 'menu']);
    } else {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }
  }

  public static function menu() {
    if ( is_multisite() ) {
        add_submenu_page(
          'settings.php',
          __('TBF Network Media Index', 'tbf-network-media-index'),
          __('Network Media Index', 'tbf-network-media-index'),
          'manage_network_options',
          'tbfnmi-settings',
          [__CLASS__, 'render']
        );
    } else {
        // Single Site: Add under Settings
        add_options_page(
            __('TBF Media Index Settings', 'tbf-network-media-index'),
            __('Media Index Config', 'tbf-network-media-index'),
            'manage_options',
            'tbfnmi-settings',
            [__CLASS__, 'render']
        );
    }
  }

  public static function render() {
    $cap = is_multisite() ? 'manage_network_options' : 'manage_options';
    if ( ! current_user_can($cap) ) {
        wp_die('Forbidden');
    }

    if ( is_multisite() ) {
      $saved = get_site_option('tbfnmi_settings', []);
    } else {
      $saved = get_option('tbfnmi_settings', []);
    }

    $defaults = [
      'who_can_browse' => 'uploaders',
      'insert_mode'    => 'proxy',
      'per_page'       => 60,
      'max_sites'      => 5000,
    ];

    $s = array_merge($defaults, is_array($saved) ? $saved : []);

    if ( isset($_POST['tbfnmi_save']) && check_admin_referer('tbfnmi_save_settings') ) {
      $s['who_can_browse'] = sanitize_text_field($_POST['who_can_browse'] ?? 'uploaders');
      $s['insert_mode'] = sanitize_text_field($_POST['insert_mode'] ?? 'proxy');
      $s['per_page']  = (int)($_POST['per_page'] ?? 60);
      $s['max_sites'] = (int)($_POST['max_sites'] ?? 5000);

      if ( is_multisite() ) {
        update_site_option('tbfnmi_settings', $s);
      } else {
        update_option('tbfnmi_settings', $s);
      }

      echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $who = $s['who_can_browse'] ?? 'uploaders';
    $insert = $s['insert_mode'] ?? 'proxy';
    
    echo '<div class="wrap">';
    echo '<h1>' . (is_multisite() ? 'Network Media Configuration' : 'Media Index Configuration') . '</h1>';
    
    echo '<form method="post">';
    wp_nonce_field('tbfnmi_save_settings');

    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row">Who can browse?</th><td>';
    echo '<select name="who_can_browse">';
    echo '<option value="uploaders"' . selected($who, 'uploaders', false) . '>Uploaders</option>';
    echo '<option value="admins"' . selected($who, 'admins', false) . '>Admins</option>';
    echo '</select></td></tr>';
    
    echo '<tr><th scope="row">Insert Mode</th><td>';
    echo '<select name="insert_mode">';
    echo '<option value="proxy"' . selected($insert, 'proxy', false) . '>Proxy (Recommended)</option>';
    echo '<option value="url"' . selected($insert, 'url', false) . '>Direct URL</option>';
    echo '</select></td></tr>';
    
    echo '</table>';
    echo '<p><button type="submit" class="button button-primary" name="tbfnmi_save" value="1">Save Changes</button></p>';
    echo '</form></div>';
  }
}