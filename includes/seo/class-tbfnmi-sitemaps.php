<?php
/**
 * File: includes/seo/class-tbfnmi-sitemaps.php
 * Version: 6.2.7 (Late Escaping XML Dates)
 */
if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Sitemaps {

  public static function init() {
    add_action('init', [__CLASS__, 'rewrite_rules']);
    add_filter('query_vars', [__CLASS__, 'query_vars']);
    add_action('template_redirect', [__CLASS__, 'maybe_render'], 0);
  }

  public static function rewrite_rules() {
    add_rewrite_rule('^photo-sitemap-index\.xml$', 'index.php?tbfnmi_sm=photo_index', 'top');
    add_rewrite_rule('^photo-sitemap-([0-9]{1,6})\.xml$', 'index.php?tbfnmi_sm=photo&tbfnmi_sm_page=$matches[1]', 'top');
    add_rewrite_rule('^video-sitemap-index\.xml$', 'index.php?tbfnmi_sm=video_index', 'top');
    add_rewrite_rule('^video-sitemap-([0-9]{1,6})\.xml$', 'index.php?tbfnmi_sm=video&tbfnmi_sm_page=$matches[1]', 'top');
  }

  public static function query_vars($vars) {
    $vars[] = 'tbfnmi_sm';
    $vars[] = 'tbfnmi_sm_page';
    return $vars;
  }

  public static function maybe_render() {
    $kind = sanitize_key((string)get_query_var('tbfnmi_sm'));
    if ( ! $kind ) return;

    $settings = class_exists('TBFNMI_Plugin') ? TBFNMI_Plugin::instance()->get_settings() : [];
    $enabled = isset($settings['photofall_enabled']) ? (int)$settings['photofall_enabled'] : 1;
    if ( ! $enabled ) { self::send_404(); return; }

    if ( $kind === 'photo_index' ) { self::render_index('photo'); exit; }
    if ( $kind === 'video_index' ) { self::render_index('video'); exit; }
    if ( $kind === 'photo' ) { self::render_chunk('photo', max(1,(int)get_query_var('tbfnmi_sm_page'))); exit; }
    if ( $kind === 'video' ) { self::render_chunk('video', max(1,(int)get_query_var('tbfnmi_sm_page'))); exit; }

    self::send_404();
  }

  private static function render_index($type) {
    header('Content-Type: application/xml; charset=UTF-8', true);
    nocache_headers();
    $pages = self::count_pages($type);
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    $base = home_url('/');
    $now  = gmdate('c');
    for ($i=1;$i<=$pages;$i++){
      $loc = esc_url($base . $type . "-sitemap-{$i}.xml");
      echo "  <sitemap>\n";
      echo "    <loc>" . esc_url($loc) . "</loc>\n";
      // LATE ESCAPING FIX: esc_html applied directly at output
      echo "    <lastmod>" . esc_html($now) . "</lastmod>\n";
      echo "  </sitemap>\n";
    }
    echo "</sitemapindex>\n";
  }

  private static function render_chunk($type, $page) {
    header('Content-Type: application/xml; charset=UTF-8', true);
    nocache_headers();

    $settings = class_exists('TBFNMI_Plugin') ? TBFNMI_Plugin::instance()->get_settings() : [];
    $chunkSize = isset($settings['photofall_sitemap_chunk']) ? (int)$settings['photofall_sitemap_chunk'] : 1000;
    $chunkSize = max(200, min(5000, $chunkSize));
    $offset = ($page - 1) * $chunkSize;

    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfnmi_index';
    $mediaType = ($type === 'video') ? 'video' : 'image';

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT blog_id, attachment_id, created_gmt, url_full, url_thumb, poster_url, content_url, embed_url
         FROM {$table}
         WHERE media_type = %s
         ORDER BY created_gmt DESC, blog_id DESC, attachment_id DESC
         LIMIT %d OFFSET %d",
        $mediaType, $chunkSize, $offset
      ),
      ARRAY_A
    );

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"\n";
    echo "  xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\"\n";
    echo "  xmlns:video=\"http://www.google.com/schemas/sitemap-video/1.1\">\n";

    foreach ($rows as $r) {
      $blogId = (int)$r['blog_id'];
      $attId  = (int)$r['attachment_id'];
      
      // Use standard site URL to prevent missing constant errors
      $href = home_url('/photo/' . ($mediaType === 'video' ? 'video' : 'image') . "/{$blogId}-{$attId}/");
      $lastmod = $r['created_gmt'] ? gmdate('c', strtotime($r['created_gmt'])) : gmdate('c');

      echo "  <url>\n";
      echo "    <loc>" . esc_url($href) . "</loc>\n";
      // LATE ESCAPING FIX: esc_html applied directly at output
      echo "    <lastmod>" . esc_html($lastmod) . "</lastmod>\n";

      if ( $mediaType === 'image' ) {
        $img = $r['url_full'] ?: '';
        if ( $img ) {
          echo "    <image:image>\n";
          echo "      <image:loc>" . esc_url($img) . "</image:loc>\n";
          echo "    </image:image>\n";
        }
      } else {
        $thumb = $r['poster_url'] ?: ($r['url_thumb'] ?: '');
        $contentUrl = $r['content_url'] ?: '';
        $embedUrl   = $r['embed_url'] ?: '';

        echo "    <video:video>\n";
        if ( $thumb ) echo "      <video:thumbnail_loc>" . esc_url($thumb) . "</video:thumbnail_loc>\n";
        echo "      <video:title>" . esc_html("Photofall Video {$blogId}-{$attId}") . "</video:title>\n";
        echo "      <video:description>" . esc_html("Video from Trott Bailey Family Photofall") . "</video:description>\n";
        if ( $contentUrl ) echo "      <video:content_loc>" . esc_url($contentUrl) . "</video:content_loc>\n";
        if ( $embedUrl ) echo "      <video:player_loc>" . esc_url($embedUrl) . "</video:player_loc>\n";
        echo "    </video:video>\n";
      }

      echo "  </url>\n";
    }

    echo "</urlset>\n";
  }

  private static function count_pages($type) {
    global $wpdb;
    $table = $wpdb->base_prefix . 'tbfnmi_index';
    $mediaType = ($type === 'video') ? 'video' : 'image';
    $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE media_type = %s", $mediaType));

    $settings = class_exists('TBFNMI_Plugin') ? TBFNMI_Plugin::instance()->get_settings() : [];
    $chunkSize = isset($settings['photofall_sitemap_chunk']) ? (int)$settings['photofall_sitemap_chunk'] : 1000;
    $chunkSize = max(200, min(5000, $chunkSize));
    $pages = (int)ceil($total / $chunkSize);
    return max(1, $pages);
  }

  private static function send_404() {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
    echo 'Not found';
    exit;
  }
}