<?php
/**
 * File: includes/network/class-tbfbkm-proxy.php
 * Version: 6.9.6 (Big King Proxy Engine)
 * Description: Creates virtual attachments for external or cross-network media.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Proxy {

  /**
   * Creates a "Proxy" attachment that points to a remote URL.
   * This allows remote media to be used in standard WP galleries and featured images.
   *
   * @param array $args {
   * @type int    $origin_blog_id       (Optional) Source Site ID
   * @type int    $origin_attachment_id (Optional) Source Attachment ID
   * @type string $url                  Remote URL
   * @type string $title                Media Title
   * @type string $mime                 Mime Type (e.g. image/jpeg)
   * @type string $source               'network' or 'external'
   * }
   * @return int|WP_Error Local Attachment ID
   */
  public static function create_proxy_attachment( $args ) {
    
    // 1. Defaults
    $defaults = [
        'origin_blog_id' => 0,
        'origin_attachment_id' => 0,
        'url' => '',
        'title' => '',
        'mime' => 'image/jpeg',
        'source' => 'network',
        'extra_meta' => []
    ];
    $args = wp_parse_args($args, $defaults);

    if ( empty($args['url']) ) return new WP_Error('tbf_proxy_error', 'Missing URL');

    // 2. Check for Duplicates
    // We don't want to create 100 proxies for the same image.
    $existing = self::find_existing_proxy($args['url']);
    if ( $existing ) return $existing;

    // 3. Prepare Attachment Post Data
    $attachment_data = [
        'post_mime_type' => $args['mime'],
        'post_title'     => sanitize_text_field($args['title']),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'guid'           => esc_url_raw($args['url']) // The Remote URL becomes the GUID
    ];

    // 4. Insert Attachment
    // Note: We do NOT download the file. We just create the DB record.
    $attach_id = wp_insert_attachment($attachment_data, $args['url']);

    if ( is_wp_error($attach_id) ) return $attach_id;

    // 5. Add Big King Meta Data
    update_post_meta($attach_id, '_tbfbkm_is_proxy', 1);
    update_post_meta($attach_id, '_tbfbkm_proxy_source', $args['source']);
    update_post_meta($attach_id, '_tbfbkm_remote_url', $args['url']);

    if ( !empty($args['origin_blog_id']) ) {
        update_post_meta($attach_id, '_tbfbkm_origin_blog', $args['origin_blog_id']);
        update_post_meta($attach_id, '_tbfbkm_origin_id', $args['origin_attachment_id']);
    }

    if ( !empty($args['extra_meta']) && is_array($args['extra_meta']) ) {
        foreach($args['extra_meta'] as $k => $v) {
            update_post_meta($attach_id, $k, $v);
        }
    }

    // 6. Generate Metadata Stub
    // Since we don't have the file locally, we fake the metadata so WP thinks it's real.
    // This prevents "Image not found" errors in the media library grid.
    $meta = [
        'width' => 800,  // Dummy width
        'height' => 600, // Dummy height
        'file' => basename($args['url'])
    ];
    wp_update_attachment_metadata($attach_id, $meta);

    return $attach_id;
  }

  /**
   * Checks if a proxy for this URL already exists to prevent duplicates.
   */
  private static function find_existing_proxy( $url ) {
      global $wpdb;
      
      // Check meta key first (Faster than GUID scan)
      $query = $wpdb->prepare(
          "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tbfbkm_remote_url' AND meta_value = %s LIMIT 1", 
          $url
      );
      $id = $wpdb->get_var($query);

      return $id ? (int)$id : false;
  }
}
