<?php
/**
 * File: includes/world-ruler/class-tbfbkm-world-ruler.php
 * Version: 7.0.1.18 (Restored Shortcode inline_render support)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_World_Ruler {

    // Store queried data so we don't hit the DB twice
    private static $gadget_data = null;

    public static function init() {
        add_action( 'wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'] );
        add_action( 'wp_footer', [__CLASS__, 'render_gadget'] );
    }

    private static function get_master_options() {
        $master_id = (int) get_site_option( 'tbfbkm_master_controller_id', is_multisite() ? get_main_site_id() : -1 );
        
        if ( is_multisite() && $master_id > 0 ) {
            switch_to_blog( $master_id );
            $opts = get_option( 'tbfbkm_photofall_options', [] );
            restore_current_blog();
        } else {
            $opts = get_option( 'tbfbkm_photofall_options', [] );
        }
        
        return is_array( $opts ) ? $opts : [];
    }

    private static function hex_to_rgb( $hex ) {
        $hex = str_replace( '#', '', $hex );
        if ( strlen( $hex ) == 3 ) {
            $hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
        }
        return [
            hexdec( substr( $hex, 0, 2 ) ), 
            hexdec( substr( $hex, 2, 2 ) ), 
            hexdec( substr( $hex, 4, 2 ) )
        ];
    }

    /**
     * Prepare all gadget data. 
     * The $force parameter allows shortcodes to bypass the global enable/disable toggle.
     */
    private static function prepare_gadget_data( $force = false ) {
        if ( self::$gadget_data !== null && self::$gadget_data !== false ) {
            return self::$gadget_data;
        }

        $opts = self::get_master_options();
        
        if ( empty( $opts['enable_world_ruler'] ) && ! $force ) {
            self::$gadget_data = false;
            return false;
        }

        $master_id = (int) get_site_option( 'tbfbkm_master_controller_id', is_multisite() ? get_main_site_id() : 1 );
        global $wpdb;
        $table = $wpdb->base_prefix . 'tbfbkm_index';

        // 1. Resolve Visuals
        $visuals_data = [];
        if ( ($opts['wr_visual_mode'] ?? 'random') === 'specific' && !empty( $opts['wr_specific_ids'] ) ) {
            $v_ids = array_filter( array_map( 'intval', explode( ',', $opts['wr_specific_ids'] ) ) );
            if ( !empty( $v_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $v_ids ), '%d' ) );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, blog_id, url_full FROM {$table} WHERE attachment_id IN ($placeholders) AND media_type='image'", $v_ids ) );
                foreach ( $rows as $r ) {
                    $link = get_site_url( $master_id, '/photo/image/' . $r->blog_id . '-' . $r->attachment_id . '/' );
                    $visuals_data[] = ['url' => esc_url( $r->url_full ), 'link' => esc_url( $link )];
                }
            }
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( "SELECT attachment_id, blog_id, url_full FROM {$table} WHERE media_type='image' ORDER BY RAND() LIMIT 15" );
            foreach ( $rows as $r ) {
                $link = get_site_url( $master_id, '/photo/image/' . $r->blog_id . '-' . $r->attachment_id . '/' );
                $visuals_data[] = ['url' => esc_url( $r->url_full ), 'link' => esc_url( $link )];
            }
        }

        if ( empty( $visuals_data ) ) {
            $visuals_data[] = ['url' => esc_url( TBFBKM_URL . 'assets/images/default-gadget.jpg' ), 'link' => '#'];
        }

        // 2. Resolve Audio Tracks
        $raw_json = $opts['wr_playlists_json'] ?? '';
        $playlists = json_decode( $raw_json, true );
        $final_tracks = [];
        
        if ( is_array( $playlists ) ) {
            $all_audio_ids = [];
            foreach ( $playlists as $pl ) {
                if ( !empty( $pl['tracks'] ) ) {
                    foreach ( $pl['tracks'] as $tid ) {
                        $all_audio_ids[] = (int)$tid;
                    }
                }
            }
            
            if ( !empty( $all_audio_ids ) ) {
                $all_audio_ids = array_unique( $all_audio_ids );
                $placeholders = implode( ',', array_fill( 0, count( $all_audio_ids ), '%d' ) );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $audio_rows = $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, title, url_full FROM {$table} WHERE attachment_id IN ($placeholders) AND media_type='audio'", $all_audio_ids ) );
                
                $db_map = [];
                foreach ( $audio_rows as $ar ) {
                    $clean_title = sanitize_text_field( trim( $ar->title ) );
                    if ( empty( $clean_title ) || is_numeric( $clean_title ) ) {
                        $filename = pathinfo( parse_url( $ar->url_full, PHP_URL_PATH ), PATHINFO_FILENAME );
                        $clean_title = sanitize_text_field( ucwords( str_replace( ['-', '_'], ' ', $filename ) ) );
                    }
                    $db_map[$ar->attachment_id] = [
                        'id'    => $ar->attachment_id,
                        'title' => $clean_title,
                        'url'   => esc_url( $ar->url_full )
                    ];
                }

                foreach ( $all_audio_ids as $req_id ) {
                    if ( isset( $db_map[$req_id] ) ) {
                        $final_tracks[] = $db_map[$req_id];
                    }
                }
            }
        }

        // 3. Compile Data
        self::$gadget_data = [
            'visuals'           => $visuals_data,
            'tracks'            => $final_tracks,
            'duration'          => isset( $opts['wr_duration'] ) ? max( 2, (int)$opts['wr_duration'] ) : 5,
            'auto_start'        => !empty( $opts['wr_auto_start'] ),
            'minimized_on_load' => empty( $opts['wr_open_default'] ),
            'bg_color'          => !empty( $opts['wr_bg_color'] ) ? $opts['wr_bg_color'] : '#121218',
            'text_color'        => !empty( $opts['wr_text_color'] ) ? $opts['wr_text_color'] : '#ffffff',
            'accent_color'      => !empty( $opts['wr_accent_color'] ) ? $opts['wr_accent_color'] : '#2271b1',
            'opacity'           => isset( $opts['wr_opacity'] ) ? max( 10, min( 100, (int)$opts['wr_opacity'] ) ) : 90
        ];

        return self::$gadget_data;
    }

    public static function enqueue_assets( $force = false ) {
        $data = self::prepare_gadget_data( $force );
        if ( ! $data ) return;

        wp_enqueue_style( 'dashicons' );

        // 1. Enqueue External CSS
        wp_enqueue_style( 'tbfbkm-world-ruler-css', TBFBKM_URL . 'assets/css/world-ruler.css', [], TBFBKM_VER );

        // 2. Inject Dynamic CSS Variables
        $opacity_decimal = $data['opacity'] / 100;
        $rgb = self::hex_to_rgb( $data['bg_color'] );
        $bg_rgba_base = "rgba({$rgb[0]}, {$rgb[1]}, {$rgb[2]}, {$opacity_decimal})";
        $bg_rgba_70 = "rgba({$rgb[0]}, {$rgb[1]}, {$rgb[2]}, 0.70)";

        $inline_css = "
        #tbfbkm-world-ruler {
            --wr-txt: " . esc_attr( $data['text_color'] ) . ";
            --wr-acc: " . esc_attr( $data['accent_color'] ) . ";
            --wr-bg-base: " . esc_attr( $bg_rgba_base ) . ";
            --wr-bg-70: " . esc_attr( $bg_rgba_70 ) . ";
        }";
        wp_add_inline_style( 'tbfbkm-world-ruler-css', $inline_css );

        // 3. Enqueue External JS & Localize Data
        wp_enqueue_script( 'tbfbkm-world-ruler-js', TBFBKM_URL . 'assets/js/world-ruler.js', [], TBFBKM_VER, true );
        
        wp_localize_script( 'tbfbkm-world-ruler-js', 'tbfbkm_wr_data', [
            'tracks'            => $data['tracks'],
            'slideDuration'     => ( $data['duration'] * 1000 ),
            'autoStart'         => $data['auto_start'],
            'isMinimizedOnLoad' => $data['minimized_on_load']
        ]);
    }

    public static function render_gadget( $force = false ) {
        $data = self::prepare_gadget_data( $force );
        if ( ! $data ) return;

        ?>
        <div id="tbfbkm-world-ruler">
            
            <div id="wr-slideshow-pane">
                <div id="wr-visuals">
                    <?php foreach ( $data['visuals'] as $idx => $vis ) : ?>
                        <a href="<?php echo esc_url( $vis['link'] ); ?>" class="wr-visual-link <?php echo $idx === 0 ? 'active' : ''; ?>">
                            <img src="<?php echo esc_url( $vis['url'] ); ?>" alt="World Ruler Slideshow Image">
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <button id="wr-slide-nav-prev" class="wr-slide-nav" title="Previous Image"><span class="dashicons dashicons-arrow-left-alt2"></span></button>
                <button id="wr-slide-nav-next" class="wr-slide-nav" title="Next Image"><span class="dashicons dashicons-arrow-right-alt2"></span></button>

                <div id="wr-slideshow-header">
                    <span id="wr-slide-title">Princess Keilah Studio</span>
                    <div class="wr-window-controls">
                        <button id="wr-slide-min" title="Minimize Slideshow"><span class="dashicons dashicons-minus"></span></button>
                    </div>
                </div>
            </div>

            <div id="wr-playlist-pane">
                <div id="wr-playlist-tracks"></div>
            </div>

            <div id="wr-bottom-bar">
                <div class="wr-bar-group wr-bar-left">
                    <button id="wr-btn-restore" title="Expand Player"><span class="dashicons dashicons-arrow-left-alt2"></span></button>
                    <button id="wr-btn-prev" class="wr-icon-btn" title="Previous Song"><span class="dashicons dashicons-controls-skipback"></span></button>
                    
                    <div id="wr-play-wrapper">
                        <svg class="wr-progress-ring" width="48" height="48">
                            <circle class="wr-ring-bg" cx="24" cy="24" r="22" stroke-width="3"></circle>
                            <circle class="wr-ring-fill" cx="24" cy="24" r="22" stroke-width="3"></circle>
                        </svg>
                        <button id="wr-btn-play"><span class="dashicons dashicons-controls-play"></span></button>
                    </div>
                    
                    <button id="wr-btn-next" class="wr-icon-btn" title="Next Song"><span class="dashicons dashicons-controls-skipforward"></span></button>
                </div>
                
                <div class="wr-bar-group wr-bar-center">
                    <button id="wr-btn-playlist" class="wr-icon-btn" title="Toggle Playlist"><span class="dashicons dashicons-playlist-audio"></span></button>
                    <button id="wr-btn-slideshow" class="wr-icon-btn" title="Toggle Slideshow"><span class="dashicons dashicons-format-gallery"></span></button>
                </div>
                
                <div class="wr-bar-group wr-bar-right">
                    <button id="wr-btn-minimize-all" class="wr-icon-btn" title="Minimize Everything"><span class="dashicons dashicons-no-alt"></span></button>
                </div>
            </div>

            <audio id="wr-native-audio" crossorigin="anonymous"></audio>
        </div>
        <?php
    }

    /**
     * Inline rendering specifically for shortcodes (Elementor/Gutenberg).
     * Forces rendering even if global options have the gadget disabled.
     */
    public static function inline_render() {
        self::enqueue_assets( true );
        ob_start();
        self::render_gadget( true );
        return ob_get_clean();
    }
}