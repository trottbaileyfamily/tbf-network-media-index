<?php
/**
 * File: includes/network/class-tbfnmi-indexer.php
 * Version: 6.9.6 (Big King Media Indexer)
 * Description: Scans network sites to populate the Big King Index and Usage Map.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Indexer {

  public static function init() {
    // Hook into WP media actions to keep index fresh
    add_action('add_attachment', [__CLASS__, 'index_single_attachment']);
    add_action('edit_attachment', [__CLASS__, 'index_single_attachment']);
    add_action('delete_attachment', [__CLASS__, 'remove_single_attachment']);
    
    // Admin Actions (Command Center)
    add_action('wp_ajax_tbfnmi_process_batch', [__CLASS__, 'process_batch']);
  }

  // ==========================================================================
  // 1. SINGLE ITEM HANDLERS (Real-Time Sync)
  // ==========================================================================

  public static function index_single_attachment($post_id) {
    if ( wp_is_post_revision($post_id) ) return;
    
    $post = get_post($post_id);
    if ( ! $post || $post->post_type !== 'attachment' ) return;

    self::update_index($post, get_current_blog_id());
    self::scan_post_usage_for_media($post_id, get_current_blog_id());
  }

  public static function remove_single_attachment($post_id) {
    global $wpdb;
    $index_table = $wpdb->base_prefix . 'tbfnmi_index';
    $usage_table = $wpdb->base_prefix . 'tbfnmi_usage_map';
    $blog_id = get_current_blog_id();

    $wpdb->delete($index_table, ['attachment_id' => $post_id, 'blog_id' => $blog_id]);
    
    // Also clear usage map where this media was the source
    // (Note: finding where *this* media was used requires a reverse URL lookup, usually handled by full re-scan)
  }

  // ==========================================================================
  // 2. CORE INDEXING LOGIC
  // ==========================================================================

  /**
   * Inserts or Updates a media item in the Big King Index.
   */
  public static function update_index($post, $blog_id) {
    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfnmi_index';

    $url = wp_get_attachment_url($post->ID);
    if ( ! $url ) return;

    // Detect Type
    $mime = get_post_mime_type($post->ID);
    $type = 'other';
    if ( strpos($mime, 'image') !== false ) $type = 'image';
    elseif ( strpos($mime, 'video') !== false ) $type = 'video';
    elseif ( strpos($mime, 'audio') !== false ) $type = 'audio';

    // Metadata
    $meta = wp_get_attachment_metadata($post->ID);
    $width = $meta['width'] ?? 0;
    $height = $meta['height'] ?? 0;
    
    // Thumbnails
    $thumb = '';
    $medium = '';
    
    if ( $type === 'image' ) {
        $thumb_data = image_get_intermediate_size($post->ID, 'thumbnail');
        $thumb = $thumb_data ? $thumb_data['url'] : '';
        
        $med_data = image_get_intermediate_size($post->ID, 'medium');
        $medium = $med_data ? $med_data['url'] : '';
    } elseif ( $type === 'video' ) {
        // Try to find a featured image set on the video attachment (if supported by theme)
        $thumb_id = get_post_thumbnail_id($post->ID);
        if ( $thumb_id ) $thumb = wp_get_attachment_url($thumb_id);
    }

    // Alt Text
    $alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);

    // Prepare Data
    $data = [
      'blog_id'       => $blog_id,
      'attachment_id' => $post->ID,
      'url_full'      => $url,
      'url_medium'    => $medium,
      'url_thumb'     => $thumb,
      'poster_url'    => ($type === 'video') ? $thumb : '', // Store video poster in dedicated col if needed
      'mime'          => $mime,
      'media_type'    => $type,
      'title'         => $post->post_title,
      'alt'           => $alt,
      'caption'       => $post->post_excerpt,
      'description'   => $post->post_content,
      'width'         => $width,
      'height'        => $height,
      'year'          => date('Y', strtotime($post->post_date)),
      'month'         => date('m', strtotime($post->post_date)),
      'created_gmt'   => $post->post_date_gmt,
      'updated_at'    => current_time('mysql', 1)
    ];

    // Upsert (Insert or Update)
    // We use REPLACE into to handle duplicates efficiently
    $wpdb->replace($table, $data);
  }

  // ==========================================================================
  // 3. KALEEYON SEO MAPPER (Deep Linking)
  // ==========================================================================

  /**
   * Scans a post to see what media it uses, populating the Usage Map.
   * This enables the "Featured In" links in Sher Photofall.
   */
  public static function scan_post_usage_for_media($post_id, $blog_id) {
      $post = get_post($post_id);
      if ( ! $post ) return;
      
      // We only care about public posts utilizing media
      if ( $post->post_type === 'attachment' || $post->post_type === 'revision' ) return;

      global $wpdb;
      $map_table = $wpdb->base_prefix . 'tbfnmi_usage_map';

      // 1. Extract URLs from Content
      $content = $post->post_content;
      // Regex to find src="..." inside <img ...> or similar tags
      preg_match_all('/src="([^"]*)"/i', $content, $matches);
      $urls = $matches[1] ?? [];

      // 2. Extract Featured Image
      if ( has_post_thumbnail($post_id) ) {
          $thumb_url = wp_get_attachment_url(get_post_thumbnail_id($post_id));
          if ( $thumb_url ) $urls[] = $thumb_url;
      }

      if ( empty($urls) ) return;

      $urls = array_unique($urls);

      // 3. Insert into Map
      foreach ( $urls as $url ) {
          // Normalize URL (remove query strings, etc if needed, but strict for now)
          $wpdb->replace($map_table, [
              'media_url' => $url,
              'blog_id'   => $blog_id,
              'post_id'   => $post_id,
              'updated_at'=> current_time('mysql', 1)
          ]);
      }
  }

  // ==========================================================================
  // 4. BULK BATCH PROCESSOR (AJAX)
  // ==========================================================================

  public static function process_batch() {
    if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Forbidden']);
    // Increase time limit for batch
    @set_time_limit(300);

    $step   = (int)($_POST['step'] ?? 1);
    $offset = (int)($_POST['offset'] ?? 0);
    $limit  = 50; 

    // Get list of sites
    $sites = get_sites(['fields' => 'ids', 'number' => 10000]); 
    $total_sites = count($sites);

    if ( $step > $total_sites ) {
        wp_send_json_success(['done' => true, 'message' => 'All sites scanned.']);
    }

    // Determine which site to scan
    $site_idx = $step - 1;
    $current_blog_id = $sites[$site_idx];

    switch_to_blog($current_blog_id);

    // Fetch batch of attachments
    $args = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'fields'         => 'ids' // Perf: get IDs only first
    ];
    $query = new WP_Query($args);
    $ids = $query->posts;

    // Fetch batch of regular posts (for Usage Map / SEO)
    // We do this in the same pass to keep index robust
    $seo_args = [
        'post_type'      => ['post', 'page'],
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'fields'         => 'ids'
    ];
    $seo_query = new WP_Query($seo_args);
    $seo_ids = $seo_query->posts;

    foreach ( $ids as $att_id ) {
        self::index_single_attachment($att_id);
    }

    foreach ( $seo_ids as $pid ) {
        self::scan_post_usage_for_media($pid, $current_blog_id);
    }

    restore_current_blog();

    $processed_count = count($ids);
    
    // Prepare response logic
    $next_offset = $offset + $limit;
    $next_step = $step;

    // If we processed fewer items than limit, we are done with this site
    if ( $processed_count < $limit && count($seo_ids) < $limit ) {
        $next_step++;
        $next_offset = 0;
        $message = "Site ID $current_blog_id complete. Moving to next...";
    } else {
        $message = "Processing Site ID $current_blog_id (Offset $next_offset)...";
    }

    wp_send_json_success([
        'done'    => false,
        'step'    => $next_step,
        'offset'  => $next_offset,
        'message' => $message,
        'progress'=> round(($step / $total_sites) * 100)
    ]);
  }
}