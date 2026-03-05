<?php
/**
 * File: includes/class-tbfbkm-ajax.php
 * Version: 7.0.1.0 (Document & Archive Icon Support)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_AJAX {

  public static function init() {
    add_action('wp_ajax_tbfbkm_list', [__CLASS__, 'list_items']);
    add_action('wp_ajax_nopriv_tbfbkm_list', [__CLASS__, 'list_items']); 
    
    // Load More Handler
    add_action('wp_ajax_tbfbkm_load_more', [__CLASS__, 'load_more']);
    add_action('wp_ajax_nopriv_tbfbkm_load_more', [__CLASS__, 'load_more']); 

    add_action('wp_ajax_tbfbkm_sites', [__CLASS__, 'sites']);
    
    // Core Actions
    add_action('wp_ajax_tbfbkm_proxy', [__CLASS__, 'proxy']);
    add_action('wp_ajax_tbfbkm_proxy_url', [__CLASS__, 'proxy_url']);
    add_action('wp_ajax_tbfbkm_set_featured_remote', [__CLASS__, 'set_featured_remote']);
    
    // Custom Audio Thumbnail Action
    add_action('wp_ajax_tbfbkm_set_audio_thumb', [__CLASS__, 'set_audio_thumb']);
    
    // Frontend Management
    add_action('wp_ajax_tbfbkm_frontend_upload', [__CLASS__, 'frontend_upload']);
    add_action('wp_ajax_tbfbkm_hide_media', [__CLASS__, 'hide_media']);
    add_action('wp_ajax_tbfbkm_delete_media', [__CLASS__, 'delete_media']);
    
    // Admin Tools & Dashboard Engine
    add_action('wp_ajax_tbfbkm_resolve_ids', [__CLASS__, 'resolve_ids']);
    add_action('wp_ajax_tbfbkm_wipe_index', [__CLASS__, 'wipe_index']);
    add_action('wp_ajax_tbfbkm_process_batch', [__CLASS__, 'process_batch']);
    
    // Princess Keilah Music
    add_action('wp_ajax_tbfbkm_get_all_audio_ids', [__CLASS__, 'get_all_audio_ids']);
    add_action('wp_ajax_tbfbkm_resolve_playlist', [__CLASS__, 'resolve_playlist']);
    add_action('wp_ajax_nopriv_tbfbkm_resolve_playlist', [__CLASS__, 'resolve_playlist']);
  }

  // ==========================================================================
  // 1. DASHBOARD INDEXER ENGINE
  // ==========================================================================

  public static function process_batch() {
      if ( ! current_user_can('manage_network_options') && ! current_user_can('manage_options') ) {
          wp_send_json_error(['message' => 'Forbidden'], 403);
      }

      $step = max(1, (int)($_POST['step'] ?? 1));
      $offset = max(0, (int)($_POST['offset'] ?? 0));
      $limit = 100; 

      $sites = is_multisite() ? get_sites(['number' => 1000, 'public' => 1, 'archived' => 0, 'spam' => 0, 'deleted' => 0]) : [ (object)['blog_id' => get_current_blog_id()] ];
      
      $total_sites = count($sites);
      $current_index = $step - 1;

      if ( $current_index >= $total_sites ) {
          wp_send_json_success(['progress' => 100, 'message' => 'Network Indexing Complete!', 'done' => true]);
      }

      $blog_id = (int)$sites[$current_index]->blog_id;
      
      if ( ! class_exists('TBFBKM_Indexer') ) {
          require_once plugin_dir_path(__FILE__) . 'indexer/class-tbfbkm-indexer.php';
      }
      
      $indexer = new TBFBKM_Indexer();
      $res = $indexer->index_site_batch($blog_id, ['limit' => $limit, 'start_after' => $offset]);

      if ( isset($res['error']) && !empty($res['error']) ) {
          wp_send_json_error(['message' => 'Indexer Error on Site ' . $blog_id . ': ' . $res['error']]);
      }

      $next_offset = $res['last_id'];
      $next_step = $step;
      
      if ( !empty($res['done']) ) {
          $next_step++;
          $next_offset = 0;
      }

      $progress = min(99, round(($current_index / $total_sites) * 100));
      
      wp_send_json_success([
          'progress' => $progress,
          'message' => "Scanning Site ID: {$blog_id}... (" . ($res['indexed'] ?? 0) . " items indexed)",
          'done' => false,
          'step' => $next_step,
          'offset' => $next_offset
      ]);
  }

  // ==========================================================================
  // 2. PRINCESS KEILAH MUSIC ENGINE
  // ==========================================================================

  public static function get_all_audio_ids() {
      if(!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);
      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfbkm_index';
      $ids = $wpdb->get_col("SELECT attachment_id FROM {$table} WHERE media_type = 'audio' ORDER BY created_gmt DESC");
      if ( empty($ids) ) wp_send_json_error(['message' => 'No audio found in network index. Run the Indexer first.']);
      wp_send_json_success($ids);
  }

  public static function resolve_playlist() {
      $ids_str = $_POST['ids'] ?? '';
      if ( empty($ids_str) ) wp_send_json_error();
      
      $raw_ids = explode(',', $ids_str);
      $tracks = [];
      $raw_ids = array_slice($raw_ids, 0, 500); 
      
      $master_id = (int) get_site_option('tbfbkm_master_controller_id', 0);
      if ( $master_id <= 0 ) $master_id = get_main_site_id();
      $current_id = get_current_blog_id();

      global $wpdb;
      $index_table = $wpdb->base_prefix . 'tbfbkm_index';

      foreach($raw_ids as $raw_id) {
          $raw_id = trim($raw_id);
          if (empty($raw_id)) continue;

          $blog_id = 0;
          $att_id = 0;

          if (strpos($raw_id, '-') !== false) {
              $parts = explode('-', $raw_id);
              $blog_id = (int)$parts[0];
              $att_id = (int)$parts[1];
          } else {
              $att_id = (int)$raw_id;
          }

          if (!$att_id) continue;
          
          $url = '';
          $title = '';

          if ($blog_id > 0) {
              $row = $wpdb->get_row($wpdb->prepare("SELECT url_full, title FROM {$index_table} WHERE blog_id = %d AND attachment_id = %d LIMIT 1", $blog_id, $att_id));
          } else {
              $row = $wpdb->get_row($wpdb->prepare("SELECT url_full, title FROM {$index_table} WHERE attachment_id = %d LIMIT 1", $att_id));
          }

          if ($row) {
              $url = $row->url_full;
              $title = $row->title;
          }

          if ( empty($url) ) {
              $local_url = wp_get_attachment_url($att_id);
              if ( $local_url ) {
                  $url = $local_url;
                  $title = get_the_title($att_id);
              } 
              elseif ( $master_id && $master_id !== $current_id ) {
                  switch_to_blog($master_id);
                  $remote_url = wp_get_attachment_url($att_id);
                  if ( $remote_url ) {
                      $url = $remote_url;
                      $title = get_the_title($att_id);
                  }
                  restore_current_blog();
              }
          }

          if($url) {
              $clean_title = trim($title);
              
              if (empty($clean_title) || is_numeric($clean_title)) {
                  $filename = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
                  $clean_title = ucwords(str_replace(['-', '_'], ' ', $filename));
              }

              $tracks[] = [ 
                  'id' => $raw_id, 
                  'url' => $url, 
                  'title' => $clean_title ?: 'Track ' . $att_id 
              ];
          }
      }
      
      if (empty($tracks)) {
         wp_send_json_error(['message' => 'Audio unavailable']);
      } else {
         wp_send_json_success($tracks);
      }
  }

  // ==========================================================================
  // 3. QUERY ENGINE (Slideshow & Photofall)
  // ==========================================================================

  public static function load_more() {
      $page = max(1, (int)($_POST['page'] ?? 1));
      $per  = 24; 
      $search = sanitize_text_field($_POST['search'] ?? '');
      $mime   = sanitize_text_field($_POST['filter'] ?? '');
      if($mime === 'all') $mime = ''; 
      
      $sort = sanitize_text_field($_POST['sort'] ?? '');
      $orderby = 'date_desc';
      if($sort === 'oldest') $orderby = 'date_asc';
      if($sort === 'random') $orderby = 'rand';

      $data = self::list_from_index_table($page, $per, $search, $mime, 0, $orderby);
      
      if ( !$data || empty($data['items']) ) {
          wp_send_json_success(['html' => '', 'max_pages' => 0]);
      }

      if ( ! class_exists('TBFBKM_Photofall_Templates') ) {
          require_once plugin_dir_path(__FILE__) . 'photofall/class-tbfbkm-photofall-templates.php';
      }

      ob_start();
      foreach($data['items'] as $item) {
          $post = new stdClass();
          $post->ID = $item['attachment_id'];
          $post->attachment_id = $item['attachment_id'];
          $post->blog_id = $item['blog_id'];
          $post->post_title = $item['title'];
          $post->title = $item['title']; 
          $post->post_excerpt = $item['caption']; 
          $post->caption = $item['caption']; 
          $post->type = $item['media_type'];
          $post->media_type = $item['media_type'];
          $post->tbf_url_full = $item['url'];
          $post->tbf_url_thumb = $item['thumb'];

          echo wp_kses(TBFBKM_Photofall_Templates::get_item_html($post), TBFBKM_Photofall_Templates::get_allowed_html());
      }
      $html = ob_get_clean();

      wp_send_json_success([
          'html' => $html,
          'max_pages' => $data['max_pages']
      ]);
  }

  public static function list_items() {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = max(1, min(200, (int)($_GET['per_page'] ?? 60)));
    $search = sanitize_text_field((string)($_GET['s'] ?? ''));
    $mime   = sanitize_text_field((string)($_GET['mime'] ?? ''));
    $originBlogId = isset($_GET['origin_blog_id']) ? (int)$_GET['origin_blog_id'] : 0;
    $orderby = sanitize_text_field((string)($_GET['orderby'] ?? 'date'));
    $include = sanitize_text_field((string)($_GET['include'] ?? ''));

    $fast = self::list_from_index_table($page, $per, $search, $mime, $originBlogId, $orderby, $include);
    
    if ( $fast !== null ) wp_send_json_success($fast);
    
    wp_send_json_success(['items' => [], 'total' => 0, 'max_pages' => 1]);
  }

  private static function list_from_index_table($page, $per, $search, $mime_filter, $originBlogId, $orderby, $include = '') {
    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfbkm_index';

    if ( !$wpdb->get_var("SHOW TABLES LIKE '{$table}'") ) return null;

    $where = "1=1";
    $params = [];

    $where .= " AND (url_full NOT LIKE %s)";
    $params[] = '%/vikinger/%';

    if ( !empty($include) ) {
        $raw_ids = explode(',', $include);
        $ids = array_filter(array_map('intval', $raw_ids));
        
        if ( !empty($ids) ) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $where .= " AND attachment_id IN ($placeholders)";
            $params = array_merge($params, $ids);
        } else {
            return ['items' => [], 'total' => 0];
        }
    } 
    else {
        if ( $originBlogId > 0 ) {
            $where .= " AND blog_id = %d";
            $params[] = $originBlogId;
        }

        if ( $mime_filter ) {
            if ( $mime_filter === 'image' ) { $where .= " AND media_type = 'image'"; } 
            elseif ( $mime_filter === 'video' ) { $where .= " AND media_type = 'video'"; } 
            elseif ( $mime_filter === 'audio' ) { $where .= " AND media_type = 'audio'"; } 
        }

        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (title LIKE %s OR alt LIKE %s OR caption LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
    }

    $totalSql = "SELECT COUNT(DISTINCT url_full) FROM {$table} WHERE {$where}";
    $total = (int)$wpdb->get_var( $wpdb->prepare($totalSql, $params) );
    $offset = ($page - 1) * $per;

    $order_sql = "ORDER BY created_gmt DESC";
    if ( $orderby === 'date_asc' ) $order_sql = "ORDER BY created_gmt ASC";
    if ( $orderby === 'rand' ) $order_sql = "ORDER BY RAND()";

    $sql = "SELECT 
                MIN(blog_id) as blog_id, MAX(attachment_id) as attachment_id, MAX(title) as title, MAX(caption) as caption, MAX(mime) as mime, MAX(media_type) as media_type, 
                url_full, MAX(url_medium) as url_medium, MAX(url_thumb) as url_thumb, MAX(poster_url) as poster_url, MIN(created_gmt) as created_gmt,
                MAX(width) as width, MAX(height) as height
            FROM {$table} WHERE {$where} GROUP BY url_full {$order_sql} LIMIT %d OFFSET %d";

    $final_params = array_merge($params, [$per, $offset]);
    $rows = $wpdb->get_results( $wpdb->prepare($sql, $final_params), ARRAY_A );

    $items = [];
    foreach ((array)$rows as $r) {
      $mime = $r['mime'] ?? '';
      $thumb = $r['url_thumb'] ?: ($r['poster_url'] ?: ($r['url_medium'] ?: $r['url_full']));
      
      // Dynamic Icon Resolution for Non-Visual Media
      if (strpos($mime, 'audio/') === 0) {
          if (empty($r['poster_url']) || preg_match('/\.(mp3|wav|ogg|flac|m4a|aac)$/i', $thumb)) {
              $thumb = includes_url('images/media/audio.png');
          }
      } elseif (strpos($mime, 'video/') === 0) {
          if (empty($r['poster_url']) && preg_match('/\.(mp4|webm|mov|avi)$/i', $thumb)) {
              $thumb = includes_url('images/media/video.png');
          }
      } elseif (strpos($mime, 'application/zip') !== false || strpos($mime, 'x-gzip') !== false || strpos($mime, 'x-rar') !== false) {
          $thumb = includes_url('images/media/archive.png');
      } elseif (strpos($mime, 'application/pdf') !== false || strpos($mime, 'application/msword') !== false || strpos($mime, 'application/vnd.') !== false || strpos($mime, 'text/') === 0) {
          $thumb = includes_url('images/media/document.png');
      } elseif (strpos($mime, 'image/') !== 0) {
          $thumb = includes_url('images/media/default.png');
      }

      $items[] = [
        'blog_id' => (int)$r['blog_id'], 
        'attachment_id' => (int)$r['attachment_id'], 
        'title' => (string)($r['title'] ?? ''), 
        'caption' => (string)($r['caption'] ?? ''), 
        'url' => (string)($r['url_full'] ?? ''), 
        'thumb' => (string)$thumb, 
        'mime' => (string)$mime, 
        'media_type' => (string)($r['media_type'] ?? ''), 
        'width' => (int)($r['width'] ?? 800), 
        'height' => (int)($r['height'] ?? 800),
      ];
    }

    return ['items' => $items, 'total' => $total, 'max_pages' => $per > 0 ? (int)ceil($total / $per) : 1, 'source' => 'index_table'];
  }

  // ==========================================================================
  // 4. FRONTEND UPLOADER 
  // ==========================================================================
  
  public static function frontend_upload() {
      check_ajax_referer('tbfbkm_frontend', 'security');
      @set_time_limit(0);

      $opts = get_option('tbfbkm_photofall_options', []);
      $is_authorized = false;
      if ( current_user_can('manage_options') || is_super_admin() ) $is_authorized = true;
      elseif ( !empty($opts['enable_frontend_upload']) ) {
          $user = wp_get_current_user();
          $allowed = !empty($opts['upload_roles']) ? $opts['upload_roles'] : ['administrator'];
          if ( !empty(array_intersect($allowed, $user->roles)) ) $is_authorized = true;
      }

      if ( !$is_authorized ) wp_send_json_error(['message' => 'Not authorized'], 403);
      if ( empty($_FILES['tbfbkm_media']) ) wp_send_json_error(['message' => 'No files'], 400);

      require_once(ABSPATH . 'wp-admin/includes/image.php');
      require_once(ABSPATH . 'wp-admin/includes/file.php');
      require_once(ABSPATH . 'wp-admin/includes/media.php');

      $title = sanitize_text_field($_POST['tbfbkm_title'] ?? '');
      $desc  = sanitize_textarea_field($_POST['tbfbkm_description'] ?? '');
      $files = $_FILES['tbfbkm_media'];
      $uploaded_ids = [];

      if ( is_array($files['name']) ) {
          foreach ($files['name'] as $key => $value) {
              if ($files['name'][$key]) {
                  $_FILES['tbf_single_upload'] = [
                      'name' => $files['name'][$key], 'type' => $files['type'][$key],
                      'tmp_name' => $files['tmp_name'][$key], 'error' => $files['error'][$key],
                      'size' => $files['size'][$key]
                  ];
                  $this_title = $title ?: pathinfo($files['name'][$key], PATHINFO_FILENAME);
                  $attachment_id = media_handle_upload('tbf_single_upload', 0, [
                      'post_title' => $this_title, 'post_content' => $desc, 'post_excerpt' => $desc
                  ]);

                  if ( !is_wp_error($attachment_id) ) {
                      $uploaded_ids[] = $attachment_id;
                      if ( class_exists('TBFBKM_Indexer') ) {
                          $indexer = new TBFBKM_Indexer();
                          $indexer->index_single_attachment($attachment_id);
                      }
                  }
              }
          }
      }

      if ( empty($uploaded_ids) ) wp_send_json_error(['message' => 'Upload failed.']);
      wp_send_json_success(['message' => 'Upload successful', 'ids' => $uploaded_ids]);
  }

  public static function hide_media() {
      if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Forbidden'], 403);
      check_ajax_referer('tbfbkm_admin_action', 'nonce');
      $att_id = (int)($_POST['attachment_id'] ?? 0);
      $hidden = get_option('tbfbkm_hidden_media', []);
      if ( in_array($att_id, $hidden) ) $hidden = array_diff($hidden, [$att_id]);
      else $hidden[] = $att_id;
      update_option('tbfbkm_hidden_media', $hidden);
      wp_send_json_success();
  }

  public static function delete_media() {
      if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Forbidden'], 403);
      check_ajax_referer('tbfbkm_admin_action', 'nonce');
      $att_id = (int)($_POST['attachment_id'] ?? 0);
      if ( wp_delete_attachment($att_id, true) ) wp_send_json_success();
      else wp_send_json_error(['message' => 'Delete failed.']);
  }

  // ==========================================================================
  // 5. ADMIN TOOLS
  // ==========================================================================

  public static function wipe_index() {
      if(!current_user_can('manage_options')) wp_send_json_error();
      check_ajax_referer('tbfbkm_wipe_nonce', 'nonce');
      global $wpdb;
      $wpdb->query("TRUNCATE TABLE {$wpdb->base_prefix}tbfbkm_index");
      $wpdb->query("TRUNCATE TABLE {$wpdb->base_prefix}tbfbkm_usage_map");
      delete_option('tbfbkm_db_version');
      wp_send_json_success(['message' => 'Index wiped.']);
  }

  public static function resolve_ids() {
      if(!current_user_can('manage_options')) wp_send_json_error();
      $ids = explode(',', sanitize_text_field($_POST['ids'] ?? ''));
      $urls = [];
      global $wpdb;
      $index_table = $wpdb->base_prefix . 'tbfbkm_index';
      foreach($ids as $id) {
          $id = (int)trim($id);
          if(!$id) continue;
          $u = wp_get_attachment_url($id);
          if(!$u) $u = $wpdb->get_var($wpdb->prepare("SELECT url_full FROM {$index_table} WHERE attachment_id = %d LIMIT 1", $id));
          if($u) $urls[] = $u;
      }
      wp_send_json_success($urls);
  }

  public static function sites() {
    if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
    if ( ! is_multisite() ) {
        wp_send_json_success(['sites' => [['blog_id' => 1, 'name' => get_bloginfo('name')]]]);
    }
    $out = [];
    $sites = get_sites(['number' => 1000, 'public' => 1]); 
    foreach ($sites as $s) {
      $bid = (int)$s->blog_id;
      $name = get_blog_option($bid, 'blogname');
      $out[] = ['blog_id' => $bid, 'name' => $name ? $name : ('Site ' . $bid)];
    }
    wp_send_json_success(['sites' => $out]);
  }

  // ==========================================================================
  // 6. PROXY, FEATURED & CUSTOM THUMBNAILS
  // ==========================================================================

  public static function set_audio_thumb() {
      if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
      
      $audio_blog_id = (int)($_POST['audio_blog_id'] ?? 0);
      $audio_id      = (int)($_POST['audio_id'] ?? 0);
      $thumb_url     = esc_url_raw($_POST['thumb_url'] ?? '');

      if ( ! $audio_id || ! $thumb_url ) {
          wp_send_json_error(['message' => 'Missing data'], 400);
      }

      if ( is_multisite() ) switch_to_blog($audio_blog_id);
      update_post_meta($audio_id, '_tbfbkm_custom_thumb_url', $thumb_url);
      if ( is_multisite() ) restore_current_blog();

      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfbkm_index';
      $wpdb->update(
          $table,
          ['poster_url' => $thumb_url, 'url_thumb' => $thumb_url],
          ['blog_id' => $audio_blog_id, 'attachment_id' => $audio_id],
          ['%s', '%s'],
          ['%d', '%d']
      );

      wp_send_json_success(['message' => 'Thumbnail updated', 'thumb_url' => $thumb_url]);
  }

  public static function proxy() {
    if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
    $originBlogId = (int)($_POST['origin_blog_id'] ?? 0);
    $originAttId  = (int)($_POST['origin_attachment_id'] ?? 0);
    $url          = esc_url_raw($_POST['url'] ?? '');
    $title        = sanitize_text_field($_POST['title'] ?? 'Media');
    $mime         = sanitize_text_field($_POST['mime'] ?? '');

    if ( ! $url ) wp_send_json_error(['message' => 'Missing remote URL payload.'], 400);

    if (empty($mime)) $mime = 'image/jpeg';

    if ( class_exists('TBFBKM_Network_Media_Index') ) remove_action('add_attachment', ['TBFBKM_Network_Media_Index', 'auto_index_attachment']);

    $localId = TBFBKM_Proxy::create_proxy_attachment([
      'origin_blog_id' => $originBlogId, 
      'origin_attachment_id' => $originAttId, 
      'url' => $url,
      'title' => $title ?: 'Media', 
      'mime' => $mime, 
      'source' => 'network',
    ]);

    if ( class_exists('TBFBKM_Network_Media_Index') ) add_action('add_attachment', ['TBFBKM_Network_Media_Index', 'auto_index_attachment']);

    if ( is_wp_error($localId) ) wp_send_json_error(['message' => $localId->get_error_message()], 500);
    wp_send_json_success(['local_attachment_id' => (int)$localId, 'url' => $url, 'mime' => $mime]);
  }

  public static function proxy_url() {
    if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
    $url = esc_url_raw((string)($_POST['url'] ?? ''));
    if ( !$url ) wp_send_json_error(['message' => 'Missing URL'], 400);
    
    $title = sanitize_text_field((string)($_POST['title'] ?? 'Media'));
    $mime = sanitize_text_field((string)($_POST['mime'] ?? 'image/jpeg'));
    $source = sanitize_key((string)($_POST['source'] ?? 'external'));

    if ( class_exists('TBFBKM_Network_Media_Index') ) remove_action('add_attachment', ['TBFBKM_Network_Media_Index', 'auto_index_attachment']);
    
    $localId = TBFBKM_Proxy::create_proxy_attachment([
      'origin_blog_id' => 0, 'origin_attachment_id' => 0, 
      'url' => $url, 'title' => $title, 'mime' => $mime, 'source' => $source
    ]);
    
    if ( class_exists('TBFBKM_Network_Media_Index') ) add_action('add_attachment', ['TBFBKM_Network_Media_Index', 'auto_index_attachment']);

    if ( is_wp_error($localId) ) wp_send_json_error(['message' => $localId->get_error_message()], 500);
    wp_send_json_success(['local_attachment_id' => (int)$localId, 'url' => $url, 'mime' => $mime]);
  }

  public static function set_featured_remote() {
    if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId <= 0 || !current_user_can('edit_post', $postId)) wp_send_json_error(['message' => 'Cannot edit post'], 403);

    $url = esc_url_raw(html_entity_decode((string)($_POST['url'] ?? '')));
    if (!$url) wp_send_json_error(['message' => 'Missing url'], 400);
    
    $mime = sanitize_text_field((string)($_POST['mime'] ?? 'image/jpeg'));
    $type = sanitize_key((string)($_POST['type'] ?? 'image'));

    update_post_meta($postId, '_tbfbkm_featured_url', $url);
    update_post_meta($postId, '_tbfbkm_featured_mime', $mime);
    update_post_meta($postId, '_tbfbkm_featured_type', $type);

    $pid = (int) TBFBKM_Placeholder::get_id();
    if ($pid > 0) update_post_meta($postId, '_thumbnail_id', $pid);

    clean_post_cache($postId);
    wp_send_json_success(['post_id' => $postId, 'placeholder_id' => $pid, 'url' => $url]);
  }
}