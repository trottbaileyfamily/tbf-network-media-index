<?php
/**
 * Plugin Name:       TBF Network Media Index
 * Plugin URI:        https://trottbaileyfamily.com/tbf-network-media-index
 * Description:       Browse and insert media from any site in a multisite network without copying/moving files. Virtual index + optional DB-only proxy for featured images.
 * Version:           1.0.27
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Kimroy Bailey, Trott Bailey Family, David Luis
 * Author URI:        https://trottbaileyfamily.com/
 * Network:           true
 * Text Domain:       tbf-network-media-index
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined('ABSPATH') ) exit;

define('TBF_NMI_VER', '1.0.27');
define('TBF_NMI_DIR', plugin_dir_path(__FILE__));
define('TBF_NMI_URL', plugin_dir_url(__FILE__));

/**
 * Graceful failure if required files are missing.
 */
function tbf_nmi_missing_files_notice() {
  if ( ! is_admin() ) return;
  echo '<div class="notice notice-error"><p><strong>TBF Network Media Index</strong> is missing required files.</p>';
  echo '<p>Re-upload the full plugin folder including <code>includes/</code> and <code>assets/</code>.</p></div>';
}

$required = [
  'includes/class-tbf-nmi-admin.php',
  'includes/class-tbf-nmi-ajax.php',
  'includes/class-tbf-nmi-proxy.php',
];

foreach ( $required as $rel ) {
  if ( ! file_exists( TBF_NMI_DIR . $rel ) ) {
    add_action('admin_notices', 'tbf_nmi_missing_files_notice');
    add_action('network_admin_notices', 'tbf_nmi_missing_files_notice');
    return;
  }
}

require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-admin.php';
require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-ajax.php';
require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-proxy.php';
require_once __DIR__ . '/includes/class-tbf-nmi-placeholder.php';
TBF_NMI_Placeholder::init();


class TBF_Network_Media_Index {

  public static function init() {
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
    // VITAL: Register the tab label string for the Media UI
    add_filter('media_view_strings', [__CLASS__, 'add_media_tab_string']);
  }

  public static function add_media_tab_string($strings) {
    $strings['tbfNetworkMediaTitle'] = __('Network Media', 'tbf-network-media-index');
    return $strings;
  }

  public static function defaults() {
    return [
      'insert_mode'    => 'proxy',
      'per_page'       => 60,
      'max_sites'      => 5000,
    ];
  }

  public static function get_settings() {
    $defaults = self::defaults();
    if ( is_multisite() ) {
      $saved = get_site_option('tbf_nmi_settings', []);
    } else {
      $saved = get_option('tbf_nmi_settings', []);
    }
    $s = is_array($saved) ? array_merge($defaults, $saved) : $defaults;
    $s['per_page']  = max(10, min(200, (int)$s['per_page']));
    $s['max_sites'] = max(1, min(20000, (int)$s['max_sites']));
    $s['insert_mode'] = in_array($s['insert_mode'], ['proxy','url'], true) ? $s['insert_mode'] : 'proxy';
    return $s;
  }

  public static function can_browse() {
    return is_user_logged_in();
  }

  public static function enqueue_assets($hook) {
    if ( ! is_admin() ) return;
    if ( ! self::can_browse() ) return;

    // Ensure core media scripts are loaded so we can patch them
    wp_enqueue_media();

    wp_enqueue_style('tbf-nmi-admin', TBF_NMI_URL . 'assets/css/admin.css', [], TBF_NMI_VER);

    wp_enqueue_script(
      'tbf-nmi-modal',
      TBF_NMI_URL . 'assets/js/modal.js',
      // Added 'media-editor' to dependencies to ensure router exists
      ['jquery', 'media-views', 'media-editor', 'wp-util', 'underscore', 'backbone'], 
      TBF_NMI_VER,
      true
    );

    wp_enqueue_script(
      'tbf-nmi-admin-page',
      TBF_NMI_URL . 'assets/js/admin-page.js',
      ['jquery', 'tbf-nmi-modal'],
      TBF_NMI_VER,
      true
    );

    $s = self::get_settings();

    wp_localize_script('tbf-nmi-modal', 'TBF_NMI', [
      'ajax'        => admin_url('admin-ajax.php'),
      'nonce'       => wp_create_nonce('tbf_nmi_nonce'),
      'insertMode'  => $s['insert_mode'],
      'perPage'     => (int)$s['per_page'],
      'maxSites'    => (int)$s['max_sites'],
      'isMultisite' => is_multisite() ? 1 : 0,
	  'placeholderId' => TBF_NMI_Placeholder::get_id(),
    ]);
  }
}

TBF_Network_Media_Index::init();
TBF_NMI_Admin::init();
TBF_NMI_AJAX::init();