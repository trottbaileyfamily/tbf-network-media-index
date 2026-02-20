<?php
/**
 * Plugin Name:       TBF Network Media Index
 * Plugin URI:        https://trottbaileyfamily.com/tbf-network-media-index
 * Description:       Browse and insert media from any site in a multisite network. Includes "Photofall" - a Pinterest-style media feed.
 * Version:           6.1.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Sherika Trott Bailey, Kimroy Bailey, David Luis
 * Author URI:        https://trottbaileyfamily.com
 * Network:           true
 * License:           GPL-2.0-or-later
 */

if ( ! defined('ABSPATH') ) exit;

define('TBFNMI_VER', '6.1.1');
define('TBFNMI_DIR', plugin_dir_path(__FILE__));
define('TBFNMI_URL', plugin_dir_url(__FILE__));

// --- 1. ALWAYS LOAD: Core Media Library Features ---
require_once TBFNMI_DIR . 'includes/class-tbfnmi-admin.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-ajax.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-proxy.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-placeholder.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-featured-media.php';
require_once TBFNMI_DIR . 'includes/class-tbfnmi-visibility.php';
require_once TBFNMI_DIR . 'includes/indexer/class-tbfnmi-indexer.php';

TBFNMI_Proxy::init();
TBFNMI_Featured_Media::register();
TBFNMI_Visibility::init();
TBFNMI_Admin::init();
TBFNMI_AJAX::init();

class TBF_Network_Media_Index {

  public static function init() {
    add_action('plugins_loaded', [__CLASS__, 'load_modules']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_core_assets']);
    add_filter('media_view_strings', [__CLASS__, 'add_media_tab_string']);
    add_action('add_attachment', [__CLASS__, 'auto_index_attachment']);
  }

  public static function auto_index_attachment($post_id) {
      $indexer = new TBFNMI_Indexer();
      $indexer->index_single_attachment($post_id);
  }

  public static function load_modules() {
    // 1. Elementor Support 
    if ( defined('ELEMENTOR_VERSION') || did_action('elementor/loaded') ) {
        if ( file_exists(TBFNMI_DIR . 'includes/integrations/class-tbfnmi-elementor-support.php') ) {
            require_once TBFNMI_DIR . 'includes/integrations/class-tbfnmi-elementor-support.php';
            TBFNMI_Elementor_Support::init();
        }
    }

    // 2. Network Admin & AJAX 
    if ( is_network_admin() || wp_doing_ajax() ) {
        require_once TBFNMI_DIR . 'includes/admin/class-tbfnmi-network-dashboard.php';
        TBFNMI_Network_Dashboard::init();
        
        // NEW: Load the Vikinger Bridge integration
        if ( file_exists(TBFNMI_DIR . 'includes/integrations/class-tbfnmi-vikinger-bridge.php') ) {
            require_once TBFNMI_DIR . 'includes/integrations/class-tbfnmi-vikinger-bridge.php';
            TBFNMI_Vikinger_Bridge::init();
        }
    }

    // 3. Photofall System
    $enabled_sites = get_site_option('tbfnmi_photofall_enabled_sites', []);
    $current_blog_id = get_current_blog_id();

    if ( in_array($current_blog_id, $enabled_sites) || wp_doing_ajax() ) {
        require_once TBFNMI_DIR . 'includes/admin/class-tbfnmi-subsite-settings.php';
        TBFNMI_Subsite_Settings::init();

        require_once TBFNMI_DIR . 'includes/photofall/class-tbfnmi-photofall-router.php';
        TBFNMI_Photofall_Router::init();

        if ( get_option('tbfnmi_version') !== TBFNMI_VER ) {
            add_action('init', [__CLASS__, 'safe_flush_rules'], 999);
        }
    }
  }

  public static function safe_flush_rules() {
      flush_rewrite_rules();
      update_option('tbfnmi_version', TBFNMI_VER);
  }

  public static function enqueue_core_assets($hook) {
    // Only for standard WP Editors
    $screen = get_current_screen();
    if ( ! $screen || ! in_array($screen->base, ['post', 'page', 'upload']) ) return;

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
    wp_localize_script('tbfnmi-modal', 'TBF_NMI', [
      'ajax'        => admin_url('admin-ajax.php'),
      'nonce'       => wp_create_nonce('tbfnmi_nonce'),
      'perPage'     => (int)($s['per_page'] ?? 60),
      'maxSites'    => (int)($s['max_sites'] ?? 5000),
    ]);
  }

  public static function add_media_tab_string($strings) {
    $strings['tbfNetworkMediaTitle'] = __('TBF Network Media', 'tbf-network-media-index');
    return $strings;
  }
}

TBF_Network_Media_Index::init();
