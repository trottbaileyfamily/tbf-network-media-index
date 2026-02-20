<?php
/**
 * File: includes/integrations/class-tbfnmi-vikinger-bridge.php
 * Version: 6.1.1 (Chunked Sync + Prefix Compliant)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Vikinger_Bridge {

    public static function init() {
        add_action('wp_ajax_tbfnmi_sync_vikinger', [__CLASS__, 'ajax_sync_vikinger']);
    }

    public static function ajax_sync_vikinger() {
        check_ajax_referer('tbfnmi_indexer_run', 'nonce');
        if ( ! current_user_can('manage_network_options') ) wp_send_json_error('Unauthorized');

        $blog_id = isset($_POST['blog_id']) ? (int)$_POST['blog_id'] : get_current_blog_id();
        $offset  = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
        $limit   = 10; // Process 10 files at a time to prevent server timeout
        
        if ( is_multisite() ) {
            switch_to_blog($blog_id);
        }

        $upload_dir = wp_upload_dir();
        $vikinger_dir = $upload_dir['basedir'] . '/vikinger';
        
        if ( ! is_dir($vikinger_dir) ) {
            if ( is_multisite() ) restore_current_blog();
            wp_send_json_success(['synced' => 0, 'done' => true, 'message' => 'No Vikinger folder found on this site.']);
        }

        // 1. Gather ALL files quickly into an array
        $all_files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vikinger_dir, RecursiveDirectoryIterator::SKIP_DOTS));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'wav', 'webm'];

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if ( in_array($ext, $allowed_exts) ) {
                $all_files[] = $file->getPathname();
            }
        }

        $total_files = count($all_files);
        
        // 2. Slice the array to get just our current chunk of 10
        $chunk = array_slice($all_files, $offset, $limit);
        $synced_count = 0;

        foreach ($chunk as $file_path) {
            $user_id = 0;
            if ( preg_match('/vikinger[\/\\\]member[\/\\\](\d+)[\/\\\]/i', $file_path, $matches) ) {
                $user_id = (int)$matches[1];
            }

            if ( $user_id > 0 ) {
                $user_meta = get_userdata($user_id);
                $is_admin = false;
                
                if ( $user_meta ) {
                    if ( is_super_admin($user_id) || in_array('administrator', (array)$user_meta->roles) ) {
                        $is_admin = true;
                    }
                }
                
                if ( ! $is_admin ) continue; // Skip normal users
            } else {
                continue; 
            }

            $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);

            global $wpdb;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
                $relative_path
            ));

            if ( ! $exists ) {
                $filetype = wp_check_filetype($file_path, null);
                
                $attachment = [
                    'guid'           => $upload_dir['baseurl'] . '/' . $relative_path,
                    'post_mime_type' => $filetype['type'],
                    'post_title'     => preg_replace('/\.[^.]+$/', '', basename($file_path)),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                ];

                $attach_id = wp_insert_attachment($attachment, $file_path);
                
                if ( ! is_wp_error($attach_id) ) {
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    // This heavy operation is why we chunk!
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    
                    $indexer = new TBFNMI_Indexer();
                    $indexer->index_single_attachment($attach_id);
                    
                    $synced_count++;
                }
            }
        }

        if ( is_multisite() ) {
            restore_current_blog();
        }

        $next_offset = $offset + $limit;
        $done = ($next_offset >= $total_files);

        wp_send_json_success([
            'synced' => $synced_count, 
            'done' => $done, 
            'next_offset' => $next_offset,
            'total' => $total_files
        ]);
    }
}