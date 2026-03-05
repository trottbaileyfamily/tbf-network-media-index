<?php
/**
 * File: includes/indexer/class-tbfbkm-indexer-admin.php
 * Version: 6.5.1 (Strict Late Escaping & Sanitization)
 */
if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Indexer_Admin {
    public static function init() {
        add_action('network_admin_menu', [__CLASS__, 'add_indexer_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function add_indexer_menu() {
        add_submenu_page(
            'tbfbkm-dashboard',
            esc_html__('Network Indexer', 'tbf-big-king-media'),
            esc_html__('Indexer Engine', 'tbf-big-king-media'),
            'manage_network_options',
            'tbfbkm-indexer',
            [__CLASS__, 'render_page']
        );
    }

    public static function enqueue_assets($hook) {
        if ( strpos($hook, 'tbfbkm-indexer') === false ) return;
        
        wp_enqueue_style('tbfbkm-indexer-admin', TBFBKM_URL . 'assets/css/indexer-admin.css', [], TBFBKM_VER);
        wp_enqueue_script('tbfbkm-indexer-admin', TBFBKM_URL . 'assets/js/indexer-admin.js', ['jquery'], TBFBKM_VER, true);
        
        // Strict JSON Encoding via wp_localize_script standard
        wp_localize_script('tbfbkm-indexer-admin', 'tbfbkm_indexer_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tbfbkm_indexer_run')
        ]);
    }

    public static function render_page() {
        if ( ! current_user_can('manage_network_options') ) return;
        ?>
        <div class="wrap tbfbkm-admin-wrap">
            <h1><?php esc_html_e('TBF Network Media - Global Indexer', 'tbf-big-king-media'); ?></h1>
            <p><?php esc_html_e('Run the indexer to catalog all media across the multisite network into the central database. This allows rapid cross-site searching without bogging down the server.', 'tbf-big-king-media'); ?></p>

            <div class="tbfbkm-indexer-card">
                <h2><?php esc_html_e('1. Core WordPress Media Library Sync', 'tbf-big-king-media'); ?></h2>
                <p><?php esc_html_e('This engine safely chunks and catalogs standard WordPress uploads across all subsites.', 'tbf-big-king-media'); ?></p>
                <button id="tbfbkm-start-index" class="button button-primary button-large"><?php esc_html_e('Run Full Network Index', 'tbf-big-king-media'); ?></button>
                
                <div id="tbfbkm-index-progress" style="display:none; margin-top:20px;">
                    <p><strong id="tbfbkm-index-status"><?php esc_html_e('Initializing...', 'tbf-big-king-media'); ?></strong></p>
                    <div style="width:100%; background:#e2e4e7; border-radius:3px; height:20px;">
                        <div id="tbfbkm-index-bar" style="width:0%; background:#2271b1; height:100%; border-radius:3px; transition: width 0.3s;"></div>
                    </div>
                </div>
            </div>

            <div class="tbfbkm-indexer-card" style="margin-top: 20px;">
                <h2><?php esc_html_e('2. Frontend/Vikinger Directory Sync', 'tbf-big-king-media'); ?></h2>
                <p><?php esc_html_e('This engine bridges physical files uploaded via BuddyPress/Vikinger frontends into the database. (Zero physical thumbnail duplication enforced).', 'tbf-big-king-media'); ?></p>
                <button id="tbfbkm-start-vikinger" class="button button-secondary button-large"><?php esc_html_e('Sync Vikinger Frontend Uploads', 'tbf-big-king-media'); ?></button>
                
                <div id="tbfbkm-vikinger-progress" style="display:none; margin-top:20px;">
                    <p><strong id="tbfbkm-vikinger-status"><?php esc_html_e('Initializing Vikinger Sync...', 'tbf-big-king-media'); ?></strong></p>
                    <div style="width:100%; background:#e2e4e7; border-radius:3px; height:20px;">
                        <div id="tbfbkm-vikinger-bar" style="width:0%; background:#00a32a; height:100%; border-radius:3px; transition: width 0.3s;"></div>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }
}
