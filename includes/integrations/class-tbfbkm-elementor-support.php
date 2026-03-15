<?php
/**
 * File: includes/integrations/class-tbfbkm-elementor-support.php
 * Version: 7.0.1.19 (Elementor Media Modal Integration)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Elementor_Support {

    public static function init() {
        // Hook into Elementor's custom editor environment to load the media tab
        add_action( 'elementor/editor/after_enqueue_scripts', [__CLASS__, 'enqueue_editor_assets'] );
        
        // Ensure the media strings (tab titles) are injected for Elementor
        add_filter( 'media_view_strings', [__CLASS__, 'add_elementor_media_strings'] );
    }

    /**
     * Injects the Big King Media JavaScript and CSS into the Elementor Editor screen.
     */
    public static function enqueue_editor_assets() {
        // 1. Ensure core WordPress media is loaded
        wp_enqueue_media();
        
        // 2. Load Big King Media Admin CSS
        wp_enqueue_style( 'tbfbkm-admin', TBFBKM_URL . 'assets/css/admin.css', [], TBFBKM_VER );
        
        // 3. Load the Media Modal JS Engine
        wp_enqueue_script(
            'tbfbkm-modal',
            TBFBKM_URL . 'assets/js/modal.js',
            ['jquery', 'media-views', 'media-editor', 'wp-util', 'underscore', 'backbone'], 
            TBFBKM_VER,
            true
        );
        
        // 4. Pass the network settings to the JavaScript
        $settings = get_option( 'tbfbkm_settings', ['per_page' => 60, 'max_sites' => 5000] );
        
        wp_localize_script( 'tbfbkm-modal', 'tbfbkm_modal_data', [
            'ajax'          => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'tbfbkm_ajax_nonce' ),
            'perPage'       => (int)( $settings['per_page'] ?? 60 ),
            'maxSites'      => (int)( $settings['max_sites'] ?? 5000 ),
            'placeholderId' => (int) get_option( 'tbfbkm_placeholder_id', 0 )
        ]);
    }

    /**
     * Renames the tab specifically within Elementor if needed.
     */
    public static function add_elementor_media_strings( $strings ) {
        $label = is_multisite() ? esc_html__( 'Big King Media', 'tbf-big-king-media' ) : esc_html__( 'Photofall Library', 'tbf-big-king-media' );
        $strings['tbfNetworkMediaTitle'] = $label;
        
        return $strings;
    }
}