<?php
/**
 * File: includes/indexer/class-tbf-nmi-indexer.php
 * Version: 4.0.0
 *
 * Photofall Indexer: builds {$wpdb->base_prefix}tbf_nmi_index
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Indexer {
  private $db;
  private $table;

  public function __construct() {
    global $wpdb;
    $this->db = $wpdb;
    $this->table = $wpdb->base_prefix . 'tbf_nmi_index';
  }

  public function table_name(){ return $this->table; }

  public function has_table() {
    $like = $this->db->esc_like($this->table);
    return ! empty($this->db->get_var($this->db->prepare("SHOW TABLES LIKE %s", $like)));
  }

  public function create_table() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $this->db->get_charset_collate();
    $sql = "CREATE TABLE {$this->table} (
      blog_id BIGINT(20) UNSIGNED NOT NULL,
      attachment_id BIGINT(20) UNSIGNED NOT NULL,
      media_type VARCHAR(20) NOT NULL DEFAULT 'image',
      provider VARCHAR(20) NOT NULL DEFAULT 'self',
      created_gmt DATETIME NULL,
      updated_gmt DATETIME NULL,
      year SMALLINT(4) UNSIGNED NOT NULL DEFAULT 0,
      month TINYINT(2) UNSIGNED NOT NULL DEFAULT 0,
      title TEXT NULL,
      alt TEXT NULL,
      caption TEXT NULL,
      url_full TEXT NULL,
      url_medium TEXT NULL,
      url_thumb TEXT NULL,
      poster_url TEXT NULL,
      content_url TEXT NULL,
      embed_url TEXT NULL,
      mime VARCHAR(120) NULL,
      width INT(11) NOT NULL DEFAULT 0,
      height INT(11) NOT NULL DEFAULT 0,
      tag_slug VARCHAR(200) NULL,
      tags_csv TEXT NULL,
      PRIMARY KEY  (blog_id, attachment_id),
      KEY media_type (media_type),
      KEY provider (provider),
      KEY created_gmt (created_gmt),
      KEY year (year),
      KEY month (month),
      KEY tag_slug (tag_slug)
    ) {$charset};";
    dbDelta($sql);
  }

  public function index_site_batch($blogId, array $args = []) {
    $blogId = (int)$blogId;
    $limit = max(50, min(2000, (int)($args['limit'] ?? 500)));
    $startAfter = max(0, (int)($args['start_after'] ?? 0));
    $images = array_key_exists('images',$args) ? (bool)$args['images'] : true;
    $videos = array_key_exists('videos',$args) ? (bool)$args['videos'] : true;

    if ( ! $this->has_table() ) $this->create_table();

    $scanned = 0; $indexed = 0; $lastId = $startAfter;

    if ( is_multisite() ) switch_to_blog($blogId);

    try {
      add_filter('posts_where', $where = function($sql) use ($startAfter) {
        global $wpdb;
        if ($startAfter > 0) $sql .= $wpdb->prepare(" AND {$wpdb->posts}.ID > %d ", $startAfter);
        return $sql;
      });

      $q = new WP_Query([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => $limit,
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
        'cache_results' => false,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
      ]);

      remove_filter('posts_where', $where);

      $posts = (array)$q->posts;
      $scanned = count($posts);
      if (!$posts) {
        if ( is_multisite() ) restore_current_blog();
        return ['scanned'=>0,'indexed'=>0,'last_id'=>$startAfter,'done'=>true];
      }

      foreach ($posts as $p) {
        $attId = (int)$p->ID;
        $lastId = max($lastId, $attId);

        $mime = (string)get_post_mime_type($attId);
        $isImage = (strpos($mime,'image/')===0);
        $isVideo = (strpos($mime,'video/')===0);

        if (($isImage && ! $images) || ($isVideo && ! $videos)) continue;
        if (!$isImage && !$isVideo) continue;

        $row = $this->build_row($blogId, $p);
        if ($this->upsert($row)) $indexed++;
      }

      $done = (count($posts) < $limit);

    } catch (Throwable $e) {
      if ( is_multisite() ) restore_current_blog();
      return ['scanned'=>$scanned,'indexed'=>$indexed,'last_id'=>$lastId,'done'=>false,'error'=>$e->getMessage()];
    }

    if ( is_multisite() ) restore_current_blog();
    return ['scanned'=>$scanned,'indexed'=>$indexed,'last_id'=>$lastId,'done'=>$done];
  }

  private function build_row($blogId, WP_Post $p) {
    $attId = (int)$p->ID;

    $mime = (string)get_post_mime_type($attId);
    $mediaType = (strpos($mime,'video/')===0) ? 'video' : 'image';

    $title = (string)get_the_title($attId);
    $alt = (string)get_post_meta($attId, '_wp_attachment_image_alt', true);
    $caption = (string)$p->post_excerpt;

    $createdGmt = get_post_time('Y-m-d H:i:s', true, $attId);
    $updatedGmt = get_post_modified_time('Y-m-d H:i:s', true, $attId);
    $year = (int)get_post_time('Y', true, $attId);
    $month = (int)get_post_time('m', true, $attId);

    $fullUrl = (string)wp_get_attachment_url($attId);
    $mediumUrl = ''; $thumbUrl=''; $width=0; $height=0;
    $posterUrl=''; $contentUrl=''; $embedUrl='';

    if ($mediaType === 'image') {
      $full = wp_get_attachment_image_src($attId, 'full');
      $med  = wp_get_attachment_image_src($attId, 'medium');
      $thumb= wp_get_attachment_image_src($attId, 'thumbnail');
      if (is_array($full)) { $fullUrl = $full[0] ?: $fullUrl; $width=(int)($full[1]??0); $height=(int)($full[2]??0); }
      if (is_array($med))  { $mediumUrl = $med[0] ?: ''; }
      if (is_array($thumb)){ $thumbUrl  = $thumb[0] ?: ''; }
    } else {
      $contentUrl = $fullUrl;
      $posterId = (int)get_post_thumbnail_id($attId);
      if ($posterId) {
        $t = wp_get_attachment_image_src($posterId, 'large');
        if (is_array($t) && !empty($t[0])) $posterUrl = $t[0];
        $tt = wp_get_attachment_image_src($posterId, 'thumbnail');
        if (is_array($tt) && !empty($tt[0])) $thumbUrl = $tt[0];
      }
    }

    $embedUrl = (string)get_post_meta($attId, '_tbf_nmi_embed_url', true);
    $provider = 'self';
    if ($embedUrl) {
      $provider = $this->detect_provider($embedUrl);
      $contentUrl = '';
      if (!$posterUrl) $posterUrl = $thumbUrl;
    }

    $tagSlug = (string)get_post_meta($attId, '_tbf_nmi_tag_slug', true);
    $tagsCsv = (string)get_post_meta($attId, '_tbf_nmi_tags_csv', true);

    return [
      'blog_id'=>(int)$blogId,
      'attachment_id'=>$attId,
      'media_type'=>$mediaType,
      'provider'=>$provider,
      'created_gmt'=>$createdGmt ?: null,
      'updated_gmt'=>$updatedGmt ?: null,
      'year'=>$year>0?$year:0,
      'month'=>$month>0?$month:0,
      'title'=>$title ?: null,
      'alt'=>$alt ?: null,
      'caption'=>$caption ?: null,
      'url_full'=>$fullUrl ?: null,
      'url_medium'=>$mediumUrl ?: null,
      'url_thumb'=>$thumbUrl ?: null,
      'poster_url'=>$posterUrl ?: null,
      'content_url'=>$contentUrl ?: null,
      'embed_url'=>$embedUrl ?: null,
      'mime'=>$mime ?: null,
      'width'=>$width,
      'height'=>$height,
      'tag_slug'=>$tagSlug ? sanitize_title($tagSlug) : null,
      'tags_csv'=>$tagsCsv ?: null,
    ];
  }

  private function upsert(array $row) {
    $where = ['blog_id'=>(int)$row['blog_id'], 'attachment_id'=>(int)$row['attachment_id']];
    $updated = $this->db->update($this->table, $row, $where);
    if ($updated === false) return false;
    if ($updated > 0) return true;
    return ($this->db->insert($this->table, $row) !== false);
  }

  private function detect_provider($url) {
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (strpos($host,'youtube.com')!==false || strpos($host,'youtu.be')!==false) return 'youtube';
    if (strpos($host,'vimeo.com')!==false) return 'vimeo';
    return $host ? 'other' : 'self';
  }
}
