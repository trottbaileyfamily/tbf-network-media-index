<?php
/**
 * File: includes/class-tbfnmi-ajax.php
 * Version: 6.9.5 (Full Code Restoration - No Stubs)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_AJAX {

  public static function init() {
    // Viewer Actions
    add_action('wp_ajax_tbfnmi_list', [__CLASS__, 'list_items']);
    add_action('wp_ajax_nopriv_tbfnmi_list', [__CLASS__, 'list_items']); 
    add_action('wp_ajax_tbfnmi_sites', [__CLASS__, 'sites']);
    
    // Core Actions (Proxy/Insert)
    add_action('wp_ajax_tbfnmi_proxy', [__CLASS__, 'proxy']);
    add_action('wp_ajax_tbfnmi_proxy_url', [__CLASS__, 'proxy_url']);
    add_action('wp_ajax_tbfnmi_set_featured_remote', [__CLASS__, 'set_featured_remote']);
    
    // Frontend Management
    add_action('wp_ajax_tbfnmi_frontend_upload', [__CLASS__, 'frontend_upload']);
    add_action('wp_ajax_tbfnmi_hide_media', [__CLASS__, 'hide_media']);
    add_action('wp_ajax_tbfnmi_delete_media', [__CLASS__, 'delete_media']);
    
    // Admin Tools
    add_action('wp_ajax_tbfnmi_resolve_ids', [__CLASS__, 'resolve_ids']);
    add_action('wp_ajax_tbfnmi_wipe_index', [__CLASS__, 'wipe_index']);
    
    // Queen Keilah Music
    add_action('wp_ajax_tbfnmi_get_all_audio_ids', [__CLASS__, 'get_all_audio_ids']);
    add_action('wp_ajax_tbfnmi_resolve_playlist', [__CLASS__, 'resolve_playlist']);
    add_action('wp_ajax_nopriv_tbfnmi_resolve_playlist', [__CLASS__, 'resolve_playlist']);
  }

  // ==========================================================================
  // 1. QUEEN KEILAH MUSIC ENGINE
  // ==========================================================================

  public static function get_all_audio_ids() {
      if(!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);
      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfnmi_index';
      // Return raw attachment IDs from the index
      $ids = $wpdb->get_col("SELECT attachment_id FROM {$table} WHERE media_type = 'audio' ORDER BY created_gmt DESC");
      if ( empty($ids) ) wp_send_json_error(['message' => 'No audio found in network index. Run the Indexer first.']);
      wp_send_json_success($ids);
  }

  public static function resolve_playlist() {
      $ids_str = $_POST['ids'] ?? '';
      if ( empty($ids_str) ) wp_send_json_error();
      
      $ids = explode(',', $ids_str);
      $tracks = [];
      global $wpdb;
      $index_table = $wpdb->base_prefix . 'tbfnmi_index';
      
      // Limit to 500 tracks per batch
      $ids = array_slice($ids, 0, 500); 
      
      foreach($ids as $id) {
          $id = (int)$id;
          if(!$id) continue;
          
          $url = '';
          $title = '';

          // 1. Try Local Library
          $local_url = wp_get_attachment_url($id);
          if ( $local_url ) {
              $url = $local_url;
              $title = get_the_title($id);
          } 
          // 2. Try Network Index (Cross-Site)
          else {
              $row = $wpdb->get_row($wpdb->prepare("SELECT url_full, title FROM {$index_table} WHERE attachment_id = %d LIMIT 1", $id));
              if ($row) {
                  $url = $row->url_full;
                  $title = $row->title;
              }
          }

          if($url) {
              $tracks[] = [ 'id' => $id, 'url' => $url, 'title' => $title ?: basename($url) ];
          }
      }
      wp_send_json_success($tracks);
  }

  // ==========================================================================
  // 2. QUERY ENGINE (Slideshow & Photofall)
  // ==========================================================================

  public static function list_items() {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = max(1, min(200, (int)($_GET['per_page'] ?? 60)));
    $search = sanitize_text_field((string)($_GET['s'] ?? ''));
    $mime   = sanitize_text_field((string)($_GET['mime'] ?? ''));
    
    // Default to 0 (All Sites) unless explicitly set
    $originBlogId = isset($_GET['origin_blog_id']) ? (int)$_GET['origin_blog_id'] : 0;
    
    $orderby = sanitize_text_field((string)($_GET['orderby'] ?? 'date'));
    $include = sanitize_text_field((string)($_GET['include'] ?? ''));

    $fast = self::list_from_index_table($page, $per, $search, $mime, $originBlogId, $orderby, $include);
    
    if ( $fast !== null ) wp_send_json_success($fast);
    
    wp_send_json_success(['items' => [], 'total' => 0, 'max_pages' => 1]);
  }

  private static function list_from_index_table($page, $per, $search, $mime, $originBlogId, $orderby, $include = '') {
    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfnmi_index';

    if ( !$wpdb->get_var("SHOW TABLES LIKE '{$table}'") ) return null;

    $where = "1=1";
    $params = [];

    // Exclude Vikinger
    $where .= " AND (url_full NOT LIKE %s)";
    $params[] = '%/vikinger/%';

    // 1. Specific IDs (World Ruler "Specific" Mode)
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
    // 2. Random/Standard Mode
    else {
        // If $originBlogId is 0, we search the WHOLE NETWORK.
        if ( $originBlogId > 0 ) {
            $where .= " AND blog_id = %d";
            $params[] = $originBlogId;
        }

        if ( $mime ) {
            if ( $mime === 'image' ) { $where .= " AND media_type = 'image'"; } 
            elseif ( $mime === 'video' ) { $where .= " AND media_type = 'video'"; } 
            elseif ( $mime === 'audio' ) { $where .= " AND media_type = 'audio'"; } 
        }

        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (title LIKE %s OR alt LIKE %s OR caption LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
    }

    // Pagination
    $totalSql = "SELECT COUNT(DISTINCT url_full) FROM {$table} WHERE {$where}";
    $total = (int)$wpdb->get_var( $wpdb->prepare($totalSql, $params) );
    $offset = ($page - 1) * $per;

    // Sort
    $order_sql = "ORDER BY created_gmt DESC";
    if ( $orderby === 'date_asc' ) $order_sql = "ORDER BY created_gmt ASC";
    if ( $orderby === 'rand' ) $order_sql = "ORDER BY RAND()";

    $sql = "SELECT 
                MIN(blog_id) as blog_id, MAX(attachment_id) as attachment_id, MAX(title) as title, MAX(mime) as mime, MAX(media_type) as media_type, 
                url_full, MAX(url_medium) as url_medium, MAX(url_thumb) as url_thumb, MAX(poster_url) as poster_url, MIN(created_gmt) as created_gmt,
                MAX(width) as width, MAX(height) as height
            FROM {$table} WHERE {$where} GROUP BY url_full {$order_sql} LIMIT %d OFFSET %d";

    $final_params = array_merge($params, [$per, $offset]);
    $rows = $wpdb->get_results( $wpdb->prepare($sql, $final_params), ARRAY_A );

    $items = [];
    foreach ((array)$rows as $r) {
      $thumb = $r['url_thumb'] ?: ($r['poster_url'] ?: ($r['url_medium'] ?: $r['url_full']));
      if ($r['media_type'] === 'audio') $thumb = includes_url('images/media/audio.png');
      $items[] = [
        'blog_id' => (int)$r['blog_id'], 
        'attachment_id' => (int)$r['attachment_id'], 
        'title' => (string)($r['title'] ?? ''), 
        'url' => (string)($r['url_full'] ?? ''), 
        'thumb' => (string)$thumb, 
        'mime' => (string)($r['mime'] ?? ''), 
        'media_type' => (string)($r['media_type'] ?? ''), 
        'width' => (int)($r['width'] ?? 800), 
        'height' => (int)($r['height'] ?? 800),
      ];
    }

    return ['items' => $items, 'total' => $total, 'max_pages' => $per > 0 ? (int)ceil($total / $per) : 1, 'source' => 'index_table'];
  }

  // ==========================================================================
  // 3. FRONTEND UPLOADER
  // ==========================================================================

  public static function frontend_upload() {
      check_ajax_referer('tbfnmi_frontend', 'security');
      @set_time_limit(0);

      $opts = get_option('tbfnmi_photofall_options', []);
      
      $is_authorized = false;
      if ( current_user_can('manage_options') || is_super_admin() ) $is_authorized = true;
      elseif ( !empty($opts['enable_frontend_upload']) ) {
          $user = wp_get_current_user();
          $allowed = !empty($opts['upload_roles']) ? $opts['upload_roles'] : ['administrator'];
          if ( !empty(array_intersect($allowed, $user->roles)) ) $is_authorized = true;
      }

      if ( !$is_authorized ) wp_send_json_error(['message' => 'Not authorized'], 403);
      if ( empty($_FILES['tbfnmi_media']) ) wp_send_json_error(['message' => 'No files'], 400);

      require_once(ABSPATH . 'wp-admin/includes/image.php');
      require_once(ABSPATH . 'wp-admin/includes/file.php');
      require_once(ABSPATH . 'wp-admin/includes/media.php');

      $title = sanitize_text_field($_POST['tbfnmi_title'] ?? '');
      $desc  = sanitize_textarea_field($_POST['tbfnmi_description'] ?? '');
      $files = $_FILES['tbfnmi_media'];
      $uploaded_ids = [];
      $errors = [];

      if ( is_array($files['name']) ) {
          foreach ($files['name'] as $key => $value) {
              if ($files['name'][$key]) {
                  $file = [
                      'name' => $files['name'][$key], 'type' => $files['type'][$key],
                      'tmp_name' => $files['tmp_name'][$key], 'error' => $files['error'][$key],
                      'size' => $files['size'][$key]
                  ];
                  $_FILES['tbf_single_upload'] = $file;
                  $this_title = $title ?: pathinfo($file['name'], PATHINFO_FILENAME);

                  $attachment_id = media_handle_upload('tbf_single_upload', 0, [
                      'post_title' => $this_title, 'post_content' => $desc, 'post_excerpt' => $desc
                  ]);

                  if ( is_wp_error($attachment_id) ) $errors[] = $attachment_id->get_error_message();
                  else {
                      $uploaded_ids[] = $attachment_id;
                      if ( class_exists('TBFNMI_Indexer') ) {
                          $indexer = new TBFNMI_Indexer();
                          $indexer->index_single_attachment($attachment_id);
                      }
                  }
              }
          }
      }

      if ( empty($uploaded_ids) ) {
          $msg = !empty($errors) ? implode(', ', $errors) : 'Upload failed.';
          wp_send_json_error(['message' => $msg]);
      }
      wp_send_json_success(['message' => 'Upload successful', 'ids' => $uploaded_ids]);
  }

  public static function hide_media() {
      if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Forbidden'], 403);
      check_ajax_referer('tbfnmi_admin_action', 'nonce');
      $att_id = (int)($_POST['attachment_id'] ?? 0);
      $hidden = get_option('tbfnmi_hidden_media', []);
      if ( in_array($att_id, $hidden) ) $hidden = array_diff($hidden, [$att_id]);
      else $hidden[] = $att_id;
      update_option('tbfnmi_hidden_media', $hidden);
      wp_send_json_success();
  }

  public static function delete_media() {
      if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Forbidden'], 403);
      check_ajax_referer('tbfnmi_admin_action', 'nonce');
      $att_id = (int)($_POST['attachment_id'] ?? 0);
      if ( wp_delete_attachment($att_id, true) ) wp_send_json_success();
      else wp_send_json_error(['message' => 'Delete failed.']);
  }

  // ==========================================================================
  // 4. ADMIN TOOLS & HELPERS
  // ==========================================================================

  public static function wipe_index() {
      if(!current_user_can('manage_options')) wp_send_json_error();
      check_ajax_referer('tbfnmi_wipe_nonce', 'nonce');
      global $wpdb;
      $wpdb->query("TRUNCATE TABLE {$wpdb->base_prefix}tbfnmi_index");
      $wpdb->query("TRUNCATE TABLE {$wpdb->base_prefix}tbfnmi_usage_map");
      delete_option('tbfnmi_db_version');
      wp_send_json_success(['message' => 'Index wiped.']);
  }

  public static function resolve_ids() {
      if(!current_user_can('manage_options')) wp_send_json_error();
      $ids = explode(',', sanitize_text_field($_POST['ids'] ?? ''));
      $urls = [];
      global $wpdb;
      $index_table = $wpdb->base_prefix . 'tbfnmi_index';
      
      foreach($ids as $id) {
          $id = (int)trim($id);
          if(!$id) continue;
          $u = wp_get_attachment_url($id);
          if(!$u) $u = $wpdb->get_var($wpdb->prepare("SELECT url_full FROM {$index_table} WHERE attachment_id = %d LIMIT 1", $id));
          if($u) $urls[] = $u;
      }
      wp_send_json_success($urls);
  }

  private static function verify() { 
      if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
      check_ajax_referer('tbfnmi_nonce', 'nonce');
  }

  // ==========================================================================
  // 5. NETWORK PROXY (FULL LOGIC RESTORED)
  // ==========================================================================

  public static function sites() {
    self::verify();
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

  public static function proxy() {
    self::verify();
    $originBlogId = (int)($_POST['origin_blog_id'] ?? 0);
    $originAttId  = (int)($_POST['origin_attachment_id'] ?? 0);
    $url          = esc_url_raw($_POST['url'] ?? '');
    $title        = sanitize_text_field($_POST['title'] ?? 'Media');
    $mime         = sanitize_text_field($_POST['mime'] ?? '');

    if ( ! $url ) wp_send_json_error(['message' => 'Missing remote URL payload.'], 400);

    // Mime detection
    $lower_url = strtolower($url);
    if (strpos($lower_url, '.gif') !== false) $mime = 'image/gif';
    elseif (strpos($lower_url, '.png') !== false) $mime = 'image/png';
    elseif (strpos($lower_url, '.webp') !== false) $mime = 'image/webp';
    elseif (strpos($lower_url, '.mp4') !== false) $mime = 'video/mp4';
    if (empty($mime) || $mime === 'application/octet-stream') $mime = 'image/jpeg';

    if ( ! class_exists('TBFNMI_Proxy') ) wp_send_json_error(['message' => 'Proxy class missing'], 500);

    // Prevent recursive loop
    if ( class_exists('TBFNMI_Network_Media_Index') ) remove_action('add_attachment', ['TBFNMI_Network_Media_Index', 'auto_index_attachment']);

    $localId = TBFNMI_Proxy::create_proxy_attachment([
      'origin_blog_id' => $originBlogId, 
      'origin_attachment_id' => $originAttId, 
      'url' => $url,
      'title' => $title ?: 'Media', 
      'mime' => $mime, 
      'source' => 'network',
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
      'origin_blog_id' => 0, 
      'origin_attachment_id' => 0, 
      'url' => $url,
      'title' => $title ?: 'Media', 
      'mime' => $mime, 
      'source' => $source ?: 'external', 
      'extra_meta' => $extra,
    ]);

    if ( class_exists('TBFNMI_Network_Media_Index') ) add_action('add_attachment', ['TBFNMI_Network_Media_Index', 'auto_index_attachment']);

    if ( is_wp_error($localId) ) wp_send_json_error(['message' => $localId->get_error_message()], 500);
    wp_send_json_success(['local_attachment_id' => (int)$localId, 'url' => $url, 'mime' => $mime]);
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
}