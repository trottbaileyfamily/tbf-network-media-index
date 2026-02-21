<?php
/*
 * File: includes/class-tbfnmi-admin.php
 * Version: 4.0.1
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Admin {

  public static function init() {
    add_action('network_admin_menu', [__CLASS__, 'menu']);
    add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu() {
    

    // Single-site settings page
    add_options_page(
      __('TBF Network Media Index', 'tbf-network-media-index'),
      __('Network Media Index', 'tbf-network-media-index'),
      'manage_options',
      'tbfnmi-settings',
      [__CLASS__, 'render']
    );
  }

  public static function render() {
    if ( is_multisite() ) {
      if ( ! current_user_can('manage_network_options') ) wp_die('Forbidden');
      $saved = get_site_option('tbfnmi_settings', []);
    } else {
      if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
      $saved = get_option('tbfnmi_settings', []);
    }

    // Local defaults (so admin never fatals if core class is out-of-sync)
    $defaults = [
      'who_can_browse' => 'uploaders',
      'insert_mode'    => 'proxy',
      'per_page'       => 60,
      'max_sites'      => 5000,
    ];

    // If the main plugin class provides defaults(), merge them in
    if ( class_exists('TBF_Network_Media_Index') && method_exists('TBF_Network_Media_Index', 'defaults') ) {
      $defaults = array_merge($defaults, (array) TBF_Network_Media_Index::defaults());
    }

    $s = array_merge($defaults, is_array($saved) ? $saved : []);

    if ( isset($_POST['tbfnmi_save']) && check_admin_referer('tbfnmi_save_settings') ) {
      $s['who_can_browse'] = in_array($_POST['who_can_browse'] ?? 'uploaders', ['uploaders','admins','superadmins'], true)
        ? sanitize_text_field($_POST['who_can_browse'])
        : 'uploaders';

      $s['insert_mode'] = in_array($_POST['insert_mode'] ?? 'proxy', ['proxy','url'], true)
        ? sanitize_text_field($_POST['insert_mode'])
        : 'proxy';

      $s['per_page']  = max(10, min(200, (int)($_POST['per_page'] ?? 60)));
      $s['max_sites'] = max(1, min(20000, (int)($_POST['max_sites'] ?? 5000)));

      if ( is_multisite() ) {
        update_site_option('tbfnmi_settings', $s);
      } else {
        update_option('tbfnmi_settings', $s);
      }

      echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $who = $s['who_can_browse'] ?? 'uploaders';
    $insert = $s['insert_mode'] ?? 'proxy';
    $per_page = (int) ($s['per_page'] ?? 60);
    $max_sites = (int) ($s['max_sites'] ?? 5000);

    echo '<div class="wrap">';
    echo '<h1>TBF Network Media Index</h1>';
    echo '<p>Browse and insert media from any site in the multisite network without copying files.</p>';

    echo '<form method="post">';
    wp_nonce_field('tbfnmi_save_settings');

    echo '<table class="form-table" role="presentation">';

    echo '<tr>';
    echo '<th scope="row"><label for="who_can_browse">Who can browse Network Media?</label></th>';
    echo '<td>';
    echo '<select name="who_can_browse" id="who_can_browse">';
    echo '<option value="uploaders"' . selected($who, 'uploaders', false) . '>Uploaders (can upload files)</option>';
    echo '<option value="admins"' . selected($who, 'admins', false) . '>Admins (manage options)</option>';
    echo '<option value="superadmins"' . selected($who, 'superadmins', false) . '>Super Admins only</option>';
    echo '</select>';
    echo '<p class="description">Controls which logged-in roles can open the Network Media modal.</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="insert_mode">Insert Mode</label></th>';
    echo '<td>';
    echo '<select name="insert_mode" id="insert_mode">';
    echo '<option value="proxy"' . selected($insert, 'proxy', false) . '>Proxy attachment (recommended)</option>';
    echo '<option value="url"' . selected($insert, 'url', false) . '>Direct URL</option>';
    echo '</select>';
    echo '<p class="description">Proxy mode improves editor compatibility (featured image, galleries) while keeping the file remote.</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="per_page">Items per page</label></th>';
    echo '<td>';
    echo '<input type="number" min="10" max="200" step="1" name="per_page" id="per_page" value="' . esc_attr($per_page) . '">';
    echo '<p class="description">Controls how many items are loaded per page in the Network Media browser.</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="max_sites">Max sites to scan/list</label></th>';
    echo '<td>';
    echo '<input type="number" min="1" max="20000" step="1" name="max_sites" id="max_sites" value="' . esc_attr($max_sites) . '">';
    echo '<p class="description">Safety cap for very large networks.</p>';
    echo '</td>';
    echo '</tr>';

    echo '</table>';

    echo '<p>';
    echo '<button type="submit" class="button button-primary" name="tbfnmi_save" value="1">Save Settings</button>';
    echo '</p>';

    echo '</form>';
    echo '</div>';
  }
}
