<?php
/**
 * File: includes/admin/class-tbfbkm-subsite-settings.php
 * Version: 7.0.1.3 (Player Color Customization)
 * Description: Manages the subsite settings, including Photofall options and the Princess Keilah Studio Playlist Manager.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Subsite_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function get_options() {
        $defaults = [
            // General Photofall
            'per_page' => 20,
            'caption_mode' => 'hover',
            'default_sort' => 'date_desc',
            'show_search' => 1,
            'show_filter_type' => 1,
            'show_filter_year' => 1,
            'show_filter_site' => 1,
            'show_sort' => 1,
            'show_random' => 1,
            
            // Kaleeyon SEO
            'seo_interlink_origin' => 1, 
            'enable_xml_sitemaps' => 1,
            
            // Network Scope
            'network_scope_mode' => 'all', 
            'network_scope_sites' => [],
            
            // Uploader
            'enable_frontend_upload' => 0,
            'upload_roles' => ['administrator'],
            
            // Princess Keilah Studio Core
            'enable_world_ruler' => 0,
            'wr_open_default' => 0, 
            'wr_auto_start' => 0,
            'wr_network_wide' => 0, 
            'wr_visual_mode' => 'random',
            'wr_specific_ids' => '',
            'wr_duration' => 5,
            'wr_playlists_json' => '[{"name":"Princess Keilah Default","tracks":[]}]',
            
            // Princess Keilah Studio Styling
            'wr_bg_color' => '#121218',
            'wr_text_color' => '#ffffff',
            'wr_accent_color' => '#2271b1'
        ];
        
        $opts = get_option('tbfbkm_photofall_options', []);
        return array_merge($defaults, is_array($opts) ? $opts : []);
    }

    public static function add_page() {
        $title = 'Big King Media';
        add_submenu_page(
            'upload.php',
            $title,
            $title,
            'manage_options',
            'tbfbkm-photofall-settings',
            [__CLASS__, 'render']
        );
    }

    public static function register_settings() {
        register_setting('tbfbkm_photofall_group', 'tbfbkm_photofall_options', [
            'sanitize_callback' => [__CLASS__, 'sanitize']
        ]);
    }

    public static function sanitize( $input ) {
        $new_input = [];
        
        // Integers
        $ints = ['per_page', 'wr_duration'];
        foreach($ints as $key) {
            if(isset($input[$key])) $new_input[$key] = absint($input[$key]);
        }

        // Strings
        $strings = ['caption_mode', 'default_sort', 'network_scope_mode', 'wr_visual_mode', 'wr_specific_ids'];
        foreach($strings as $key) {
            if(isset($input[$key])) $new_input[$key] = sanitize_text_field($input[$key]);
        }
        
        // Hex Colors
        $hexes = ['wr_bg_color', 'wr_text_color', 'wr_accent_color'];
        foreach($hexes as $key) {
            if(isset($input[$key])) $new_input[$key] = sanitize_hex_color($input[$key]);
        }

        // Booleans
        $bools = [
            'show_search', 'show_filter_type', 'show_filter_year', 'show_filter_site', 
            'show_sort', 'show_random', 'seo_interlink_origin', 'enable_xml_sitemaps', 
            'enable_frontend_upload', 'enable_world_ruler', 'wr_open_default', 
            'wr_auto_start', 'wr_network_wide'
        ];
        foreach($bools as $key) {
            $new_input[$key] = isset($input[$key]) ? 1 : 0;
        }

        // Arrays
        if ( isset($input['network_scope_sites']) && is_array($input['network_scope_sites']) ) {
            $new_input['network_scope_sites'] = array_map('intval', $input['network_scope_sites']);
        } else {
            $new_input['network_scope_sites'] = [];
        }

        if ( isset($input['upload_roles']) && is_array($input['upload_roles']) ) {
            $new_input['upload_roles'] = array_map('sanitize_text_field', $input['upload_roles']);
        } else {
            $new_input['upload_roles'] = ['administrator'];
        }

        // JSON Playlists
        if ( isset($input['wr_playlists_json']) ) {
            $json_raw = stripslashes($input['wr_playlists_json']);
            $json = json_decode($json_raw, true);
            
            if ( is_array($json) ) {
                array_walk_recursive($json, function(&$item, $key){
                    if(is_string($item)) $item = sanitize_text_field($item);
                });
                $new_input['wr_playlists_json'] = json_encode($json);
            } else {
                $new_input['wr_playlists_json'] = '[{"name":"Princess Keilah Default","tracks":[]}]';
            }
        }

        return $new_input;
    }

    public static function render() {
        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script('jquery-ui-sortable');
        
        $opts = self::get_options();
        $gallery_url = home_url('/photo/');
        ?>
        <div class="wrap">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1 class="wp-heading-inline"><?php esc_html_e('Big King Media Settings', 'tbf-big-king-media'); ?></h1>
                <a href="<?php echo esc_url($gallery_url); ?>" target="_blank" class="page-title-action" style="border:1px solid #2271b1; color:#2271b1; font-weight:bold;">View Live Gallery &rarr;</a>
            </div>
            <hr class="wp-header-end">
            
            <form method="post" action="options.php" id="tbfbkm_settings_form">
                <?php settings_fields('tbfbkm_photofall_group'); ?>
                
                <h2 class="nav-tab-wrapper">
                    <a href="#tab-general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">Photofall Settings</a>
                    <a href="#tab-seo" class="nav-tab" onclick="switchTab(event, 'seo')">Kaleeyon SEO</a>
                    <?php if ( is_multisite() ): ?>
                    <a href="#tab-scope" class="nav-tab" onclick="switchTab(event, 'scope')">Network Scope</a>
                    <?php endif; ?>
                    <a href="#tab-uploader" class="nav-tab" onclick="switchTab(event, 'uploader')">Frontend Uploader</a>
                    <a href="#tab-worldruler" class="nav-tab" onclick="switchTab(event, 'worldruler')">Princess Keilah Studio</a>
                </h2>

                <div id="tab-general" class="tbf-tab-content" style="padding-top:20px;">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Items Per Page</th>
                            <td><input type="number" name="tbfbkm_photofall_options[per_page]" value="<?php echo esc_attr($opts['per_page']); ?>" min="1" max="100" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Default Sort Order</th>
                            <td>
                                <select name="tbfbkm_photofall_options[default_sort]">
                                    <option value="date_desc" <?php selected('date_desc', $opts['default_sort']); ?>>Newest First</option>
                                    <option value="date_asc" <?php selected('date_asc', $opts['default_sort']); ?>>Oldest First</option>
                                    <option value="random" <?php selected('random', $opts['default_sort']); ?>>Random Shuffle</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Caption Display Mode</th>
                            <td>
                                <select name="tbfbkm_photofall_options[caption_mode]">
                                    <option value="hover" <?php selected('hover', $opts['caption_mode']); ?>>Show on Hover</option>
                                    <option value="always" <?php selected('always', $opts['caption_mode']); ?>>Always Show</option>
                                    <option value="never" <?php selected('never', $opts['caption_mode']); ?>>Never Show</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Toolbar Features</th>
                            <td>
                                <label><input type="checkbox" name="tbfbkm_photofall_options[show_search]" value="1" <?php checked(1, $opts['show_search']); ?> /> Show Search Bar</label><br>
                                <label><input type="checkbox" name="tbfbkm_photofall_options[show_filter_type]" value="1" <?php checked(1, $opts['show_filter_type']); ?> /> Show Media Type Filter</label><br>
                                <label><input type="checkbox" name="tbfbkm_photofall_options[show_filter_year]" value="1" <?php checked(1, $opts['show_filter_year']); ?> /> Show Year Filter</label><br>
                                <?php if ( is_multisite() ): ?>
                                <label><input type="checkbox" name="tbfbkm_photofall_options[show_filter_site]" value="1" <?php checked(1, $opts['show_filter_site']); ?> /> Show Origin Site Filter</label><br>
                                <?php endif; ?>
                                <label><input type="checkbox" name="tbfbkm_photofall_options[show_sort]" value="1" <?php checked(1, $opts['show_sort']); ?> /> Show Sort Dropdown</label><br>
                                <label><input type="checkbox" name="tbfbkm_photofall_options[show_random]" value="1" <?php checked(1, $opts['show_random']); ?> /> Show Shuffle Button</label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-seo" class="tbf-tab-content" style="display:none; padding-top:20px;">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">XML Sitemaps</th>
                            <td><label><input type="checkbox" name="tbfbkm_photofall_options[enable_xml_sitemaps]" value="1" <?php checked(1, $opts['enable_xml_sitemaps']); ?> /> Enable Dynamic Photo & Video Sitemaps</label></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Deep Interlinking</th>
                            <td><label><input type="checkbox" name="tbfbkm_photofall_options[seo_interlink_origin]" value="1" <?php checked(1, $opts['seo_interlink_origin']); ?> /> Show "Featured In" backlinks on attachment pages</label></td>
                        </tr>
                    </table>
                </div>

                <div id="tab-scope" class="tbf-tab-content" style="display:none; padding-top:20px;">
                    <?php if ( is_multisite() ): ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Display Scope</th>
                            <td>
                                <select name="tbfbkm_photofall_options[network_scope_mode]" id="tbfbkm_scope_mode">
                                    <option value="all" <?php selected('all', $opts['network_scope_mode']); ?>>Show Media from Entire Network</option>
                                    <option value="specific" <?php selected('specific', $opts['network_scope_mode']); ?>>Show Media from Specific Sites Only</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top" id="tbfbkm_scope_sites_row">
                            <th scope="row">Allowed Sites</th>
                            <td>
                                <div style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fff;">
                                    <?php 
                                    $sites = get_sites(['number' => 500]);
                                    $selected_sites = $opts['network_scope_sites'] ?? [];
                                    foreach ( $sites as $s ) {
                                        $checked = in_array($s->blog_id, $selected_sites) ? 'checked' : '';
                                        echo '<label style="display:block; margin-bottom: 5px;"><input type="checkbox" name="tbfbkm_photofall_options[network_scope_sites][]" value="' . esc_attr($s->blog_id) . '" ' . $checked . '> ' . esc_html(get_blog_option($s->blog_id, 'blogname')) . ' (ID: ' . $s->blog_id . ')</label>';
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <?php endif; ?>
                </div>

                <div id="tab-uploader" class="tbf-tab-content" style="display:none; padding-top:20px;">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Enable Frontend Uploading</th>
                            <td><label><input type="checkbox" name="tbfbkm_photofall_options[enable_frontend_upload]" value="1" <?php checked(1, $opts['enable_frontend_upload']); ?> /> Allow users to upload media via the frontend</label></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Authorized User Roles</th>
                            <td>
                                <select name="tbfbkm_photofall_options[upload_roles][]" multiple style="height: 120px; width: 300px; padding: 5px;">
                                    <?php 
                                    $roles = wp_roles()->roles;
                                    $selected = $opts['upload_roles'] ?? ['administrator'];
                                    foreach($roles as $key => $role) {
                                        echo '<option value="' . esc_attr($key) . '" ' . (in_array($key, $selected) ? 'selected' : '') . '>' . esc_html($role['name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-worldruler" class="tbf-tab-content" style="display:none; padding-top:20px;">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Enable Global Gadget</th>
                            <td><label><input type="checkbox" name="tbfbkm_photofall_options[enable_world_ruler]" value="1" <?php checked(1, $opts['enable_world_ruler']); ?> /> Display floating player on this site</label></td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">Player Colors</th>
                            <td>
                                <div style="margin-bottom: 15px;">
                                    <label style="display:inline-block; width:120px; font-weight:bold;">Background</label>
                                    <input type="text" name="tbfbkm_photofall_options[wr_bg_color]" value="<?php echo esc_attr($opts['wr_bg_color']); ?>" class="tbfbkm-color-picker" />
                                </div>
                                <div style="margin-bottom: 15px;">
                                    <label style="display:inline-block; width:120px; font-weight:bold;">Text / Icons</label>
                                    <input type="text" name="tbfbkm_photofall_options[wr_text_color]" value="<?php echo esc_attr($opts['wr_text_color']); ?>" class="tbfbkm-color-picker" />
                                </div>
                                <div style="margin-bottom: 15px;">
                                    <label style="display:inline-block; width:120px; font-weight:bold;">Accent / Active</label>
                                    <input type="text" name="tbfbkm_photofall_options[wr_accent_color]" value="<?php echo esc_attr($opts['wr_accent_color']); ?>" class="tbfbkm-color-picker" />
                                </div>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">Gadget Behavior</th>
                            <td>
                                <label><input type="checkbox" name="tbfbkm_photofall_options[wr_open_default]" value="1" <?php checked(1, $opts['wr_open_default']); ?> /> Gadget is Expanded/Open by Default</label><br>
                                <label><input type="checkbox" name="tbfbkm_photofall_options[wr_auto_start]" value="1" <?php checked(1, $opts['wr_auto_start']); ?> /> Auto-Start Audio on Page Load</label>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">Audio Playlists Manager</th>
                            <td>
                                <div id="wr_playlist_manager" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                    <textarea name="tbfbkm_photofall_options[wr_playlists_json]" id="wr_playlists_json" style="display:none;"><?php echo esc_textarea($opts['wr_playlists_json']); ?></textarea>
                                    <div id="wr_playlists_container" class="sortable-list"></div>
                                    <button type="button" class="button button-primary button-hero" id="btn_add_playlist" style="margin-top:15px;">+ Create New Playlist</button>
                                </div>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">Visual Source Mode</th>
                            <td>
                                <select name="tbfbkm_photofall_options[wr_visual_mode]">
                                    <option value="random" <?php selected('random', $opts['wr_visual_mode']); ?>>Random Images from Entire Network</option>
                                    <option value="specific" <?php selected('specific', $opts['wr_visual_mode']); ?>>Specific Selected Images</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">Specific Images</th>
                            <td>
                                <textarea id="wr_specific_ids" name="tbfbkm_photofall_options[wr_specific_ids]" class="large-text code" style="display:none;" onchange="renderPreview()"><?php echo esc_textarea($opts['wr_specific_ids']); ?></textarea>
                                <div style="margin-bottom:15px;">
                                    <button type="button" class="button button-secondary" id="btn_select_images">Select Big King Media Images</button>
                                    <button type="button" class="button button-link-delete" onclick="jQuery('#wr_visual_preview').empty(); jQuery('#wr_specific_ids').val('');">Clear All Visuals</button>
                                </div>
                                <div id="wr_visual_preview" class="sortable-grid" style="display:flex; gap:10px; flex-wrap:wrap; background:#fff; padding:15px; border:1px solid #ddd; border-radius:4px; min-height:80px;"></div>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">Slideshow Duration</th>
                            <td><input type="number" name="tbfbkm_photofall_options[wr_duration]" value="<?php echo esc_attr($opts['wr_duration']); ?>" min="2" max="60" style="width: 80px;" /> seconds per image</td>
                        </tr>
                    </table>
                </div>

                <div style="margin-top: 30px;">
                    <?php submit_button('Save All Changes', 'primary large'); ?>
                </div>
            </form>

            <style>
                .tbf-tab-content { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-top: none; }
                .wr-playlist-item { background: #fff; border: 1px solid #ccd0d4; margin-bottom: 15px; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
                .wr-pl-header { background: #f0f0f1; padding: 12px 15px; display: flex; align-items: center; justify-content: space-between; cursor: move; border-bottom: 1px solid #ddd; }
                .wr-pl-header input { font-weight: 600; font-size: 14px; border: 1px solid transparent; background: transparent; box-shadow: none; width: 60%; padding: 6px; transition: all 0.2s; }
                .wr-pl-header input:focus, .wr-pl-header input:hover { border: 1px solid #2271b1; background: #fff; outline: none; }
                .wr-pl-body { padding: 15px; display: none; background: #fff; }
                .wr-pl-body.open { display: block; }
                .wr-track-list { margin: 0 0 15px 0; max-height: 300px; overflow-y: auto; border: 1px solid #e2e4e7; padding: 10px; background: #f9f9f9; border-radius: 3px; }
                .wr-track { padding: 10px; border: 1px solid #eee; font-size: 13px; display: flex; align-items: center; background: #fff; margin-bottom: 4px; cursor: move; border-radius:3px; transition: background 0.2s; }
                .wr-track:hover { background: #f0f6fc; border-color: #c3c4c7; }
                .action-icons { display: flex; align-items: center; gap: 8px; }
                .action-icons .dashicons { cursor: pointer; color: #50575e; padding: 4px; border-radius: 3px; transition: all 0.2s; }
                .action-icons .dashicons:hover { background: #e2e4e7; color: #1d2327; }
                .action-icons .dashicons-trash:hover { background: #f8dede; color: #d63638; }
                .wr-visual-item { position: relative; width: 80px; height: 80px; cursor: move; border-radius: 4px; overflow: hidden; border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
                .wr-visual-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
                .wr-visual-remove { position: absolute; top: 4px; right: 4px; background: rgba(214, 54, 56, 0.9); color: #fff; width: 20px; height: 20px; font-size: 14px; text-align: center; line-height: 18px; cursor: pointer; display: none; border-radius: 50%; font-weight: bold; }
                .wr-visual-item:hover .wr-visual-remove { display: block; }
            </style>

            <script>
            jQuery(document).ready(function($){
                // Initialize Color Pickers
                $('.tbfbkm-color-picker').wpColorPicker();

                window.switchTab = function(e, id) {
                    e.preventDefault();
                    $('.tbf-tab-content').hide();
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('#tab-' + id).show();
                    $(e.target).addClass('nav-tab-active');
                };

                var playlists = [];
                try { playlists = JSON.parse($('#wr_playlists_json').val()); } catch(e) { playlists = []; }
                if(!Array.isArray(playlists)) playlists = [];
                
                window.tbfTrackCache = window.tbfTrackCache || {};

                function renderPlaylists() {
                    var html = '';
                    var missingIds = [];

                    playlists.forEach(function(pl, idx) {
                        html += `
                        <div class="wr-playlist-item" data-idx="${idx}">
                            <div class="wr-pl-header">
                                <span class="dashicons dashicons-sort" style="color:#8c8f94; cursor:move;"></span>
                                <input type="text" value="${pl.name}" onkeyup="updateName(${idx}, this.value)" onchange="updateName(${idx}, this.value)" placeholder="Enter Playlist Name">
                                <div class="action-icons">
                                    <span style="font-size:12px; margin-right:10px; font-weight:600; background:#e0e0e0; padding:2px 8px; border-radius:10px;">${pl.tracks.length} Tracks</span>
                                    <span class="dashicons dashicons-arrow-down-alt2" onclick="toggleBody(${idx})" title="Toggle Tracks"></span>
                                    <span class="dashicons dashicons-trash" onclick="removePlaylist(${idx})" title="Delete Playlist"></span>
                                </div>
                            </div>
                            <div class="wr-pl-body" id="pl-body-${idx}">
                                <div class="wr-track-list" id="track-list-${idx}" data-pl-idx="${idx}">`;

                        if (pl.tracks && pl.tracks.length) {
                            pl.tracks.forEach(function(t) {
                                var displayTitle = window.tbfTrackCache[t] ? window.tbfTrackCache[t] : 'Loading Title... (ID: ' + t + ')';
                                if (!window.tbfTrackCache[t]) missingIds.push(t);

                                html += `<div class="wr-track" data-id="${t}">
                                            <span class="dashicons dashicons-menu" style="font-size:16px; color:#a7aaad; margin-right:10px; cursor:move;"></span>
                                            <span class="track-title" data-id="${t}" style="flex-grow:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500;">${displayTitle}</span>
                                            <span class="dashicons dashicons-no-alt" onclick="removeTrack(${idx}, '${t}')" style="cursor:pointer; font-size:16px; color:#d63638;" title="Remove Audio"></span>
                                         </div>`;
                            });
                        } else {
                            html += '<div style="padding:20px; color:#646970; text-align:center; font-style:italic;">This playlist is empty. Click "Select Big King Audio" below to add songs.</div>';
                        }

                        html += `
                                </div>
                                <div style="display:flex; justify-content:space-between; margin-top:15px;">
                                    <button type="button" class="button button-secondary" onclick="addTracks(${idx})">Select Big King Audio</button>
                                    <button type="button" class="button button-link-delete" onclick="clearTracks(${idx})">Empty Playlist</button>
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    $('#wr_playlists_container').html(html);
                    $('#wr_playlists_json').val(JSON.stringify(playlists));
                    
                    $('#wr_playlists_container').sortable({
                        handle: '.wr-pl-header',
                        update: function() {
                            var newOrder = [];
                            $('.wr-playlist-item').each(function(){ newOrder.push(playlists[$(this).data('idx')]); });
                            playlists = newOrder;
                            renderPlaylists();
                        }
                    });

                    $('.wr-track-list').sortable({
                        update: function() {
                            var plIdx = $(this).data('pl-idx');
                            var newTracks = [];
                            $(this).find('.wr-track').each(function(){ newTracks.push($(this).data('id')); });
                            playlists[plIdx].tracks = newTracks;
                            $('#wr_playlists_json').val(JSON.stringify(playlists));
                        }
                    });

                    if (missingIds.length > 0) {
                        var uniqueMissing = [...new Set(missingIds)];
                        $.post(ajaxurl, { action: 'tbfbkm_resolve_playlist', ids: uniqueMissing.join(',') }, function(res) {
                            if (res.success && res.data) {
                                res.data.forEach(function(track) {
                                    window.tbfTrackCache[track.id] = track.title;
                                    $('.track-title[data-id="'+track.id+'"]').text(track.title).attr('title', track.title);
                                });
                            }
                        });
                    }
                }

                window.updateName = function(idx, val) { playlists[idx].name = val; $('#wr_playlists_json').val(JSON.stringify(playlists)); };
                window.toggleBody = function(idx) { $('#pl-body-'+idx).toggleClass('open'); };
                window.clearTracks = function(idx) { if(confirm('Remove all tracks?')) { playlists[idx].tracks = []; renderPlaylists(); setTimeout(function(){$('#pl-body-'+idx).addClass('open');}, 10); } };
                window.removePlaylist = function(idx) { if(confirm('Delete entire playlist?')) { playlists.splice(idx, 1); renderPlaylists(); } };
                window.removeTrack = function(plIdx, trackId) { playlists[plIdx].tracks = playlists[plIdx].tracks.filter(id => id != trackId); renderPlaylists(); setTimeout(function(){$('#pl-body-'+plIdx).addClass('open');}, 10); };

                window.addTracks = function(idx) {
                    var frame = wp.media({ title: 'Select Big King Media Audio', multiple: 'add', library: { type: 'audio' }, button: { text: 'Add to Playlist' } });
                    frame.on('select', function() {
                        frame.state().get('selection').map(att => { 
                            var data = att.toJSON();
                            window.tbfTrackCache[data.id] = data.title || data.filename || 'Track ' + data.id;
                            playlists[idx].tracks.push(data.id); 
                        });
                        renderPlaylists();
                        setTimeout(function(){$('#pl-body-'+idx).addClass('open');}, 10);
                    });
                    frame.open();
                };

                $('#btn_add_playlist').click(function(e) { 
                    e.preventDefault();
                    playlists.push({ name: "New Custom Playlist", tracks: [] }); 
                    renderPlaylists(); 
                    setTimeout(function(){$('#pl-body-'+(playlists.length-1)).addClass('open');}, 10);
                });

                $('#btn_select_images').click(function(e) {
                    e.preventDefault();
                    var frame = wp.media({ title: 'Select Big King Media Visuals', multiple: 'add', library: { type: 'image' }, button: { text: 'Use Selected Images' } });
                    frame.on('select', function() {
                        var ids = [];
                        frame.state().get('selection').map(att => ids.push(att.id));
                        var curr = $('#wr_specific_ids').val();
                        if(curr) ids = [curr, ids.join(',')];
                        ids = ids.filter(Boolean).join(',').split(',').filter(Boolean); 
                        $('#wr_specific_ids').val(ids.join(','));
                        renderPreview();
                    });
                    frame.open();
                });

                window.renderPreview = function() {
                    var ids = $('#wr_specific_ids').val();
                    if(!ids) { $('#wr_visual_preview').html('<div style="color:#999; width:100%; text-align:center;">No specific images selected.</div>'); return; }
                    $('#wr_visual_preview').html('<div style="color:#999;">Loading previews...</div>');
                    $.post(ajaxurl, { action: 'tbfbkm_resolve_ids', ids: ids }, function(res) {
                        if(res.success) {
                            var html = '';
                            res.data.forEach(function(url) {
                                html += `<div class="wr-visual-item"><img src="${url}"><div class="wr-visual-remove" onclick="removeVisual(this)">&times;</div></div>`;
                            });
                            $('#wr_visual_preview').html(html);
                        }
                    });
                };
                
                window.removeVisual = function(el) { $(el).parent().remove(); };

                renderPlaylists();
                if($('#wr_specific_ids').val()) { renderPreview(); } 
                else { $('#wr_visual_preview').html('<div style="color:#999; width:100%; text-align:center;">No specific images selected.</div>'); }
                
                $('#tbfbkm_settings_form').submit(function() { $('#wr_playlists_json').val(JSON.stringify(playlists)); });
                
                var scopeSel = document.getElementById('tbfbkm_scope_mode');
                var scopeRow = document.getElementById('tbfbkm_scope_sites_row');
                if(scopeSel && scopeRow) {
                    scopeSel.addEventListener('change', function() { scopeRow.style.display = (this.value === 'specific') ? 'table-row' : 'none'; });
                    scopeRow.style.display = (scopeSel.value === 'specific') ? 'table-row' : 'none';
                }
            });
            </script>
        </div>
        <?php
    }
}