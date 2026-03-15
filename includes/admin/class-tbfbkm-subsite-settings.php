<?php
/**
 * File: includes/admin/class-tbfbkm-subsite-settings.php
 * Version: 7.0.2.5 (Fixed Menu Registration Logic)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TBFBKM_Subsite_Settings {

    public static function init() {
        add_action( 'admin_menu', [__CLASS__, 'add_page'], 1 ); 
        add_action( 'admin_init', [__CLASS__, 'register_settings'] );
        add_action( 'admin_enqueue_scripts', [__CLASS__, 'enqueue_assets'] );
    }

    public static function get_options() {
        $defaults = [
            'per_page'               => 20,
            'caption_mode'           => 'hover',
            'default_sort'           => 'date_desc',
            'show_search'            => 1,
            'show_filter_type'       => 1,
            'show_filter_year'       => 1,
            'show_filter_site'       => 1,
            'show_sort'              => 1,
            'show_random'            => 1,
            'seo_interlink_origin'   => 1, 
            'enable_xml_sitemaps'    => 1,
            'network_scope_mode'     => 'all', 
            'network_scope_sites'    => [],
            'enable_frontend_upload' => 0,
            'upload_roles'           => ['administrator'],
            'enable_world_ruler'     => 0,
            'wr_open_default'        => 0, 
            'wr_auto_start'          => 0,
            'wr_network_wide'        => 0, 
            'wr_visual_mode'         => 'random',
            'wr_specific_ids'        => '',
            'wr_duration'            => 5,
            'wr_opacity'             => 90,
            'wr_playlists_json'      => '[{"name":"Princess Keilah Default","tracks":[]}]',
            'wr_bg_color'            => '#121218',
            'wr_text_color'          => '#ffffff',
            'wr_accent_color'        => '#2271b1'
        ];
        
        $opts = get_option( 'tbfbkm_photofall_options', [] );
        return array_merge( $defaults, is_array( $opts ) ? $opts : [] );
    }

    public static function add_page() {
        // ALWAYS register the menu page, regardless of Single/Multisite.
        // This ensures the "Big King Media" tab ALWAYS appears under the "Media" menu.
        add_submenu_page(
            'upload.php',
            'Big King Media',
            'Big King Media',
            'manage_options',
            'tbfbkm-photofall-settings',
            [__CLASS__, 'render']
        );
    }

    public static function register_settings() {
        register_setting( 'tbfbkm_photofall_group', 'tbfbkm_photofall_options', [
            'sanitize_callback' => [__CLASS__, 'sanitize']
        ]);

        if ( ! is_multisite() ) {
            register_setting( 'tbfbkm_photofall_group', 'tbfbkm_settings', [
                'sanitize_callback' => [__CLASS__, 'sanitize_system']
            ]);
        }
    }

    public static function sanitize_system( $input ) {
        $new = [];
        $new['who_can_browse'] = sanitize_text_field( $input['who_can_browse'] ?? 'uploaders' );
        $new['insert_mode']    = sanitize_text_field( $input['insert_mode'] ?? 'proxy' );
        $new['per_page']       = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 60;
        return $new;
    }

    public static function sanitize( $input ) {
        $new_input = [];
        
        $ints = ['per_page', 'wr_duration', 'wr_opacity'];
        foreach ( $ints as $key ) {
            if ( isset( $input[$key] ) ) {
                $new_input[$key] = absint( $input[$key] );
            }
        }

        $strings = ['caption_mode', 'default_sort', 'network_scope_mode', 'wr_visual_mode', 'wr_specific_ids'];
        foreach ( $strings as $key ) {
            if ( isset( $input[$key] ) ) {
                $new_input[$key] = sanitize_text_field( $input[$key] );
            }
        }
        
        $hexes = ['wr_bg_color', 'wr_text_color', 'wr_accent_color'];
        foreach ( $hexes as $key ) {
            if ( isset( $input[$key] ) ) {
                $new_input[$key] = sanitize_hex_color( $input[$key] );
            }
        }

        $bools = [
            'show_search', 'show_filter_type', 'show_filter_year', 'show_filter_site', 
            'show_sort', 'show_random', 'seo_interlink_origin', 'enable_xml_sitemaps', 
            'enable_frontend_upload', 'enable_world_ruler', 'wr_open_default', 
            'wr_auto_start', 'wr_network_wide'
        ];
        foreach ( $bools as $key ) {
            $new_input[$key] = isset( $input[$key] ) ? 1 : 0;
        }

        if ( isset( $input['network_scope_sites'] ) && is_array( $input['network_scope_sites'] ) ) {
            $new_input['network_scope_sites'] = array_map( 'intval', $input['network_scope_sites'] );
        } else {
            $new_input['network_scope_sites'] = [];
        }

        if ( isset( $input['upload_roles'] ) && is_array( $input['upload_roles'] ) ) {
            $new_input['upload_roles'] = array_map( 'sanitize_text_field', $input['upload_roles'] );
        } else {
            $new_input['upload_roles'] = ['administrator'];
        }

        if ( isset( $input['wr_playlists_json'] ) ) {
            $json_raw = stripslashes( $input['wr_playlists_json'] );
            $json = json_decode( $json_raw, true );
            
            if ( is_array( $json ) ) {
                array_walk_recursive( $json, function( &$item, $key ) {
                    if ( is_string( $item ) ) {
                        $item = sanitize_text_field( $item );
                    }
                });
                $new_input['wr_playlists_json'] = wp_json_encode( $json );
            } else {
                $new_input['wr_playlists_json'] = '[{"name":"Princess Keilah Default","tracks":[]}]';
            }
        }

        return $new_input;
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'tbfbkm-photofall-settings' ) === false ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script( 'jquery-ui-sortable' );

        ob_start();
        ?>
            .tbf-tab-content { 
                background: #fff; 
                padding: 20px; 
                border: 1px solid #c3c4c7; 
                border-top: none; 
            }
            .wr-playlist-item { 
                background: #fff; 
                border: 1px solid #ccd0d4; 
                margin-bottom: 15px; 
                border-radius: 4px; 
                overflow: hidden; 
                box-shadow: 0 1px 1px rgba(0,0,0,0.04); 
            }
            .wr-pl-header { 
                background: #f0f0f1; 
                padding: 12px 15px; 
                display: flex; 
                align-items: center; 
                justify-content: space-between; 
                cursor: move; 
                border-bottom: 1px solid #ddd; 
            }
            .wr-pl-header input { 
                font-weight: 600; 
                font-size: 14px; 
                border: 1px solid transparent; 
                background: transparent; 
                box-shadow: none; 
                width: 60%; 
                padding: 6px; 
                transition: all 0.2s; 
            }
            .wr-pl-header input:focus, 
            .wr-pl-header input:hover { 
                border: 1px solid #2271b1; 
                background: #fff; 
                outline: none; 
            }
            .wr-pl-body { 
                padding: 15px; 
                display: none; 
                background: #fff; 
            }
            .wr-pl-body.open { 
                display: block; 
            }
            .wr-track-list { 
                margin: 0 0 15px 0; 
                max-height: 300px; 
                overflow-y: auto; 
                border: 1px solid #e2e4e7; 
                padding: 10px; 
                background: #f9f9f9; 
                border-radius: 3px; 
            }
            .wr-track { 
                padding: 10px; 
                border: 1px solid #eee; 
                font-size: 13px; 
                display: flex; 
                align-items: center; 
                background: #fff; 
                margin-bottom: 4px; 
                cursor: move; 
                border-radius:3px; 
                transition: background 0.2s; 
            }
            .wr-track:hover { 
                background: #f0f6fc; 
                border-color: #c3c4c7; 
            }
            .action-icons { 
                display: flex; 
                align-items: center; 
                gap: 8px; 
            }
            .action-icons .dashicons { 
                cursor: pointer; 
                color: #50575e; 
                padding: 4px; 
                border-radius: 3px; 
                transition: all 0.2s; 
            }
            .action-icons .dashicons:hover { 
                background: #e2e4e7; 
                color: #1d2327; 
            }
            .action-icons .dashicons-trash:hover { 
                background: #f8dede; 
                color: #d63638; 
            }
            .wr-visual-item { 
                position: relative; 
                width: 80px; 
                height: 80px; 
                cursor: move; 
                border-radius: 4px; 
                overflow: hidden; 
                border: 2px solid #fff; 
                box-shadow: 0 1px 4px rgba(0,0,0,0.2); 
            }
            .wr-visual-item img { 
                width: 100%; 
                height: 100%; 
                object-fit: cover; 
                display: block; 
            }
            .wr-visual-remove { 
                position: absolute; 
                top: 4px; 
                right: 4px; 
                background: rgba(214, 54, 56, 0.9); 
                color: #fff; 
                width: 20px; 
                height: 20px; 
                font-size: 14px; 
                text-align: center; 
                line-height: 18px; 
                cursor: pointer; 
                display: none; 
                border-radius: 50%; 
                font-weight: bold; 
            }
            .wr-visual-item:hover .wr-visual-remove { 
                display: block; 
            }
            .supercharge-banner { 
                padding: 15px; 
                margin-bottom: 20px; 
                border-radius: 4px; 
                color: #fff; 
            }
            .supercharge-banner h2 { 
                margin:0; 
                color: #fff; 
                font-size: 18px; 
            }
            .supercharge-banner p { 
                margin: 5px 0 0; 
                font-size: 14px; 
                opacity: 0.9; 
            }

            /* Indexer CSS for Single Site Tab */
            #tbfbkm-progress-wrap { 
                display:none; 
                margin-bottom:15px; 
                background: #f0f0f1; 
                padding: 15px; 
                border-radius: 4px; 
                border: 1px solid #c3c4c7; 
            }
            #tbfbkm-bar-container { 
                background:#ddd; 
                height:20px; 
                border-radius:10px; 
                overflow:hidden; 
                margin-bottom: 10px; 
            }
            #tbfbkm-bar { 
                width:0%; 
                background:#2271b1; 
                height:100%; 
                transition:width 0.2s; 
            }
            #tbfbkm-log { 
                padding:10px; 
                background:#1d2327; 
                color:#d1d1d1; 
                font-family:monospace; 
                font-size:12px; 
                height:180px; 
                overflow-y:auto; 
                border-radius:4px; 
                display:none; 
                line-height: 1.6; 
            }
        <?php
        $css = ob_get_clean();

        wp_register_style( 'tbfbkm-settings-css', false );
        wp_enqueue_style( 'tbfbkm-settings-css' );
        wp_add_inline_style( 'tbfbkm-settings-css', $css );

        wp_register_script( 'tbfbkm-settings-js', false, ['jquery', 'jquery-ui-sortable', 'wp-color-picker'], TBFBKM_VER, true );
        wp_enqueue_script( 'tbfbkm-settings-js' );
        
        wp_localize_script( 'tbfbkm-settings-js', 'tbfbkm_settings_data', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'tbfbkm_ajax_nonce' )
        ]);

        ob_start();
        ?>
        jQuery(document).ready(function($) {
            
            // 1. Init Color Pickers
            $('.tbfbkm-color-picker').wpColorPicker();

            // 2. Tab Navigation Logic
            window.switchTab = function(e, id) {
                e.preventDefault();
                $('.tbf-tab-content').hide();
                $('.nav-tab').removeClass('nav-tab-active');
                $('#tab-' + id).show();
                $(e.target).addClass('nav-tab-active');
            };

            // 3. Single Site Indexer Logic
            function writeLog(msg) {
                var d = new Date();
                var ts = d.toLocaleTimeString('en-US', { hour12: false });
                var logEl = $('#tbfbkm-log');
                logEl.append('<div><span style="color:#888;">[' + ts + ']</span> ' + msg + '</div>');
                logEl.scrollTop(logEl[0].scrollHeight);
            }

            $('#tbfbkm-run-indexer').click(function(e) {
                e.preventDefault();
                $(this).prop('disabled', true).text('Processing...');
                $('#tbfbkm-progress-wrap').slideDown();
                $('#tbfbkm-log').show().empty();
                writeLog('Initializing Big King Indexer...');
                processBatch(1, 0);
            });

            function processBatch(step, offset) {
                $.post(tbfbkm_settings_data.ajaxurl, { 
                    action: 'tbfbkm_process_batch', 
                    nonce: tbfbkm_settings_data.nonce, 
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
                    }
                });
            }

            $('#tbfbkm-wipe-index').click(function(e) {
                e.preventDefault();
                if (!confirm('Clear the media index database?')) {
                    return;
                }
                $(this).text('Clearing...');
                $.post(tbfbkm_settings_data.ajaxurl, { 
                    action: 'tbfbkm_wipe_index', 
                    nonce: tbfbkm_settings_data.nonce 
                }, function(res) {
                    location.reload();
                });
            });

            // 4. SEO Ping Engine
            $('#tbf-notify-search-engines').click(function(e) {
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true).text('Pinging Engines...');
                
                $.post(tbfbkm_settings_data.ajaxurl, {
                    action: 'tbfbkm_notify_search_engines',
                    nonce: tbfbkm_settings_data.nonce
                }, function(res) {
                    $('#seo-notify-status').html('<span style="color:green;">' + (res.data || 'Notification Sent Successfully.') + '</span>');
                    btn.prop('disabled', false).text('Notify Search Engines Now');
                });
            });

            // 5. Playlist Manager Engine
            var playlists = [];
            try { 
                playlists = JSON.parse($('#wr_playlists_json').val()); 
            } catch(e) { 
                playlists = []; 
            }
            if (!Array.isArray(playlists)) {
                playlists = [];
            }
            
            window.tbfbkmTrackCache = window.tbfbkmTrackCache || {};

            function renderPlaylists() {
                var html = '';
                var missingIds = [];

                playlists.forEach(function(pl, idx) {
                    html += `<div class='wr-playlist-item' data-idx='` + idx + `'>`;
                    html += `    <div class='wr-pl-header'>`;
                    html += `        <span class='dashicons dashicons-sort' style='color:#8c8f94; cursor:move;'></span>`;
                    html += `        <input type='text' value='` + pl.name + `' onkeyup='updateName(` + idx + `, this.value)' onchange='updateName(` + idx + `, this.value)' placeholder='Enter Playlist Name'>`;
                    html += `        <div class='action-icons'>`;
                    html += `            <span style='font-size:12px; margin-right:10px; font-weight:600; background:#e0e0e0; padding:2px 8px; border-radius:10px;'>` + pl.tracks.length + ` Tracks</span>`;
                    html += `            <span class='dashicons dashicons-arrow-down-alt2' onclick='toggleBody(` + idx + `)' title='Toggle Tracks'></span>`;
                    html += `            <span class='dashicons dashicons-trash' onclick='removePlaylist(` + idx + `)' title='Delete Playlist'></span>`;
                    html += `        </div>`;
                    html += `    </div>`;
                    html += `    <div class='wr-pl-body' id='pl-body-` + idx + `'>`;
                    html += `        <div class='wr-track-list' id='track-list-` + idx + `' data-pl-idx='` + idx + `'>`;

                    if (pl.tracks && pl.tracks.length) {
                        pl.tracks.forEach(function(t) {
                            var displayTitle = window.tbfbkmTrackCache[t] ? window.tbfbkmTrackCache[t] : 'Loading Title... (ID: ' + t + ')';
                            if (!window.tbfbkmTrackCache[t]) {
                                missingIds.push(t);
                            }

                            html += `        <div class='wr-track' data-id='` + t + `'>`;
                            html += `            <span class='dashicons dashicons-menu' style='font-size:16px; color:#a7aaad; margin-right:10px; cursor:move;'></span>`;
                            html += `            <span class='track-title' data-id='` + t + `' style='flex-grow:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500;'>` + displayTitle + `</span>`;
                            html += `            <span class='dashicons dashicons-no-alt' onclick='removeTrack(` + idx + `, "` + t + `")' style='cursor:pointer; font-size:16px; color:#d63638;' title='Remove Audio'></span>`;
                            html += `        </div>`;
                        });
                    } else {
                        html += `        <div style='padding:20px; color:#646970; text-align:center; font-style:italic;'>This playlist is empty. Click "Select Big King Audio" below to add songs.</div>`;
                    }

                    html += `        </div>`;
                    html += `        <div style='display:flex; justify-content:space-between; margin-top:15px;'>`;
                    html += `            <button type='button' class='button button-secondary' onclick='addTracks(` + idx + `)'>Select Big King Audio</button>`;
                    html += `            <button type='button' class='button button-link-delete' onclick='clearTracks(` + idx + `)'>Empty Playlist</button>`;
                    html += `        </div>`;
                    html += `    </div>`;
                    html += `</div>`;
                });
                
                $('#wr_playlists_container').html(html);
                $('#wr_playlists_json').val(JSON.stringify(playlists));
                
                $('#wr_playlists_container').sortable({
                    handle: '.wr-pl-header',
                    update: function() {
                        var newOrder = [];
                        $('.wr-playlist-item').each(function() { 
                            newOrder.push(playlists[$(this).data('idx')]); 
                        });
                        playlists = newOrder;
                        renderPlaylists();
                    }
                });

                $('.wr-track-list').sortable({
                    update: function() {
                        var plIdx = $(this).data('pl-idx');
                        var newTracks = [];
                        $(this).find('.wr-track').each(function() { 
                            newTracks.push($(this).data('id')); 
                        });
                        playlists[plIdx].tracks = newTracks;
                        $('#wr_playlists_json').val(JSON.stringify(playlists));
                    }
                });

                if (missingIds.length > 0) {
                    var uniqueMissing = [...new Set(missingIds)];
                    $.post(tbfbkm_settings_data.ajaxurl, { 
                        action: 'tbfbkm_resolve_playlist', 
                        nonce: tbfbkm_settings_data.nonce,
                        ids: uniqueMissing.join(',') 
                    }, function(res) {
                        if (res.success && res.data) {
                            res.data.forEach(function(track) {
                                window.tbfbkmTrackCache[track.id] = track.title;
                                $('.track-title[data-id="' + track.id + '"]').text(track.title).attr('title', track.title);
                            });
                        }
                    });
                }
            }

            window.updateName = function(idx, val) { 
                playlists[idx].name = val; 
                $('#wr_playlists_json').val(JSON.stringify(playlists)); 
            };
            
            window.toggleBody = function(idx) { 
                $('#pl-body-' + idx).toggleClass('open'); 
            };
            
            window.clearTracks = function(idx) { 
                if (confirm('Remove all tracks?')) { 
                    playlists[idx].tracks = []; 
                    renderPlaylists(); 
                    setTimeout(function() { $('#pl-body-' + idx).addClass('open'); }, 10); 
                } 
            };
            
            window.removePlaylist = function(idx) { 
                if (confirm('Delete entire playlist?')) { 
                    playlists.splice(idx, 1); 
                    renderPlaylists(); 
                } 
            };
            
            window.removeTrack = function(plIdx, trackId) { 
                playlists[plIdx].tracks = playlists[plIdx].tracks.filter(id => id != trackId); 
                renderPlaylists(); 
                setTimeout(function() { $('#pl-body-' + plIdx).addClass('open'); }, 10); 
            };

            window.addTracks = function(idx) {
                var frame = wp.media({ 
                    title: 'Select Big King Media Audio', 
                    multiple: 'add', 
                    library: { type: 'audio' }, 
                    button: { text: 'Add to Playlist' } 
                });
                
                frame.on('select', function() {
                    frame.state().get('selection').map(att => { 
                        var data = att.toJSON();
                        window.tbfbkmTrackCache[data.id] = data.title || data.filename || 'Track ' + data.id;
                        playlists[idx].tracks.push(data.id); 
                    });
                    renderPlaylists();
                    setTimeout(function() { $('#pl-body-' + idx).addClass('open'); }, 10);
                });
                
                frame.open();
            };

            $('#btn_add_playlist').click(function(e) { 
                e.preventDefault();
                playlists.push({ name: 'New Custom Playlist', tracks: [] }); 
                renderPlaylists(); 
                setTimeout(function() { $('#pl-body-' + (playlists.length - 1)).addClass('open'); }, 10);
            });

            // 6. Visual Media Chooser
            $('#btn_select_images').click(function(e) {
                e.preventDefault();
                var frame = wp.media({ 
                    title: 'Select Big King Media Visuals', 
                    multiple: 'add', 
                    library: { type: 'image' }, 
                    button: { text: 'Use Selected Images' } 
                });
                
                frame.on('select', function() {
                    var ids = [];
                    frame.state().get('selection').map(att => ids.push(att.id));
                    
                    var curr = $('#wr_specific_ids').val();
                    if (curr) {
                        ids = [curr, ids.join(',')];
                    }
                    
                    ids = ids.filter(Boolean).join(',').split(',').filter(Boolean); 
                    $('#wr_specific_ids').val(ids.join(','));
                    renderPreview();
                });
                
                frame.open();
            });

            window.renderPreview = function() {
                var ids = $('#wr_specific_ids').val();
                if (!ids) { 
                    $('#wr_visual_preview').html('<div style="color:#999; width:100%; text-align:center;">No specific images selected.</div>'); 
                    return; 
                }
                
                $('#wr_visual_preview').html('<div style="color:#999;">Loading previews...</div>');
                
                $.post(tbfbkm_settings_data.ajaxurl, { 
                    action: 'tbfbkm_resolve_ids', 
                    nonce: tbfbkm_settings_data.nonce,
                    ids: ids 
                }, function(res) {
                    if (res.success) {
                        var html = '';
                        res.data.forEach(function(url) {
                            html += `<div class='wr-visual-item'><img src='` + url + `'><div class='wr-visual-remove' onclick='removeVisual(this)'>&times;</div></div>`;
                        });
                        $('#wr_visual_preview').html(html);
                    }
                });
            };
            
            window.removeVisual = function(el) { 
                $(el).parent().remove(); 
            };

            // Initialize views on load
            renderPlaylists();
            
            if ($('#wr_specific_ids').val()) { 
                renderPreview(); 
            } else { 
                $('#wr_visual_preview').html('<div style="color:#999; width:100%; text-align:center;">No specific images selected.</div>'); 
            }
            
            // Ensure JSON is pushed to the hidden field on form submit
            $('#tbfbkm_settings_form').submit(function() { 
                $('#wr_playlists_json').val(JSON.stringify(playlists)); 
            });
            
            // Scope Dropdown Toggle
            var scopeSel = document.getElementById('tbfbkm_scope_mode');
            var scopeRow = document.getElementById('tbfbkm_scope_sites_row');
            if (scopeSel && scopeRow) {
                scopeSel.addEventListener('change', function() { 
                    scopeRow.style.display = (this.value === 'specific') ? 'table-row' : 'none'; 
                });
                scopeRow.style.display = (scopeSel.value === 'specific') ? 'table-row' : 'none';
            }
        });
        <?php
        $js = ob_get_clean();
        wp_add_inline_script( 'tbfbkm-settings-js', $js );
    }

    public static function render() {
        $opts = self::get_options();
        $gallery_url = home_url( '/photo/' );

        // Determine control authority
        $master_id = (int) get_site_option( 'tbfbkm_master_controller_id', is_multisite() ? get_main_site_id() : -1 );
        $current_id = get_current_blog_id();
        $is_independent = ( $master_id === -1 || !is_multisite() );
        $is_master = ( $is_independent || $master_id === $current_id );

        // If Single Site, grab the system settings as well
        if ( ! is_multisite() ) {
            $sys_opts = get_option( 'tbfbkm_settings', [] );
            $who = $sys_opts['who_can_browse'] ?? 'uploaders';
            $insert = $sys_opts['insert_mode'] ?? 'proxy';
            $per_page = $sys_opts['per_page'] ?? 60;

            global $wpdb;
            $index_table = $wpdb->base_prefix . 'tbfbkm_index';
            $total_media = 0;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$index_table}'" ) ) {
                $total_media = $wpdb->get_var( "SELECT COUNT(*) FROM $index_table" );
            }
        }

        ?>
        <div class="wrap">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1 class="wp-heading-inline"><?php esc_html_e( 'Big King Media', 'tbf-big-king-media' ); ?></h1>
                <a href="<?php echo esc_url( $gallery_url ); ?>" target="_blank" class="page-title-action" style="border:1px solid #2271b1; color:#2271b1; font-weight:bold;">View Live Gallery &rarr;</a>
            </div>
            <hr class="wp-header-end">
            <p style="margin-top: 5px; font-style: italic; color: #666;">By Sher Trott Bailey</p>

            <?php if ( ! $is_master ) : ?>
                <div style="background: #fff; padding: 40px; border: 1px solid #c3c4c7; border-radius: 8px; text-align: center; max-width: 800px; margin-top: 30px;">
                    <span class="dashicons dashicons-lock" style="font-size: 60px; height: 60px; width: 60px; color: #d63638; margin-bottom: 20px;"></span>
                    <h2 style="font-size: 24px; margin-bottom: 20px;">Network Override Active</h2>
                    <p style="font-size: 16px; color: #3c434a;">World Ruler Photofall, Kaleeyon Media SEO, and Princess Keilah Studio settings are currently controlled by the Master Site:</p>
                    <p style="font-size: 20px; font-weight: bold; color: #2271b1;">
                        <?php echo esc_html( get_blog_option( $master_id, 'blogname' ) ); ?>
                    </p>
                    <div style="margin-top: 30px; display: flex; justify-content: center; gap: 15px;">
                        <a href="<?php echo esc_url( get_admin_url( $master_id, 'upload.php?page=tbfbkm-photofall-settings' ) ); ?>" class="button button-primary button-large">Edit Settings on Master Site</a>
                    </div>
                </div>
            <?php else : ?>

                <form method="post" action="options.php" id="tbfbkm_settings_form">
                    <?php settings_fields( 'tbfbkm_photofall_group' ); ?>
                    
                    <h2 class="nav-tab-wrapper">
                        <?php if ( ! is_multisite() ) : ?>
                            <a href="#tab-system" class="nav-tab nav-tab-active" onclick="switchTab(event, 'system')">System & Index</a>
                            <a href="#tab-general" class="nav-tab" onclick="switchTab(event, 'general')">World Ruler Sher Photofall</a>
                        <?php else : ?>
                            <a href="#tab-general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">World Ruler Sher Photofall</a>
                        <?php endif; ?>

                        <a href="#tab-seo" class="nav-tab" onclick="switchTab(event, 'seo')">Kaleeyon Media SEO</a>
                        
                        <?php if ( is_multisite() ) : ?>
                            <a href="#tab-scope" class="nav-tab" onclick="switchTab(event, 'scope')">Network Scope</a>
                        <?php endif; ?>
                        
                        <a href="#tab-uploader" class="nav-tab" onclick="switchTab(event, 'uploader')">Frontend Uploader</a>
                        <a href="#tab-worldruler" class="nav-tab" onclick="switchTab(event, 'worldruler')">Princess Keilah Studio</a>
                        <a href="#tab-shortcodes" class="nav-tab" onclick="switchTab(event, 'shortcodes')">Shortcodes</a>
                    </h2>

                    <?php if ( ! is_multisite() ) : ?>
                    <div id="tab-system" class="tbf-tab-content" style="padding-top:20px;">
                        <div class="supercharge-banner" style="background: #1d2327;">
                            <h2>SUPERCHARGE: System & Indexer</h2>
                            <p>The engine room. Control administrative permissions and run the high-speed indexer to catalogue your media library into the World Ruler display format.</p>
                        </div>

                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Browser Permissions</th>
                                <td>
                                    <select name="tbfbkm_settings[who_can_browse]">
                                        <option value="uploaders" <?php selected( 'uploaders', $who ); ?>>Uploaders & Admins</option>
                                        <option value="admins" <?php selected( 'admins', $who ); ?>>Admins Only</option>
                                    </select>
                                    <p class="description">Who is allowed to browse the network media modal.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Insertion Mode</th>
                                <td>
                                    <select name="tbfbkm_settings[insert_mode]">
                                        <option value="proxy" <?php selected( 'proxy', $insert ); ?>>Smart Proxy (Recommended)</option>
                                        <option value="url" <?php selected( 'url', $insert ); ?>>Direct URL</option>
                                    </select>
                                    <p class="description">How images are placed into the editor from the modal.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Modal Items Per Page</th>
                                <td>
                                    <input type="number" name="tbfbkm_settings[per_page]" value="<?php echo esc_attr( $per_page ); ?>" min="10" max="200" />
                                    <p class="description">Number of items loaded per AJAX request in the modal.</p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin:30px 0;">

                        <h3>Big King Indexer</h3>
                        <p>Database Status: <strong><?php echo esc_html( number_format( $total_media ) ); ?> items indexed</strong>.</p>
                        
                        <div id="tbfbkm-progress-wrap">
                            <div id="tbfbkm-bar-container">
                                <div id="tbfbkm-bar"></div>
                            </div>
                            <p id="tbfbkm-status" style="margin:0 0 10px 0; font-weight:bold; color:#2271b1;">Initializing scan...</p>
                            <div id="tbfbkm-log"></div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <button id="tbfbkm-run-indexer" class="button button-primary button-hero" style="background: #ff8800; border-color: #dd7700; text-shadow: none; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">Run Big King Indexer</button>
                            <button id="tbfbkm-wipe-index" class="button button-link-delete" style="color: #d63638;">Wipe Index Database</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="tab-general" class="tbf-tab-content" style="<?php echo !is_multisite() ? 'display:none;' : ''; ?> padding-top:20px;">
                        <div class="supercharge-banner" style="background: #0044cc;">
                            <h2>SUPERCHARGE: World Ruler Sher Photofall</h2>
                            <p>The ultimate visual engine for the Trott Bailey Family Kingdom. Configure the frontend infinite scroll gallery layout and filter settings.</p>
                        </div>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Items Per Page</th>
                                <td><input type="number" name="tbfbkm_photofall_options[per_page]" value="<?php echo esc_attr( $opts['per_page'] ); ?>" min="1" max="100" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Default Sort Order</th>
                                <td>
                                    <select name="tbfbkm_photofall_options[default_sort]">
                                        <option value="date_desc" <?php selected( 'date_desc', $opts['default_sort'] ); ?>>Newest First</option>
                                        <option value="date_asc" <?php selected( 'date_asc', $opts['default_sort'] ); ?>>Oldest First</option>
                                        <option value="random" <?php selected( 'random', $opts['default_sort'] ); ?>>Random Shuffle</option>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Caption Display Mode</th>
                                <td>
                                    <select name="tbfbkm_photofall_options[caption_mode]">
                                        <option value="hover" <?php selected( 'hover', $opts['caption_mode'] ); ?>>Show on Hover</option>
                                        <option value="always" <?php selected( 'always', $opts['caption_mode'] ); ?>>Always Show</option>
                                        <option value="never" <?php selected( 'never', $opts['caption_mode'] ); ?>>Never Show</option>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Toolbar Features</th>
                                <td>
                                    <label><input type="checkbox" name="tbfbkm_photofall_options[show_search]" value="1" <?php checked( 1, $opts['show_search'] ); ?> /> Show Search Bar</label><br>
                                    <label><input type="checkbox" name="tbfbkm_photofall_options[show_filter_type]" value="1" <?php checked( 1, $opts['show_filter_type'] ); ?> /> Show Media Type Filter</label><br>
                                    <label><input type="checkbox" name="tbfbkm_photofall_options[show_filter_year]" value="1" <?php checked( 1, $opts['show_filter_year'] ); ?> /> Show Year Filter</label><br>
                                    <?php if ( is_multisite() ) : ?>
                                        <label><input type="checkbox" name="tbfbkm_photofall_options[show_filter_site]" value="1" <?php checked( 1, $opts['show_filter_site'] ); ?> /> Show Origin Site Filter</label><br>
                                    <?php endif; ?>
                                    <label><input type="checkbox" name="tbfbkm_photofall_options[show_sort]" value="1" <?php checked( 1, $opts['show_sort'] ); ?> /> Show Sort Dropdown</label><br>
                                    <label><input type="checkbox" name="tbfbkm_photofall_options[show_random]" value="1" <?php checked( 1, $opts['show_random'] ); ?> /> Show Shuffle Button</label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div id="tab-seo" class="tbf-tab-content" style="display:none; padding-top:20px;">
                        <div class="supercharge-banner" style="background: #cc0044;">
                            <h2>SUPERCHARGE: Kaleeyon Media SEO</h2>
                            <p>Built specifically for high-impact media. Force-feeds your visual content to search engines by injecting metadata directly into the global search crawl.</p>
                        </div>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">XML Sitemaps</th>
                                <td><label><input type="checkbox" name="tbfbkm_photofall_options[enable_xml_sitemaps]" value="1" <?php checked( 1, $opts['enable_xml_sitemaps'] ); ?> /> Enable Dynamic Photo & Video Sitemaps</label></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Deep Interlinking</th>
                                <td><label><input type="checkbox" name="tbfbkm_photofall_options[seo_interlink_origin]" value="1" <?php checked( 1, $opts['seo_interlink_origin'] ); ?> /> Show "Featured In" backlinks on attachment pages</label></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Manual Indexing</th>
                                <td>
                                    <button id="tbf-notify-search-engines" class="button button-secondary">Notify Search Engines Now</button>
                                    <div id="seo-notify-status" style="margin-top:10px; font-weight:bold;"></div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php if ( is_multisite() ) : ?>
                    <div id="tab-scope" class="tbf-tab-content" style="display:none; padding-top:20px;">
                        <div class="supercharge-banner" style="background: #1d2327;">
                            <h2>SUPERCHARGE: Network Scope</h2>
                            <p>Control the boundaries of your media empire. Define exactly which sites within the Trott Bailey Family Kingdom contribute assets to this specific installation.</p>
                        </div>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Display Scope</th>
                                <td>
                                    <select name="tbfbkm_photofall_options[network_scope_mode]" id="tbfbkm_scope_mode">
                                        <option value="all" <?php selected( 'all', $opts['network_scope_mode'] ); ?>>Show Media from Entire Network</option>
                                        <option value="specific" <?php selected( 'specific', $opts['network_scope_mode'] ); ?>>Show Media from Specific Sites Only</option>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top" id="tbfbkm_scope_sites_row">
                                <th scope="row">Allowed Sites</th>
                                <td>
                                    <div style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fff;">
                                        <?php 
                                        $sites = get_sites( ['number' => 500] );
                                        $selected_sites = $opts['network_scope_sites'] ?? [];
                                        foreach ( $sites as $s ) {
                                            printf(
                                                '<label style="display:block; margin-bottom: 5px;"><input type="checkbox" name="tbfbkm_photofall_options[network_scope_sites][]" value="%1$s" %2$s> %3$s (ID: %1$s)</label>',
                                                esc_attr( $s->blog_id ),
                                                checked( in_array( $s->blog_id, $selected_sites ), true, false ),
                                                esc_html( get_blog_option( $s->blog_id, 'blogname' ) )
                                            );
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <?php endif; ?>

                    <div id="tab-uploader" class="tbf-tab-content" style="display:none; padding-top:20px;">
                        <div class="supercharge-banner" style="background: #007cba;">
                            <h2>SUPERCHARGE: Frontend Uploader</h2>
                            <p>Empower your trusted citizens. Allow designated users to upload media directly from the frontend.</p>
                        </div>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Enable Frontend Uploading</th>
                                <td><label><input type="checkbox" name="tbfbkm_photofall_options[enable_frontend_upload]" value="1" <?php checked( 1, $opts['enable_frontend_upload'] ); ?> /> Allow users to upload media via the frontend</label></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Authorized User Roles</th>
                                <td>
                                    <select name="tbfbkm_photofall_options[upload_roles][]" multiple style="height: 120px; width: 300px; padding: 5px;">
                                        <?php 
                                        $roles = wp_roles()->roles;
                                        $selected = $opts['upload_roles'] ?? ['administrator'];
                                        foreach ( $roles as $key => $role ) {
                                            echo '<option value="' . esc_attr( $key ) . '" ' . ( in_array( $key, $selected ) ? 'selected' : '' ) . '>' . esc_html( $role['name'] ) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div id="tab-worldruler" class="tbf-tab-content" style="display:none; padding-top:20px;">
                        <div class="supercharge-banner" style="background: #8e24aa;">
                            <h2>SUPERCHARGE: Princess Keilah Studio</h2>
                            <p>The heartbeat of the Kingdom. Provides an omnipresent, floating multimedia gadget that plays your curated audio and visual playlists globally.</p>
                        </div>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Enable Global Gadget</th>
                                <td><label><input type="checkbox" name="tbfbkm_photofall_options[enable_world_ruler]" value="1" <?php checked( 1, $opts['enable_world_ruler'] ); ?> /> Display floating player on this site</label></td>
                            </tr>
                            
                            <tr valign="top">
                                <th scope="row">Player Styling</th>
                                <td>
                                    <div style="margin-bottom: 15px;">
                                        <label style="display:inline-block; width:120px; font-weight:bold;">Background</label>
                                        <input type="text" name="tbfbkm_photofall_options[wr_bg_color]" value="<?php echo esc_attr( $opts['wr_bg_color'] ); ?>" class="tbfbkm-color-picker" />
                                    </div>
                                    <div style="margin-bottom: 15px;">
                                        <label style="display:inline-block; width:120px; font-weight:bold;">Text / Icons</label>
                                        <input type="text" name="tbfbkm_photofall_options[wr_text_color]" value="<?php echo esc_attr( $opts['wr_text_color'] ); ?>" class="tbfbkm-color-picker" />
                                    </div>
                                    <div style="margin-bottom: 15px;">
                                        <label style="display:inline-block; width:120px; font-weight:bold;">Accent / Active</label>
                                        <input type="text" name="tbfbkm_photofall_options[wr_accent_color]" value="<?php echo esc_attr( $opts['wr_accent_color'] ); ?>" class="tbfbkm-color-picker" />
                                    </div>
                                    <div style="margin-bottom: 15px;">
                                        <label style="display:inline-block; width:120px; font-weight:bold;">Panel Opacity</label>
                                        <input type="number" name="tbfbkm_photofall_options[wr_opacity]" value="<?php echo esc_attr( $opts['wr_opacity'] ); ?>" min="10" max="100" style="width: 80px;" /> %
                                    </div>
                                </td>
                            </tr>
                            
                            <tr valign="top">
                                <th scope="row">Gadget Behavior</th>
                                <td>
                                    <label><input type="checkbox" name="tbfbkm_photofall_options[wr_open_default]" value="1" <?php checked( 1, $opts['wr_open_default'] ); ?> /> Gadget is Expanded/Open by Default</label><br>
                                    <label><input type="checkbox" name="tbfbkm_photofall_options[wr_auto_start]" value="1" <?php checked( 1, $opts['wr_auto_start'] ); ?> /> Auto-Start Audio on Page Load</label>
                                </td>
                            </tr>
                            
                            <tr valign="top">
                                <th scope="row">Audio Playlists Manager</th>
                                <td>
                                    <div id="wr_playlist_manager" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                        <textarea name="tbfbkm_photofall_options[wr_playlists_json]" id="wr_playlists_json" style="display:none;"><?php echo esc_textarea( $opts['wr_playlists_json'] ); ?></textarea>
                                        <div id="wr_playlists_container" class="sortable-list"></div>
                                        <button type="button" class="button button-primary button-hero" id="btn_add_playlist" style="margin-top:15px;">+ Create New Playlist</button>
                                    </div>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Visual Source Mode</th>
                                <td>
                                    <select name="tbfbkm_photofall_options[wr_visual_mode]">
                                        <option value="random" <?php selected( 'random', $opts['wr_visual_mode'] ); ?>>Random Images from Entire Network</option>
                                        <option value="specific" <?php selected( 'specific', $opts['wr_visual_mode'] ); ?>>Specific Selected Images</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr valign="top">
                                <th scope="row">Specific Images</th>
                                <td>
                                    <textarea id="wr_specific_ids" name="tbfbkm_photofall_options[wr_specific_ids]" class="large-text code" style="display:none;" onchange="renderPreview()"><?php echo esc_textarea( $opts['wr_specific_ids'] ); ?></textarea>
                                    <div style="margin-bottom:15px;">
                                        <button type="button" class="button button-secondary" id="btn_select_images">Select Big King Media Images</button>
                                        <button type="button" class="button button-link-delete" onclick="jQuery('#wr_visual_preview').empty(); jQuery('#wr_specific_ids').val('');">Clear All Visuals</button>
                                    </div>
                                    <div id="wr_visual_preview" class="sortable-grid" style="display:flex; gap:10px; flex-wrap:wrap; background:#fff; padding:15px; border:1px solid #ddd; border-radius:4px; min-height:80px;"></div>
                                </td>
                            </tr>
                            
                            <tr valign="top">
                                <th scope="row">Slideshow Duration</th>
                                <td><input type="number" name="tbfbkm_photofall_options[wr_duration]" value="<?php echo esc_attr( $opts['wr_duration'] ); ?>" min="2" max="60" style="width: 80px;" /> seconds per image</td>
                            </tr>
                        </table>
                    </div>

                    <div id="tab-shortcodes" class="tbf-tab-content" style="display:none; padding-top:20px;">
                        <div class="supercharge-banner" style="background: #222;">
                            <h2>SUPERCHARGE: Kingdom Integration</h2>
                            <p>Deploy your media anywhere. Use these elite shortcodes to embed the Trott Bailey Family Kingdom experience into any page, post, or custom layout instantly.</p>
                        </div>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><strong>Princess Keilah Studio</strong></th>
                                <td>
                                    <p style="margin-top:0;">Embeds the interactive multimedia gadget inline on any page.</p>
                                    <code style="font-size: 14px; background: #f0f0f1; padding: 4px 8px;">[tbfbkm_princess_keilah_studio]</code>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><strong>World Ruler Photofall</strong></th>
                                <td>
                                    <p style="margin-top:0;">Displays the master Photofall infinite scroll gallery.</p>
                                    <code style="font-size: 14px; background: #f0f0f1; padding: 4px 8px;">[tbfbkm_photofall_grid limit="20" type="image"]</code>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><strong>Kaleeyon Media SEO</strong></th>
                                <td>
                                    <p style="margin-top:0;">Outputs raw SEO metadata schemas for external crawlers.</p>
                                    <code style="font-size: 14px; background: #f0f0f1; padding: 4px 8px;">[tbfbkm_kaleeyon_seo_data id="123"]</code>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <?php submit_button( 'Save All Changes', 'primary large' ); ?>
                    </div>
                </form>

            <?php endif; ?>
        </div>
        <?php
    }
}