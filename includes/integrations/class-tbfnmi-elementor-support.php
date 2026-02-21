<?php
/**
 * File: includes/integrations/class-tbfnmi-elementor-support.php
 * Version: 6.2.7
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Elementor_Support {

  public static function init() {
    add_action('elementor/editor/after_enqueue_scripts', [__CLASS__, 'enqueue_editor_scripts']);
  }

  public static function enqueue_editor_scripts() {
    wp_enqueue_media();

    wp_enqueue_style(
      'tbfnmi-admin', 
      TBFNMI_URL . 'assets/css/admin.css', 
      [], 
      TBFNMI_VER
    );

    wp_enqueue_script(
      'tbfnmi-modal',
      TBFNMI_URL . 'assets/js/modal.js',
      ['jquery', 'media-views', 'media-editor', 'wp-util', 'underscore', 'backbone'], 
      TBFNMI_VER,
      true
    );
    
    $s = get_option('tbfnmi_settings', ['per_page' => 60, 'max_sites' => 5000]);
    
    // PREFIX FIX: Matches the main file rename
    wp_localize_script('tbfnmi-modal', 'tbfnmi_modal_data', [
      'ajax'        => admin_url('admin-ajax.php'),
      'nonce'       => wp_create_nonce('tbfnmi_nonce'),
      'perPage'     => (int)($s['per_page'] ?? 60),
      'maxSites'    => (int)($s['max_sites'] ?? 5000),
    ]);
  }
}