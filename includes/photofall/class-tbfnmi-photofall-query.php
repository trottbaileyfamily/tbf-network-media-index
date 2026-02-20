<?php
/**
 * File: includes/photofall/class-tbfnmi-photofall-query.php
 * Version: 6.0.6 (Cross-Site Single Item Fix)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Photofall_Query {

  public static function get_single($id, $blog_id = 0) {
      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfnmi_index';
      
      $id = (int)$id;
      $blog_id = (int)$blog_id;

      // Ensure table exists (failsafe)
      $like = $wpdb->esc_like($table);
      if ( empty($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like))) ) {
          return false;
      }

      // Query the Global Index Table
      $where = $wpdb->prepare("attachment_id = %d", $id);
      if ($blog_id > 0) {
          $where .= $wpdb->prepare(" AND blog_id = %d", $blog_id);
      }

      // Order by latest in case of fallback collision (if no blog_id was provided)
      $sql = "SELECT blog_id, attachment_id, media_type, title, caption, mime, url_full, url_medium, url_thumb, poster_url, content_url, width, height 
              FROM $table 
              WHERE $where 
              ORDER BY created_gmt DESC 
              LIMIT 1";
              
      $row = $wpdb->get_row($sql);

      // Failsafe: If not in index, try local WP Post (for extremely new uploads not yet indexed)
      if ( ! $row ) {
          $post = get_post($id);
          if ( ! $post || $post->post_type !== 'attachment' ) return false;
          
          $row = new stdClass();
          $row->attachment_id = $post->ID;
          $row->blog_id       = get_current_blog_id();
          $row->title         = $post->post_title;
          $row->caption       = $post->post_excerpt;
          $row->mime          = $post->post_mime_type;
          
          $mediaType = 'image';
          if (strpos($row->mime, 'video') !== false) $mediaType = 'video';
          if (strpos($row->mime, 'audio') !== false) $mediaType = 'audio';
          $row->media_type    = $mediaType;
          
          $row->url_full      = wp_get_attachment_url($post->ID);
          $row->url_medium    = '';
          $row->url_thumb     = '';
          $row->poster_url    = '';
          $row->content_url   = $row->url_full;
          $row->width         = 800;
          $row->height        = 600;
      }

      // Hydrate into an Object for the Templates
      $p = new stdClass();
      $p->ID = $row->attachment_id;
      $p->post_title = $row->title;
      $p->post_excerpt = $row->caption;
      $p->post_mime_type = $row->mime;
      $p->type = $row->media_type;
      $p->blog_id = $row->blog_id;

      if ($row->media_type === 'video' || $row->media_type === 'audio') {
          $p->tbf_url_full = $row->content_url ?: $row->url_full;
          $p->tbf_url_thumb = $row->poster_url ?: $row->url_thumb;
          
          if (empty($p->tbf_url_thumb)) {
              $icon = ($row->media_type === 'video') ? 'video.png' : 'audio.png';
              $p->tbf_url_thumb = includes_url('images/media/' . $icon);
          }
      } else {
          $p->tbf_url_full   = $row->url_full;
          $p->tbf_url_thumb  = $row->url_medium ?: $row->url_thumb;
          if ( empty($p->tbf_url_thumb) ) $p->tbf_url_thumb = $row->url_full;
      }

      $p->tbf_width  = (int)$row->width;
      $p->tbf_height = (int)$row->height;

      return $p;
  }

  public static function get_filter_data() {
      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfnmi_index';
      
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
    $table = $wpdb->base_prefix . 'tbfnmi_index';

    $settings = TBFNMI_Subsite_Settings::get_options();
    
    $defaults = [
      'allowed_types' => $settings['allowed_types'],
      'sort'          => $settings['default_sort'],
      'filter'        => 'all',
      'search'        => '',
      'year'          => '',
      'site_filter'   => '',
      'page'          => 1,
      'per_page'      => 50,
      'exclude'       => [],
      'source_sites'  => $settings['source_sites'],
      'show_frontend' => isset($settings['show_frontend']) ? $settings['show_frontend'] : 1,
    ];
    $args = wp_parse_args($args, $defaults);

    $where = ["1=1"];
    
    if ( ! empty($args['search']) ) {
        $like = '%' . $wpdb->esc_like( sanitize_text_field($args['search']) ) . '%';
        $where[] = $wpdb->prepare("(title LIKE %s OR caption LIKE %s OR tags_csv LIKE %s)", $like, $like, $like);
    }

    if ( ! empty($args['year']) ) {
        $where[] = $wpdb->prepare("year = %d", intval($args['year']));
    }

    if ( ! empty($args['site_filter']) ) {
        $where[] = $wpdb->prepare("blog_id = %d", intval($args['site_filter']));
    } elseif ( ! empty($args['source_sites']) ) {
        $site_ids = array_map('intval', $args['source_sites']);
        $where[] = "blog_id IN (" . implode(',', $site_ids) . ")";
    }

    $types = ($args['filter'] !== 'all') ? [$args['filter']] : $args['allowed_types'];
    $type_clauses = [];
    foreach ($types as $t) $type_clauses[] = $wpdb->prepare("media_type = %s", $t);
    if (!empty($type_clauses)) $where[] = "(" . implode(' OR ', $type_clauses) . ")";
    else $where[] = "1=0"; 

    if (!empty($args['exclude'])) {
        $exclude_ids = array_map('intval', $args['exclude']);
        $where[] = "attachment_id NOT IN (" . implode(',', $exclude_ids) . ")";
    }

    $orderby = "created_gmt DESC";
    if ($args['sort'] === 'date_asc') $orderby = "created_gmt ASC";
    if ($args['sort'] === 'random')   $orderby = "RAND()";

    $page = max(1, (int)$args['page']);
    $per_page = (int)$args['per_page'];
    $offset = ($page - 1) * $per_page;

    $where_sql = implode(' AND ', $where);
    
    $sql = "SELECT blog_id, attachment_id, media_type, title, caption, mime, url_full, url_medium, url_thumb, poster_url, content_url, width, height 
            FROM $table 
            WHERE $where_sql 
            ORDER BY $orderby 
            LIMIT $offset, $per_page";
            
    $results = $wpdb->get_results($sql);

    $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
    $total = $wpdb->get_var($count_sql);
    $max_pages = ceil($total / $per_page);

    $posts = [];
    foreach ($results as $row) {
        $p = new stdClass();
        $p->ID = $row->attachment_id;
        $p->post_title = $row->title;
        $p->post_excerpt = $row->caption;
        $p->post_mime_type = $row->mime;
        $p->type = $row->media_type;
        $p->blog_id = $row->blog_id;

        if ($row->media_type === 'video' || $row->media_type === 'audio') {
            $p->tbf_url_full = $row->content_url ?: $row->url_full;
            $p->tbf_url_thumb = $row->poster_url ?: $row->url_thumb;
            
            if (empty($p->tbf_url_thumb)) {
                $icon = ($row->media_type === 'video') ? 'video.png' : 'audio.png';
                $p->tbf_url_thumb = includes_url('images/media/' . $icon);
            }
        } else {
            $p->tbf_url_full   = $row->url_full;
            $p->tbf_url_thumb  = $row->url_medium ?: $row->url_thumb;
            if ( empty($p->tbf_url_thumb) ) $p->tbf_url_thumb = $row->url_full;
        }

        $p->tbf_width      = (int)$row->width;
        $p->tbf_height     = (int)$row->height;
        $posts[] = $p;
    }

    return ['posts' => $posts, 'max_pages' => $max_pages];
  }
}
