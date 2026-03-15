<?php
/**
 * File: includes/seo/class-tbfbkm-seo-meta.php
 * Version: 7.0.1.3 (WP Review Compliance - Late Escaping & Full Feature Restoration)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_SEO_Meta {

  public static function init() {
    add_action('init', [__CLASS__, 'add_sitemap_rules']);
    add_filter('query_vars', [__CLASS__, 'add_sitemap_vars']);
    add_action('template_redirect', [__CLASS__, 'render_sitemaps']);

    add_action('wp_head', [__CLASS__, 'inject_opengraph_tags'], 5);
    add_filter('the_content', [__CLASS__, 'append_network_interlinks']);
    
    add_action('save_post', [__CLASS__, 'sync_post_media_usage'], 99, 2);
  }

  public static function add_sitemap_rules() {
    $opts = get_option('tbfbkm_photofall_options', []);
    if ( empty($opts['enable_xml_sitemaps']) ) return;

    add_rewrite_rule('^photo-sitemap-index\.xml$', 'index.php?tbfbkm_sitemap=photo', 'top');
    add_rewrite_rule('^video-sitemap-index\.xml$', 'index.php?tbfbkm_sitemap=video', 'top');
  }

  public static function add_sitemap_vars($vars) {
    $vars[] = 'tbfbkm_sitemap';
    return $vars;
  }

  public static function render_sitemaps() {
    $type = get_query_var('tbfbkm_sitemap');
    if ( ! $type ) return;

    $opts = get_option('tbfbkm_photofall_options', []);
    if ( empty($opts['enable_xml_sitemaps']) ) {
        status_header(404); die('Sitemaps disabled');
    }

    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfbkm_index';
    
    if ( empty($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)))) ) {
        status_header(404); die('Index table missing');
    }

    $limit = 1000; 
    
    header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    
    if ( $type === 'video' ) {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE media_type = 'video' ORDER BY created_gmt DESC LIMIT {$limit}");
        
        foreach ($rows as $row) {
            $loc = home_url('/photo/video/' . (int)$row->blog_id . '-' . (int)$row->attachment_id . '/');
            $thumb = $row->poster_url ?: $row->url_thumb;
            $title = htmlspecialchars($row->title ?: 'Video', ENT_XML1);
            $desc  = htmlspecialchars($row->caption ?: 'AgriGames Video', ENT_XML1);
            
            // Late Escaping for WP Review Compliance
            echo "\t<url>\n";
            echo "\t\t<loc>" . esc_url($loc) . "</loc>\n";
            echo "\t\t<video:video>\n";
            echo "\t\t\t<video:thumbnail_loc>" . esc_url($thumb) . "</video:thumbnail_loc>\n";
            echo "\t\t\t<video:title>" . esc_html($title) . "</video:title>\n";
            echo "\t\t\t<video:description>" . esc_html($desc) . "</video:description>\n";
            echo "\t\t\t<video:player_loc>" . esc_url($row->url_full) . "</video:player_loc>\n";
            echo "\t\t</video:video>\n";
            echo "\t</url>\n";
        }
        echo '</urlset>';
    } else {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE media_type = 'image' ORDER BY created_gmt DESC LIMIT {$limit}");
        
        foreach ($rows as $row) {
            $loc = home_url('/photo/image/' . (int)$row->blog_id . '-' . (int)$row->attachment_id . '/');
            $imgLoc = $row->url_full;
            $title = htmlspecialchars($row->title ?: '', ENT_XML1);
            
            // Late Escaping for WP Review Compliance
            echo "\t<url>\n";
            echo "\t\t<loc>" . esc_url($loc) . "</loc>\n";
            echo "\t\t<image:image>\n";
            echo "\t\t\t<image:loc>" . esc_url($imgLoc) . "</image:loc>\n";
            if($title) echo "\t\t\t<image:title>" . esc_html($title) . "</image:title>\n";
            echo "\t\t</image:image>\n";
            echo "\t</url>\n";
        }
        echo '</urlset>';
    }
    
    exit;
  }

  public static function inject_opengraph_tags() {
    if ( ! is_attachment() ) return;
    
    $post = get_post();
    if ( ! $post ) return;

    $url = wp_get_attachment_url($post->ID);
    if ( ! $url ) return;

    $title = get_the_title($post->ID);
    $mime  = get_post_mime_type($post->ID);

    echo "\n\n";
    // Late Escaping for WP Review Compliance
    echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url(get_permalink($post->ID)) . '" />' . "\n";
    
    if ( strpos($mime, 'image/') === 0 ) {
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:image" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($url) . '" />' . "\n";
    } elseif ( strpos($mime, 'video/') === 0 ) {
        echo '<meta property="og:type" content="video.other" />' . "\n";
        echo '<meta property="og:video" content="' . esc_url($url) . '" />' . "\n";
    }
    echo "\n\n";
  }

  public static function append_network_interlinks($content) {
    if ( ! is_attachment() || ! in_the_loop() || ! is_main_query() ) return $content;

    $opts = get_option('tbfbkm_photofall_options', []);
    if ( empty($opts['seo_interlink_origin']) ) return $content;

    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfbkm_usage_map';
    
    $like = $wpdb->esc_like($table);
    if ( empty($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like))) ) return $content;

    $post_id = get_the_ID();
    $url = wp_get_attachment_url($post_id);
    
    if ( ! $url ) return $content;

    $url_clean = strtok($url, '?');
    $url_http = set_url_scheme($url_clean, 'http');
    $url_https = set_url_scheme($url_clean, 'https');

    $results = $wpdb->get_results($wpdb->prepare("
        SELECT post_title, permalink, site_name 
        FROM {$table} 
        WHERE media_url = %s OR media_url = %s 
        ORDER BY id DESC LIMIT 5
    ", $url_http, $url_https));

    if ( empty($results) ) return $content;

    $html  = '<div class="tbfbkm-seo-interlink-box" style="margin-top: 2.5em; padding: 1.5em; background: #fafafa; border-left: 4px solid #2271b1; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
    $html .= '<h4 style="margin-top: 0; margin-bottom: 0.5em; font-size: 1.1em; color: #1d2327;">Featured Context</h4>';
    $html .= '<p style="margin: 0 0 10px; font-size: 0.95em; color: #3c434a; line-height: 1.5;">This media file is featured in the following spaces across the AgriGames network:</p>';
    $html .= '<ul style="margin: 0; padding-left: 20px; font-size: 0.95em; color: #3c434a;">';
    
    foreach ( $results as $res ) {
        // Late Escaping for WP Review Compliance
        $html .= '<li style="margin-bottom: 5px;"><a href="' . esc_url($res->permalink) . '" style="color: #2271b1; text-decoration: none; font-weight: 600;" target="_blank" rel="noopener">' . esc_html($res->post_title) . '</a> on ' . esc_html($res->site_name) . '</li>';
    }
    
    $html .= '</ul></div>';

    return $content . $html;
  }

  public static function sync_post_media_usage($post_id, $post) {
      if ( wp_is_post_revision($post_id) || $post->post_status !== 'publish' ) return;
      if ( $post->post_type === 'attachment' || $post->post_type === 'nav_menu_item' ) return;

      global $wpdb;
      $table = $wpdb->base_prefix . 'tbfbkm_usage_map';
      
      $like = $wpdb->esc_like($table);
      if ( empty($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like))) ) return;

      $blog_id = get_current_blog_id();
      $site_name = get_bloginfo('name');
      $permalink = get_permalink($post_id);
      $title = $post->post_title ?: 'Untitled';

      $wpdb->delete($table, ['blog_id' => $blog_id, 'post_id' => $post_id]);

      $urls = [];

      // 1. Standard Content Parsing
      if ( preg_match_all('/(?:src|href)="([^"]+\.(?:jpg|jpeg|png|gif|mp4|webm|mov|mp3|wav|ogg|flac|m4a|aac)[^"]*)"/i', $post->post_content, $matches) ) {
          foreach ( $matches[1] as $src ) {
              $urls[] = esc_url_raw(strtok($src, '?'));
          }
      }

      // 2. Elementor Deep Integration Parsing
      $elementor_data = get_post_meta($post_id, '_elementor_data', true);
      if ( !empty($elementor_data) ) {
          if ( preg_match_all('/"(?:url|src)":"([^"]+\.(?:jpg|jpeg|png|gif|mp4|webm|mov|mp3|wav|ogg|flac|m4a|aac)[^"]*)"/i', $elementor_data, $el_matches) ) {
              foreach ( $el_matches[1] as $src ) {
                  $urls[] = esc_url_raw(strtok(stripslashes($src), '?'));
              }
          }
      }

      // 3. Featured Image and Proxies
      $thumb_id = get_post_thumbnail_id($post_id);
      if ( $thumb_id ) {
          $thumb_url = wp_get_attachment_url($thumb_id);
          if ( $thumb_url ) {
              $urls[] = esc_url_raw(strtok($thumb_url, '?'));
          }
          
          $origin_url = get_post_meta($thumb_id, '_tbfbkm_origin_url', true);
          if (!$origin_url) $origin_url = get_post_meta($thumb_id, '_tbfbkm_featured_url', true);
          if (!$origin_url) $origin_url = get_post_meta($thumb_id, '_tbfbkm_proxy_url', true);
          
          if ( $origin_url ) {
              $urls[] = esc_url_raw(strtok($origin_url, '?'));
          }
      }

      // 4. Remote Featured Meta
      $tbf_featured = get_post_meta($post_id, '_tbfbkm_featured_url', true);
      if ( $tbf_featured ) {
          $urls[] = esc_url_raw(strtok($tbf_featured, '?'));
      }

      $urls = array_unique(array_filter($urls));

      foreach ( $urls as $url ) {
          $wpdb->insert($table, [
              'media_url'  => sanitize_text_field($url),
              'blog_id'    => (int)$blog_id,
              'post_id'    => (int)$post_id,
              'post_title' => sanitize_text_field($title),
              'permalink'  => esc_url_raw($permalink),
              'site_name'  => sanitize_text_field($site_name)
          ]);
      }
  }
}