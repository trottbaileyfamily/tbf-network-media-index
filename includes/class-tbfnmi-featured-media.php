<?php
/**
 * File: includes/class-tbfnmi-featured-media.php
 * Version: 6.2.2 (Porto Theme & Frontend Block Fixes)
 *
 * Changes:
 * - Fixes frontend plugin blocks (Porto/Elementor/WPBakery) not showing remote featured images.
 * - Intelligently connects the Placeholder Attachment ID to the loop's current Post ID.
 * - Injects safe fake metadata to prevent strict builders from dropping the image.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Featured_Media {

  public static function register() {
    // 1. Rendering Filters
    add_filter('image_downsize', [__CLASS__, 'filter_image_downsize'], 10, 3);
    add_filter('wp_get_attachment_url', [__CLASS__, 'filter_attachment_url'], 10, 2);
    
    // 2. Attributes & Lazy Load
    add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'filter_attr'], 20, 3);
    add_filter('wp_calculate_image_srcset', [__CLASS__, 'disable_srcset'], 10, 5);

    // 3. Admin Preview (Metabox)
    add_filter('admin_post_thumbnail_html', [__CLASS__, 'filter_admin_preview'], 10, 3);

    // 4. NEW: Strict Theme Fixes (Porto, Elementor Grids, etc.)
    add_filter('post_thumbnail_html', [__CLASS__, 'filter_post_thumbnail_html'], 99, 5);
    add_filter('wp_get_attachment_metadata', [__CLASS__, 'filter_metadata'], 10, 2);
  }

  public static function get_proxy_url($id) {
      $id = (int)$id;
      if ($id <= 0) return false;

      // 1. Proxy Attachment Mode (Image inserted into media library)
      $url = get_post_meta($id, '_tbfnmi_proxy_url', true);
      if (!$url) $url = get_post_meta($id, '_tbfnmi_origin_url', true); // Fallback for older imports
      
      if ($url) return esc_url($url);

      // 2. Remote Featured Image Mode (Using the shared Placeholder)
      // When themes like Porto query the placeholder, we sniff the current loop's post ID to get the real URL.
      $placeholder_id = (int) get_option('tbfnmi_placeholder_id', 0);
      if ($placeholder_id > 0 && $id === $placeholder_id) {
          $post_id = get_the_ID();
          if ($post_id) {
              $remote_url = get_post_meta($post_id, '_tbfnmi_featured_url', true);
              if ($remote_url) return esc_url($remote_url);
          }
      }

      return false;
  }

  /**
   * Directly intercepts the HTML generation for Featured Images
   * This guarantees Porto post blocks render the image instantly.
   */
  public static function filter_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
      $remote_url = get_post_meta($post_id, '_tbfnmi_featured_url', true);
      
      if ( $remote_url ) {
          $url = esc_url($remote_url);
          $alt = esc_attr(get_the_title($post_id));
          
          $class = 'attachment-post-thumbnail size-post-thumbnail wp-post-image tbfnmi-remote-thumb';
          if (is_array($attr) && isset($attr['class'])) {
              $class .= ' ' . esc_attr($attr['class']);
          }

          // Build a bulletproof img tag that supports Porto lazy loading
          $img_tag = sprintf(
              '<img src="%s" data-src="%s" class="%s" alt="%s" width="800" height="600" loading="lazy" decoding="async" />',
              $url,
              $url,
              $class,
              $alt
          );

          return $img_tag;
      }
      return $html;
  }

  /**
   * Prevents strict builders from rejecting the proxy attachment due to missing physical metadata.
   */
  public static function filter_metadata($data, $post_id) {
      if ( self::get_proxy_url($post_id) ) {
          // If no data exists, provide safe fallback metadata so Porto doesn't trigger a 404 block
          if ( empty($data) ) {
              return [
                  'width'  => 800,
                  'height' => 600,
                  'file'   => 'network-media-proxy.jpg',
                  'sizes'  => []
              ];
          }
      }
      return $data;
  }

  /**
   * Fixes 0x0 Dimensions & GIF Animation
   */
  public static function filter_image_downsize($out, $id, $size) {
    $url = self::get_proxy_url($id);
    if ( ! $url ) return $out;

    $w = 800;
    $h = 600;

    $mime = get_post_mime_type($id);
    $is_gif = (strpos($mime, 'gif') !== false) || (substr($url, -4) === '.gif');
    
    $is_intermediate = $is_gif ? false : true;

    return [$url, $w, $h, $is_intermediate];
  }

  public static function filter_attachment_url($url, $id) {
    $proxy_url = self::get_proxy_url($id);
    return $proxy_url ? $proxy_url : $url;
  }

  public static function filter_attr($attr, $attachment, $size) {
    $id = isset($attachment->ID) ? (int)$attachment->ID : 0;
    $url = self::get_proxy_url($id);
    
    if ( $url ) {
        $attr['src'] = $url;
        
        if ( empty($attr['width']) ) $attr['width'] = 800;
        if ( empty($attr['height']) ) $attr['height'] = 600;

        // Porto Lazy Load Support
        if (strpos($attr['class'] ?? '', 'owl-lazy') !== false || isset($attr['data-src'])) {
            $attr['data-src'] = $url;
            $attr['data-plugin-lazyload'] = ''; // Porto specific hook
        }
        
        unset($attr['srcset'], $attr['sizes']);
    }
    return $attr;
  }

  public static function disable_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    return self::get_proxy_url($attachment_id) ? false : $sources;
  }

  public static function filter_admin_preview($content, $post_id, $thumbnail_id) {
    // Admin preview usually has the real post ID
    $remote_url = get_post_meta($post_id, '_tbfnmi_featured_url', true);
    $url = $remote_url ? $remote_url : self::get_proxy_url($thumbnail_id);
    
    if ( $url && (empty($content) || strpos($content, 'width="1"') !== false) ) {
        return '<p style="color:#2271b1;font-weight:bold;">Network Media Remote Image</p><img src="' . esc_url($url) . '" style="max-width:100%;height:auto;display:block;" />';
    }
    return $content;
  }
}

TBFNMI_Featured_Media::register();