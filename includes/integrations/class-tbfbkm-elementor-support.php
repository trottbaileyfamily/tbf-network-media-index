<?php
/**
 * File: includes/integrations/class-tbfbkm-elementor-support.php
 * Version: 6.9.27
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Elementor_Support {

  public static function init() {
    add_action('elementor/editor/after_enqueue_scripts', [__CLASS__, 'enqueue_editor_scripts']);
  }

  public static function enqueue_editor_scripts() {
    wp_enqueue_media();

    wp_enqueue_style(
      'tbfbkm-admin', 
      TBFBKM_URL . 'assets/css/admin.css', 
      [], 
      TBFBKM_VER
    );

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
      'nonce'         => wp_create_nonce('tbfbkm_nonce'),
      'perPage'       => (int)($s['per_page'] ?? 60),
      'maxSites'      => (int)($s['max_sites'] ?? 5000),
      'placeholderId' => (int) get_option('tbfbkm_placeholder_id', 0)
    ]);
  }
}
