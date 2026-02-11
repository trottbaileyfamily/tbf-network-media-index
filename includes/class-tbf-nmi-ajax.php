<?php
/**
 * File: includes/class-tbf-nmi-ajax.php
 * Version: 4.0.0
 *
 * AJAX endpoints used by assets/js/modal.js
 *
 * Actions:
 * - tbf_nmi_list
 * - tbf_nmi_sites
 * - tbf_nmi_proxy
 * - tbf_nmi_proxy_url
 *
 * v4 change:
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
  }

  private static function verify() {
    if ( ! current_user_can('upload_files') ) {
      wp_send_json_error(['message' => 'Permission denied'], 403);
    }
    check_ajax_referer('tbf_nmi_nonce', 'nonce');
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

    $sql = "SELECT blog_id, attachment_id, title, mime, media_type, url_full, url_thumb, poster_url, created_gmt
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
      $thumb = $r['url_thumb'] ?: ($r['poster_url'] ?: $r['url_full']);
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
        if ($originBlogId && $originBlogId !== $bid) continue;

        switch_to_blog($bid);

        $q = new WP_Query([
          'post_type' => 'attachment',
          'post_status' => 'inherit',
          'posts_per_page' => 200,
          's' => $search,
          'orderby' => 'date',
          'order' => 'DESC',
        ]);

        foreach ($q->posts as $p) {
          $id = (int)$p->ID;
          $pm = (string)get_post_mime_type($id);
          if ($mime && strpos($pm, $mime . '/') !== 0) continue;

          $url = wp_get_attachment_url($id);
          $thumb = wp_get_attachment_image_src($id, 'thumbnail');
          $items[] = [
            'blog_id' => $bid,
            'attachment_id' => $id,
            'title' => get_the_title($id),
            'url' => $url,
            'thumb' => is_array($thumb) ? $thumb[0] : $url,
            'mime' => $pm,
          ];
        }

        restore_current_blog();
      }
    } else {
      $q = new WP_Query([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 500,
        's' => $search,
        'orderby' => 'date',
        'order' => 'DESC',
      ]);

      foreach ($q->posts as $p) {
        $id = (int)$p->ID;
        $pm = (string)get_post_mime_type($id);
        if ($mime && strpos($pm, $mime . '/') !== 0) continue;

        $url = wp_get_attachment_url($id);
        $thumb = wp_get_attachment_image_src($id, 'thumbnail');
        $items[] = [
          'blog_id' => get_current_blog_id(),
          'attachment_id' => $id,
          'title' => get_the_title($id),
          'url' => $url,
          'thumb' => is_array($thumb) ? $thumb[0] : $url,
          'mime' => $pm,
        ];
      }
    }

    $total = count($items);
    $offset = ($page - 1) * $per;
    $slice = array_slice($items, $offset, $per);

    return [
      'items' => $slice,
      'total' => $total,
      'max_pages' => (int)ceil($total / $per),
      'source' => 'fallback_scan',
    ];
  }

  public static function sites() {
    self::verify();

    $out = [];
    if ( is_multisite() ) {
      $sites = get_sites(['number' => 5000]);
      foreach ($sites as $s) {
        $bid = (int)$s->blog_id;
        $out[] = [
          'blog_id' => $bid,
          'name' => get_blog_option($bid, 'blogname'),
        ];
      }
    } else {
      $out[] = [
        'blog_id' => get_current_blog_id(),
        'name' => get_bloginfo('name'),
      ];
    }

    wp_send_json_success($out);
  }

  public static function proxy() {
    self::verify();

    $originBlogId = (int)($_POST['origin_blog_id'] ?? 0);
    $originAttId  = (int)($_POST['origin_attachment_id'] ?? 0);

    if ( ! $originBlogId || ! $originAttId ) {
      wp_send_json_error(['message' => 'Invalid origin']);
    }

    switch_to_blog($originBlogId);
    $url = wp_get_attachment_url($originAttId);
    $title = get_the_title($originAttId);
    $mime = get_post_mime_type($originAttId);
    restore_current_blog();

    if ( ! $url ) {
      wp_send_json_error(['message' => 'Could not get URL']);
    }

    $localId = TBF_NMI_Proxy::create_proxy_attachment([
      'origin_blog_id' => $originBlogId,
      'origin_attachment_id' => $originAttId,
      'url' => $url,
      'title' => $title,
      'mime' => $mime,
      'source' => 'network',
    ]);

    if ( is_wp_error($localId) ) {
      wp_send_json_error(['message' => $localId->get_error_message()]);
    }

    wp_send_json_success(['local_attachment_id' => (int)$localId]);
  }

  public static function proxy_url() {
    self::verify();

    $url = esc_url_raw((string)($_POST['url'] ?? ''));
    $title = sanitize_text_field((string)($_POST['title'] ?? 'Media'));
    $mime  = sanitize_text_field((string)($_POST['mime'] ?? 'image/*'));

    if ( ! $url ) {
      wp_send_json_error(['message' => 'Missing URL']);
    }

    $localId = TBF_NMI_Proxy::create_proxy_attachment([
      'origin_blog_id' => 0,
      'origin_attachment_id' => 0,
      'url' => $url,
      'title' => $title,
      'mime' => $mime,
      'source' => 'external',
    ]);

    if ( is_wp_error($localId) ) {
      wp_send_json_error(['message' => $localId->get_error_message()]);
    }

    wp_send_json_success(['local_attachment_id' => (int)$localId]);
  }
}
