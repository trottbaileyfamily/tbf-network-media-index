<?php
/**
 * File: includes/class-tbfnmi-proxy.php
 * Version: 4.3.6 (Robust Hidden Status & Migration)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Proxy {

  const STATUS_HIDDEN = 'tbfnmi-hidden';

  public static function init() {
    // 1. Register Status (Publicly queryable but hidden from UI lists)
    register_post_status(self::STATUS_HIDDEN, [
      'label'                     => 'Network Proxy',
      'public'                    => true, 
      'exclude_from_search'       => true,
      'show_in_admin_all_list'    => false,
      'show_in_admin_status_list' => false,
      'internal'                  => true,
    ]);

    // 2. Prevent WP from reverting status to 'inherit'
    add_filter('wp_insert_post_data', [__CLASS__, 'prevent_status_revert'], 999, 2);

    // 3. Force Migration (Retries until confirmed empty)
    add_action('admin_init', [__CLASS__, 'maintenance_migrate_status']);
  }

  /**
   * Guard: Ensures proxies always stay hidden.
   */
  public static function prevent_status_revert($data, $postarr) {
      if ( $data['post_type'] !== 'attachment' ) return $data;
      
      // If this is a proxy, force hidden status
      $id = isset($postarr['ID']) ? (int)$postarr['ID'] : 0;
      if ( $id > 0 && self::is_proxy($id) ) {
          $data['post_status'] = self::STATUS_HIDDEN;
      }
      return $data;
  }

  /**
   * Migration: Moves 'inherit' proxies to 'tbfnmi-hidden'.
   * Runs efficiently on admin_init.
   */
  public static function maintenance_migrate_status() {
      // Check if we still have visible proxies (Limit 1 to be fast)
      global $wpdb;
      
      $has_visible = $wpdb->get_var("
          SELECT ID FROM {$wpdb->posts} p
          INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
          WHERE p.post_type = 'attachment' 
          AND p.post_status = 'inherit'
          AND pm.meta_key = '_tbfnmi_is_proxy'
          LIMIT 1
      ");

      if ( $has_visible ) {
          // Bulk update
          $wpdb->query("
              UPDATE {$wpdb->posts} p
              INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
              SET p.post_status = '" . self::STATUS_HIDDEN . "'
              WHERE p.post_type = 'attachment' 
              AND p.post_status = 'inherit'
              AND pm.meta_key = '_tbfnmi_is_proxy'
          ");
      }
  }

  public static function is_proxy($post_id) {
    return (bool) get_post_meta($post_id, '_tbfnmi_is_proxy', true);
  }

  public static function create_proxy_attachment(array $args) {
    $url = isset($args['url']) ? esc_url_raw((string)$args['url']) : '';
    if ( ! $url ) return new WP_Error('tbfnmi_proxy_missing_url', 'Missing remote URL.');

    // Check existing
    $existing = self::find_existing_proxy($args);
    if ( $existing ) return $existing;

    $title = isset($args['title']) ? sanitize_text_field((string)$args['title']) : 'Network Media';
    $mime  = isset($args['mime']) ? sanitize_text_field((string)$args['mime']) : 'application/octet-stream';

    // Create as HIDDEN immediately
    $attId = wp_insert_post([
      'post_title'     => $title,
      'post_status'    => self::STATUS_HIDDEN,
      'post_type'      => 'attachment',
      'post_mime_type' => $mime,
      'guid'           => $url,
    ], true);

    if ( is_wp_error($attId) ) return $attId;
    $attId = (int)$attId;

    update_post_meta($attId, '_tbfnmi_is_proxy', 1);
    update_post_meta($attId, '_tbfnmi_proxy_url', $url); // v4 style
    update_post_meta($attId, '_tbfnmi_origin_url', $url); // v1 style (compat)

    if ( !empty($args['origin_blog_id']) ) update_post_meta($attId, '_tbfnmi_origin_blog_id', (int)$args['origin_blog_id']);
    if ( !empty($args['origin_attachment_id']) ) update_post_meta($attId, '_tbfnmi_origin_attachment_id', (int)$args['origin_attachment_id']);

    return $attId;
  }

  private static function find_existing_proxy($args) {
    // Simplified lookup to catch duplicates
    $url = isset($args['url']) ? $args['url'] : '';
    if (!$url) return 0;
    
    global $wpdb;
    $sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tbfnmi_proxy_url' AND meta_value = %s LIMIT 1";
    return (int) $wpdb->get_var($wpdb->prepare($sql, $url));
  }
}

add_action('init', ['TBFNMI_Proxy', 'init']);
