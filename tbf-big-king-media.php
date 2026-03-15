<?php
/**
 * Plugin Name:       TBF Big King Media: WordPress Multisite Shared Media Library + Photofall
 * Plugin URI:        https://trottbaileyfamily.com/tbf-big-king-media
 * Description:       A WordPress Multisite shared media library plugin for browsing, searching & inserting network media without duplication.
 * Version:           7.0.4.8
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Sherika Trott Bailey, Kimroy Bailey, Trott Bailey Family
 * Author URI:        https://trottbaileyfamily.com
 * Text Domain:       tbf-big-king-media
 * License:           GPL-2.0-or-later
 * Network:           true
 */

if ( ! defined('ABSPATH') ) exit;

define('TBFBKM_VER', '7.0.4.8');
define('TBFBKM_DIR', plugin_dir_path(__FILE__));
define('TBFBKM_URL', plugin_dir_url(__FILE__));

// Core Requirements
require_once TBFBKM_DIR . 'includes/class-tbfbkm-ajax.php';
require_once TBFBKM_DIR . 'includes/class-tbfbkm-proxy.php';
require_once TBFBKM_DIR . 'includes/class-tbfbkm-placeholder.php';
require_once TBFBKM_DIR . 'includes/class-tbfbkm-featured-media.php';
require_once TBFBKM_DIR . 'includes/class-tbfbkm-visibility.php';
require_once TBFBKM_DIR . 'includes/indexer/class-tbfbkm-indexer.php';
require_once TBFBKM_DIR . 'includes/class-tbfbkm-gutenberg.php'; 
require_once TBFBKM_DIR . 'includes/seo/class-tbfbkm-seo-meta.php';

// Init Core Systems
TBFBKM_Proxy::init();
TBFBKM_Featured_Media::register();
TBFBKM_Visibility::init();
TBFBKM_Indexer::init();
TBFBKM_AJAX::init();
TBFBKM_Gutenberg::init(); 
TBFBKM_SEO_Meta::init();

class TBFBKM_Network_Media_Index {

  public static function init() {
    add_action('plugins_loaded', [__CLASS__, 'load_modules'], 5);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_core_assets']);
    add_filter('media_view_strings', [__CLASS__, 'add_media_tab_string']);
    
    // Fallback hook for individual file uploads
    add_action('add_attachment', [__CLASS__, 'auto_index_attachment']);
  }

  public static function auto_index_attachment($post_id) {
      if ( class_exists('TBFBKM_Indexer') ) {
          TBFBKM_Indexer::index_single_attachment($post_id);
      }
  }

  public static function load_modules() {
    // 1. Elementor Support
    if ( defined('ELEMENTOR_VERSION') || did_action('elementor/loaded') ) {
        if ( file_exists(TBFBKM_DIR . 'includes/integrations/class-tbfbkm-elementor-support.php') ) {
            require_once TBFBKM_DIR . 'includes/integrations/class-tbfbkm-elementor-support.php';
            TBFBKM_Elementor_Support::init();
        }
    }

    // 2. ARCHITECTURE FIX: ONLY load Network Dashboard on Multisite.
    if ( is_multisite() ) {
        require_once TBFBKM_DIR . 'includes/admin/class-tbfbkm-network-dashboard.php';
        TBFBKM_Network_Dashboard::init();
    }

    // 3. Always load Subsite Settings (It now correctly handles Single-Site natively)
    require_once TBFBKM_DIR . 'includes/admin/class-tbfbkm-subsite-settings.php';
    TBFBKM_Subsite_Settings::init();

    // 4. Core Modules
    require_once TBFBKM_DIR . 'includes/photofall/class-tbfbkm-photofall-router.php';
    TBFBKM_Photofall_Router::init();

    require_once TBFBKM_DIR . 'includes/world-ruler/class-tbfbkm-world-ruler.php';
    TBFBKM_World_Ruler::init();

    // 5. External Integrations
    if ( file_exists(TBFBKM_DIR . 'includes/integrations/class-tbfbkm-keilah-widget.php') ) {
        require_once TBFBKM_DIR . 'includes/integrations/class-tbfbkm-keilah-widget.php';
        TBFBKM_Keilah_Widget::init();
    }

    // 6. Rewrite Rules Auto-Flusher
    if ( get_option('tbfbkm_version') !== TBFBKM_VER ) {
        add_action('init', [__CLASS__, 'safe_flush_rules'], 999);
    }
  }

  public static function safe_flush_rules() {
      flush_rewrite_rules();
      update_option('tbfbkm_version', TBFBKM_VER);
  }

  public static function enqueue_core_assets($hook) {
    $screen = get_current_screen();
    
    $valid_bases = [
        'post', 'page', 'upload', 
        'media_page_tbfbkm-photofall-settings', 
        'settings_page_tbfbkm-photofall-settings'
    ];
    
    if ( ! $screen || ! in_array($screen->base, $valid_bases) ) return;

    wp_enqueue_media();
    wp_enqueue_style('tbfbkm-admin', TBFBKM_URL . 'assets/css/admin.css', [], TBFBKM_VER);
    
    wp_enqueue_script(
      'tbfbkm-modal',
      TBFBKM_URL . 'assets/js/modal.js',
      ['jquery', 'media-views', 'media-editor', 'wp-util', 'underscore', 'backbone'], 
      TBFBKM_VER,
      true
    );
    
    $s = get_option('tbfbkm_settings', ['per_page' => 60, 'max_sites' => 5000]);
    
    wp_localize_script('tbfbkm-modal', 'tbfbkm_modal_data', [
      'ajax'          => admin_url('admin-ajax.php'),
      'nonce'         => wp_create_nonce('tbfbkm_ajax_nonce'),
      'perPage'       => (int)($s['per_page'] ?? 60),
      'maxSites'      => (int)($s['max_sites'] ?? 5000),
      'placeholderId' => (int) get_option('tbfbkm_placeholder_id', 0)
    ]);
  }

  public static function add_media_tab_string($strings) {
    $label = is_multisite() ? esc_html__('Big King Media', 'tbf-big-king-media') : esc_html__('Photofall Library', 'tbf-big-king-media');
    $strings['tbfNetworkMediaTitle'] = $label;
    return $strings;
  }
}

TBFBKM_Network_Media_Index::init();