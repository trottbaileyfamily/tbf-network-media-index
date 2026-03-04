<?php
/**
 * File: includes/world-ruler/class-tbfnmi-world-ruler.php
 * Version: 6.9.5.11 (Centralized Master Links)
 * Description: Renders the Princess Keilah Studio gadget and handles centralized routing.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_World_Ruler {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_footer', [__CLASS__, 'render_gadget']);
    }

    /**
     * Determines which Site ID controls the network.
     * Used for both Playlist Data AND Link Generation.
     */
    private static function get_controller_id() {
        // 1. Check Network Setting
        $db_id = (int) get_site_option('tbfnmi_master_controller_id', 0);
        
        // INDEPENDENT MODE: If set to -1, the *Current* site controls itself.
        if ( $db_id === -1 ) {
            return get_current_blog_id();
        }

        // SPECIFIC MASTER: If a specific site ID is set (e.g., 1drop)
        if ( $db_id > 0 ) return $db_id;

        // 2. Fallback to Constant (Legacy)
        if ( defined('TBFNMI_MASTER_ID') && (int)TBFNMI_MASTER_ID > 0 ) {
            return (int)TBFNMI_MASTER_ID;
        }

        // 3. Default to Main Site (ID 1)
        return get_main_site_id();
    }

    /**
     * Determines visibility and config based on Master Controller & Site Selector.
     */
    private static function get_global_config() {
        $local = get_option('tbfnmi_photofall_options', []);
        if ( !is_array($local) ) $local = [];

        $enabled = false;
        $controller_id = self::get_controller_id();
        $network_override = false;
        
        if ( is_multisite() ) {
            // A. Check Visibility (Site Selector)
            $active_sites = get_site_option('tbfnmi_network_active_sites', []);
            $current_id = get_current_blog_id();

            if ( is_array($active_sites) && in_array($current_id, $active_sites) ) {
                $enabled = true;
            }

            // B. Load Configuration
            if ( $current_id !== $controller_id ) {
                switch_to_blog($controller_id);
                $master_opts = get_option('tbfnmi_photofall_options', []);
                restore_current_blog();

                if ( ! is_array($master_opts) ) $master_opts = [];

                if ( !empty($master_opts['wr_network_wide']) ) {
                    $network_override = true;
                    $local = array_merge($local, $master_opts);
                }
            } else {
                if ( !empty($local['wr_network_wide']) ) $network_override = true;
            }

            // C. Load Global Defaults
            $net_behavior = get_site_option('tbfnmi_network_behavior', []);
            if ( !empty($net_behavior['open_default']) ) $local['wr_open_default'] = 1;
            if ( !empty($net_behavior['auto_start']) ) $local['wr_auto_start'] = 1;
        }

        // Local override
        if ( !empty($local['enable_world_ruler']) ) $enabled = true;

        return ['enabled' => $enabled, 'opts' => $local];
    }

    public static function enqueue_assets() {
        $config = self::get_global_config();
        if ( !$config['enabled'] ) return;
        
        $opts = $config['opts'];

        wp_enqueue_style('tbf-world-ruler', TBFNMI_URL . 'assets/css/world-ruler.css', ['dashicons'], TBFNMI_VER);
        wp_enqueue_script('tbf-world-ruler', TBFNMI_URL . 'assets/js/world-ruler.js', ['jquery'], TBFNMI_VER, true);

        $raw_json = $opts['wr_playlists_json'] ?? '[]';
        $playlists = json_decode($raw_json, true);
        if ( !is_array($playlists) ) $playlists = [];

        // --- CENTRALIZATION FIX ---
        // We determine the controller ID to build the "Home URL".
        // This forces all clicks to redirect to the Master Site (1drop) instead of the local site.
        $controller_id = self::get_controller_id();
        $target_home_url = get_site_url($controller_id, '/photo/');

        wp_localize_script('tbf-world-ruler', 'tbf_wr_data', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'playlists'     => $playlists,
            'mode'          => isset($opts['wr_visual_mode']) ? $opts['wr_visual_mode'] : 'random', 
            'specific_ids'  => isset($opts['wr_specific_ids']) ? $opts['wr_specific_ids'] : '',
            'duration'      => isset($opts['wr_duration']) ? (int)$opts['wr_duration'] * 1000 : 5000,
            'home_url'      => $target_home_url, // <--- Now points to Master Site
            'open_default'  => !empty($opts['wr_open_default']),
            'auto_start'    => !empty($opts['wr_auto_start'])
        ]);
    }

    public static function render_gadget() {
        $config = self::get_global_config();
        if ( !$config['enabled'] ) return;
        
        $class = !empty($config['opts']['wr_open_default']) ? 'expanded' : 'collapsed';
        ?>
        <div id="tbf-world-ruler-container" class="<?php echo esc_attr($class); ?>">
            <div id="tbf-wr-tab">
                <span class="dashicons dashicons-format-audio"></span>
                <span class="wr-label">Princess Keilah</span>
            </div>
            
            <div id="tbf-wr-gadget">
                <div class="wr-header">
                    <span class="wr-title">Princess Keilah Studio</span>
                    <div class="wr-controls">
                        <span id="wr-minimize" class="dashicons dashicons-minus" title="Minimize"></span>
                        <span id="wr-maximize" class="dashicons dashicons-editor-expand" title="Maximize"></span>
                        <span id="wr-close" class="dashicons dashicons-no-alt" title="Close & Stop"></span>
                    </div>
                </div>
                
                <div class="wr-stage">
                    <div id="wr-slideshow-layer"></div>
                    <div id="wr-loading">Connecting...</div>
                    <div class="wr-slide-nav wr-prev-slide" title="Previous Image">&#10094;</div>
                    <div class="wr-slide-nav wr-next-slide" title="Next Image">&#10095;</div>
                    
                    <div id="wr-playlist-overlay">
                        <div class="wr-overlay-header">
                            <span id="wr-back-playlists" class="dashicons dashicons-arrow-left-alt2" style="display:none; cursor:pointer;" title="Back to Playlists"></span>
                            <span class="wr-overlay-title">Playlists</span>
                            <span id="wr-close-overlay" class="dashicons dashicons-no" style="cursor:pointer;" title="Close Overlay"></span>
                        </div>
                        <div id="wr-overlay-content"></div>
                    </div>
                </div>

                <div class="wr-player">
                    <div class="wr-track-info">Select a Playlist...</div>
                    <div class="wr-audio-row">
                        <button class="wr-audio-btn small" id="wr-playlist-toggle" title="Playlists"><span class="dashicons dashicons-playlist-audio"></span></button>
                        <button class="wr-audio-btn" id="wr-prev-track" title="Previous"><span class="dashicons dashicons-controls-skipback"></span></button>
                        <audio id="wr-audio-element" controls controlsList="nodownload"></audio>
                        <button class="wr-audio-btn" id="wr-next-track" title="Next"><span class="dashicons dashicons-controls-skipforward"></span></button>
                        <button class="wr-audio-btn small" id="wr-shuffle-toggle" title="Shuffle"><span class="dashicons dashicons-randomize"></span></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}