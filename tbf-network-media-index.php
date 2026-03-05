<?php
/**
 * Plugin Name:       TBF Big King Media: Multisite Shared Library +Photofall
 * Plugin URI:        https://trottbaileyfamily.com/tbf-network-media-index
 * Description:       The ultimate media library enhancement. Includes "Photofall", "Kaleeyon SEO", and "Princess Keilah Studio".
 * Version:           6.9.24
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Sherika Trott Bailey, Kimroy Bailey, Trott Bailey Family
 * Author URI:        https://trottbaileyfamily.com
 * Text Domain:       tbf-network-media-index
 * License:           GPL-2.0-or-later
 * Network:           true
 */

if ( ! defined('ABSPATH') ) exit;

define('TBFNMI_VER', '6.9.24');
define('TBFNMI_DIR', plugin_dir_path(__FILE__));
define('TBFNMI_URL', plugin_dir_url(__FILE__));

// Core Requirements
require_once TBFNMI_DIR . 'includes/class-tbfnmi-admin.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-ajax.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-proxy.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-placeholder.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-featured-media.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-visibility.php';
require_once TBFNMI_DIR . 'includes/indexer/class-tbfnmi-indexer.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-gutenberg.php'; 
require_once TBFNMI_DIR . 'includes/seo/class-tbfnmi-seo-meta.php';

// Init Core Systems
TBFNMI_Proxy::init();
TBFNMI_Featured_Media::register();
TBFNMI_Visibility::init();
TBFNMI_Admin::init();
TBFNMI_AJAX::init();
TBFNMI_Gutenberg::init(); 
TBFNMI_SEO_Meta::init();

class TBFNMI_Network_Media_Index {

  public static function init() {
    add_action('plugins_loaded', [__CLASS__, 'load_modules'], 5);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_core_assets']);
    add_filter('media_view_strings', [__CLASS__, 'add_media_tab_string']);
    add_action('add_attachment', [__CLASS__, 'auto_index_attachment']);
  }

  public static function auto_index_attachment($post_id) {
      $indexer = new TBFNMI_Indexer();
      $indexer->index_single_attachment($post_id);
  }

  public static function load_modules() {
    // 1. Elementor
    if ( defined('ELEMENTOR_VERSION') || did_action('elementor/loaded') ) {
        if ( file_exists(TBFNMI_DIR . 'includes/integrations/class-tbfnmi-elementor-support.php') ) {
            require_once TBFNMI_DIR . 'includes/integrations/class-tbfnmi-elementor-support.php';
            TBFNMI_Elementor_Support::init();
        }
    }

    // 2. Network Dashboard
    if ( (is_multisite() && is_network_admin()) || (!is_multisite() && is_admin()) ) {
        require_once TBFNMI_DIR . 'includes/admin/class-tbfnmi-network-dashboard.php';
        TBFNMI_Network_Dashboard::init();
    }

    // 3. Subsite Settings & Features
    require_once TBFNMI_DIR . 'includes/admin/class-tbfnmi-subsite-settings.php';
    TBFNMI_Subsite_Settings::init();

    require_once TBFNMI_DIR . 'includes/photofall/class-tbfnmi-photofall-router.php';
    TBFNMI_Photofall_Router::init();

    // 4. World Ruler Engine (Princess Keilah)
    require_once TBFNMI_DIR . 'includes/world-ruler/class-tbfnmi-world-ruler.php';
    TBFNMI_World_Ruler::init();

    if ( get_option('tbfnmi_version') !== TBFNMI_VER ) {
        add_action('init', [__CLASS__, 'safe_flush_rules'], 999);
    }
  }

  public static function safe_flush_rules() {
      flush_rewrite_rules();
      update_option('tbfnmi_version', TBFNMI_VER);
  }

  public static function enqueue_core_assets($hook) {
    $screen = get_current_screen();
    $valid_bases = ['post', 'page', 'upload', 'settings_page_tbfnmi-photofall-settings'];
    
    if ( ! $screen || ! in_array($screen->base, $valid_bases) ) return;

    wp_enqueue_media();
    wp_enqueue_style('tbfnmi-admin', TBFNMI_URL . 'assets/css/admin.css', [], TBFNMI_VER);
    
    wp_enqueue_script(
      'tbfnmi-modal',
      TBFNMI_URL . 'assets/js/modal.js',
      ['jquery', 'media-views', 'media-editor', 'wp-util', 'underscore', 'backbone'], 
      TBFNMI_VER,
      true
    );
    
    $s = get_option('tbfnmi_settings', ['per_page' => 60, 'max_sites' => 5000]);
    
    wp_localize_script('tbfnmi-modal', 'tbfnmi_modal_data', [
      'ajax'        => admin_url('admin-ajax.php'),
      'nonce'       => wp_create_nonce('tbfnmi_nonce'),
      'perPage'     => (int)($s['per_page'] ?? 60),
      'maxSites'    => (int)($s['max_sites'] ?? 5000),
      'placeholderId' => (int) get_option('tbfnmi_placeholder_id', 0)
    ]);
  }

  public static function add_media_tab_string($strings) {
    $label = is_multisite() ? __('Big King Media', 'tbf-network-media-index') : __('Photofall Library', 'tbf-network-media-index');
    $strings['tbfNetworkMediaTitle'] = $label;
    return $strings;
  }
}

TBFNMI_Network_Media_Index::init();