<?php
/**
 * File: includes/class-tbf-nmi-proxy.php
 * Version: 4.1.8
 *
 * Attachment "proxy" creator:
 * - Creates a local attachment pointing to a remote file URL (no copying).
 * - Used by the media modal "Network Media" tab.
 *
 * This class intentionally does NOT attempt to make proxy attachments behave like local files.
 * Featured media behavior is handled by TBF_NMI_Featured_Media (FIFU-style).
 */

if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Proxy {

  /**
   * Create a local proxy attachment for a remote media item (from another blog or external source).
   *
   * @param array $args {
   *   @type int    $origin_blog_id
   *   @type int    $origin_attachment_id
   *   @type string $url          Remote file URL
   *   @type string $title        Optional
   *   @type string $mime         Optional
   *   @type string $source       Optional (e.g., 'network' or 'external')
   *   @type array  $extra_meta   Optional associative meta to store
   * }
   * @return int|WP_Error Local attachment ID or WP_Error
   */
  public static function create_proxy_attachment(array $args) {
    $originBlogId = isset($args['origin_blog_id']) ? (int)$args['origin_blog_id'] : 0;
    $originAttId  = isset($args['origin_attachment_id']) ? (int)$args['origin_attachment_id'] : 0;

    $url       = isset($args['url']) ? esc_url_raw((string)$args['url']) : '';
    $title     = isset($args['title']) ? sanitize_text_field((string)$args['title']) : '';
    $mime      = isset($args['mime']) ? sanitize_text_field((string)$args['mime']) : '';
    $source    = isset($args['source']) ? sanitize_key((string)$args['source']) : 'network';
    $extraMeta = (isset($args['extra_meta']) && is_array($args['extra_meta'])) ? $args['extra_meta'] : [];

    if ( ! $url ) {
      return new WP_Error('tbf_nmi_proxy_missing_url', 'Missing remote URL.');
    }

    $existing = self::find_existing_proxy($source, $originBlogId, $originAttId, $url);
    if ( $existing ) return $existing;

    if ( $title === '' ) {
      $title = wp_basename(parse_url($url, PHP_URL_PATH) ?: 'media');
      $title = preg_replace('/\.[a-z0-9]{1,6}$/i', '', $title);
      $title = str_replace(['-', '_'], ' ', $title);
      $title = trim($title);
      if ( $title === '' ) $title = 'Network Media';
    }

    if ( $mime === '' ) {
      $mime = self::guess_mime_from_url($url);
    }
    if ( $mime === '' ) {
      $mime = 'application/octet-stream';
    }

    $attachment = [
      'post_title'     => $title,
      'post_status'    => 'inherit',
      'post_type'      => 'attachment',
      'post_mime_type' => $mime,
      'guid'           => $url,
    ];

    $attId = wp_insert_post($attachment, true);
    if ( is_wp_error($attId) ) return $attId;
    $attId = (int)$attId;

    update_post_meta($attId, '_tbf_nmi_is_proxy', 1);
    update_post_meta($attId, '_tbf_nmi_proxy_source', $source);
    update_post_meta($attId, '_tbf_nmi_proxy_url', $url);

    if ( $originBlogId > 0 ) update_post_meta($attId, '_tbf_nmi_origin_blog_id', $originBlogId);
    if ( $originAttId > 0 ) update_post_meta($attId, '_tbf_nmi_origin_attachment_id', $originAttId);

    if ( strpos($mime, 'image/') === 0 ) {
      update_post_meta($attId, '_wp_attachment_image_alt', $title);
    }

    foreach ( $extraMeta as $k => $v ) {
      $k = sanitize_key((string)$k);
      if ( $k === '' ) continue;
      update_post_meta($attId, '_tbf_nmi_' . $k, maybe_serialize($v));
    }

    return $attId;
  }

  private static function find_existing_proxy($source, $originBlogId, $originAttId, $url) {
    $metaQuery = [
      'relation' => 'AND',
      [
        'key' => '_tbf_nmi_is_proxy',
        'value' => '1',
        'compare' => '=',
      ],
      [
        'key' => '_tbf_nmi_proxy_source',
        'value' => $source,
        'compare' => '=',
      ],
    ];

    if ( $originBlogId > 0 ) {
      $metaQuery[] = [
        'key' => '_tbf_nmi_origin_blog_id',
        'value' => (string)$originBlogId,
        'compare' => '=',
      ];
    }

    if ( $originAttId > 0 ) {
      $metaQuery[] = [
        'key' => '_tbf_nmi_origin_attachment_id',
        'value' => (string)$originAttId,
        'compare' => '=',
      ];
    } else {
      $metaQuery[] = [
        'key' => '_tbf_nmi_proxy_url',
        'value' => $url,
        'compare' => '=',
      ];
    }

    $q = new WP_Query([
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'posts_per_page' => 1,
      'fields' => 'ids',
      'meta_query' => $metaQuery,
      'no_found_rows' => true,
      'cache_results' => true,
    ]);

    if ( ! empty($q->posts[0]) ) return (int)$q->posts[0];
    return 0;
  }

  private static function guess_mime_from_url($url) {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    $map = [
      'jpg'  => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png'  => 'image/png',
      'gif'  => 'image/gif',
      'webp' => 'image/webp',
      'svg'  => 'image/svg+xml',

      'mp4'  => 'video/mp4',
      'webm' => 'video/webm',
      'mov'  => 'video/quicktime',

      'mp3'  => 'audio/mpeg',
      'wav'  => 'audio/wav',
      'ogg'  => 'audio/ogg',

      'pdf'  => 'application/pdf',
    ];

    return $map[$ext] ?? '';
  }
}
