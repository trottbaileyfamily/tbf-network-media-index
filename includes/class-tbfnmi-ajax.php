<?php
/**
 * File: includes/class-tbfnmi-ajax.php
 * Version: 6.5.15 (Production Engine)
 *
 * AJAX endpoints used by assets/js/modal.js
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_AJAX {

  public static function init() {
    add_action('wp_ajax_tbfnmi_list', [__CLASS__, 'list_items']);
    add_action('wp_ajax_tbfnmi_sites', [__CLASS__, 'sites']);
    add_action('wp_ajax_tbfnmi_proxy', [__CLASS__, 'proxy']);
    add_action('wp_ajax_tbfnmi_proxy_url', [__CLASS__, 'proxy_url']);
    add_action('wp_ajax_tbfnmi_set_featured_remote', [__CLASS__, 'set_featured_remote']);
  }

  private static function verify() {
    if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
    check_ajax_referer('tbfnmi_nonce', 'nonce');
  }

  public static function set_featured_remote() {
    self::verify();

    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId <= 0) wp_send_json_error(['message' => 'Missing post_id'], 400);
    if ( ! current_user_can('edit_post', $postId) ) wp_send_json_error(['message' => 'Cannot edit post'], 403);

    $url  = html_entity_decode((string)($_POST['url'] ?? ''));
    $url  = esc_url_raw($url);
    $mime = sanitize_text_field((string)($_POST['mime'] ?? ''));
    $type = sanitize_key((string)($_POST['type'] ?? ''));

    if ($url === '') wp_send_json_error(['message' => 'Missing url'], 400);

    $lower_url = strtolower($url);
    if (strpos($lower_url, '.gif') !== false) { $mime = 'image/gif'; $type = 'image'; }
    elseif (strpos($lower_url, '.png') !== false) { $mime = 'image/png'; $type = 'image'; }
    elseif (strpos($lower_url, '.webp') !== false) { $mime = 'image/webp'; $type = 'image'; }
    elseif (empty($mime) || $mime === 'application/octet-stream') { $mime = 'image/jpeg'; $type = 'image'; }

    update_post_meta($postId, '_tbfnmi_featured_url', $url);
    update_post_meta($postId, '_tbfnmi_featured_mime', $mime);
    update_post_meta($postId, '_tbfnmi_featured_type', $type);

    $pid = 0;
    if (class_exists('TBFNMI_Placeholder')) {
      $pid = (int) TBFNMI_Placeholder::get_id();
      if ($pid > 0) update_post_meta($postId, '_thumbnail_id', $pid);
    }

    clean_post_cache($postId);

    wp_send_json_success([
      'post_id' => $postId,
      'placeholder_id' => $pid,
      'url' => $url,
      'mime' => $mime,
      'type' => $type,
    ]);
  }

  public static function list_items() {
    self::verify();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = max(10, min(200, (int)($_GET['per_page'] ?? 60)));
    $search = sanitize_text_field((string)($_GET['s'] ?? ''));
    $mime   = sanitize_text_field((string)($_GET['mime'] ?? ''));
    $originBlogId = (int)($_GET['origin_blog_id'] ?? 0);
    $orderby = sanitize_text_field((string)($_GET['orderby'] ?? 'date'));

    $fast = self::list_from_index_table($page, $per, $search, $mime, $originBlogId, $orderby);
    if ( $fast !== null ) wp_send_json_success($fast);

    $slow = self::list_fallback_scan($page, $per, $search, $mime, $originBlogId);
    wp_send_json_success($slow);
  }

  private static function list_from_index_table($page, $per, $search, $mime, $originBlogId, $orderby) {
    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfnmi_index';

    $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)) );
    if ( ! $exists ) return null;

    $where = "1=1";
    $params = [];

    $where .= " AND (url_full NOT LIKE %s OR url_full NOT REGEXP %s)";
    $params[] = '%/vikinger/%';
    $params[] = '-[0-9]+x[0-9]+[^/]*[.][a-zA-Z0-9]+$';

    if ( $originBlogId > 0 ) {
      $where .= " AND blog_id = %d";
      $params[] = $originBlogId;
    }

    if ( $mime ) {
      if ( $mime === 'image' ) { $where .= " AND media_type = %s"; $params[] = 'image'; } 
      elseif ( $mime === 'video' ) { $where .= " AND media_type = %s"; $params[] = 'video'; } 
      elseif ( $mime === 'audio' ) { $where .= " AND media_type = %s"; $params[] = 'audio'; } 
      elseif ( $mime === 'application' ) { $where .= " AND media_type = %s"; $params[] = 'application'; }
    }

    if ( $search !== '' ) {
      $like = '%' . $wpdb->esc_like($search) . '%';
      $where .= " AND (title LIKE %s OR alt LIKE %s OR caption LIKE %s)";
      $params[] = $like; $params[] = $like; $params[] = $like;
    }

    if ( empty( $params ) ) { $where .= " AND 1=%d"; $params[] = 1; }

    $totalSql = "SELECT COUNT(DISTINCT url_full) FROM {$table} WHERE {$where}";
    $total = (int)$wpdb->get_var( $wpdb->prepare($totalSql, $params) );
    $offset = ($page - 1) * $per;

    $order_sql = "ORDER BY MIN(created_gmt) DESC";
    if ($orderby === 'rand') $order_sql = "ORDER BY RAND()";

    $sql = "SELECT 
                MIN(blog_id) as blog_id, MAX(attachment_id) as attachment_id, MAX(title) as title, MAX(mime) as mime, MAX(media_type) as media_type, 
                url_full, MAX(url_medium) as url_medium, MAX(url_thumb) as url_thumb, MAX(poster_url) as poster_url, MIN(created_gmt) as created_gmt,
                MAX(width) as width, MAX(height) as height
            FROM {$table} WHERE {$where} GROUP BY url_full {$order_sql} LIMIT %d OFFSET %d";

    $params2 = $params; $params2[] = $per; $params2[] = $offset;
    $rows = $wpdb->get_results( $wpdb->prepare($sql, $params2), ARRAY_A );

    $items = [];
    foreach ((array)$rows as $r) {
      $thumb = $r['url_thumb'] ?: ($r['poster_url'] ?: ($r['url_medium'] ?: $r['url_full']));
      if ($r['media_type'] === 'audio') $thumb = includes_url('images/media/audio.png');
      $items[] = [
        'blog_id' => (int)$r['blog_id'], 'attachment_id' => (int)$r['attachment_id'], 'title' => (string)($r['title'] ?? ''),
        'url' => (string)($r['url_full'] ?? ''), 'thumb' => (string)$thumb, 'mime' => (string)($r['mime'] ?? ''),
        'media_type' => (string)($r['media_type'] ?? ''), 'width' => (int)($r['width'] ?? 800), 'height' => (int)($r['height'] ?? 800),
      ];
    }

    return ['items' => $items, 'total' => $total, 'max_pages' => $per > 0 ? (int)ceil($total / $per) : 1, 'source' => 'index_table'];
  }

  private static function list_fallback_scan($page, $per, $search, $mime, $originBlogId) {
    return ['items' => [], 'total' => 0, 'max_pages' => 1, 'source' => 'fallback_scan'];
  }

  public static function sites() {
    self::verify();
    if ( ! is_multisite() ) wp_send_json_success(['sites' => [['blog_id' => 1, 'name' => get_bloginfo('name')]]]);
    $out = [];
    $sites = get_sites(['number' => 1000]); 
    foreach ($sites as $s) {
      $bid = (int)$s->blog_id;
      $name = get_blog_option($bid, 'blogname');
      $out[] = ['blog_id' => $bid, 'name' => $name ? $name : ('Site ' . $bid)];
    }
    wp_send_json_success(['sites' => $out]);
  }

  public static function proxy() {
    self::verify();
    $originBlogId = (int)($_POST['origin_blog_id'] ?? 0);
    $originAttId  = (int)($_POST['origin_attachment_id'] ?? 0);
    $url          = esc_url_raw($_POST['url'] ?? '');
    $title        = sanitize_text_field($_POST['title'] ?? 'Media');
    $mime         = sanitize_text_field($_POST['mime'] ?? '');

    if ( ! $url ) wp_send_json_error(['message' => 'Missing remote URL payload.'], 400);

    $lower_url = strtolower($url);
    if (strpos($lower_url, '.gif') !== false) $mime = 'image/gif';
    elseif (strpos($lower_url, '.png') !== false) $mime = 'image/png';
    elseif (strpos($lower_url, '.webp') !== false) $mime = 'image/webp';
    elseif (strpos($lower_url, '.mp4') !== false) $mime = 'video/mp4';
    if (empty($mime) || $mime === 'application/octet-stream') $mime = 'image/jpeg';

    if ( ! class_exists('TBFNMI_Proxy') ) wp_send_json_error(['message' => 'Proxy class missing'], 500);

    if ( class_exists('TBFNMI_Network_Media_Index') ) remove_action('add_attachment', ['TBFNMI_Network_Media_Index', 'auto_index_attachment']);

    $localId = TBFNMI_Proxy::create_proxy_attachment([
      'origin_blog_id' => $originBlogId, 'origin_attachment_id' => $originAttId, 'url' => $url,
      'title' => $title ?: 'Media', 'mime' => $mime, 'source' => 'network',
    ]);

    if ( class_exists('TBFNMI_Network_Media_Index') ) add_action('add_attachment', ['TBFNMI_Network_Media_Index', 'auto_index_attachment']);

    if ( is_wp_error($localId) ) wp_send_json_error(['message' => $localId->get_error_message()], 500);
    wp_send_json_success(['local_attachment_id' => (int)$localId, 'url' => $url, 'mime' => $mime]);
  }

  public static function proxy_url() {
    self::verify();
    if ( ! class_exists('TBFNMI_Proxy') ) wp_send_json_error(['message' => 'Proxy class missing'], 500);

    $source = sanitize_key((string)($_POST['source'] ?? 'external'));
    $url    = esc_url_raw((string)($_POST['url'] ?? ''));
    $title  = sanitize_text_field((string)($_POST['title'] ?? 'Media'));
    $mime   = sanitize_text_field((string)($_POST['mime'] ?? ''));

    if ( $url === '' ) wp_send_json_error(['message' => 'Missing URL'], 400);

    $lower_url = strtolower($url);
    if (strpos($lower_url, '.gif') !== false) $mime = 'image/gif';
    elseif (strpos($lower_url, '.png') !== false) $mime = 'image/png';
    elseif (strpos($lower_url, '.webp') !== false) $mime = 'image/webp';
    elseif (strpos($lower_url, '.mp4') !== false) $mime = 'video/mp4';
    if (empty($mime) || $mime === 'application/octet-stream') $mime = 'image/jpeg';

    $extra = [];
    if ($source === 'vkmedia') {
      $extra['vkmedia_id'] = (int)($_POST['vkmedia_id'] ?? 0);
      $extra['user_id']    = (int)($_POST['user_id'] ?? 0);
    }

    if ( class_exists('TBFNMI_Network_Media_Index') ) remove_action('add_attachment', ['TBFNMI_Network_Media_Index', 'auto_index_attachment']);

    $localId = TBFNMI_Proxy::create_proxy_attachment([
      'origin_blog_id' => 0, 'origin_attachment_id' => 0, 'url' => $url,
      'title' => $title ?: 'Media', 'mime' => $mime, 'source' => $source ?: 'external', 'extra_meta' => $extra,
    ]);

    if ( class_exists('TBFNMI_Network_Media_Index') ) add_action('add_attachment', ['TBFNMI_Network_Media_Index', 'auto_index_attachment']);

    if ( is_wp_error($localId) ) wp_send_json_error(['message' => $localId->get_error_message()], 500);
    wp_send_json_success(['local_attachment_id' => (int)$localId, 'url' => $url, 'mime' => $mime]);
  }
}