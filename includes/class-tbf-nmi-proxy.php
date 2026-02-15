<?php
/*
 * File: includes/class-tbf-nmi-proxy.php
 * Version: 1.0.24
 */

if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Proxy {

  const META_IS_PROXY  = '_tbf_nmi_is_proxy';
  const META_ORIG_BLOG = '_tbf_nmi_origin_blog_id';
  const META_ORIG_ATT  = '_tbf_nmi_origin_attachment_id';
  const META_ORIG_URL  = '_tbf_nmi_origin_url';

  public static function ensure_proxy($origin_blog_id, $origin_attachment_id) {

    // Reuse existing proxy if present
    $existing = self::find_existing_proxy($origin_blog_id, $origin_attachment_id);
    if ( $existing ) return $existing;

    // Fetch origin URL + basic fields
    switch_to_blog($origin_blog_id);
    $url   = wp_get_attachment_url($origin_attachment_id);
    $title = get_the_title($origin_attachment_id);
    $mime  = get_post_mime_type($origin_attachment_id);
    restore_current_blog();

    if ( ! $url ) {
      return new WP_Error('missing_origin', 'Origin attachment URL not found');
    }

    // Create local attachment record with GUID as remote URL (DB-only)
    $attachment = [
      'post_mime_type' => $mime ?: 'image/jpeg',
      'post_title'     => $title ?: ('Network Media ' . $origin_attachment_id),
      'post_content'   => '',
      'post_status'    => 'inherit',
      'guid'           => esc_url_raw($url),
    ];

    $local_id = wp_insert_attachment($attachment, ''); // no local file path
    if ( is_wp_error($local_id) ) return $local_id;

    update_post_meta($local_id, self::META_IS_PROXY, 1);
    update_post_meta($local_id, self::META_ORIG_BLOG, (int)$origin_blog_id);
    update_post_meta($local_id, self::META_ORIG_ATT,  (int)$origin_attachment_id);
    update_post_meta($local_id, self::META_ORIG_URL,  esc_url_raw($url));

    return (int)$local_id;
  }

  private static function find_existing_proxy($origin_blog_id, $origin_attachment_id) {
    $q = new WP_Query([
      'post_type'      => 'attachment',
      'post_status'    => 'inherit',
      'fields'         => 'ids',
      'posts_per_page' => 1,
      'meta_query'     => [
        ['key' => self::META_IS_PROXY,  'value' => '1', 'compare' => '='],
        ['key' => self::META_ORIG_BLOG, 'value' => (string)(int)$origin_blog_id, 'compare' => '='],
        ['key' => self::META_ORIG_ATT,  'value' => (string)(int)$origin_attachment_id, 'compare' => '='],
      ],
    ]);

    return ! empty($q->posts) ? (int)$q->posts[0] : 0;
  }

  public static function is_proxy($post_id) {
    return (bool) get_post_meta($post_id, self::META_IS_PROXY, true);
  }

  public static function origin_url($post_id) {
    $orig = get_post_meta($post_id, self::META_ORIG_URL, true);
    return $orig ? esc_url_raw($orig) : '';
  }

  /**
   * Ensure proxy URLs always resolve.
   */
  public static function filter_attachment_url($url, $post_id) {
    if ( self::is_proxy($post_id) ) {
      $orig = self::origin_url($post_id);
      if ( $orig ) return $orig;
    }
    return $url;
  }

  /**
   * CRITICAL FIX for Featured Image UI:
   * WP uses image_downsize() to build the preview HTML. Proxies have no local file/metadata,
   * so we short-circuit and return the origin URL as the "downsized" image.
   *
   * Return format: [url, width, height, is_intermediate]
   */
  public static function filter_image_downsize($out, $id, $size) {
    $id = (int) $id;
    if ( ! $id || ! self::is_proxy($id) ) return $out;

    $url = self::origin_url($id);
    if ( ! $url ) return $out;

    // Best-effort dimensions (WP UI is fine even if these are approximate)
    $w = 0; $h = 0;

    if ( is_array($size) && count($size) >= 2 ) {
      $w = (int) $size[0];
      $h = (int) $size[1];
    } elseif ( is_string($size) ) {
      switch ($size) {
        case 'thumbnail': $w = 150; $h = 150; break;
        case 'medium':    $w = 300; $h = 300; break;
        case 'large':     $w = 1024; $h = 1024; break;
        case 'full':      $w = 0; $h = 0; break;
        default:
          // For any custom size name, leave 0x0; WP will still output <img src="">
          $w = 0; $h = 0;
      }
    }

    return [$url, $w, $h, true];
  }
}

// Ensure proxy URLs always resolve
add_filter('wp_get_attachment_url', ['TBF_NMI_Proxy', 'filter_attachment_url'], 10, 2);

// Ensure featured image + attachment previews work for proxy images
add_filter('image_downsize', ['TBF_NMI_Proxy', 'filter_image_downsize'], 10, 3);
