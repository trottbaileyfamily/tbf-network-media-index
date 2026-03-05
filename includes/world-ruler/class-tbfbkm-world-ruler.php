<?php
/**
 * File: includes/world-ruler/class-tbfbkm-world-ruler.php
 * Version: 7.0.1.3 (Dynamic Colors & UI Overlay Fixes)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_World_Ruler {

    private static $inline_counter = 0;

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_footer', [__CLASS__, 'render_gadget']);
    }

    private static function get_controller_id() {
        $db_id = (int) get_site_option('tbfbkm_master_controller_id', 0);
        if ( $db_id === -1 ) return get_current_blog_id();
        if ( $db_id > 0 ) return $db_id;
        if ( defined('TBFBKM_MASTER_ID') && (int)TBFBKM_MASTER_ID > 0 ) return (int)TBFBKM_MASTER_ID;
        return get_main_site_id();
    }

    private static function get_global_config() {
        $local = get_option('tbfbkm_photofall_options', []);
        if ( !is_array($local) ) $local = [];

        $enabled = false;
        $controller_id = self::get_controller_id();
        
        if ( is_multisite() ) {
            $active_sites = get_site_option('tbfbkm_network_active_sites', []);
            $current_id = get_current_blog_id();

            if ( is_array($active_sites) && in_array($current_id, $active_sites) ) $enabled = true;

            if ( $current_id !== $controller_id ) {
                switch_to_blog($controller_id);
                $master_opts = get_option('tbfbkm_photofall_options', []);
                restore_current_blog();

                if ( ! is_array($master_opts) ) $master_opts = [];
                if ( !empty($master_opts['wr_network_wide']) ) $local = array_merge($local, $master_opts);
            }

            $net_behavior = get_site_option('tbfbkm_network_behavior', []);
            if ( !empty($net_behavior['open_default']) ) $local['wr_open_default'] = 1;
            if ( !empty($net_behavior['auto_start']) ) $local['wr_auto_start'] = 1;
        }

        if ( !empty($local['enable_world_ruler']) ) $enabled = true;
        return ['enabled' => $enabled, 'opts' => $local];
    }

    public static function enqueue_assets() {
        wp_enqueue_style('tbf-world-ruler', TBFBKM_URL . 'assets/css/world-ruler.css', ['dashicons'], TBFBKM_VER);
        wp_enqueue_script('tbf-world-ruler', TBFBKM_URL . 'assets/js/world-ruler.js', ['jquery'], TBFBKM_VER, true);

        $config = self::get_global_config();
        $opts = $config['opts'];

        $raw_json = $opts['wr_playlists_json'] ?? '[]';
        $playlists = json_decode($raw_json, true);
        if ( !is_array($playlists) ) $playlists = [];

        $controller_id = self::get_controller_id();
        $target_home_url = get_site_url($controller_id, '/photo/');

        wp_localize_script('tbf-world-ruler', 'tbf_wr_data', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'playlists'     => $playlists,
            'mode'          => isset($opts['wr_visual_mode']) ? $opts['wr_visual_mode'] : 'random', 
            'specific_ids'  => isset($opts['wr_specific_ids']) ? $opts['wr_specific_ids'] : '',
            'duration'      => isset($opts['wr_duration']) ? (int)$opts['wr_duration'] * 1000 : 5000,
            'home_url'      => $target_home_url,
            'open_default'  => !empty($opts['wr_open_default']),
            'auto_start'    => !empty($opts['wr_auto_start'])
        ]);
    }

    public static function render_gadget() {
        $config = self::get_global_config();
        if ( !$config['enabled'] ) return;
        
        $opts = $config['opts'];
        $class = !empty($opts['wr_open_default']) ? 'expanded' : 'collapsed';
        
        $bg = esc_attr($opts['wr_bg_color'] ?? '#121218');
        $text = esc_attr($opts['wr_text_color'] ?? '#ffffff');
        $accent = esc_attr($opts['wr_accent_color'] ?? '#2271b1');
        ?>
        <div id="tbf-world-ruler-container" class="tbf-wr-instance tbf-global-instance <?php echo esc_attr($class); ?>" data-instance="global" style="--wr-bg: <?php echo $bg; ?>; --wr-text: <?php echo $text; ?>; --wr-accent: <?php echo $accent; ?>;">
            <div id="tbf-wr-tab" class="wr-floating-tab" style="background: var(--wr-bg); color: var(--wr-text); border: 1px solid var(--wr-accent);">
                <span class="dashicons dashicons-format-audio"></span>
                <span class="wr-label">Princess Keilah</span>
            </div>
            
            <div id="tbf-wr-gadget" class="tbf-wr-gadget-box" style="background: var(--wr-bg); border-color: var(--wr-accent);">
                <?php self::render_internal_ui(false); ?>
            </div>
        </div>
        <?php
    }

    public static function inline_render($custom_config = []) {
        self::$inline_counter++;
        $instance_id = 'inline_' . self::$inline_counter;
        $config_json = json_encode($custom_config);
        
        $config = self::get_global_config();
        $opts = $config['opts'];
        
        $bg = esc_attr($opts['wr_bg_color'] ?? '#121218');
        $text = esc_attr($opts['wr_text_color'] ?? '#ffffff');
        $accent = esc_attr($opts['wr_accent_color'] ?? '#2271b1');
        
        ?>
        <div class="tbf-wr-instance tbf-inline-instance expanded" data-instance="<?php echo esc_attr($instance_id); ?>" data-config='<?php echo esc_attr($config_json); ?>' style="position:relative; margin-bottom: 20px; --wr-bg: <?php echo $bg; ?>; --wr-text: <?php echo $text; ?>; --wr-accent: <?php echo $accent; ?>;">
            <div class="tbf-wr-gadget-box inline-mode" style="display:flex; width: 100%; max-width: 400px; height: 500px; border-radius: 12px; background: var(--wr-bg); overflow: hidden; border: 1px solid var(--wr-accent); flex-direction: column; margin: 0 auto;">
                <?php self::render_internal_ui(true); ?>
            </div>
        </div>
        <?php
    }

    private static function render_internal_ui($is_inline = false) {
        ?>
        <style>
        .tbf-wr-instance .wr-playlist-overlay {
            background-color: var(--wr-bg) !important;
            opacity: 0.98 !important; 
            z-index: 1000 !important; 
            display: flex;
            flex-direction: column;
        }
        .tbf-wr-instance .wr-overlay-content {
            overflow-y: auto !important; 
            flex-grow: 1;
            max-height: calc(100% - 50px) !important;
            padding-bottom: 20px;
        }
        .tbf-wr-instance .wr-overlay-header {
            position: relative;
            z-index: 1001 !important;
            background-color: var(--wr-bg) !important;
            color: var(--wr-text) !important;
            border-bottom: 1px solid var(--wr-accent) !important;
            flex-shrink: 0;
        }
        .tbf-wr-instance .wr-slide-nav {
            z-index: 50 !important; 
        }
        .tbf-wr-instance .wr-header,
        .tbf-wr-instance .wr-player {
            background-color: var(--wr-bg) !important;
            color: var(--wr-text) !important;
        }
        .tbf-wr-instance .wr-audio-btn,
        .tbf-wr-instance .wr-controls span {
            color: var(--wr-text) !important;
        }
        .tbf-wr-instance .wr-audio-btn:hover,
        .tbf-wr-instance .wr-shuffle-toggle.active {
            color: var(--wr-accent) !important;
        }
        .tbf-wr-instance .wr-pl-track.active {
            border-left-color: var(--wr-accent) !important;
        }
        .tbf-wr-instance .wr-overlay-content::-webkit-scrollbar {
            width: 6px;
        }
        .tbf-wr-instance .wr-overlay-content::-webkit-scrollbar-thumb {
            background: var(--wr-accent);
            border-radius: 4px;
        }
        </style>

        <div class="wr-header">
            <span class="wr-title" style="color: var(--wr-text);">Princess Keilah Studio</span>
            <?php if (!$is_inline): ?>
            <div class="wr-controls">
                <span class="wr-minimize dashicons dashicons-minus" title="Minimize"></span>
                <span class="wr-maximize dashicons dashicons-editor-expand" title="Maximize"></span>
                <span class="wr-close dashicons dashicons-no-alt" title="Close & Stop"></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="wr-stage">
            <div class="wr-slideshow-layer"></div>
            <div class="wr-loading" style="color: var(--wr-text);">Connecting...</div>
            <div class="wr-slide-nav wr-prev-slide" title="Previous Image">&#10094;</div>
            <div class="wr-slide-nav wr-next-slide" title="Next Image">&#10095;</div>
            
            <div class="wr-playlist-overlay">
                <div class="wr-overlay-header">
                    <span class="wr-back-playlists dashicons dashicons-arrow-left-alt2" style="display:none; cursor:pointer;" title="Back to Playlists"></span>
                    <span class="wr-overlay-title">Playlists</span>
                    <span class="wr-close-overlay dashicons dashicons-no" style="cursor:pointer;" title="Close Overlay"></span>
                </div>
                <div class="wr-overlay-content"></div>
            </div>
        </div>

        <div class="wr-player">
            <div class="wr-track-info" style="color: var(--wr-accent);">Select a Playlist...</div>
            <div class="wr-audio-row">
                <button class="wr-audio-btn small wr-playlist-toggle" title="Playlists"><span class="dashicons dashicons-playlist-audio"></span></button>
                <button class="wr-audio-btn wr-prev-track" title="Previous"><span class="dashicons dashicons-controls-skipback"></span></button>
                <audio class="wr-audio-element" controls controlsList="nodownload"></audio>
                <button class="wr-audio-btn wr-next-track" title="Next"><span class="dashicons dashicons-controls-skipforward"></span></button>
                <button class="wr-audio-btn small wr-shuffle-toggle" title="Shuffle"><span class="dashicons dashicons-randomize"></span></button>
            </div>
        </div>
        <?php
    }
}