<?php
/**
 * File: includes/world-ruler/class-tbfnmi-world-ruler.php
 * Version: 6.9.3 (Auto-Start & Complete Queen Keilah Engine)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_World_Ruler {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_footer', [__CLASS__, 'render_gadget']);
    }

    /**
     * Determines the active configuration based on Site Settings vs Network Override.
     */
    private static function get_global_config() {
        $local = get_option('tbfnmi_photofall_options', []);
        $network_override = false;
        
        if ( is_multisite() ) {
            // Check Main Site for "Network Wide" enforcement
            switch_to_blog(get_main_site_id());
            $main_opts = get_option('tbfnmi_photofall_options', []);
            restore_current_blog();

            if ( !empty($main_opts['wr_network_wide']) ) {
                $network_override = true;
                // Merge main settings over local, forcing the gadget to appear
                $local = array_merge($local, $main_opts);
            }
        }

        $enabled = !empty($local['enable_world_ruler']) || $network_override;
        return ['enabled' => $enabled, 'opts' => $local];
    }

    public static function enqueue_assets() {
        $config = self::get_global_config();
        if ( !$config['enabled'] ) return;
        
        $opts = $config['opts'];

        wp_enqueue_style('tbf-world-ruler', TBFNMI_URL . 'assets/css/world-ruler.css', ['dashicons'], TBFNMI_VER);
        wp_enqueue_script('tbf-world-ruler', TBFNMI_URL . 'assets/js/world-ruler.js', ['jquery'], TBFNMI_VER, true);

        // Process Playlists
        $raw_json = $opts['wr_playlists_json'] ?? '[]';
        $playlists = json_decode($raw_json, true);
        if ( !is_array($playlists) ) $playlists = [];

        // Pass Configuration to Frontend
        wp_localize_script('tbf-world-ruler', 'tbf_wr_data', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'playlists'     => $playlists,
            'mode'          => isset($opts['wr_visual_mode']) ? $opts['wr_visual_mode'] : 'random', 
            'specific_ids'  => isset($opts['wr_specific_ids']) ? $opts['wr_specific_ids'] : '',
            'duration'      => isset($opts['wr_duration']) ? (int)$opts['wr_duration'] * 1000 : 5000,
            'home_url'      => home_url('/photo/'),
            'open_default'  => !empty($opts['wr_open_default']),
            'auto_start'    => !empty($opts['wr_auto_start']) // NEW: Auto Start Flag
        ]);
    }

    public static function render_gadget() {
        $config = self::get_global_config();
        if ( !$config['enabled'] ) return;
        
        // Initial state class
        $class = !empty($config['opts']['wr_open_default']) ? 'expanded' : 'collapsed';
        ?>
        <div id="tbf-world-ruler-container" class="<?php echo esc_attr($class); ?>">
            <div id="tbf-wr-tab">
                <span class="dashicons dashicons-admin-site-alt3"></span>
                <span class="wr-label">Queen Keilah</span>
            </div>
            
            <div id="tbf-wr-gadget">
                <div class="wr-header">
                    <span class="wr-title">Queen Keilah</span>
                    <div class="wr-controls">
                        <span id="wr-minimize" class="dashicons dashicons-minus" title="Minimize"></span>
                        <span id="wr-maximize" class="dashicons dashicons-editor-expand" title="Maximize"></span>
                        <span id="wr-close" class="dashicons dashicons-no-alt" title="Close & Stop"></span>
                    </div>
                </div>
                
                <div class="wr-stage">
                    <div id="wr-slideshow-layer"></div>
                    <div id="wr-loading">Loading Network...</div>
                    
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
                        <button class="wr-audio-btn small" id="wr-playlist-toggle" title="Playlists">
                            <span class="dashicons dashicons-playlist-audio"></span>
                        </button>
                        
                        <button class="wr-audio-btn" id="wr-prev-track" title="Previous">
                            <span class="dashicons dashicons-controls-skipback"></span>
                        </button>
                        
                        <audio id="wr-audio-element" controls controlsList="nodownload"></audio>
                        
                        <button class="wr-audio-btn" id="wr-next-track" title="Next">
                            <span class="dashicons dashicons-controls-skipforward"></span>
                        </button>
                        
                        <button class="wr-audio-btn small" id="wr-shuffle-toggle" title="Shuffle">
                            <span class="dashicons dashicons-randomize"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}