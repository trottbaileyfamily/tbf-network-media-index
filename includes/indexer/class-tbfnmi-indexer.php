<?php
/**
 * File: includes/indexer/class-tbfnmi-indexer.php
 * Version: 6.6.25 (Single Site Compatibility)
 */
if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Indexer {
  private $db;
  private $table;

  public function __construct() {
    global $wpdb;
    $this->db = $wpdb;
    $this->table = $wpdb->base_prefix . 'tbfnmi_index';
  }

  public function table_name(){ return $this->table; }

  public function has_table() {
    $like = $this->db->esc_like($this->table);
    return ! empty($this->db->get_var($this->db->prepare("SHOW TABLES LIKE %s", $like)));
  }

  public function index_single_attachment( $post_id ) {
      // (Keep existing validation logic...)
      if ( get_post_type($post_id) !== 'attachment' ) return;
      if ( isset($_REQUEST['action']) && strpos($_REQUEST['action'], 'tbf_nmi') !== false ) return;

      if ( ! $this->has_table() ) $this->create_table();
      
      $post = get_post($post_id);
      $blog_id = get_current_blog_id(); 
      $row = $this->build_row($blog_id, $post);
      $this->upsert($row);
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
      KEY created_gmt (created_gmt)
    ) {$charset};";

    $usage_table = $this->db->base_prefix . 'tbfnmi_usage_map';
    $sql .= "\nCREATE TABLE {$usage_table} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      media_url VARCHAR(500) NOT NULL,
      blog_id BIGINT(20) UNSIGNED NOT NULL,
      post_id BIGINT(20) UNSIGNED NOT NULL,
      post_title TEXT NULL,
      permalink TEXT NULL,
      site_name VARCHAR(250) NULL,
      PRIMARY KEY  (id),
      KEY media_url (media_url(191)),
      KEY blog_post (blog_id, post_id)
    ) {$charset};";

    dbDelta($sql);
  }

  public function index_site_batch($blogId, array $args = []) {
    $blogId = (int)$blogId;
    $limit = max(50, min(2000, (int)($args['limit'] ?? 500)));
    $startAfter = max(0, (int)($args['start_after'] ?? 0));
    
    $this->create_table();

    $scanned = 0; $indexed = 0; $lastId = $startAfter;

    // Single Site Safety: Only switch if we are actually multisite
    $switched = false;
    if ( is_multisite() && get_current_blog_id() !== $blogId ) {
        switch_to_blog($blogId);
        $switched = true;
    }

    try {
      add_filter('posts_where', $where = function($sql) use ($startAfter) {
        global $wpdb;
        if ($startAfter > 0) $sql .= $wpdb->prepare(" AND {$wpdb->posts}.ID > %d ", $startAfter);
        return $sql;
      });
      
      $types = get_post_types(['public' => true], 'names');
      $types[] = 'attachment'; 
      
      $q = new WP_Query([
        'post_type' => array_values($types), 
        'post_status' => ['inherit', 'publish'],
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
        if ( $switched ) restore_current_blog();
        return ['scanned'=>0,'indexed'=>0,'last_id'=>$startAfter,'done'=>true];
      }

      foreach ($posts as $p) {
        $lastId = max($lastId, $p->ID);
        
        // SEO Crawler for all post types
        if ( $p->post_type !== 'attachment' ) {
            if ( class_exists('TBFNMI_SEO_Meta') ) {
                TBFNMI_SEO_Meta::sync_post_media_usage($p->ID, $p);
                $indexed++;
            }
            continue;
        }

        // Standard Indexing
        $attId = (int)$p->ID;
        $mime = (string)get_post_mime_type($attId);
        $isImage = (strpos($mime,'image/')===0);
        $isVideo = (strpos($mime,'video/')===0);
        $isAudio = (strpos($mime,'audio/')===0);

        if (!$isImage && !$isVideo && !$isAudio) continue;

        // Skip our own proxies to prevent loops
        $all_meta = get_post_meta($attId);
        $is_proxy = false;
        if (is_array($all_meta)) {
            foreach ($all_meta as $key => $val) {
                if (strpos($key, '_tbfnmi_proxy') !== false || strpos($key, '_tbfnmi_origin') !== false) {
                    $is_proxy = true; break;
                }
            }
        }
        if ($is_proxy) continue;

        $row = $this->build_row($blogId, $p);
        if ($this->upsert($row)) $indexed++;
      }
      $done = (count($posts) < $limit);

    } catch (Throwable $e) {
      if ( $switched ) restore_current_blog();
      return ['scanned'=>$scanned,'indexed'=>$indexed,'last_id'=>$lastId,'done'=>false,'error'=>$e->getMessage()];
    }

    if ( $switched ) restore_current_blog();
    return ['scanned'=>$scanned,'indexed'=>$indexed,'last_id'=>$lastId,'done'=>$done];
  }

  // (Include build_row, upsert, detect_provider methods from v6.6.14 here - unchanged)
  // ... [Omitted for brevity, assume identical to previous version] ...
  
  private function build_row($blogId, WP_Post $p) {
    // ... [Use the build_row logic from v6.6.14] ...
    // Re-inserting core logic for completeness in this file context:
    $attId = (int)$p->ID;
    $mime = (string)get_post_mime_type($attId);
    $mediaType = 'image';
    if (strpos($mime,'video/')===0) $mediaType = 'video';
    if (strpos($mime,'audio/')===0) $mediaType = 'audio';

    $title = (string)get_the_title($attId);
    $alt = (string)get_post_meta($attId, '_wp_attachment_image_alt', true);
    $caption = (string)$p->post_excerpt;
    $createdGmt = get_post_time('Y-m-d H:i:s', true, $attId);
    $updatedGmt = get_post_modified_time('Y-m-d H:i:s', true, $attId);
    $year = (int)get_post_time('Y', true, $attId);
    $month = (int)get_post_time('m', true, $attId);
    $fullUrl = (string)wp_get_attachment_url($attId);
    $mediumUrl = ''; $thumbUrl=''; $width=0; $height=0; $posterUrl=''; $contentUrl=''; $embedUrl='';

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
    
    return [
      'blog_id'=>(int)$blogId, 'attachment_id'=>$attId, 'media_type'=>$mediaType,
      'provider'=>'self', 'created_gmt'=>$createdGmt, 'updated_gmt'=>$updatedGmt,
      'year'=>$year, 'month'=>$month, 'title'=>$title, 'alt'=>$alt, 'caption'=>$caption,
      'url_full'=>$fullUrl, 'url_medium'=>$mediumUrl, 'url_thumb'=>$thumbUrl,
      'poster_url'=>$posterUrl, 'content_url'=>$contentUrl, 'embed_url'=>$embedUrl,
      'mime'=>$mime, 'width'=>$width, 'height'=>$height, 'tag_slug'=>'', 'tags_csv'=>''
    ];
  }

  private function upsert(array $row) {
    $where = ['blog_id'=>(int)$row['blog_id'], 'attachment_id'=>(int)$row['attachment_id']];
    $updated = $this->db->update($this->table, $row, $where);
    if ($updated === false) return false;
    if ($updated > 0) return true;
    return ($this->db->insert($this->table, $row) !== false);
  }
}