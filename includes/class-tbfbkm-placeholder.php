<?php
/**
 * File: includes/class-tbfbkm-placeholder.php
 * Version: 7.0.3.2 (Single Site & Multisite Universal Support)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TBFBKM_Placeholder {

    /**
     * Gets or creates the local placeholder attachment ID.
     * On Single Site, it creates it in the local library.
     * On Multisite, it stores it on the Main Site for network-wide reference.
     */
    public static function get_id() {
        $placeholder_id = 0;

        if ( is_multisite() ) {
            $main_site_id = get_main_site_id();
            $current_site_id = get_current_blog_id();

            if ( $current_site_id != $main_site_id ) {
                switch_to_blog( $main_site_id );
                $placeholder_id = self::get_or_create_local_placeholder();
                restore_current_blog();
            } else {
                $placeholder_id = self::get_or_create_local_placeholder();
            }
        } else {
            // Standalone WordPress logic
            $placeholder_id = self::get_or_create_local_placeholder();
        }

        return (int) $placeholder_id;
    }

    /**
     * Internal function to handle the physical creation/retrieval.
     */
    private static function get_or_create_local_placeholder() {
        $id = get_option( 'tbfbkm_placeholder_id', 0 );

        if ( $id && get_post( $id ) ) {
            return $id;
        }

        // Check if it exists but the option was lost
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare( 
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'attachment' LIMIT 1", 
            'TBF Big King Placeholder' 
        ) );

        if ( $existing ) {
            update_option( 'tbfbkm_placeholder_id', $existing );
            return $existing;
        }

        // Create the placeholder
        $upload_dir = wp_upload_dir();
        $filename   = 'tbf-placeholder.png';
        $filepath   = $upload_dir['basedir'] . '/' . $filename;

        // Create a 1x1 transparent PNG if it doesn't exist
        if ( ! file_exists( $filepath ) ) {
            $img = imagecreatetruecolor( 1, 1 );
            imagesavealpha( $img, true );
            $color = imagecolorallocatealpha( $img, 0, 0, 0, 127 );
            imagefill( $img, 0, 0, $color );
            imagepng( $img, $filepath );
            imagedestroy( $img );
        }

        $attachment = [
            'guid'           => $upload_dir['baseurl'] . '/' . $filename,
            'post_mime_type' => 'image/png',
            'post_title'     => 'TBF Big King Placeholder',
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $attach_id = wp_insert_attachment( $attachment, $filepath );
        
        if ( ! is_wp_error( $attach_id ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filepath );
            wp_update_attachment_metadata( $attach_id, $attach_data );
            update_option( 'tbfbkm_placeholder_id', $attach_id );
            return $attach_id;
        }

        return 0;
    }
}