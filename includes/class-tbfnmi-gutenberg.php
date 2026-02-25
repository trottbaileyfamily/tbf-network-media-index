<?php
/**
 * File: includes/class-tbfnmi-gutenberg.php
 * Version: 6.5.13
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Gutenberg {

  public static function init() {
    add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_assets']);
  }

  public static function enqueue_assets() {
    wp_enqueue_script(
        'tbfnmi-gutenberg-sidebar',
        TBFNMI_URL . 'assets/js/gutenberg-sidebar.js',
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'jquery', 'wp-dom-ready'],
        TBFNMI_VER,
        true
    );

    wp_localize_script('tbfnmi-gutenberg-sidebar', 'tbfnmi_gutenberg', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('tbfnmi_nonce')
    ]);

    // CRITICAL FIX: Removed the CSS that forced the native panel to hide entirely.
    wp_add_inline_style('wp-edit-post', '
        .tbfnmi-sidebar-preview { width: 100%; border-radius: 4px; margin-bottom: 15px; border: 1px solid #ddd; background: #f0f0f1; object-fit: cover; aspect-ratio: 16/9; }
        .tbfnmi-sidebar-btn { width: 100%; justify-content: center; margin-bottom: 10px; }
    ');
  }
}