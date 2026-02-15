<?php
/**
 * File: includes/class-tbf-nmi-ajax.php
 * Version: 4.2.3
 *
 * AJAX endpoints used by assets/js/modal.js
 *
 * Actions:
 * - tbf_nmi_list
 * - tbf_nmi_sites
 * - tbf_nmi_proxy
 * - tbf_nmi_proxy_url
 * - tbf_nmi_set_featured_remote
 *
 * v4:
 * - If the Photofall index table exists, listing pulls from it (FAST).
 * - Falls back to cross-blog WP_Query if index table is missing.
 */

if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_AJAX {

  public static function init() {
    add_action('wp_ajax_tbf_nmi_list', [__CLASS__, 'list_items']);
    add_action('wp_ajax_tbf_nmi_sites', [__CLASS__, 'sites']);
    add_action('wp_ajax_tbf_nmi_proxy', [__CLASS__, 'proxy']);
    add_action('wp_ajax_tbf_nmi_proxy_url', [__CLASS__, 'proxy_url']);
    add_action('wp_ajax_tbf_nmi_set_featured_remote', [__CLASS__, 'set_featured_remote']);
  }

  private static function verify() {
    if ( ! current_user_can('upload_files') ) {
      wp_send_json_error(['message' => 'Permission denied'], 403);
    }
    check_ajax_referer('tbf_nmi_nonce', 'nonce');
  }

  /**
   * Save remote featured media (no file copying):
   * - Stores URL/type/mime on the post
   * - Forces _thumbnail_id to placeholder to keep Gutenberg stable
   */
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

    update_post_meta($postId, '_tbf_nmi_featured_url', $url);
    update_post_meta($postId, '_tbf_nmi_featured_mime', $mime);
    update_post_meta($postId, '_tbf_nmi_featured_type', $type);

    $pid = 0;
    if (class_exists('TBF_NMI_Placeholder')) {
      $pid = (int) TBF_NMI_Placeholder::get_id();
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
    $table = $wpdb->base_prefix . 'tbf_nmi_index';

    $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)) );
    if ( ! $exists ) return null;

    $where = "1=1";
    $params = [];

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

    $totalSql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
    $total = (int)$wpdb->get_var( $params ? $wpdb->prepare($totalSql, $params) : $totalSql );

    $offset = ($page - 1) * $per;

    $sql = "SELECT blog_id, attachment_id, title, mime, media_type, url_full, url_medium, url_thumb, poster_url, created_gmt
            FROM {$table}
            WHERE {$where}
            ORDER BY created_gmt DESC, blog_id DESC, attachment_id DESC
            LIMIT %d OFFSET %d";

    $params2 = $params;
    $params2[] = $per;
    $params2[] = $offset;

    $rows = $wpdb->get_results( $wpdb->prepare($sql, $params2), ARRAY_A );

    $items = [];
    foreach ((array)$rows as $r) {
      $thumb = $r['url_thumb'] ?: ($r['poster_url'] ?: ($r['url_medium'] ?: $r['url_full']));
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

    // non-multisite
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

  /**
   * Create a local proxy attachment for a media item from another blog in the network.
   * No file copying, just store remote URL in proxy meta.
   */
  public static function proxy() {
    self::verify();

    $originBlogId = (int)($_POST['origin_blog_id'] ?? 0);
    $originAttId  = (int)($_POST['origin_attachment_id'] ?? 0);

    if ( $originBlogId <= 0 || $originAttId <= 0 ) {
      wp_send_json_error(['message' => 'Invalid origin'], 400);
    }

    if ( ! class_exists('TBF_NMI_Proxy') ) {
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

    $localId = TBF_NMI_Proxy::create_proxy_attachment([
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

  /**
   * Create a local proxy attachment for an external URL (including vkmedia).
   * No file copying.
   */
  public static function proxy_url() {
    self::verify();

    if ( ! class_exists('TBF_NMI_Proxy') ) {
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

    $localId = TBF_NMI_Proxy::create_proxy_attachment([
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
