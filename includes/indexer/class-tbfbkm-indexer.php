<?php
/**
 * File: includes/indexer/class-tbfbkm-indexer.php
 * Version: 7.0.2.9 (Raw MySQL Force Creation & Guaranteed Table Healing)
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

class TBFBKM_Indexer {

    public static function init() {
        add_action( 'admin_init', [__CLASS__, 'auto_heal_tables'] );
        add_action( 'wp_ajax_tbfbkm_process_batch', [__CLASS__, 'process_batch'] );
        add_action( 'add_attachment', [__CLASS__, 'auto_index_hook'] );
    }

    public static function auto_heal_tables() {
        global $wpdb;
        $table_name = $wpdb->base_prefix . 'tbfbkm_index';
        
        // Physically check if the table exists in the database
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
        
        if ( ! $table_exists ) {
            self::create_tables();
        }
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->base_prefix . 'tbfbkm_index';
        $usage_table = $wpdb->base_prefix . 'tbfbkm_usage_map';

        // BYPASS dbDelta: Using raw MySQL queries to forcefully generate the tables.
        // Changed varchar(2000) to TEXT to prevent InnoDB row-size limits from silently killing the build.
        $sql1 = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `blog_id` bigint(20) NOT NULL,
            `attachment_id` bigint(20) NOT NULL,
            `url_full` text NOT NULL,
            `url_medium` text NOT NULL,
            `url_thumb` text NOT NULL,
            `poster_url` text NOT NULL,
            `title` text NOT NULL,
            `caption` text NOT NULL,
            `alt` text NOT NULL,
            `mime` varchar(100) NOT NULL,
            `media_type` varchar(20) NOT NULL,
            `width` int(11) NOT NULL DEFAULT 0,
            `height` int(11) NOT NULL DEFAULT 0,
            `year` int(4) NOT NULL DEFAULT 0,
            `month` int(2) NOT NULL DEFAULT 0,
            `created_gmt` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
            PRIMARY KEY (`id`),
            UNIQUE KEY `blog_att_unique` (`blog_id`, `attachment_id`),
            KEY `media_type` (`media_type`),
            KEY `year_month` (`year`, `month`)
        ) {$charset_collate};";
        
        $wpdb->query( $sql1 );

        $sql2 = "CREATE TABLE IF NOT EXISTS `{$usage_table}` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `media_url` text NOT NULL,
            `post_id` bigint(20) NOT NULL,
            `blog_id` bigint(20) NOT NULL,
            `site_name` varchar(255) NOT NULL,
            `post_title` text NOT NULL,
            `permalink` text NOT NULL,
            PRIMARY KEY (`id`)
        ) {$charset_collate};";
        
        $wpdb->query( $sql2 );

        // HARD VERIFICATION: Did MySQL listen to us?
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        if ( $exists !== $table_name ) {
            error_log( 'TBFBKM CRITICAL ERROR: MySQL violently rejected table creation. Error: ' . $wpdb->last_error );
            return false;
        }

        return true;
    }

    public static function process_batch() {
        check_ajax_referer( 'tbfbkm_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( ['message' => 'Unauthorized'] );
        }

        // FORCE DATABASE CREATION CHECK RIGHT BEFORE SCANNING
        self::auto_heal_tables();

        $step       = isset( $_POST['step'] ) ? max( 1, (int)$_POST['step'] ) : 1;
        $offset     = isset( $_POST['offset'] ) ? max( 0, (int)$_POST['offset'] ) : 0;
        $batch_size = 50;

        if ( is_multisite() ) {
            $sites = get_sites( ['number' => 1000, 'public' => 1, 'archived' => 0, 'spam' => 0, 'deleted' => 0] );
            $site_ids = array_map( function( $s ) { return (int)$s->blog_id; }, $sites );
        } else {
            $site_ids = [1]; 
        }

        if ( $step > count( $site_ids ) ) {
            wp_send_json_success( [
                'done'     => true, 
                'message'  => 'Global media indexing complete.', 
                'progress' => 100
            ] );
        }

        $current_blog_id = $site_ids[ $step - 1 ];
        
        if ( is_multisite() ) {
            switch_to_blog( $current_blog_id );
        }

        global $wpdb;
        $attachments = $wpdb->get_col( $wpdb->prepare( 
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID ASC LIMIT %d OFFSET %d", 
            $batch_size, 
            $offset 
        ) );

        if ( empty( $attachments ) ) {
            if ( is_multisite() ) {
                restore_current_blog();
            }
            
            $progress = round( ( $step / count( $site_ids ) ) * 100 );
            
            wp_send_json_success([
                'done'     => false,
                'step'     => $step + 1,
                'offset'   => 0,
                'progress' => $progress,
                'message'  => "Completed scanning site ID: {$current_blog_id}."
            ]);
        }

        $processed_count = 0;
        $failed_count = 0;
        $last_sql_error = '';

        // Execute the insertion loop
        foreach ( $attachments as $att_id ) {
            $result = self::index_single_attachment( $att_id, $current_blog_id );
            
            if ( is_wp_error( $result ) ) {
                $failed_count++;
                $last_sql_error = $result->get_error_message();
            } elseif ( $result === true ) {
                $processed_count++;
            }
        }

        if ( is_multisite() ) {
            restore_current_blog();
        }

        $progress = round( ( ( $step - 1 ) / count( $site_ids ) ) * 100 );
        
        // Output the results directly to the terminal
        $message = "Indexing site ID: {$current_blog_id} (Processed: {$processed_count}).";
        if ( $failed_count > 0 ) {
            $message .= " <span style='color:#ff4444;'>[FAILED: {$failed_count} items. DB Error: " . esc_html( $last_sql_error ) . "]</span>";
        }
        
        wp_send_json_success([
            'done'     => false,
            'step'     => $step,
            'offset'   => $offset + $batch_size,
            'progress' => $progress,
            'message'  => $message
        ]);
    }

    public static function auto_index_hook( $post_id ) {
        $blog_id = is_multisite() ? get_current_blog_id() : 1;
        self::index_single_attachment( $post_id, $blog_id );
    }

    public static function index_single_attachment( $att_id, $blog_id = null ) {
        if ( ! $blog_id ) {
            $blog_id = is_multisite() ? get_current_blog_id() : 1;
        }
        
        $mime = get_post_mime_type( $att_id );
        if ( ! $mime ) {
            return false;
        }

        $type = 'document';
        if ( strpos( $mime, 'image/' ) === 0 ) {
            $type = 'image';
        } elseif ( strpos( $mime, 'video/' ) === 0 ) {
            $type = 'video';
        } elseif ( strpos( $mime, 'audio/' ) === 0 ) {
            $type = 'audio';
        }

        if ( ! in_array( $type, ['image', 'video', 'audio'] ) ) {
            return false; 
        }

        $url_full = wp_get_attachment_url( $att_id );
        if ( ! $url_full ) {
            return false;
        }

        $post = get_post( $att_id );
        if ( ! $post ) {
            return false;
        }

        $title = isset( $post->post_title ) ? $post->post_title : '';
        $caption = isset( $post->post_excerpt ) ? $post->post_excerpt : '';
        $alt = get_post_meta( $att_id, '_wp_attachment_image_alt', true );

        $thumb = '';
        $medium = '';
        $poster_url = '';

        if ( $type === 'image' ) {
            $img_thumb = wp_get_attachment_image_src( $att_id, 'thumbnail' );
            if ( $img_thumb ) $thumb = $img_thumb[0];
            
            $img_med = wp_get_attachment_image_src( $att_id, 'medium_large' );
            if ( $img_med ) $medium = $img_med[0];
        } elseif ( $type === 'video' || $type === 'audio' ) {
            $meta_poster = get_post_meta( $att_id, '_tbfbkm_custom_thumb_url', true );
            if ( ! empty( $meta_poster ) ) {
                $poster_url = $meta_poster;
            }
        }

        $meta = wp_get_attachment_metadata( $att_id );
        $width = isset( $meta['width'] ) ? (int)$meta['width'] : 0;
        $height = isset( $meta['height'] ) ? (int)$meta['height'] : 0;

        $year = (int)mysql2date( 'Y', $post->post_date );
        $month = (int)mysql2date( 'm', $post->post_date );

        $created_gmt = $post->post_date_gmt;
        if ( empty( $created_gmt ) || strpos( $created_gmt, '0000-00-00' ) !== false ) {
            $created_gmt = gmdate( 'Y-m-d H:i:s' );
        }

        global $wpdb;
        $table = $wpdb->base_prefix . 'tbfbkm_index';

        $data = [
            'blog_id'       => $blog_id,
            'attachment_id' => $att_id,
            'url_full'      => $url_full,
            'url_medium'    => $medium,
            'url_thumb'     => $thumb,
            'poster_url'    => $poster_url,
            'title'         => sanitize_text_field( $title ),
            'caption'       => sanitize_text_field( $caption ),
            'alt'           => sanitize_text_field( $alt ),
            'mime'          => $mime,
            'media_type'    => $type,
            'width'         => $width,
            'height'        => $height,
            'year'          => $year,
            'month'         => $month,
            'created_gmt'   => $created_gmt
        ];

        $formats = [
            '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'
        ];

        // Execute the insertion
        $result = $wpdb->replace( $table, $data, $formats );

        // Surface actual DB errors if it fails
        if ( $result === false ) {
            return new WP_Error( 'db_insert_failed', $wpdb->last_error );
        }

        return true;
    }
}