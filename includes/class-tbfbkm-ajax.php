<?php
/**
 * File: includes/class-tbfbkm-ajax.php
 * Version: 7.0.4.8 (Fully Expanded Structure & Frontend Nonce Removal)
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

class TBFBKM_AJAX {

    public static function init() {
        add_action( 'wp_ajax_tbfbkm_list', [__CLASS__, 'list_items'] );
        add_action( 'wp_ajax_nopriv_tbfbkm_list', [__CLASS__, 'list_items'] ); 
        
        add_action( 'wp_ajax_tbfbkm_load_more', [__CLASS__, 'load_more'] );
        add_action( 'wp_ajax_nopriv_tbfbkm_load_more', [__CLASS__, 'load_more'] ); 
        
        // Frontend Player Endpoints (No Privileges Required)
        add_action( 'wp_ajax_tbfbkm_resolve_playlist', [__CLASS__, 'resolve_playlist'] );
        add_action( 'wp_ajax_nopriv_tbfbkm_resolve_playlist', [__CLASS__, 'resolve_playlist'] );

        add_action( 'wp_ajax_tbfbkm_resolve_ids', [__CLASS__, 'resolve_ids'] );
        add_action( 'wp_ajax_nopriv_tbfbkm_resolve_ids', [__CLASS__, 'resolve_ids'] );

        add_action( 'wp_ajax_tbfbkm_get_all_audio_ids', [__CLASS__, 'get_all_audio_ids'] );
        add_action( 'wp_ajax_nopriv_tbfbkm_get_all_audio_ids', [__CLASS__, 'get_all_audio_ids'] );

        // Backend Admin Endpoints
        add_action( 'wp_ajax_tbfbkm_sites', [__CLASS__, 'sites'] );
        add_action( 'wp_ajax_tbfbkm_proxy', [__CLASS__, 'proxy'] );
        add_action( 'wp_ajax_tbfbkm_proxy_url', [__CLASS__, 'proxy_url'] );
        add_action( 'wp_ajax_tbfbkm_set_featured_remote', [__CLASS__, 'set_featured_remote'] );
        add_action( 'wp_ajax_tbfbkm_set_audio_thumb', [__CLASS__, 'set_audio_thumb'] );
        add_action( 'wp_ajax_tbfbkm_frontend_upload', [__CLASS__, 'frontend_upload'] );
        add_action( 'wp_ajax_tbfbkm_hide_media', [__CLASS__, 'hide_media'] );
        add_action( 'wp_ajax_tbfbkm_delete_media', [__CLASS__, 'delete_media'] );
        add_action( 'wp_ajax_tbfbkm_wipe_index', [__CLASS__, 'wipe_index'] );
    }

    private static function send_json( $data, $success = true, $status_code = null ) {
        if ( ob_get_length() ) {
            ob_clean();
        }
        
        if ( $success ) {
            wp_send_json_success( $data, $status_code );
        } else {
            wp_send_json_error( $data, $status_code );
        }
    }

    private static function verify_nonce() {
        $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
        
        if ( empty( $nonce ) && isset( $_REQUEST['_ajax_nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) );
        }

        if ( ! wp_verify_nonce( $nonce, 'tbfbkm_ajax_nonce' ) ) {
            self::send_json( ['message' => 'Security token invalid or expired.'], false, 403 );
        }
    }

    public static function get_all_audio_ids() {
        // FIX: Removed verify_nonce() so public frontend visitors can load the player data
        nocache_headers();
        
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT blog_id, attachment_id FROM {$wpdb->base_prefix}tbfbkm_index WHERE media_type = 'audio' GROUP BY title ORDER BY created_gmt DESC", ARRAY_A );
        
        $formatted = [];
        foreach ( $rows as $r ) {
            $formatted[] = $r['blog_id'] . '-' . $r['attachment_id'];
        }
        
        if ( empty( $formatted ) ) {
            self::send_json( ['message' => 'No audio found in network index.'], false );
        }
        
        self::send_json( array_values( array_unique( $formatted ) ) );
    }

    public static function resolve_playlist() {
        // FIX: Removed verify_nonce() so public frontend visitors can load the player data
        nocache_headers();
        
        $ids_str = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
        
        if ( empty( $ids_str ) ) {
            self::send_json( [] );
        }
        
        $raw_ids = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $ids_str ) ) ) ) );
        $tracks = [];
        $raw_ids = array_slice( $raw_ids, 0, 500 ); 
        
        $master_id = (int) get_site_option( 'tbfbkm_master_controller_id', 0 );
        if ( $master_id <= 0 ) {
            $master_id = get_main_site_id();
        }
        $current_id = get_current_blog_id();

        global $wpdb;
        $processed_urls = [];

        foreach ( $raw_ids as $raw_id ) {
            $blog_id = 0;
            $att_id = 0;

            if ( strpos( $raw_id, '-' ) !== false ) {
                $parts = explode( '-', $raw_id );
                $blog_id = (int)$parts[0];
                $att_id = (int)$parts[1];
            } else {
                $att_id = (int)$raw_id;
            }

            if ( ! $att_id ) {
                continue;
            }
            
            $url = '';
            $title = '';

            if ( $blog_id > 0 ) {
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT url_full, title FROM {$wpdb->base_prefix}tbfbkm_index WHERE blog_id = %d AND attachment_id = %d LIMIT 1", $blog_id, $att_id ) );
            } else {
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT url_full, title FROM {$wpdb->base_prefix}tbfbkm_index WHERE attachment_id = %d LIMIT 1", $att_id ) );
            }

            if ( $row ) {
                $url = $row->url_full;
                $title = $row->title;
            }

            if ( empty( $url ) ) {
                $local_url = wp_get_attachment_url( $att_id );
                
                if ( $local_url ) {
                    $url = $local_url;
                    $title = get_the_title( $att_id );
                } elseif ( $master_id && $master_id !== $current_id ) {
                    switch_to_blog( $master_id );
                    $remote_url = wp_get_attachment_url( $att_id );
                    
                    if ( $remote_url ) {
                        $url = $remote_url;
                        $title = get_the_title( $att_id );
                    }
                    restore_current_blog();
                }
            }

            if ( $url ) {
                if ( in_array( $url, $processed_urls ) ) {
                    continue;
                }
                $processed_urls[] = $url;

                $clean_title = sanitize_text_field( trim( $title ) );
                
                // Aggressive Title Extraction: Hits ID3 Metadata first, URL second.
                if ( empty( $clean_title ) || is_numeric( $clean_title ) || preg_match( '/^Track \d+$/i', $clean_title ) ) {
                    $meta = wp_get_attachment_metadata( $att_id );
                    
                    if ( ! empty( $meta['title'] ) ) {
                        $clean_title = sanitize_text_field( $meta['title'] );
                    } else {
                        $path = parse_url( $url, PHP_URL_PATH );
                        if ( $path ) {
                            $filename = pathinfo( $path, PATHINFO_FILENAME );
                            $filename = preg_replace( '/-\d+$/', '', $filename ); // Strip WordPress proxy numbers
                            $clean_title = sanitize_text_field( ucwords( str_replace( ['-', '_'], ' ', $filename ) ) );
                        }
                    }
                }

                $tracks[] = [ 
                    'id'    => esc_attr( $raw_id ), 
                    'url'   => esc_url_raw( $url ), 
                    'title' => $clean_title ?: 'Track ' . $att_id 
                ];
            }
        }
        
        self::send_json( $tracks );
    }

    public static function load_more() {
        self::verify_nonce();

        $page = isset( $_POST['page'] ) ? max( 1, (int) wp_unslash( $_POST['page'] ) ) : 1;
        $opts = get_option( 'tbfbkm_photofall_options', [] );
        $per  = isset( $opts['per_page'] ) ? max( 1, (int)$opts['per_page'] ) : 20;
        
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $mime   = isset( $_POST['filter'] ) ? sanitize_text_field( wp_unslash( $_POST['filter'] ) ) : '';
        
        if ( $mime === 'all' ) {
            $mime = ''; 
        }
        
        $sort = isset( $_POST['sort'] ) ? sanitize_text_field( wp_unslash( $_POST['sort'] ) ) : '';
        $orderby = 'date_desc';
        
        if ( $sort === 'oldest' ) {
            $orderby = 'date_asc';
        }
        if ( $sort === 'random' ) {
            $orderby = 'rand';
        }

        $originBlogId = isset( $_POST['site_filter'] ) ? (int) wp_unslash( $_POST['site_filter'] ) : 0;
        $year = isset( $_POST['year'] ) ? (int) wp_unslash( $_POST['year'] ) : 0;

        $data = self::list_from_index_table( $page, $per, $search, $mime, $originBlogId, $orderby, '', $year );
        
        if ( ! $data || empty( $data['items'] ) ) {
            self::send_json( ['html' => '', 'max_pages' => 0] );
        }

        if ( ! class_exists( 'TBFBKM_Photofall_Templates' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'photofall/class-tbfbkm-photofall-templates.php';
        }

        ob_start();
        foreach ( $data['items'] as $item ) {
            $post = new stdClass();
            $post->ID = $item['attachment_id'];
            $post->attachment_id = $item['attachment_id'];
            $post->blog_id = $item['blog_id'];
            $post->post_title = $item['title'];
            $post->title = $item['title']; 
            $post->post_excerpt = $item['caption']; 
            $post->caption = $item['caption']; 
            $post->type = $item['media_type'];
            $post->media_type = $item['media_type'];
            $post->tbf_url_full = $item['url'];
            $post->tbf_url_thumb = $item['thumb'];

            echo wp_kses( TBFBKM_Photofall_Templates::get_item_html( $post ), TBFBKM_Photofall_Templates::get_allowed_html() );
        }
        $html = ob_get_clean();

        self::send_json( [
            'html' => $html,
            'max_pages' => $data['max_pages']
        ]);
    }

    public static function list_items() {
        self::verify_nonce();

        $page = isset( $_GET['page'] ) ? max( 1, (int) wp_unslash( $_GET['page'] ) ) : 1;
        $per  = isset( $_GET['per_page'] ) ? max( 1, min( 200, (int) wp_unslash( $_GET['per_page'] ) ) ) : 60;
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $mime   = isset( $_GET['mime'] ) ? sanitize_text_field( wp_unslash( $_GET['mime'] ) ) : '';
        $originBlogId = isset( $_GET['origin_blog_id'] ) ? (int) wp_unslash( $_GET['origin_blog_id'] ) : 0;
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
        $include = isset( $_GET['include'] ) ? sanitize_text_field( wp_unslash( $_GET['include'] ) ) : '';

        $fast = self::list_from_index_table( $page, $per, $search, $mime, $originBlogId, $orderby, $include );
        
        if ( $fast !== null ) {
            self::send_json( $fast );
        }
        
        self::send_json( ['items' => [], 'total' => 0, 'max_pages' => 1] );
    }

    private static function list_from_index_table( $page, $per, $search, $mime_filter, $originBlogId, $orderby, $include = '', $year = 0 ) {
        global $wpdb;

        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->base_prefix}tbfbkm_index'" ) ) {
            return null;
        }

        $where = "title != 'TBF Big King Placeholder' AND (url_full NOT LIKE '%/vikinger/%')";
        $params = [];

        if ( ! empty( $include ) ) {
            $raw_ids = explode( ',', $include );
            $ids = array_filter( array_map( 'intval', $raw_ids ) );
            
            if ( ! empty( $ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $where .= " AND attachment_id IN ($placeholders)";
                $params = array_merge( $params, $ids );
            } else {
                return ['items' => [], 'total' => 0];
            }
        } 
        else {
            if ( $originBlogId > 0 ) {
                $where .= " AND blog_id = %d";
                $params[] = $originBlogId;
            }

            if ( $year > 0 ) {
                $where .= " AND year = %d";
                $params[] = $year;
            }

            if ( in_array( $mime_filter, ['image', 'video', 'audio'], true ) ) {
                $where .= " AND media_type = %s";
                $params[] = $mime_filter;
            } elseif ( ! empty( $mime_filter ) && strpos( $mime_filter, '/' ) !== false ) {
                $where .= " AND mime = %s";
                $params[] = $mime_filter;
            }

            if ( $search !== '' ) {
                $like = '%' . $wpdb->esc_like( $search ) . '%';
                $where .= " AND (title LIKE %s OR alt LIKE %s OR caption LIKE %s)";
                $params[] = $like; 
                $params[] = $like; 
                $params[] = $like;
            }
        }

        $offset = ( $page - 1 ) * $per;

        $order_sql = "ORDER BY agg_created_gmt DESC";
        if ( $orderby === 'date_asc' ) {
            $order_sql = "ORDER BY agg_created_gmt ASC";
        }
        if ( $orderby === 'rand' ) {
            $order_sql = "ORDER BY RAND()";
        }

        $totalSql = "SELECT COUNT(DISTINCT title, media_type, width, height) FROM {$wpdb->base_prefix}tbfbkm_index WHERE {$where}";
        $total = (int)$wpdb->get_var( $wpdb->prepare( $totalSql, $params ) );
        
        $sql = "SELECT 
                    MIN(blog_id) as blog_id, 
                    MAX(attachment_id) as attachment_id, 
                    title, 
                    MAX(caption) as caption, 
                    MAX(mime) as mime, 
                    media_type, 
                    MAX(url_full) as url_full, 
                    MAX(url_medium) as url_medium, 
                    MAX(url_thumb) as url_thumb, 
                    MAX(poster_url) as poster_url, 
                    MAX(created_gmt) as agg_created_gmt, 
                    width, 
                    height 
                FROM {$wpdb->base_prefix}tbfbkm_index 
                WHERE {$where} 
                GROUP BY title, media_type, width, height
                {$order_sql} 
                LIMIT %d OFFSET %d";
        
        $final_params = array_merge( $params, [$per, $offset] );
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $final_params ), ARRAY_A );

        $items = [];
        
        foreach ( (array)$rows as $r ) {
            $url_full = $r['url_full'] ?? '';
            $mime     = $r['mime'] ?? '';
            $thumb    = $r['url_thumb'] ?: ( $r['poster_url'] ?: ( $r['url_medium'] ?: $url_full ) );
            
            $is_audio = strpos( $mime, 'audio/' ) === 0 || preg_match( '/\.(mp3|wav|ogg|flac|m4a|aac)(\?.*)?$/i', $url_full );
            $is_video = ! $is_audio && ( strpos( $mime, 'video/' ) === 0 || preg_match( '/\.(mp4|webm|mov|avi)(\?.*)?$/i', $url_full ) );

            if ( $is_audio ) {
                $media_type = 'audio';
                if ( empty( $r['poster_url'] ) || preg_match( '/\.(mp3|wav|ogg|flac|m4a|aac)(\?.*)?$/i', $thumb ) ) {
                    $thumb = includes_url( 'images/media/audio.png' );
                }
            } elseif ( $is_video ) {
                $media_type = 'video';
                if ( empty( $r['poster_url'] ) && preg_match( '/\.(mp4|webm|mov|avi)(\?.*)?$/i', $thumb ) ) {
                    $thumb = includes_url( 'images/media/video.png' );
                }
            } elseif ( strpos( $mime, 'application/zip' ) !== false || strpos( $mime, 'x-gzip' ) !== false || strpos( $mime, 'x-rar' ) !== false ) {
                $media_type = 'application';
                $thumb = includes_url( 'images/media/archive.png' );
            } elseif ( strpos( $mime, 'application/pdf' ) !== false || strpos( $mime, 'application/msword' ) !== false || strpos( $mime, 'application/vnd.' ) !== false || strpos( $mime, 'text/' ) === 0 ) {
                $media_type = 'application';
                $thumb = includes_url( 'images/media/document.png' );
            } else {
                $media_type = 'image';
                if ( strpos( $mime, 'image/' ) !== 0 && ! preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i', $url_full ) ) {
                    $thumb = includes_url( 'images/media/default.png' );
                }
            }

            $clean_title = sanitize_text_field( trim( (string)( $r['title'] ?? '' ) ) );
            
            if ( empty( $clean_title ) || is_numeric( $clean_title ) || preg_match( '/^Track \d+$/i', $clean_title ) ) {
                $path = parse_url( $url_full, PHP_URL_PATH );
                
                if ( $path ) {
                    $filename = pathinfo( $path, PATHINFO_FILENAME );
                    $filename = preg_replace( '/-\d+$/', '', $filename );
                    $clean_title = sanitize_text_field( ucwords( str_replace( ['-', '_'], ' ', $filename ) ) );
                }
            }
            
            $final_title = $clean_title ?: 'Track ' . $r['attachment_id'];

            $items[] = [
                'blog_id'       => (int)$r['blog_id'], 
                'attachment_id' => (int)$r['attachment_id'], 
                'title'         => $final_title, 
                'caption'       => sanitize_text_field( (string)( $r['caption'] ?? '' ) ), 
                'url'           => esc_url_raw( (string)$url_full ), 
                'thumb'         => esc_url_raw( (string)$thumb ), 
                'mime'          => sanitize_text_field( (string)$mime ), 
                'media_type'    => sanitize_text_field( (string)$media_type ), 
                'width'         => (int)( $r['width'] ?? 800 ), 
                'height'        => (int)( $r['height'] ?? 800 ),
            ];
        }

        return ['items' => $items, 'total' => $total, 'max_pages' => $per > 0 ? (int)ceil( $total / $per ) : 1, 'source' => 'index_table'];
    }

    public static function frontend_upload() {
        self::verify_nonce();
        @set_time_limit( 0 );

        $opts = get_option( 'tbfbkm_photofall_options', [] );
        $is_authorized = false;
        
        if ( current_user_can( 'manage_options' ) || is_super_admin() ) {
            $is_authorized = true;
        } elseif ( ! empty( $opts['enable_frontend_upload'] ) ) {
            $user = wp_get_current_user();
            $allowed = !empty( $opts['upload_roles'] ) ? $opts['upload_roles'] : ['administrator'];
            
            if ( ! empty( array_intersect( $allowed, $user->roles ) ) ) {
                $is_authorized = true;
            }
        }

        if ( ! $is_authorized ) {
            self::send_json( ['message' => 'Not authorized'], false, 403 );
        }
        if ( empty( $_FILES['tbfbkm_media'] ) ) {
            self::send_json( ['message' => 'No files provided'], false, 400 );
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $title = isset( $_POST['tbfbkm_title'] ) ? sanitize_text_field( wp_unslash( $_POST['tbfbkm_title'] ) ) : '';
        $desc  = isset( $_POST['tbfbkm_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tbfbkm_description'] ) ) : '';
        $files = $_FILES['tbfbkm_media'];
        $uploaded_ids = [];

        if ( is_array( $files['name'] ) ) {
            foreach ( $files['name'] as $key => $value ) {
                if ( $files['name'][$key] ) {
                    $_FILES['tbf_single_upload'] = [
                        'name'     => sanitize_file_name( $files['name'][$key] ), 
                        'type'     => sanitize_mime_type( $files['type'][$key] ),
                        'tmp_name' => $files['tmp_name'][$key], 
                        'error'    => $files['error'][$key],
                        'size'     => $files['size'][$key]
                    ];
                    
                    $this_title = $title ?: pathinfo( $files['name'][$key], PATHINFO_FILENAME );
                    $attachment_id = media_handle_upload( 'tbf_single_upload', 0, [
                        'post_title'   => sanitize_text_field( $this_title ), 
                        'post_content' => sanitize_textarea_field( $desc ), 
                        'post_excerpt' => sanitize_textarea_field( $desc )
                    ]);

                    if ( ! is_wp_error( $attachment_id ) ) {
                        $uploaded_ids[] = $attachment_id;
                        if ( class_exists( 'TBFBKM_Indexer' ) ) {
                            TBFBKM_Indexer::index_single_attachment( $attachment_id );
                        }
                    }
                }
            }
        }

        if ( empty( $uploaded_ids ) ) {
            self::send_json( ['message' => 'Upload failed.'], false );
        }
        
        self::send_json( ['message' => 'Upload successful', 'ids' => $uploaded_ids] );
    }

    public static function hide_media() {
        self::verify_nonce();
        
        if ( ! current_user_can( 'manage_options' ) ) {
            self::send_json( ['message' => 'Forbidden'], false, 403 );
        }
        
        $att_id = isset( $_POST['attachment_id'] ) ? (int) wp_unslash( $_POST['attachment_id'] ) : 0;
        $hidden = get_option( 'tbfbkm_hidden_media', [] );
        
        if ( in_array( $att_id, $hidden ) ) {
            $hidden = array_diff( $hidden, [$att_id] );
        } else {
            $hidden[] = $att_id;
        }
        
        update_option( 'tbfbkm_hidden_media', $hidden );
        self::send_json( [] );
    }

    public static function delete_media() {
        self::verify_nonce();
        
        if ( ! current_user_can( 'manage_options' ) ) {
            self::send_json( ['message' => 'Forbidden'], false, 403 );
        }
        
        $att_id = isset( $_POST['attachment_id'] ) ? (int) wp_unslash( $_POST['attachment_id'] ) : 0;
        
        if ( wp_delete_attachment( $att_id, true ) ) {
            self::send_json( [] );
        } else {
            self::send_json( ['message' => 'Delete failed.'], false );
        }
    }

    public static function wipe_index() {
        self::verify_nonce();
        
        if ( ! current_user_can( 'manage_options' ) ) {
            self::send_json( [], false );
        }
        
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}tbfbkm_index" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}tbfbkm_usage_map" );
        delete_option( 'tbfbkm_db_version' );
        
        self::send_json( ['message' => 'Index wiped.'] );
    }

    public static function resolve_ids() {
        // FIX: Removed verify_nonce() so public frontend visitors can load the player data
        nocache_headers();
        
        $raw_ids = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
        $ids = array_filter( array_map( 'intval', explode( ',', $raw_ids ) ) );
        $urls = [];
        global $wpdb;
        
        foreach ( $ids as $id ) {
            if ( ! $id ) {
                continue;
            }
            
            $u = wp_get_attachment_url( $id );
            
            if ( ! $u ) {
                $u = $wpdb->get_var( $wpdb->prepare( "SELECT url_full FROM {$wpdb->base_prefix}tbfbkm_index WHERE attachment_id = %d LIMIT 1", $id ) );
            }
            
            if ( $u ) {
                $urls[] = esc_url_raw( $u ); 
            }
        }
        
        self::send_json( $urls );
    }

    public static function sites() {
        self::verify_nonce();
        
        if ( ! current_user_can( 'upload_files' ) ) {
            self::send_json( ['message' => 'Permission denied'], false, 403 );
        }
        
        if ( ! is_multisite() ) {
            self::send_json( ['sites' => [['blog_id' => 1, 'name' => sanitize_text_field( get_bloginfo( 'name' ) )]]] );
        }
        
        $out = [];
        $sites = get_sites( ['number' => 1000, 'public' => 1] ); 
        
        foreach ( $sites as $s ) {
            $bid = (int)$s->blog_id;
            $name = get_blog_option( $bid, 'blogname' );
            $out[] = ['blog_id' => $bid, 'name' => sanitize_text_field( $name ? $name : ( 'Site ' . $bid ) )];
        }
        
        self::send_json( ['sites' => $out] );
    }

    public static function set_audio_thumb() {
        self::verify_nonce();
        
        if ( ! current_user_can( 'upload_files' ) ) {
            self::send_json( ['message' => 'Permission denied'], false, 403 );
        }
        
        $audio_blog_id = isset( $_POST['audio_blog_id'] ) ? (int) wp_unslash( $_POST['audio_blog_id'] ) : 0;
        $audio_id      = isset( $_POST['audio_id'] ) ? (int) wp_unslash( $_POST['audio_id'] ) : 0;
        $thumb_url     = isset( $_POST['thumb_url'] ) ? esc_url_raw( wp_unslash( $_POST['thumb_url'] ) ) : '';

        if ( ! $audio_id || ! $thumb_url ) {
            self::send_json( ['message' => 'Missing data'], false, 400 );
        }

        if ( is_multisite() ) {
            switch_to_blog( $audio_blog_id );
        }
        
        update_post_meta( $audio_id, '_tbfbkm_custom_thumb_url', $thumb_url );
        
        if ( is_multisite() ) {
            restore_current_blog();
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->base_prefix . 'tbfbkm_index',
            ['poster_url' => $thumb_url, 'url_thumb' => $thumb_url],
            ['blog_id' => $audio_blog_id, 'attachment_id' => $audio_id],
            ['%s', '%s'],
            ['%d', '%d']
        );

        self::send_json( ['message' => 'Thumbnail updated', 'thumb_url' => $thumb_url] );
    }

    public static function proxy() {
        self::verify_nonce();
        
        if ( ! current_user_can( 'upload_files' ) ) {
            self::send_json( ['message' => 'Permission denied'], false, 403 );
        }
        
        $originBlogId = isset( $_POST['origin_blog_id'] ) ? (int) wp_unslash( $_POST['origin_blog_id'] ) : 0;
        $originAttId  = isset( $_POST['origin_attachment_id'] ) ? (int) wp_unslash( $_POST['origin_attachment_id'] ) : 0;
        $url          = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        $title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Media';
        $mime         = isset( $_POST['mime'] ) ? sanitize_text_field( wp_unslash( $_POST['mime'] ) ) : '';

        if ( ! $url ) {
            self::send_json( ['message' => 'Missing remote URL payload.'], false, 400 );
        }
        
        if ( empty( $mime ) ) {
            $mime = 'image/jpeg';
        }

        if ( class_exists( 'TBFBKM_Network_Media_Index' ) ) {
            remove_action( 'add_attachment', ['TBFBKM_Network_Media_Index', 'auto_index_attachment'] );
        }

        $localId = TBFBKM_Proxy::create_proxy_attachment([
            'origin_blog_id'       => $originBlogId, 
            'origin_attachment_id' => $originAttId, 
            'url'                  => $url,
            'title'                => $title ?: 'Media', 
            'mime'                 => $mime, 
            'source'               => 'network',
        ]);

        if ( class_exists( 'TBFBKM_Network_Media_Index' ) ) {
            add_action( 'add_attachment', ['TBFBKM_Network_Media_Index', 'auto_index_attachment'] );
        }

        if ( is_wp_error( $localId ) ) {
            self::send_json( ['message' => esc_html( $localId->get_error_message() )], false, 500 );
        }
        
        self::send_json( ['local_attachment_id' => (int)$localId, 'url' => esc_url_raw( $url ), 'mime' => sanitize_text_field( $mime )] );
    }

    public static function proxy_url() {
        self::verify_nonce();
        
        if ( ! current_user_can( 'upload_files' ) ) {
            self::send_json( ['message' => 'Permission denied'], false, 403 );
        }
        
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        
        if ( ! $url ) {
            self::send_json( ['message' => 'Missing URL'], false, 400 );
        }
        
        $title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Media';
        $mime   = isset( $_POST['mime'] ) ? sanitize_text_field( wp_unslash( $_POST['mime'] ) ) : 'image/jpeg';
        $source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'external';

        if ( class_exists( 'TBFBKM_Network_Media_Index' ) ) {
            remove_action( 'add_attachment', ['TBFBKM_Network_Media_Index', 'auto_index_attachment'] );
        }
        
        $localId = TBFBKM_Proxy::create_proxy_attachment([
            'origin_blog_id'       => 0, 
            'origin_attachment_id' => 0, 
            'url'                  => $url, 
            'title'                => $title, 
            'mime'                 => $mime, 
            'source'               => $source
        ]);
        
        if ( class_exists( 'TBFBKM_Network_Media_Index' ) ) {
            add_action( 'add_attachment', ['TBFBKM_Network_Media_Index', 'auto_index_attachment'] );
        }

        if ( is_wp_error( $localId ) ) {
            self::send_json( ['message' => esc_html( $localId->get_error_message() )], false, 500 );
        }
        
        self::send_json( ['local_attachment_id' => (int)$localId, 'url' => esc_url_raw( $url ), 'mime' => sanitize_text_field( $mime )] );
    }

    public static function set_featured_remote() {
        self::verify_nonce();
        
        if ( ! current_user_can( 'upload_files' ) ) {
            self::send_json( ['message' => 'Permission denied'], false, 403 );
        }
        
        $postId = isset( $_POST['post_id'] ) ? (int) wp_unslash( $_POST['post_id'] ) : 0;
        
        if ( $postId <= 0 || ! current_user_can( 'edit_post', $postId ) ) {
            self::send_json( ['message' => 'Cannot edit post'], false, 403 );
        }

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        
        if ( ! $url ) {
            self::send_json( ['message' => 'Missing url'], false, 400 );
        }
        
        $mime = isset( $_POST['mime'] ) ? sanitize_text_field( wp_unslash( $_POST['mime'] ) ) : 'image/jpeg';
        $type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'image';

        update_post_meta( $postId, '_tbfbkm_featured_url', $url );
        update_post_meta( $postId, '_tbfbkm_featured_mime', $mime );
        update_post_meta( $postId, '_tbfbkm_featured_type', $type );

        $pid = (int) TBFBKM_Placeholder::get_id();
        
        if ( $pid > 0 ) {
            update_post_meta( $postId, '_thumbnail_id', $pid );
        }

        clean_post_cache( $postId );
        self::send_json( ['post_id' => $postId, 'placeholder_id' => $pid, 'url' => esc_url_raw( $url )] ); 
    }
}