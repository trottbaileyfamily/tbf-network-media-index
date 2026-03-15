<?php
/**
 * File: includes/admin/class-tbfbkm-network-dashboard.php
 * Version: 7.0.2.4 (Fully Expanded & Strictly Multisite)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TBFBKM_Network_Dashboard {

    public static function init() {
        // Architecture Fix: If this is a Single Site, do not run this file at all.
        // Single Site settings are handled entirely by class-tbfbkm-subsite-settings.php
        if ( ! is_multisite() ) {
            return;
        }

        add_action( 'network_admin_menu', [__CLASS__, 'add_network_page'] );
        add_action( 'network_admin_edit_tbfbkm_save_network', [__CLASS__, 'save_network_settings'] );
        add_action( 'admin_enqueue_scripts', [__CLASS__, 'enqueue_assets'] );
    }

    public static function add_network_page() {
        add_submenu_page(
            'settings.php',
            'Big King Media Network',
            'Big King Media', 
            'manage_network_options',
            'tbfbkm-network',
            [__CLASS__, 'render_dashboard']
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'tbfbkm-network' ) === false ) {
            return;
        }
        
        wp_register_script( 'tbfbkm-network-js', false, ['jquery'], TBFBKM_VER, true );
        wp_enqueue_script( 'tbfbkm-network-js' );
        
        wp_localize_script( 'tbfbkm-network-js', 'tbfbkm_network_data', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'tbfbkm_ajax_nonce' )
        ]);

        ob_start();
        ?>
        jQuery(document).ready(function($) {
            
            // Handle Select All Checkbox for Sites
            $('#tbfbkm_select_all').change(function() {
                $('.tbfbkm_site_cb').prop('checked', $(this).is(':checked'));
            });

            // Logger Function for the Terminal
            function writeLog(msg) {
                var d = new Date();
                var ts = d.toLocaleTimeString('en-US', { hour12: false });
                var logEl = $('#tbfbkm-log');
                logEl.append('<div><span style="color:#888;">[' + ts + ']</span> ' + msg + '</div>');
                logEl.scrollTop(logEl[0].scrollHeight);
            }

            // Run Indexer Button Click
            $('#tbfbkm-run-indexer').click(function(e) {
                e.preventDefault();
                $(this).prop('disabled', true).text('Processing...');
                $('#tbfbkm-progress-wrap').slideDown();
                $('#tbfbkm-log').show().empty();
                writeLog('Initializing global network scan...');
                processBatch(1, 0);
            });

            // AJAX Batch Processor
            function processBatch(step, offset) {
                $.post(tbfbkm_network_data.ajaxurl, { 
                    action: 'tbfbkm_process_batch', 
                    nonce: tbfbkm_network_data.nonce, 
                    step: step, 
                    offset: offset 
                }, function(res) {
                    if (res.success) {
                        $('#tbfbkm-bar').css('width', res.data.progress + '%');
                        $('#tbfbkm-status').text('Scanning... ' + res.data.progress + '%');
                        writeLog(res.data.message);

                        if (!res.data.done) {
                            processBatch(res.data.step, res.data.offset);
                        } else {
                            $('#tbfbkm-bar').css('width', '100%');
                            $('#tbfbkm-status').text('Processing Complete!');
                            writeLog('<strong style="color:#00ff00;">All media successfully indexed. Refreshing page...</strong>');
                            setTimeout(function() { 
                                location.reload(); 
                            }, 2000);
                        }
                    } else {
                        $('#tbfbkm-status').text('Error encountered.');
                        writeLog('<strong style="color:#ff4444;">Error:</strong> ' + res.data.message);
                        $('#tbfbkm-run-indexer').prop('disabled', false).text('Retry Process');
                    }
                }).fail(function() {
                    $('#tbfbkm-status').text('Server Error. Check logs.');
                    writeLog('<strong style="color:#ff4444;">Critical Server Error. Payload timed out.</strong>');
                    $('#tbfbkm-run-indexer').prop('disabled', false).text('Retry Process');
                });
            }

            // Wipe Index Button Click
            $('#tbfbkm-wipe-index').click(function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to delete the entire network media index?')) {
                    return;
                }
                
                var btn = $(this);
                btn.text('Clearing...');
                
                $.post(tbfbkm_network_data.ajaxurl, { 
                    action: 'tbfbkm_wipe_index', 
                    nonce: tbfbkm_network_data.nonce 
                }, function(res) {
                    alert(res.data ? res.data.message : 'Database Wiped');
                    location.reload();
                });
            });
        });
        <?php
        $js = ob_get_clean();
        wp_add_inline_script( 'tbfbkm-network-js', $js );
    }

    public static function save_network_settings() {
        check_admin_referer( 'tbfbkm_network_options' );
        
        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( 'Access Denied' );
        }

        // 1. Save Master Controller ID
        $master_id = isset( $_POST['tbfbkm_master_id'] ) ? (int) $_POST['tbfbkm_master_id'] : get_main_site_id();
        update_site_option( 'tbfbkm_master_controller_id', $master_id );

        // 2. Save Active Sites Array
        $active_sites = [];
        if ( isset( $_POST['tbfbkm_active_sites'] ) && is_array( $_POST['tbfbkm_active_sites'] ) ) {
            $active_sites = array_map( 'intval', $_POST['tbfbkm_active_sites'] );
        }
        update_site_option( 'tbfbkm_network_active_sites', $active_sites );

        // 3. Save Gadget Behavior
        $behavior = [
            'open_default' => isset( $_POST['tbfbkm_global_open'] ) ? 1 : 0,
            'auto_start'   => isset( $_POST['tbfbkm_global_autostart'] ) ? 1 : 0
        ];
        update_site_option( 'tbfbkm_network_behavior', $behavior );

        // 4. Save Legacy Settings (Permissions & Modes)
        $legacy = get_site_option( 'tbfbkm_settings', [] );
        $legacy['who_can_browse'] = sanitize_text_field( $_POST['tbfbkm_who_browse'] ?? 'uploaders' );
        $legacy['insert_mode']    = sanitize_text_field( $_POST['tbfbkm_insert_mode'] ?? 'proxy' );
        $legacy['per_page']       = isset( $_POST['tbfbkm_per_page'] ) ? (int) $_POST['tbfbkm_per_page'] : 60;
        $legacy['max_sites']      = isset( $_POST['tbfbkm_max_sites'] ) ? (int) $_POST['tbfbkm_max_sites'] : 5000;
        update_site_option( 'tbfbkm_settings', $legacy );

        // Redirect back to dashboard
        wp_redirect( network_admin_url( 'settings.php?page=tbfbkm-network&updated=true' ) );
        exit;
    }

    public static function render_dashboard() {
        global $wpdb;
        $index_table = $wpdb->base_prefix . 'tbfbkm_index';
        
        $total_media = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$index_table}'" ) ) {
            $total_media = $wpdb->get_var( "SELECT COUNT(*) FROM $index_table" );
        }
        
        $sites = get_sites(['number' => 500, 'public' => 1, 'archived' => 0, 'spam' => 0, 'deleted' => 0]);
        $master_id = (int) get_site_option( 'tbfbkm_master_controller_id', get_main_site_id() );
        
        $active_sites = get_site_option( 'tbfbkm_network_active_sites', [] );
        if ( ! is_array( $active_sites ) ) {
            $active_sites = [];
        }
        
        $behavior = get_site_option( 'tbfbkm_network_behavior', ['open_default' => 0, 'auto_start' => 0] );
        
        $legacy    = get_site_option( 'tbfbkm_settings', [] );
        $who       = $legacy['who_can_browse'] ?? 'uploaders';
        $insert    = $legacy['insert_mode'] ?? 'proxy';
        $per_page  = $legacy['per_page'] ?? 60;
        $max_sites = $legacy['max_sites'] ?? 5000;

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Big King Media | Network Command Center</h1>
            <hr class="wp-header-end">
            <p style="margin-top: 5px; font-style: italic; color: #666;">By Sher Trott Bailey</p>
            
            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <p><?php esc_html_e( 'Settings saved successfully.', 'tbf-big-king-media' ); ?></p>
                </div>
            <?php endif; ?>

            <div style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
                
                <form method="post" action="<?php echo esc_url( admin_url( 'edit.php?action=tbfbkm_save_network' ) ); ?>">
                    <?php wp_nonce_field( 'tbfbkm_network_options' ); ?>
                    
                    <div style="max-width:800px;">
                        
                        <div style="background: #eef5fa; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 25px;">
                            <label for="tbfbkm_master_id" style="font-weight:bold; font-size:14px; display:block; margin-bottom:5px;">Master Control Site</label>
                            <p style="margin-top:0; color:#555; font-size:13px; margin-bottom:10px;">Who controls the <strong>World Ruler Sher Photofall & Princess Keilah Studio</strong>?</p>
                            
                            <select name="tbfbkm_master_id" id="tbfbkm_master_id" style="width:100%; max-width:400px;">
                                <option value="-1" <?php selected( -1, $master_id ); ?>>--- No Master (Each Site Independent) ---</option>
                                <?php foreach ( $sites as $s ) : ?>
                                    <?php $bid = (int) $s->blog_id; ?>
                                    <option value="<?php echo esc_attr( $bid ); ?>" <?php selected( $bid, $master_id ); ?>>
                                        <?php echo esc_html( get_blog_option( $bid, 'blogname' ) ); ?> (ID: <?php echo esc_html( $bid ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Permission Level</th>
                                <td>
                                    <select name="tbfbkm_who_browse">
                                        <option value="uploaders" <?php selected( 'uploaders', $who ); ?>>Uploaders & Admins</option>
                                        <option value="admins" <?php selected( 'admins', $who ); ?>>Admins Only</option>
                                    </select>
                                    <p class="description">Who is allowed to browse the network media modal.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Insertion Mode</th>
                                <td>
                                    <select name="tbfbkm_insert_mode">
                                        <option value="proxy" <?php selected( 'proxy', $insert ); ?>>Smart Proxy (Recommended)</option>
                                        <option value="url" <?php selected( 'url', $insert ); ?>>Direct URL</option>
                                    </select>
                                    <p class="description">How external images are placed into the editor.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Modal Items Per Page</th>
                                <td>
                                    <input type="number" name="tbfbkm_per_page" value="<?php echo esc_attr( $per_page ); ?>" min="10" max="200" />
                                    <p class="description">Number of items loaded per AJAX request in the modal.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Max Sites to Search</th>
                                <td>
                                    <input type="number" name="tbfbkm_max_sites" value="<?php echo esc_attr( $max_sites ); ?>" min="1" max="10000" />
                                    <p class="description">Maximum sub-sites the indexer will process.</p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 30px 0;">

                        <h3>Global Activation (Princess Keilah Gadget)</h3>
                        <p class="description" style="margin-bottom: 10px;">Select which subsites should load the floating media player.</p>
                        
                        <div style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9; margin-bottom: 20px;">
                            <label style="font-weight:bold; display:block; margin-bottom:10px; border-bottom:1px solid #ccc; padding-bottom:5px;">
                                <input type="checkbox" id="tbfbkm_select_all"> Select All Sites
                            </label>
                            
                            <?php foreach ( $sites as $s ) : ?>
                                <?php $bid = (int) $s->blog_id; ?>
                                <label style="display:block; padding: 4px 0; border-bottom:1px solid #eee;">
                                    <input type="checkbox" name="tbfbkm_active_sites[]" value="<?php echo esc_attr( $bid ); ?>" <?php checked( in_array( $bid, $active_sites ), true, false ); ?> class="tbfbkm_site_cb"> 
                                    <strong><?php echo esc_html( get_blog_option( $bid, 'blogname' ) ); ?></strong> 
                                    <span style="color:#888; font-size:12px;">(ID: <?php echo esc_attr( $bid ); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <h3>Gadget Behavior Defaults</h3>
                        <div style="margin-top: 15px;">
                            <label style="margin-right: 20px; font-weight: 500;">
                                <input type="checkbox" name="tbfbkm_global_open" value="1" <?php checked( 1, $behavior['open_default'] ); ?>> 
                                Gadget Open by Default
                            </label>
                            <label style="font-weight: 500;">
                                <input type="checkbox" name="tbfbkm_global_autostart" value="1" <?php checked( 1, $behavior['auto_start'] ); ?>> 
                                Auto-Start Audio Playback
                            </label>
                        </div>

                        <p class="submit" style="margin-top: 30px;">
                            <input type="submit" name="submit" id="submit" class="button button-primary button-large" value="Save Network Settings">
                        </p>
                    </div>
                </form>

                <hr style="margin: 40px 0;">

                <div style="background: #fff; padding: 25px; border: 1px solid #c3c4c7; border-radius: 4px; max-width: 800px;">
                    <h2 style="margin-top: 0; font-size: 20px;">Big King Indexer</h2>
                    <p style="font-size: 14px;">The engine room. This tool scours your media library, catalogues every single image, video, and audio file, and builds the unified database that powers World Ruler Sher Photofall.</p>
                    
                    <p style="font-size: 14px;"><strong>Indexed Assets:</strong> <?php echo esc_html( number_format( $total_media ) ); ?> items currently in database.</p>
                    
                    <div id="tbfbkm-progress-wrap" style="display:none; margin-bottom:20px; background: #f0f0f1; padding: 20px; border-radius: 6px; border: 1px solid #c3c4c7;">
                        <div style="background:#ddd; height:24px; border-radius:12px; overflow:hidden; margin-bottom: 10px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                            <div id="tbfbkm-bar" style="width:0%; background:#2271b1; height:100%; transition:width 0.3s ease;"></div>
                        </div>
                        <p id="tbfbkm-status" style="margin:0 0 10px 0; font-weight:bold; color:#2271b1; font-size: 14px;">Initializing system scan...</p>
                        <div id="tbfbkm-log" style="padding:15px; background:#1d2327; color:#d1d1d1; font-family:monospace; font-size:12px; height:200px; overflow-y:auto; border-radius:6px; display:none; line-height: 1.6; box-shadow: inset 0 2px 5px rgba(0,0,0,0.5);"></div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <button id="tbfbkm-run-indexer" class="button button-primary button-hero" style="background: #ff8800; border-color: #dd7700; text-shadow: none; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">Run Big King Indexer</button>
                        <button id="tbfbkm-wipe-index" class="button button-link-delete" style="color: #d63638;">Wipe Index Database</button>
                    </div>
                </div>

            </div>
            
            <p style="margin-top: 30px; text-align: center; color: #999; font-size: 12px;">Trott Bailey Family Kingdom &copy; <?php echo date('Y'); ?></p>
        </div>
        <?php
    }
}