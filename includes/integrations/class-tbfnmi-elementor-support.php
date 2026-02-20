<?php
/**
 * File: includes/integrations/class-tbfnmi-elementor-support.php
 * Version: 5.4.0
 * * Adds TBF Network Media support to Elementor Editor
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Elementor_Support {

  public static function init() {
    // Hook into Elementor's editor scripts
    add_action('elementor/editor/after_enqueue_scripts', [__CLASS__, 'enqueue_editor_scripts']);
  }

  public static function enqueue_editor_scripts() {
    // 1. Ensure WordPress Media logic is loaded (Elementor usually loads it, but we ensure it)
    wp_enqueue_media();

    // 2. Load our Styles
    wp_enqueue_style(
      'tbfnmi-admin', 
      TBFNMI_URL . 'assets/css/admin.css', 
      [], 
      TBFNMI_VER
    );

    // 3. Load our Modal Script (The logic that adds the tab)
    wp_enqueue_script(
      'tbfnmi-modal',
      TBFNMI_URL . 'assets/js/modal.js',
      ['jquery', 'media-views', 'media-editor', 'wp-util', 'underscore', 'backbone'], 
      TBFNMI_VER,
      true
    );
    
    // 4. Pass Data to JS (Same as in core)
    $s = get_option('tbfnmi_settings', ['per_page' => 60, 'max_sites' => 5000]);
    wp_localize_script('tbfnmi-modal', 'TBF_NMI', [
      'ajax'        => admin_url('admin-ajax.php'),
      'nonce'       => wp_create_nonce('tbfnmi_nonce'),
      'perPage'     => (int)($s['per_page'] ?? 60),
      'maxSites'    => (int)($s['max_sites'] ?? 5000),
    ]);
  }
}
