<?php
/**
 * File: includes/photofall/class-tbf-nmi-photofall-query.php
 * Version: 4.0.3
 *
 * Query helper reading from tbf_nmi_index (fast).
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_PhotoFall_Query {

  private $table;

  public function __construct() {
    global $wpdb;
    $this->table = $wpdb->base_prefix . 'tbf_nmi_index';
  }

  public function list(array $args) {
    global $wpdb;
    $route = sanitize_key((string)($args['route'] ?? 'root'));
    $page = max(1, (int)($args['page'] ?? 1));
    $pageSize = max(24, min(200, (int)($args['page_size'] ?? 96)));
    $blogId = (int)($args['blog_id'] ?? 0);
    $year = (int)($args['year'] ?? 0);
    $month = (int)($args['month'] ?? 0);
    $tag = sanitize_title((string)($args['tag'] ?? ''));
    $q = sanitize_text_field((string)($args['q'] ?? ''));

    $offset = ($page - 1) * $pageSize;

    $where = "1=1";
    $params = [];

    if ($route === 'site' && $blogId > 0) { $where .= " AND blog_id=%d"; $params[]=$blogId; }
    if ($route === 'year' && $year > 0) { $where .= " AND year=%d"; $params[]=$year; }
    if ($route === 'month' && $year>0 && $month>0) { $where .= " AND year=%d AND month=%d"; $params[]=$year; $params[]=$month; }
    if ($route === 'tag' && $tag) { $where .= " AND tag_slug=%s"; $params[]=$tag; }

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $where .= " AND (title LIKE %s OR alt LIKE %s OR caption LIKE %s)";
      $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $totalSql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";
    $total = (int)$wpdb->get_var($params ? $wpdb->prepare($totalSql, $params) : $totalSql);

    $sql = "SELECT blog_id, attachment_id, media_type, provider, created_gmt, title, alt, caption, url_full, url_thumb, poster_url, width, height, mime
            FROM {$this->table}
            WHERE {$where}
            ORDER BY created_gmt DESC, blog_id DESC, attachment_id DESC
            LIMIT %d OFFSET %d";
    $params2 = $params + [];
    $params2[] = $pageSize;
    $params2[] = $offset;

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params2), ARRAY_A);

    $items = [];
    foreach ((array)$rows as $r) {
      $items[] = $this->row_to_item($r);
    }

    return [
      'items' => $items,
      'page' => $page,
      'page_size' => $pageSize,
      'total' => $total,
      'has_more' => ($offset + count($items)) < $total,
    ];
  }

  public function get_item($blogId, $attId) {
    global $wpdb;
    $blogId = (int)$blogId; $attId=(int)$attId;
    if ($attId<=0) return null;

    // 1) Strict match first (old behavior).
    $row = null;
    if ($blogId > 0) {
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$this->table} WHERE blog_id=%d AND attachment_id=%d LIMIT 1", $blogId, $attId),
        ARRAY_A
      );
    }

    // 2) Fallback: match by attachment_id only (fixes broken detail URLs when blog_id differs).
    if (!$row) {
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$this->table} WHERE attachment_id=%d ORDER BY created_gmt DESC LIMIT 1", $attId),
        ARRAY_A
      );
    }

    if (!$row) return null;
    return $this->row_to_item($row, true);
  }

  public function related($blogId, $attId, $limit=12) {
    global $wpdb;
    $blogId = (int)$blogId; $attId=(int)$attId;
    $limit = max(4, min(48, (int)$limit));
    if ($attId <= 0) return [];

    // Try strict first, then fallback
    $row = null;
    if ($blogId > 0) {
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT media_type, year, month, tag_slug FROM {$this->table} WHERE blog_id=%d AND attachment_id=%d LIMIT 1", $blogId, $attId),
        ARRAY_A
      );
    }
    if (!$row) {
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT media_type, year, month, tag_slug FROM {$this->table} WHERE attachment_id=%d ORDER BY created_gmt DESC LIMIT 1", $attId),
        ARRAY_A
      );
    }

    if (!$row) return [];

    $where = "media_type=%s AND attachment_id<>%d";
    $params = [$row['media_type'], $attId];

    // Prefer same tag if available
    if (!empty($row['tag_slug'])) {
      $where .= " AND tag_slug=%s";
      $params[] = $row['tag_slug'];
    } else {
      // Otherwise same month
      if (!empty($row['year']) && !empty($row['month'])) {
        $where .= " AND year=%d AND month=%d";
        $params[] = (int)$row['year'];
        $params[] = (int)$row['month'];
      }
    }

    $sql = "SELECT blog_id, attachment_id, media_type, provider, created_gmt, title, alt, caption, url_full, url_thumb, poster_url, width, height, mime
            FROM {$this->table}
            WHERE {$where}
            ORDER BY created_gmt DESC
            LIMIT %d";
    $params[] = $limit;

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    $items = [];
    foreach ((array)$rows as $r) $items[] = $this->row_to_item($r);
    return $items;
  }

  private function row_to_item(array $r, $includeSources=false) {
    $blogId = (int)($r['blog_id'] ?? 0);
    $attId = (int)($r['attachment_id'] ?? 0);
    $mediaType = (string)($r['media_type'] ?? 'image');

    // Always provide a working thumbnail (prevents broken placeholders).
    $urlFull  = (string)($r['url_full'] ?? '');
    $urlThumb = (string)($r['url_thumb'] ?? '');
    if ($urlThumb === '' && $urlFull !== '') {
      $urlThumb = $urlFull;
    }

    // Keep URL structure the same; detail lookup is now resilient if blog_id is off.
    $base = defined('TBF_NMI_PHOTOFALL_BASE') ? (string)TBF_NMI_PHOTOFALL_BASE : 'photo';
    $href = home_url('/' . trim($base,'/') . '/' . ($mediaType === 'video' ? 'v' : 'i') . "/{$blogId}/{$attId}/");

    $item = [
      'blog_id' => $blogId,
      'attachment_id' => $attId,
      'media_type' => $mediaType,
      'provider' => (string)($r['provider'] ?? 'self'),
      'created_gmt' => (string)($r['created_gmt'] ?? ''),
      'title' => (string)($r['title'] ?? ''),
      'alt' => (string)($r['alt'] ?? ''),
      'caption' => (string)($r['caption'] ?? ''),
      'url_full' => $urlFull,
      'thumb_url' => $urlThumb,
      'poster_url' => (string)($r['poster_url'] ?? ''),
      'width' => (int)($r['width'] ?? 0),
      'height' => (int)($r['height'] ?? 0),
      'mime' => (string)($r['mime'] ?? ''),
      'href' => $href,
    ];

    if ($includeSources) {
      $item['content_url'] = (string)($r['content_url'] ?? '');
      $item['embed_url'] = (string)($r['embed_url'] ?? '');
      // Helpful for debugging without breaking anything:
      $item['_lookup'] = [
        'blog_id_used' => $blogId,
        'attachment_id_used' => $attId,
      ];
    }

    return $item;
  }
}
