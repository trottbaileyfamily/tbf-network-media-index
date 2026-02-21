<?php
/**
 * File: includes/class-tbfnmi-ajax.php
 * Version: 6.2.9 (Backend Duplication & Thumbnail Sweeper)
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
    if ( ! current_user_can('upload_files') ) {
      wp_send_json_error(['message' => 'Permission denied'], 403);
    }
    check_ajax_referer('tbfnmi_nonce', 'nonce');
  }

  public static function set_featured_remote() {
    self::verify();

    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId <= 0) {
      wp_send_json_error(['message' => 'Missing post_id'], 400);
    }

    if ( ! current_user_can('edit_post', $postId) ) {
      wp_send_json_error(['message' => 'Cannot edit post'], 403);
    }

    $url  = esc_url_raw((string)($_POST['url'] ?? ''));
    $mime = sanitize_text_field((string)($_POST['mime'] ?? 'application/octet-stream'));
    $type = sanitize_key((string)($_POST['type'] ?? ''));

    if ($url === '') {
      wp_send_json_error(['message' => 'Missing url'], 400);
    }

    if (!in_array($type, ['image','video','audio','file'], true)) {
      $type = 'file';
      if (strpos($mime, 'image/') === 0) $type = 'image';
      elseif (strpos($mime, 'video/') === 0) $type = 'video';
      elseif (strpos($mime, 'audio/') === 0) $type = 'audio';
    }

    update_post_meta($postId, '_tbfnmi_featured_url', $url);
    update_post_meta($postId, '_tbfnmi_featured_mime', $mime);
    update_post_meta($postId, '_tbfnmi_featured_type', $type);

    $pid = 0;
    if (class_exists('TBFNMI_Placeholder')) {
      $pid = (int) TBFNMI_Placeholder::get_id();
      if ($pid > 0) {
        update_post_meta($postId, '_thumbnail_id', $pid);
      }
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

    $fast = self::list_from_index_table($page, $per, $search, $mime, $originBlogId);
    if ( $fast !== null ) {
      wp_send_json_success($fast);
    }

    $slow = self::list_fallback_scan($page, $per, $search, $mime, $originBlogId);
    wp_send_json_success($slow);
  }

  private static function list_from_index_table($page, $per, $search, $mime, $originBlogId) {
    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfnmi_index';

    $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)) );
    if ( ! $exists ) return null;

    $where = "1=1";
    $params = [];

    // SWEEPER: Hide auto-generated sizes from Vikinger in the backend UI
    $where .= " AND (url_full NOT LIKE %s OR url_full NOT REGEXP %s)";
    $params[] = '%/vikinger/%';
    $params[] = '-[0-9]+x[0-9]+[^/]*[.][a-zA-Z0-9]+$';

    if ( $originBlogId > 0 ) {
      $where .= " AND blog_id = %d";
      $params[] = $originBlogId;
    }

    if ( $mime ) {
      if ( $mime === 'image' ) {
        $where .= " AND media_type = %s";
        $params[] = 'image';
      } elseif ( $mime === 'video' ) {
        $where .= " AND media_type = %s";
        $params[] = 'video';
      } elseif ( $mime === 'audio' ) {
        $where .= " AND media_type = %s";
        $params[] = 'audio';
      } elseif ( $mime === 'application' ) {
        $where .= " AND media_type = %s";
        $params[] = 'application';
      }
    }

    if ( $search !== '' ) {
      $like = '%' . $wpdb->esc_like($search) . '%';
      $where .= " AND (title LIKE %s OR alt LIKE %s OR caption LIKE %s)";
      $params[] = $like; $params[] = $like; $params[] = $like;
    }

    if ( empty( $params ) ) {
        $where .= " AND 1=%d";
        $params[] = 1;
    }

    // FIX: Distinct URL count so pagination calculates correctly
    $totalSql = "SELECT COUNT(DISTINCT url_full) FROM {$table} WHERE {$where}";
    $total = (int)$wpdb->get_var( $wpdb->prepare($totalSql, $params) );

    $offset = ($page - 1) * $per;

    // FIX: Group by url_full to perfectly merge all backend duplicate entries into one
    $sql = "SELECT 
                MAX(blog_id) as blog_id, 
                MAX(attachment_id) as attachment_id, 
                MAX(title) as title, 
                MAX(mime) as mime, 
                MAX(media_type) as media_type, 
                url_full, 
                MAX(url_medium) as url_medium, 
                MAX(url_thumb) as url_thumb, 
                MAX(poster_url) as poster_url, 
                MAX(created_gmt) as created_gmt
            FROM {$table}
            WHERE {$where}
            GROUP BY url_full
            ORDER BY created_gmt DESC
            LIMIT %d OFFSET %d";

    $params2 = $params;
    $params2[] = $per;
    $params2[] = $offset;

    $rows = $wpdb->get_results( $wpdb->prepare($sql, $params2), ARRAY_A );

    $items = [];
    foreach ((array)$rows as $r) {
      $thumb = $r['url_thumb'] ?: ($r['poster_url'] ?: ($r['url_medium'] ?: $r['url_full']));
      
      // Fallback icon for backend audio files
      if ($r['media_type'] === 'audio') {
          $thumb = includes_url('images/media/audio.png');
      }
      
      $items[] = [
        'blog_id' => (int)$r['blog_id'],
        'attachment_id' => (int)$r['attachment_id'],
        'title' => (string)($r['title'] ?? ''),
        'url' => (string)($r['url_full'] ?? ''),
        'thumb' => (string)$thumb,
        'mime' => (string)($r['mime'] ?? ''),
        'media_type' => (string)($r['media_type'] ?? ''),
      ];
    }

    return [
      'items' => $items,
      'total' => $total,
      'max_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
      'source' => 'index_table',
    ];
  }

  private static function list_fallback_scan($page, $per, $search, $mime, $originBlogId) {
    $items = [];

    if ( is_multisite() ) {
      $sites = get_sites(['number' => 2000]);
      foreach ($sites as $s) {
        $bid = (int)$s->blog_id;
        if ($originBlogId > 0 && $bid !== $originBlogId) continue;

        switch_to_blog($bid);

        $args = [
          'post_type' => 'attachment',
          'post_status' => 'inherit',
          'posts_per_page' => $per,
          'paged' => $page,
          's' => $search ?: '',
          'orderby' => 'date',
          'order' => 'DESC',
        ];

        if ($mime) {
          if ($mime === 'image') $args['post_mime_type'] = 'image';
          elseif ($mime === 'video') $args['post_mime_type'] = 'video';
          elseif ($mime === 'audio') $args['post_mime_type'] = 'audio';
          elseif ($mime === 'application') $args['post_mime_type'] = 'application';
        }

        $q = new WP_Query($args);

        foreach ($q->posts as $p) {
          $aid = (int)$p->ID;
          $url = wp_get_attachment_url($aid);
          if (!$url) continue;

          $mimeType = (string)get_post_mime_type($aid);
          $mediaType = 'application';
          if (strpos($mimeType, 'image/') === 0) $mediaType = 'image';
          elseif (strpos($mimeType, 'video/') === 0) $mediaType = 'video';
          elseif (strpos($mimeType, 'audio/') === 0) $mediaType = 'audio';

          $thumb = '';
          if ($mediaType === 'image') {
            $t = wp_get_attachment_image_src($aid, 'thumbnail');
            $thumb = is_array($t) ? (string)$t[0] : '';
          } elseif ($mediaType === 'audio') {
            $thumb = includes_url('images/media/audio.png');
          }
          if (!$thumb) $thumb = $url;

          $items[] = [
            'blog_id' => $bid,
            'attachment_id' => $aid,
            'title' => (string)get_the_title($aid),
            'url' => (string)$url,
            'thumb' => (string)$thumb,
            'mime' => $mimeType,
            'media_type' => $mediaType,
          ];
        }

        restore_current_blog();
      }

      return [
        'items' => $items,
        'total' => count($items),
        'max_pages' => 1,
        'source' => 'fallback_scan',
      ];
    }

    $args = [
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'posts_per_page' => $per,
      'paged' => $page,
      's' => $search ?: '',
      'orderby' => 'date',
      'order' => 'DESC',
    ];

    if ($mime) {
      if ($mime === 'image') $args['post_mime_type'] = 'image';
      elseif ($mime === 'video') $args['post_mime_type'] = 'video';
      elseif ($mime === 'audio') $args['post_mime_type'] = 'audio';
      elseif ($mime === 'application') $args['post_mime_type'] = 'application';
    }

    $q = new WP_Query($args);

    foreach ($q->posts as $p) {
      $aid = (int)$p->ID;
      $url = wp_get_attachment_url($aid);
      if (!$url) continue;

      $mimeType = (string)get_post_mime_type($aid);
      $mediaType = 'application';
      if (strpos($mimeType, 'image/') === 0) $mediaType = 'image';
      elseif (strpos($mimeType, 'video/') === 0) $mediaType = 'video';
      elseif (strpos($mimeType, 'audio/') === 0) $mediaType = 'audio';

      $thumb = '';
      if ($mediaType === 'image') {
        $t = wp_get_attachment_image_src($aid, 'thumbnail');
        $thumb = is_array($t) ? (string)$t[0] : '';
      } elseif ($mediaType === 'audio') {
        $thumb = includes_url('images/media/audio.png');
      }
      if (!$thumb) $thumb = $url;

      $items[] = [
        'blog_id' => get_current_blog_id(),
        'attachment_id' => $aid,
        'title' => (string)get_the_title($aid),
        'url' => (string)$url,
        'thumb' => (string)$thumb,
        'mime' => $mimeType,
        'media_type' => $mediaType,
      ];
    }

    return [
      'items' => $items,
      'total' => count($items),
      'max_pages' => 1,
      'source' => 'fallback_scan',
    ];
  }

  public static function sites() {
    self::verify();

    if ( ! is_multisite() ) {
      wp_send_json_success(['sites' => [
        ['blog_id' => 1, 'name' => get_bloginfo('name')],
      ]]);
    }

    $out = [];
    $sites = get_sites(['number' => 2000]);
    foreach ($sites as $s) {
      $bid = (int)$s->blog_id;
      $details = get_blog_details($bid);
      $out[] = [
        'blog_id' => $bid,
        'name' => $details ? $details->blogname : ('Site ' . $bid),
      ];
    }

    wp_send_json_success(['sites' => $out]);
  }

  public static function proxy() {
    self::verify();

    $originBlogId = (int)($_POST['origin_blog_id'] ?? 0);
    $originAttId  = (int)($_POST['origin_attachment_id'] ?? 0);

    if ( $originBlogId <= 0 || $originAttId <= 0 ) {
      wp_send_json_error(['message' => 'Invalid origin'], 400);
    }

    if ( ! class_exists('TBFNMI_Proxy') ) {
      wp_send_json_error(['message' => 'Proxy class missing'], 500);
    }

    if ( ! is_multisite() ) {
      wp_send_json_error(['message' => 'Not multisite'], 400);
    }

    switch_to_blog($originBlogId);
    $url   = wp_get_attachment_url($originAttId);
    $title = get_the_title($originAttId);
    $mime  = (string) get_post_mime_type($originAttId);
    restore_current_blog();

    if ( ! $url ) {
      wp_send_json_error(['message' => 'Could not get origin URL'], 404);
    }

    $localId = TBFNMI_Proxy::create_proxy_attachment([
      'origin_blog_id' => $originBlogId,
      'origin_attachment_id' => $originAttId,
      'url' => $url,
      'title' => (string)$title,
      'mime' => $mime ?: 'application/octet-stream',
      'source' => 'network',
    ]);

    if ( is_wp_error($localId) ) {
      wp_send_json_error(['message' => $localId->get_error_message()], 500);
    }

    wp_send_json_success(['local_attachment_id' => (int)$localId]);
  }

  public static function proxy_url() {
    self::verify();

    if ( ! class_exists('TBFNMI_Proxy') ) {
      wp_send_json_error(['message' => 'Proxy class missing'], 500);
    }

    $source = sanitize_key((string)($_POST['source'] ?? 'external'));
    $url    = esc_url_raw((string)($_POST['url'] ?? ''));
    $title  = sanitize_text_field((string)($_POST['title'] ?? 'Media'));
    $mime   = sanitize_text_field((string)($_POST['mime'] ?? 'application/octet-stream'));

    if ( $url === '' ) {
      wp_send_json_error(['message' => 'Missing URL'], 400);
    }

    $extra = [];
    if ($source === 'vkmedia') {
      $extra['vkmedia_id'] = (int)($_POST['vkmedia_id'] ?? 0);
      $extra['user_id']    = (int)($_POST['user_id'] ?? 0);
    }

    $localId = TBFNMI_Proxy::create_proxy_attachment([
      'origin_blog_id' => 0,
      'origin_attachment_id' => 0,
      'url' => $url,
      'title' => $title ?: 'Media',
      'mime' => $mime ?: 'application/octet-stream',
      'source' => $source ?: 'external',
      'extra_meta' => $extra,
    ]);

    if ( is_wp_error($localId) ) {
      wp_send_json_error(['message' => $localId->get_error_message()], 500);
    }

    wp_send_json_success(['local_attachment_id' => (int)$localId]);
  }
}