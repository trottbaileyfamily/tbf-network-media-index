<?php
/**
 * File: includes/class-tbfnmi-ajax.php
 * Version: 6.9.5.7 (Cross-Network Audio Fix)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_AJAX {

  public static function init() {
    add_action('wp_ajax_tbfnmi_list', [__CLASS__, 'list_items']);
    add_action('wp_ajax_nopriv_tbfnmi_list', [__CLASS__, 'list_items']); 
    
    // Load More Handler
    add_action('wp_ajax_tbfnmi_load_more', [__CLASS__, 'load_more']);
    add_action('wp_ajax_nopriv_tbfnmi_load_more', [__CLASS__, 'load_more']); 

    add_action('wp_ajax_tbfnmi_sites', [__CLASS__, 'sites']);
    
    // Core Actions
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
    
    // Princess Keilah Music
    add_action('wp_ajax_tbfnmi_get_all_audio_ids', [__CLASS__, 'get_all_audio_ids']);
    add_action('wp_ajax_tbfnmi_resolve_playlist', [__CLASS__, 'resolve_playlist']);
    add_action('wp_ajax_nopriv_tbfnmi_resolve_playlist', [__CLASS__, 'resolve_playlist']);
  }

  // ==========================================================================
  // 1. PRINCESS KEILAH MUSIC ENGINE
  // ==========================================================================

  public static function get_all_audio_ids() {
      if(!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);
      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfnmi_index';
      $ids = $wpdb->get_col("SELECT attachment_id FROM {$table} WHERE media_type = 'audio' ORDER BY created_gmt DESC");
      if ( empty($ids) ) wp_send_json_error(['message' => 'No audio found in network index. Run the Indexer first.']);
      wp_send_json_success($ids);
  }

  public static function resolve_playlist() {
      $ids_str = $_POST['ids'] ?? '';
      if ( empty($ids_str) ) wp_send_json_error();
      
      $ids = explode(',', $ids_str);
      $tracks = [];
      $ids = array_slice($ids, 0, 500); 
      
      // Determine Master Site ID
      $master_id = (int) get_site_option('tbfnmi_master_controller_id', 0);
      if ( $master_id <= 0 ) $master_id = get_main_site_id();
      $current_id = get_current_blog_id();

      foreach($ids as $id) {
          $id = (int)$id;
          if(!$id) continue;
          
          $url = '';
          $title = '';

          // A. Try Local Site First
          $local_url = wp_get_attachment_url($id);
          if ( $local_url ) {
              $url = $local_url;
              $title = get_the_title($id);
          } 
          // B. Try Master Site (If we aren't already on it)
          elseif ( $master_id && $master_id !== $current_id ) {
              switch_to_blog($master_id);
              $remote_url = wp_get_attachment_url($id);
              if ( $remote_url ) {
                  $url = $remote_url;
                  $title = get_the_title($id);
              }
              restore_current_blog();
          }

          // C. If still failed, try Big King Index Table (Last Resort)
          if ( empty($url) ) {
              global $wpdb;
              $index_table = $wpdb->base_prefix . 'tbfnmi_index';
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
      
      if (empty($tracks)) {
         wp_send_json_error(['message' => 'Audio unavailable']);
      } else {
         wp_send_json_success($tracks);
      }
  }

  // ==========================================================================
  // 2. QUERY ENGINE (Slideshow & Photofall)
  // ==========================================================================

  public static function load_more() {
      $page = max(1, (int)($_POST['page'] ?? 1));
      $per  = 24; 
      $search = sanitize_text_field($_POST['search'] ?? '');
      $mime   = sanitize_text_field($_POST['filter'] ?? '');
      if($mime === 'all') $mime = ''; 
      
      $sort = sanitize_text_field($_POST['sort'] ?? '');
      $orderby = 'date';
      if($sort === 'oldest') $orderby = 'date_asc';
      if($sort === 'random') $orderby = 'rand';

      $data = self::list_from_index_table($page, $per, $search, $mime, 0, $orderby);
      
      if ( !$data || empty($data['items']) ) {
          wp_send_json_success(['html' => '', 'max_pages' => 0]);
      }

      ob_start();
      foreach($data['items'] as $item) {
          $full = esc_url($item['url']);
          $thumb = esc_url($item['thumb']);
          $type = esc_attr($item['media_type']);
          $caption = esc_attr($item['title']); 
          $permalink = get_site_url($item['blog_id'], '/') . '?attachment_id=' . $item['attachment_id']; 

          echo '<div class="tbf-grid-item">';
          echo '<img class="tbf-photofall-img" 
                     src="' . $thumb . '" 
                     loading="lazy"
                     data-full="' . $full . '" 
                     data-type="' . $type . '" 
                     data-caption="' . $caption . '" 
                     data-source-title="Site ' . $item['blog_id'] . '" 
                     data-source-url="' . $permalink . '" 
                     data-permalink="' . $permalink . '" 
                     onclick="tbfnmi_photofall.open(this)" />';
          
          if($type === 'video') {
              echo '<span class="tbf-type-icon">▶</span>';
          }
          echo '</div>';
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

  private static function list_from_index_table($page, $per, $search, $mime, $originBlogId, $orderby, $include = '') {
    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfnmi_index';

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

    $totalSql = "SELECT COUNT(DISTINCT url_full) FROM {$table} WHERE {$where}";
    $total = (int)$wpdb->get_var( $wpdb->prepare($totalSql, $params) );
    $offset = ($page - 1) * $per;

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
  // 3. FRONTEND UPLOADER (No Changes Needed)
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
                      if ( class_exists('TBFNMI_Indexer') ) {
                          $indexer = new TBFNMI_Indexer();
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
  // 4. ADMIN TOOLS
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
  // 5. PROXY & FEATURED
  // ==========================================================================

  public static function proxy() {
    if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
    $originBlogId = (int)($_POST['origin_blog_id'] ?? 0);
    $originAttId  = (int)($_POST['origin_attachment_id'] ?? 0);
    $url          = esc_url_raw($_POST['url'] ?? '');
    $title        = sanitize_text_field($_POST['title'] ?? 'Media');
    $mime         = sanitize_text_field($_POST['mime'] ?? '');

    if ( ! $url ) wp_send_json_error(['message' => 'Missing remote URL payload.'], 400);

    if (empty($mime)) $mime = 'image/jpeg';

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
    // Same proxy logic for external URLs
    if ( ! current_user_can('upload_files') ) wp_send_json_error(['message' => 'Permission denied'], 403);
    $url = esc_url_raw((string)($_POST['url'] ?? ''));
    if ( !$url ) wp_send_json_error(['message' => 'Missing URL'], 400);
    
    $title = sanitize_text_field((string)($_POST['title'] ?? 'Media'));
    $mime = sanitize_text_field((string)($_POST['mime'] ?? 'image/jpeg'));
    $source = sanitize_key((string)($_POST['source'] ?? 'external'));

    if ( class_exists('TBFNMI_Network_Media_Index') ) remove_action('add_attachment', ['TBFNMI_Network_Media_Index', 'auto_index_attachment']);
    
    $localId = TBFNMI_Proxy::create_proxy_attachment([
      'origin_blog_id' => 0, 'origin_attachment_id' => 0, 
      'url' => $url, 'title' => $title, 'mime' => $mime, 'source' => $source
    ]);
    
    if ( class_exists('TBFNMI_Network_Media_Index') ) add_action('add_attachment', ['TBFNMI_Network_Media_Index', 'auto_index_attachment']);

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

    update_post_meta($postId, '_tbfnmi_featured_url', $url);
    update_post_meta($postId, '_tbfnmi_featured_mime', $mime);
    update_post_meta($postId, '_tbfnmi_featured_type', $type);

    $pid = (int) TBFNMI_Placeholder::get_id();
    if ($pid > 0) update_post_meta($postId, '_thumbnail_id', $pid);

    clean_post_cache($postId);
    wp_send_json_success(['post_id' => $postId, 'placeholder_id' => $pid, 'url' => $url]);
  }
}