<?php
/**
 * File: includes/class-tbfbkm-gutenberg.php
 * Version: 7.0.1.3 (WP Review Compliance - Nonce Security)
 * Description: Manages Gutenberg sidebar integration and asset injection.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Gutenberg {

    public static function init() {
        add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_block_editor_assets']);
        add_action('init', [__CLASS__, 'register_meta']);
    }

    /**
     * Register meta fields for Gutenberg sidebar access
     */
    public static function register_meta() {
        $meta_keys = [
            '_tbfbkm_featured_url',
            '_tbfbkm_featured_mime',
            '_tbfbkm_featured_type'
        ];

        foreach ( $meta_keys as $key ) {
            register_post_meta('', $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ]);
        }
    }

    /**
     * Enqueue JS/CSS for the Gutenberg Sidebar
     */
    public static function enqueue_block_editor_assets() {
        // Enqueue the Sidebar JS
        wp_enqueue_script(
            'tbfbkm-gutenberg-sidebar',
            TBFBKM_URL . 'assets/js/gutenberg-sidebar.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-media', 'jquery'],
            TBFBKM_VER,
            true
        );

        // Enqueue Sidebar CSS
        wp_enqueue_style(
            'tbfbkm-gutenberg-sidebar-css',
            TBFBKM_URL . 'assets/css/admin.css',
            [],
            TBFBKM_VER
        );

        // SECURITY FIX: Unified Nonce for Gutenberg operations
        wp_localize_script('tbfbkm-gutenberg-sidebar', 'tbfbkm_gutenberg', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tbfbkm_ajax_nonce'),
            'ver'     => TBFBKM_VER
        ]);
    }
}