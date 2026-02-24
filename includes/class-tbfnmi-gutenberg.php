<?php
/**
 * File: includes/class-tbfnmi-gutenberg.php
 * Version: 6.4.9.4 (Forced Custom Field Support)
 * Description: Registers the standalone TBF Network Media Sidebar Panel
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Gutenberg {

    public static function init() {
        add_action('init', [__CLASS__, 'register_meta_fields'], 99);
        add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_sidebar_assets']);
    }

    public static function register_meta_fields() {
        $meta_args = [
            'type'         => 'string',
            'description'  => 'TBF Network Media URL',
            'single'       => true,
            'show_in_rest' => true,
        ];

        // Force register across all public post types
        $post_types = get_post_types(['public' => true]);
        foreach ($post_types as $pt) {
            // CRITICAL: Gutenberg will hide the React sidebar if the post type lacks this support
            add_post_type_support($pt, 'custom-fields');
            
            register_post_meta($pt, '_tbfnmi_featured_url', $meta_args);
            register_post_meta($pt, '_tbfnmi_featured_mime', $meta_args);
            register_post_meta($pt, '_tbfnmi_featured_type', $meta_args);
        }
    }

    public static function enqueue_sidebar_assets() {
        wp_enqueue_script(
            'tbfnmi-gutenberg-sidebar',
            TBFNMI_URL . 'assets/js/gutenberg-sidebar.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'jquery'],
            TBFNMI_VER,
            true
        );
    }
}