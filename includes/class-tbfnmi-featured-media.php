<?php
/**
 * File: includes/class-tbfnmi-featured-media.php
 * Version: 4.3.7 (GIF Fix + Dimension Safety)
 *
 * Changes:
 * - Fixes "Invisible Image" bug by returning fallback dimensions (800x600) instead of 0x0.
 * - Fixes "Static GIF" bug by forcing is_intermediate=false for GIFs.
 * - Fixes "Broken Gallery" bug by providing valid integers for aspect ratio calculations.
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
  }

  public static function get_proxy_url($id) {
      $id = (int)$id;
      if ($id <= 0) return false;

      // Try v4 meta
      $url = get_post_meta($id, '_tbfnmi_proxy_url', true);
      
      // Fallback to v1 meta
      if (!$url) $url = get_post_meta($id, '_tbfnmi_origin_url', true); 
      
      return $url ? esc_url($url) : false;
  }

  /**
   * Fixes 0x0 Dimensions & GIF Animation
   */
  public static function filter_image_downsize($out, $id, $size) {
    $url = self::get_proxy_url($id);
    if ( ! $url ) return $out;

    // 1. Fix "Broken Gallery" / "Invisible Image"
    // We must return VALID integers. 0 causes division-by-zero errors in Gutenberg.
    // 800x600 is a safe standard aspect ratio that ensures visibility in the editor.
    // The browser will scale the real image to fit the container anyway.
    $w = 800;
    $h = 600;

    // 2. Fix "Static GIF"
    // If it's a GIF, we must say it is NOT intermediate (i.e., it is the 'Full' size).
    // Intermediate sizes in WP are usually static frames. Full size preserves animation.
    $mime = get_post_mime_type($id);
    $is_gif = (strpos($mime, 'gif') !== false) || (substr($url, -4) === '.gif');
    
    $is_intermediate = $is_gif ? false : true;

    // Return format: [url, width, height, is_intermediate]
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
        
        // Ensure width/height attributes are present to prevent layout shifts
        // (Using our safe fallback if WP didn't calculate them)
        if ( empty($attr['width']) ) $attr['width'] = 800;
        if ( empty($attr['height']) ) $attr['height'] = 600;

        // Porto Lazy Load
        if (strpos($attr['class'] ?? '', 'owl-lazy') !== false || isset($attr['data-src'])) {
            $attr['data-src'] = $url;
        }
        
        // Kill srcset for proxies (prevents 404s on missing sub-sizes)
        unset($attr['srcset'], $attr['sizes']);
    }
    return $attr;
  }

  public static function disable_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    return self::get_proxy_url($attachment_id) ? false : $sources;
  }

  public static function filter_admin_preview($content, $post_id, $thumbnail_id) {
    $url = self::get_proxy_url($thumbnail_id);
    
    // If WP failed to render (empty string) OR rendered a broken 1px image
    if ( $url && (empty($content) || strpos($content, 'width="1"') !== false) ) {
        // Force a visible preview
        return '<p>Network Media Proxy</p><img src="' . $url . '" style="max-width:100%;height:auto;display:block;" />';
    }
    return $content;
  }
}

TBFNMI_Featured_Media::register();
