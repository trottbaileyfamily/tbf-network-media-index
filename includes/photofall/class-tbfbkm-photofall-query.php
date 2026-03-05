<?php
/**
 * File: includes/photofall/class-tbfbkm-photofall-query.php
 * Version: 6.2.5 (URL-Based De-duplication & Strict Grouping)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Photofall_Query {

  public static function get_single($id, $blog_id = 0) {
      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfbkm_index';
      $id = (int)$id; $blog_id = (int)$blog_id;

      $like = $wpdb->esc_like($table);
      if ( empty($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like))) ) return false;

      $where = "attachment_id = %d"; $prepare_values = [$id];
      if ($blog_id > 0) { $where .= " AND blog_id = %d"; $prepare_values[] = $blog_id; }

      $sql = "SELECT blog_id, attachment_id, media_type, title, caption, mime, url_full, url_medium, url_thumb, poster_url, content_url, width, height 
              FROM $table WHERE $where ORDER BY created_gmt DESC LIMIT 1";
      $row = $wpdb->get_row( $wpdb->prepare($sql, $prepare_values) );

      if ( ! $row ) {
          $post = get_post($id);
          if ( ! $post || $post->post_type !== 'attachment' ) return false;
          
          $row = new stdClass();
          $row->attachment_id = $post->ID; $row->blog_id = get_current_blog_id();
          $row->title = $post->post_title; $row->caption = $post->post_excerpt;
          $row->mime = $post->post_mime_type;
          
          $mediaType = 'image';
          if (strpos($row->mime, 'video') !== false) $mediaType = 'video';
          if (strpos($row->mime, 'audio') !== false) $mediaType = 'audio';
          $row->media_type = $mediaType;
          
          $row->url_full = wp_get_attachment_url($post->ID); $row->url_medium = ''; $row->url_thumb = '';
          $row->poster_url = ''; $row->content_url = $row->url_full; $row->width = 800; $row->height = 600;
      }

      $p = new stdClass();
      $p->ID = $row->attachment_id; $p->post_title = $row->title; $p->post_excerpt = $row->caption;
      $p->post_mime_type = $row->mime; $p->type = $row->media_type; $p->blog_id = $row->blog_id;

      if ($row->media_type === 'video' || $row->media_type === 'audio') {
          $p->tbf_url_full = $row->content_url ?: $row->url_full;
          $p->tbf_url_thumb = $row->poster_url ?: $row->url_thumb;
          if (empty($p->tbf_url_thumb)) {
              $icon = ($row->media_type === 'video') ? 'video.png' : 'audio.png';
              $p->tbf_url_thumb = includes_url('images/media/' . $icon);
          }
      } else {
          $p->tbf_url_full = $row->url_full; $p->tbf_url_thumb = $row->url_medium ?: $row->url_thumb;
          if ( empty($p->tbf_url_thumb) ) $p->tbf_url_thumb = $row->url_full;
      }

      $p->tbf_width = (int)$row->width; $p->tbf_height = (int)$row->height;
      return $p;
  }

  public static function get_filter_data() {
      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfbkm_index';
      $years = $wpdb->get_col("SELECT DISTINCT year FROM $table WHERE year > 0 ORDER BY year DESC");
      $site_ids = $wpdb->get_col("SELECT DISTINCT blog_id FROM $table");
      $sites = [];
      foreach ($site_ids as $id) {
          $name = get_blog_option($id, 'blogname');
          if ($name) $sites[$id] = $name;
      }
      asort($sites);
      return ['years' => $years, 'sites' => $sites];
  }

  public static function get_media($args = []) {
    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfbkm_index';
    $settings = TBFBKM_Subsite_Settings::get_options();
    
    $defaults = [
      'allowed_types' => $settings['allowed_types'],
      'sort'          => $settings['default_sort'],
      'filter'        => 'all',
      'source'        => 'all', 
      'search'        => '',
      'year'          => '',
      'site_filter'   => '',
      'page'          => 1,
      'per_page'      => 50,
      'exclude'       => [],
      'source_sites'  => $settings['source_sites'],
    ];
    $args = wp_parse_args($args, $defaults);

    $where = ["1=1"];
    $prepare_values = [];
    
    // UGLY THUMBNAIL SWEEPER
    $where[] = "(url_full NOT LIKE %s OR url_full NOT REGEXP %s)";
    $prepare_values[] = '%/vikinger/%';
    $prepare_values[] = '-[0-9]+x[0-9]+[^/]*[.][a-zA-Z0-9]+$';

    // SOURCE DROPDOWN: Frontend vs Backend
    if ( ! empty($args['source']) && $args['source'] !== 'all' ) {
        if ( $args['source'] === 'frontend' ) {
            $where[] = "url_full LIKE %s";
            $prepare_values[] = '%/vikinger/%';
        } elseif ( $args['source'] === 'backend' ) {
            $where[] = "url_full NOT LIKE %s";
            $prepare_values[] = '%/vikinger/%';
        }
    }

    if ( ! empty($args['search']) ) {
        $like = '%' . $wpdb->esc_like( sanitize_text_field($args['search']) ) . '%';
        $where[] = "(title LIKE %s OR caption LIKE %s OR tags_csv LIKE %s)";
        array_push($prepare_values, $like, $like, $like);
    }

    if ( ! empty($args['year']) ) {
        $where[] = "year = %d"; $prepare_values[] = intval($args['year']);
    }

    if ( ! empty($args['site_filter']) ) {
        $where[] = "blog_id = %d"; $prepare_values[] = intval($args['site_filter']);
    } elseif ( ! empty($args['source_sites']) ) {
        $site_ids = array_map('intval', $args['source_sites']);
        $placeholders = implode( ', ', array_fill( 0, count( $site_ids ), '%d' ) );
        $where[] = "blog_id IN ( $placeholders )";
        $prepare_values = array_merge($prepare_values, $site_ids);
    }

    $types = ($args['filter'] !== 'all') ? [$args['filter']] : $args['allowed_types'];
    if ( ! empty($types) ) {
        $placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
        $where[] = "media_type IN ( $placeholders )";
        $prepare_values = array_merge($prepare_values, $types);
    } else {
        $where[] = "1=0"; 
    }

    if (!empty($args['exclude'])) {
        $exclude_ids = array_map('intval', $args['exclude']);
        $placeholders = implode( ', ', array_fill( 0, count( $exclude_ids ), '%d' ) );
        $where[] = "attachment_id NOT IN ( $placeholders )";
        $prepare_values = array_merge($prepare_values, $exclude_ids);
    }

    $orderby = "created_gmt DESC";
    if ($args['sort'] === 'date_asc') $orderby = "created_gmt ASC";
    if ($args['sort'] === 'random')   $orderby = "RAND()";

    $page = max(1, (int)$args['page']);
    $per_page = (int)$args['per_page'];
    $offset = ($page - 1) * $per_page;

    $where_sql = implode(' AND ', $where);
    
    // COUNT FIX: Count distinct URLs to fix pagination math
    $count_sql = "SELECT COUNT(DISTINCT url_full) FROM $table WHERE $where_sql";
    if ( ! empty($prepare_values) ) {
        $total = $wpdb->get_var( $wpdb->prepare($count_sql, $prepare_values) );
    } else {
        $total = $wpdb->get_var( $count_sql );
    }
    
    $max_pages = ceil($total / $per_page);

    // DEDUPLICATION FIX: Group by URL to eliminate duplicate attachments. 
    // Uses MAX() to ensure strict SQL mode compatibility.
    $sql = "SELECT 
                MAX(blog_id) as blog_id, 
                MAX(attachment_id) as attachment_id, 
                MAX(media_type) as media_type, 
                MAX(title) as title, 
                MAX(caption) as caption, 
                MAX(mime) as mime, 
                url_full, 
                MAX(url_medium) as url_medium, 
                MAX(url_thumb) as url_thumb, 
                MAX(poster_url) as poster_url, 
                MAX(content_url) as content_url, 
                MAX(width) as width, 
                MAX(height) as height, 
                MAX(created_gmt) as created_gmt 
            FROM $table 
            WHERE $where_sql 
            GROUP BY url_full 
            ORDER BY $orderby 
            LIMIT %d, %d";
            
    $prepare_values[] = $offset; $prepare_values[] = $per_page;
    $results = $wpdb->get_results( $wpdb->prepare($sql, $prepare_values) );

    $posts = [];
    foreach ($results as $row) {
        $p = new stdClass();
        $p->ID = $row->attachment_id; $p->post_title = $row->title; $p->post_excerpt = $row->caption;
        $p->post_mime_type = $row->mime; $p->type = $row->media_type; $p->blog_id = $row->blog_id;

        if ($row->media_type === 'video' || $row->media_type === 'audio') {
            $p->tbf_url_full = $row->content_url ?: $row->url_full;
            $p->tbf_url_thumb = $row->poster_url ?: $row->url_thumb;
            if (empty($p->tbf_url_thumb)) {
                $icon = ($row->media_type === 'video') ? 'video.png' : 'audio.png';
                $p->tbf_url_thumb = includes_url('images/media/' . $icon);
            }
        } else {
            $p->tbf_url_full = $row->url_full; $p->tbf_url_thumb = $row->url_medium ?: $row->url_thumb;
            if ( empty($p->tbf_url_thumb) ) $p->tbf_url_thumb = $row->url_full;
        }

        $p->tbf_width = (int)$row->width; $p->tbf_height = (int)$row->height; $posts[] = $p;
    }
    return ['posts' => $posts, 'max_pages' => $max_pages];
  }
}
